<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PublishListingToChannel;
use App\Jobs\RefreshChannelStatus;
use App\Models\ChannelConnection;
use App\Models\Property;
use App\Services\Channels\ChannelPublishBlocked;
use App\Services\Channels\ListingContentMapper;
use App\Services\Channels\ImportFromAirbnbService;
use App\Services\Channels\RepullConnectService;
use App\Services\Channels\RepullListingService;
use App\Services\Repull\RepullException;
use App\Services\Repull\RepullClient;
use App\Services\FeatureGate;
use Illuminate\Http\Request;

/** Per-listing "Channels" screen: connect OTA accounts, publish content, see per-section results. */
class ListingChannelController extends Controller
{
    public function show(Request $request, Property $property, RepullListingService $service, ListingContentMapper $mapper, RepullClient $repull)
    {
        $this->authorizeProperty($request, $property);

        $listing = $property->listing()->with(['photos', 'channelLinks'])->first();
        $listing?->setRelation('property', $property);

        $airbnbListings = [];
        $ownerAirbnbConnections = ChannelConnection::query()
            ->where('owner_id', $property->owner_id)
            ->where('provider', 'repull')
            ->where('channel', 'airbnb')
            ->where('status', 'connected')
            ->get();
        if ($property->owner && FeatureGate::enabled($property->owner, 'repull_channel_sync')
            && app(RepullClient::class)->isConfigured() && $ownerAirbnbConnections->isNotEmpty()) {
            try {
                $remote = app(RepullClient::class)->getAirbnbListings();
                $accountIds = $ownerAirbnbConnections->pluck('external_account_id')->map(fn ($id) => (string) $id)->all();
                $connectionIds = $ownerAirbnbConnections->map(fn ($connection) => (string) data_get($connection->meta, 'connection_id'))->filter()->all();
                $airbnbListings = collect(data_get($remote, 'data', []))->map(function ($item) use ($accountIds, $connectionIds) {
                    $item['connections'] = collect($item['connections'] ?? [])->filter(function ($connection) use ($accountIds, $connectionIds) {
                        return in_array((string) ($connection['accountId'] ?? ''), $accountIds, true)
                            || in_array((string) ($connection['id'] ?? ''), $connectionIds, true);
                    })->values()->all();
                    return $item;
                })->filter(fn ($item) => ! empty($item['connections']))->values()->all();
            } catch (RepullException $e) {
                report($e);
            }
        }

        $missing = [];
        foreach (RepullListingService::PUBLISHABLE as $channel) {
            $missing[$channel] = $listing ? $mapper->missing($listing, $channel) : ['listing' => 'Create the direct booking listing first'];
        }

        $bookingLink = $listing?->channelLinks->firstWhere('channel', 'booking');
        $bookingLicenceInfo = null;
        if ($bookingLink && filled($bookingLink->external_listing_id) && $repull->isConfigured()
            && $property->owner && FeatureGate::enabled($property->owner, 'repull_channel_sync')) {
            try {
                $bookingLicenceInfo = $repull->bookingContent('GET', [], [
                    'property_id' => (string) $bookingLink->external_listing_id,
                    'type' => 'licences',
                ]);
            } catch (RepullException $e) {
                $bookingLicenceInfo = ['error' => $e->getMessage()];
            }
        }

        return view('admin.listings.channels', [
            'property' => $property,
            'listing' => $listing,
            'bookingLink' => $bookingLink,
            'bookingLicenceInfo' => $bookingLicenceInfo,
            'airbnbListings' => $airbnbListings,
            'links' => $listing ? $listing->channelLinks->keyBy('channel') : collect(),
            'connections' => ChannelConnection::query()->where('owner_id', $property->owner_id)->where('provider', 'repull')->where('status', 'connected')->get()->groupBy('channel'),
            'missing' => $missing,
            'capabilities' => $service->capabilities(),
            'configured' => app(\App\Services\Repull\RepullClient::class)->isConfigured(),
            'featureEnabled' => $property->owner && FeatureGate::enabled($property->owner, 'repull_channel_sync'),
        ]);
    }

