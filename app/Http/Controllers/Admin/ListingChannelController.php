<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PublishListingToChannel;
use App\Models\ChannelConnection;
use App\Models\Property;
use App\Services\Channels\ChannelPublishBlocked;
use App\Services\Channels\ListingContentMapper;
use App\Services\Channels\RepullConnectService;
use App\Services\Channels\RepullListingService;
use App\Services\Repull\RepullException;
use Illuminate\Http\Request;

/** Per-listing "Channels" screen: connect OTA accounts, publish content, see per-section results. */
class ListingChannelController extends Controller
{
    public function show(Request $request, Property $property, RepullListingService $service, ListingContentMapper $mapper)
    {
        $this->authorizeProperty($request, $property);

        $listing = $property->listing()->with(['photos', 'channelLinks'])->first();
        $listing?->setRelation('property', $property);

        $missing = [];
        foreach (RepullListingService::PUBLISHABLE as $channel) {
            $missing[$channel] = $listing ? $mapper->missing($listing, $channel) : ['listing' => 'Create the direct booking listing first'];
        }

        return view('admin.listings.channels', [
            'property' => $property,
            'listing' => $listing,
            'links' => $listing ? $listing->channelLinks->keyBy('channel') : collect(),
            'connections' => ChannelConnection::query()->where('owner_id', $property->owner_id)->where('status', 'connected')->get()->groupBy('channel'),
            'missing' => $missing,
            'capabilities' => $service->capabilities(),
            'configured' => app(\App\Services\Repull\RepullClient::class)->isConfigured(),
        ]);
    }

    public function connect(Request $request, Property $property, string $channel, RepullConnectService $connect)
    {
        $this->authorizeProperty($request, $property);
        abort_unless(in_array($channel, RepullListingService::CHANNELS, true), 404);
        abort_unless($property->owner, 422, 'This property has no owner to connect channels for.');

        try {
            return redirect()->away($connect->start($property->owner, [$channel]));
        } catch (RepullException $e) {
            return back()->withErrors(['channel' => $this->friendly($e)]);
        }
    }

    /** Where Repull sends the host after the hosted Connect flow. The webhook records the connection. */
    public function connected(Request $request)
    {
        return redirect()->route('admin.properties.index')
            ->with('success', 'Channel connection finished. It will show on the Channels screen in a moment.');
    }

    public function publish(Request $request, Property $property, string $channel, RepullListingService $service)
    {
        $this->authorizeProperty($request, $property);
        abort_unless(in_array($channel, RepullListingService::PUBLISHABLE, true), 404);

        $listing = $property->listing()->with('photos')->first();
        abort_unless($listing, 404);
        $listing->setRelation('property', $property);

        $blocked = app(ListingContentMapper::class)->missing($listing, $channel);
        if ($blocked !== []) {
            return back()->withErrors(['channel' => 'Complete the listing first: '.implode(', ', $blocked).'.']);
        }
        if (! $service->connectionFor($listing, $channel)) {
            return back()->withErrors(['channel' => 'Connect the host\'s '.ucfirst($channel).' account first.']);
        }

        $service->linkFor($listing, $channel)->forceFill(['state' => 'publishing', 'last_error' => null])->save();
        PublishListingToChannel::dispatch($listing->id, $channel, $request->boolean('force'));

        return back()->with('success', 'Publishing to '.ucfirst($channel).' started. Refresh this page in a minute for the result.');
    }

    public function unlist(Request $request, Property $property, string $channel, RepullListingService $service)
    {
        $this->authorizeProperty($request, $property);
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

    public function readiness(Request $request, Property $property, RepullListingService $service)
    {
        $this->authorizeProperty($request, $property);

        $listing = $property->listing()->first();
        abort_unless($listing, 404);

        try {
            $status = $service->readiness($listing);
        } catch (RepullException $e) {
            return back()->withErrors(['channel' => $this->friendly($e)]);
        }

        $airbnb = data_get($status, 'addressReadiness.airbnb');
        $msg = $airbnb && ($airbnb['ready'] ?? false) === false
            ? 'Airbnb needs: '.implode(', ', (array) ($airbnb['missing'] ?? []))
            : 'Readiness check passed (or nothing reported yet).';

        return back()->with('success', $msg);
    }

    private function friendly(RepullException $e): string
    {
        return $e->errorCode === 'not_configured'
            ? 'Channel sync is not switched on yet.'
            : 'Channel service error ['.$e->errorCode.']: '.$e->getMessage();
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