    public function connect(Request $request, Property $property, string $channel, RepullConnectService $connect)
    {
        $this->authorizeProperty($request, $property);
        $this->authorizeChannelAddon($property);
        abort_unless(in_array($channel, RepullListingService::CHANNELS, true), 404);
        abort_unless($property->owner, 422, 'This property has no owner to connect channels for.');

        $data = $request->validate([
            'access_type' => ['nullable', 'in:full_access,messaging,read_only'],
            'connection_mode' => ['nullable', 'in:standard,extranet'],
        ]);

        try {
            return redirect()->away($connect->start(
                $property->owner,
                [$channel],
                $data['access_type'] ?? 'full_access',
                $data['connection_mode'] ?? 'standard',
            ));
        } catch (RepullException $e) {
            return back()->withErrors(['channel' => $this->friendly($e)]);
        }
    }

    public function importAirbnb(Request $request, Property $property, ImportFromAirbnbService $import)
    {
        $this->authorizeProperty($request, $property);
        $this->authorizeChannelAddon($property);
        abort_unless($property->owner, 422, 'This property has no owner to import listings for.');

        $data = $request->validate(['selection' => ['required', 'string', 'max:200']]);
        $parts = explode('|', $data['selection']);
        if (count($parts) !== 3 || in_array('', $parts, true)) {
            return back()->withErrors(['selection' => 'Choose a valid Airbnb listing and account.']);
        }
        [$repullListingId, $connectionId, $accountId] = $parts;

        $ownedConnection = ChannelConnection::query()
            ->where('owner_id', $property->owner_id)
            ->where('provider', 'repull')
            ->where('channel', 'airbnb')
            ->where('status', 'connected')
            ->get()
            ->first(fn ($connection) => (string) $connection->external_account_id === (string) $accountId
                || (string) data_get($connection->meta, 'connection_id') === (string) $connectionId);
        if (! $ownedConnection) {
            return back()->withErrors(['selection' => 'That Airbnb account is not connected to this host.']);
        }

        // The Repull workspace key can see every host's account. Never trust
        // a browser-supplied listing id just because one of the submitted
        // connection/account ids belongs to this owner.
        try {
            $remote = app(RepullClient::class)->getAirbnbListings();
        } catch (RepullException $e) {
            return back()->withErrors(['selection' => $this->friendly($e)]);
        }

        $selectedListing = collect(data_get($remote, 'data', []))->first(
            fn ($item) => (string) ($item['listingId'] ?? '') === (string) $repullListingId
        );
        $belongsToSelectedAccount = $selectedListing && collect($selectedListing['connections'] ?? [])->contains(
            fn ($connection) => (string) ($connection['id'] ?? '') === (string) $connectionId
                && (string) ($connection['accountId'] ?? '') === (string) $accountId
        );
        if (! $belongsToSelectedAccount
            || (string) $ownedConnection->external_account_id !== (string) $accountId
            || (string) data_get($ownedConnection->meta, 'connection_id') !== (string) $connectionId) {
            return back()->withErrors(['selection' => 'That Airbnb listing does not belong to the selected connected account. Refresh the page and choose one of your own listings.']);
        }

        try {
            $listing = $import->importInto($property, (string) $repullListingId, (string) $connectionId);
        } catch (ChannelPublishBlocked|RepullException $e) {
            return back()->withErrors(['channel' => $e instanceof RepullException ? $this->friendly($e) : $e->getMessage()]);
        }

        return back()->with('success', 'Airbnb listing imported into an unpublished GuestHub draft. Review the content, photos, rooms and rates before publishing.');
    }

    public function refreshAirbnbStatus(Request $request, Property $property)
    {
        $this->authorizeProperty($request, $property);
        $this->authorizeChannelAddon($property);
        abort_unless($property->owner, 422, 'This property has no owner to refresh.');

        RefreshChannelStatus::dispatch((int) $property->owner_id);

        return back()->with('success', 'Airbnb sync status refresh queued. Refresh this page shortly.');
    }

    /** Where Repull sends the host after the hosted Connect flow. The webhook records the connection. */
    public function connected(Request $request, RepullConnectService $connect)
    {
        if ($request->query('status') !== 'connected') {
            return redirect()->route('admin.properties.index')
                ->with('warning', 'The channel connection was not completed.');
        }

        $ownerId = $connect->ownerIdFromState((string) $request->query('state', ''));
        $provider = (string) $request->query('provider', '');
        $externalAccountId = (string) ($request->query('externalAccountId') ?? $request->query('accountId') ?? $request->query('connectionId') ?? '');
        if (! $ownerId || (int) $request->user()->id !== $ownerId || $provider === '' || $externalAccountId === '') {
            return redirect()->route('admin.properties.index')
                ->withErrors(['channel' => 'The returned channel connection could not be securely matched to your account.']);
        }

        try {
            $connect->confirmConnection($ownerId, $provider, $externalAccountId, (string) $request->query('connectionId', ''), (string) $request->query('accessType', ''));
        } catch (RepullException $e) {
            return redirect()->route('admin.properties.index')
                ->withErrors(['channel' => $this->friendly($e)]);
        }

        return redirect()->route('admin.properties.index')
            ->with('success', 'Channel account connected and confirmed.');
    }

    public function publish(Request $request, Property $property, string $channel, RepullListingService $service)
    {
        $this->authorizeProperty($request, $property);
        $this->authorizeChannelAddon($property);
        abort_unless(in_array($channel, RepullListingService::PUBLISHABLE, true), 404);
        $data = $request->validate(['hotel_id' => ['nullable', 'string', 'max:80']]);

        $listing = $property->listing()->with('photos')->first();
        abort_unless($listing, 404);
        $listing->setRelation('property', $property);

        $blocked = app(ListingContentMapper::class)->missing($listing, $channel);
        if ($blocked !== []) {
            return back()->withErrors(['channel' => 'Complete the listing first: '.implode(', ', $blocked).'.']);
        }
        if (! $service->connectionFor($listing, $channel)) {
            return back()->withErrors(['channel' => 'Connect the host\'s '.ucfirst($channel).' account with full management access before publishing.']);
        }

        $service->linkFor($listing, $channel)->forceFill(['state' => 'publishing', 'last_error' => null])->save();
        PublishListingToChannel::dispatch($listing->id, $channel, $request->boolean('force'), $data['hotel_id'] ?? null);

        return back()->with('success', 'Publishing to '.ucfirst($channel).' started. Refresh this page in a minute for the result.');
    }

    public function saveBookingLicence(Request $request, Property $property, RepullClient $repull)
    {
        $this->authorizeProperty($request, $property);
        $this->authorizeChannelAddon($property);
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'min:1'],
            'content_data_json' => ['required', 'string', 'max:12000'],
        ]);

        $listing = $property->listing()->with('channelLinks')->first();
        abort_unless($listing, 404);
        $link = $listing->channelLinks->firstWhere('channel', 'booking');
        abort_unless($link && filled($link->external_listing_id), 422, 'Create or link the Booking.com property first.');

        $contentData = json_decode($data['content_data_json'], true);
        if (! is_array($contentData) || ! array_is_list($contentData)) {
            return back()->withErrors(['content_data_json' => 'Enter a JSON array of name/value objects.']);
        }
        foreach ($contentData as $item) {
            if (! is_array($item) || ! is_string($item['name'] ?? null) || ! array_key_exists('value', $item)) {
                return back()->withErrors(['content_data_json' => 'Each licence entry needs a name and value.']);
            }
        }

        try {
            $result = $repull->bookingContent('POST', [
                'type' => 'licences',
                'property_id' => (string) $link->external_listing_id,
                'variantId' => (int) $data['variant_id'],
                'contentData' => $contentData,
            ]);
        } catch (RepullException $e) {
            return back()->withErrors(['booking_licence' => $this->friendly($e)]);
        }

        return back()->with('booking_licence_result', $result)->with('success', 'Booking.com licence details submitted. Re-check readiness before publishing.');
    }

    public function unlist(Request $request, Property $property, string $channel, RepullListingService $service)
    {
        $this->authorizeProperty($request, $property);
        $this->authorizeChannelAddon($property);
        abort_unless(in_array($channel, RepullListingService::PUBLISHABLE, true), 404);

        $listing = $property->listing()->first();
        abort_unless($listing, 404);
        $listing->setRelation('property', $property);

        try {
            $service->unlist($listing, $channel);
        } catch (ChannelPublishBlocked|RepullException $e) {
            return back()->withErrors(['channel' => $e instanceof RepullException ? $this->friendly($e) : $e->getMessage()]);
        }

        return back()->with('success', ucfirst($channel).' listing taken off the market.');
    }

    public function setListingOnline(Request $request, Property $property, RepullListingService $service)
    {
        $this->authorizeProperty($request, $property);
        $this->authorizeChannelAddon($property);
        $listing = $property->listing()->first();
        abort_unless($listing, 404);
        $listing->setRelation('property', $property);

        $data = $request->validate(['hotel_id' => ['nullable', 'string', 'max:128']]);

        try {
            $result = $service->setListingOnline($listing, $request->boolean('online'), $data['hotel_id'] ?? null);
        } catch (ChannelPublishBlocked|RepullException $e) {
            return back()->withErrors(['channel' => $e instanceof RepullException ? $this->friendly($e) : $e->getMessage()]);
        }

        $message = $request->boolean('online') ? 'Online' : 'Offline';
        return back()
            ->with('channel_results', $result['channels'])
            ->with($result['ok'] ? 'success' : 'warning', 'Repull '.$message.' request finished. Review each channel result below; any failed channel may still be live.');
    }

    public function readiness(Request $request, Property $property, RepullListingService $service)
    {
        $this->authorizeProperty($request, $property);
        $this->authorizeChannelAddon($property);

        $listing = $property->listing()->first();
        abort_unless($listing, 404);

        try {
            $status = $service->readiness($listing);
        } catch (RepullException $e) {
            return back()->withErrors(['channel' => $this->friendly($e)]);
        }

        $addressReadiness = (array) (data_get($status, 'addressReadiness') ?? data_get($status, 'data.addressReadiness') ?? []);
        $results = [];
        foreach (RepullListingService::CHANNELS as $channel) {
            $item = $addressReadiness[$channel] ?? null;
            if (! is_array($item)) {
                continue;
            }
            $results[$channel] = [
                'ready' => array_key_exists('ready', $item) ? (bool) $item['ready'] : null,
                'missing' => array_values((array) ($item['missing'] ?? [])),
            ];
        }

        $blocked = collect($results)->filter(fn ($item) => $item['ready'] === false);
        $msg = $blocked->isNotEmpty()
            ? 'Readiness check found items to fix: '.$blocked->map(fn ($item, $channel) => ucfirst($channel).': '.implode(', ', $item['missing']))->implode('; ')
            : ($results !== [] ? 'Repull returned readiness information for the connected channels.' : 'Repull did not return channel address-readiness details.');

        return back()->with('success', $msg)->with('readiness_results', $results);
    }

    private function friendly(RepullException $e): string
    {
        return $e->errorCode === 'not_configured'
            ? 'Channel sync is not switched on yet.'
            : 'Channel service error ['.$e->errorCode.']: '.$e->getMessage();
    }

    private function authorizeChannelAddon(Property $property): void
    {
        abort_unless($property->owner && FeatureGate::enabled($property->owner, 'repull_channel_sync'), 403, 'OTA channel sync is an add-on. Enable it from Settings → Plan.');
    }

    private function authorizeProperty(Request $request, Property $property): void
    {
        abort_unless(
            $request->user()->hasRole('admin') || Property::visibleTo($request->user())->whereKey($property->id)->exists(),
            403,
            'You do not have access to this property.',
        );
    }
}
