<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\HostPaymentMethod;
use App\Models\ListingPhoto;
use App\Models\MediaFile;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\FeatureGate;
use App\Services\IndexNowService;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class PropertyListingController extends Controller
{
    private const PAYMENT_TYPES = ['zelle', 'venmo', 'paypal', 'cashapp', 'other'];

    public function edit(Request $request, Property $property)
    {
        $this->authorizeProperty($request, $property);

        return view('admin.listings.edit', [
            'property' => $property->load(['amenities']),
            'listing' => $property->listing()->with('photos')->firstOrNew(),
            'paymentMethods' => $property->owner_id
                ? HostPaymentMethod::query()->where('owner_id', $property->owner_id)->orderBy('id')->get()
                : collect(),
        ]);
    }

    public function save(Request $request, Property $property)
    {
        $this->authorizeProperty($request, $property);

        $listing = $property->listing()->firstOrNew();
        $data = $request->validate([
            'listing_slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('property_listings', 'listing_slug')->ignore($listing->exists ? $listing->getKey() : null),
            ],
            'public_title' => ['nullable', 'string', 'max:255'],
            'public_description' => ['nullable', 'string', 'max:30000'],
            'max_guests' => ['nullable', 'integer', 'min:1', 'max:100'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:100'],
            'base_rate' => ['nullable', 'string', 'max:11', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/'],
            'cleaning_fee' => ['nullable', 'string', 'max:11', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'booking_mode' => ['required', Rule::in(['instant', 'request'])],
            'advance_notice_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'same_day_cutoff_time' => ['nullable', 'date_format:H:i'],
            'min_nights' => ['required', 'integer', 'min:1', 'max:365'],
            'cancellation_policy' => ['required', Rule::in(['flexible', 'moderate', 'strict'])],
            'cancellation_policy_text' => ['nullable', 'string', 'max:2000'],
            'house_rules' => ['nullable', 'string', 'max:20000'],
            'lease_enabled' => ['nullable', 'boolean'],
            'lease_application_fee' => ['nullable', 'string', 'max:11', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/'],
            'lease_allow_passport' => ['nullable', 'boolean'],
            'lease_fee_refund_policy' => ['nullable', 'in:unset,non_refundable,refundable_if_declined'],
            'lease_fee_refund_policy' => ['nullable', 'in:unset,non_refundable,refundable_if_declined'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'published' => ['required', 'boolean'],
            'photo_order' => ['nullable', 'array'],
            'photo_order.*' => ['integer', 'distinct'],
            'remove_photo_ids' => ['nullable', 'array'],
            'remove_photo_ids.*' => ['integer', 'distinct'],
            'photos' => ['nullable', 'array', 'max:30'],
            'photos.*' => ['image', 'max:15360'],
            'new_photo_alt' => ['nullable', 'array'],
            'new_photo_alt.*' => ['nullable', 'string', 'max:255'],
            'photo_alt' => ['nullable', 'array'],
            'photo_alt.*' => ['nullable', 'string', 'max:255'],
            'payment_methods' => ['nullable', 'array', 'max:10'],
            'payment_methods.*.type' => ['nullable', 'string', Rule::in(self::PAYMENT_TYPES)],
            'payment_methods.*.label' => ['nullable', 'string', 'max:120'],
            'payment_methods.*.handle' => ['nullable', 'string', 'max:255'],
            'payment_methods.*.instructions' => ['nullable', 'string', 'max:5000'],
            'payment_methods.*.enabled' => ['nullable', 'boolean'],
        ]);

        $published = (bool) $data['published'];
        $wasPublished = $listing->exists && $listing->published_at !== null;
        $baseRateCents = $this->dollarsToCents($data['base_rate'] ?? null, 'base_rate');
        $cleaningFeeCents = $this->dollarsToCents($data['cleaning_fee'] ?? null, 'cleaning_fee');
        $leaseFeeCents = $this->dollarsToCents($data['lease_application_fee'] ?? null, 'lease_application_fee');
        $methods = $this->paymentMethods($data['payment_methods'] ?? []);

        $title = trim((string) ($data['public_title'] ?? ''));
        $description = trim((string) ($data['public_description'] ?? ''));
        if ($published && $title === '') {
            $title = $this->defaultTitle($property);
        }
        if ($published && $description === '') {
            $description = $this->defaultDescription($property, $title);
        }

        $slug = trim((string) ($data['listing_slug'] ?? ''));
        if ($slug === '') {
            $slug = $listing->listing_slug ?: Str::slug($title ?: $property->name);
            $slug = $this->availableSlug($slug, $listing);
        }

        $listing->fill([
            'listing_slug' => $slug,
            'public_title' => $title ?: null,
            'public_description' => $description ?: null,
            'max_guests' => $data['max_guests'] ?? null,
            'bedrooms' => $data['bedrooms'] ?? $property->beds,
            'base_rate_cents' => $baseRateCents,
            'cleaning_fee_cents' => $cleaningFeeCents,
            'tax_percent' => $data['tax_percent'] ?? null,
            'booking_mode' => $data['booking_mode'],
            'advance_notice_hours' => $data['advance_notice_hours'],
            'same_day_cutoff_time' => $data['same_day_cutoff_time'] ?? null,
            'min_nights' => $data['min_nights'],
            'cancellation_policy' => $data['cancellation_policy'],
            'cancellation_policy_text' => filled($data['cancellation_policy_text'] ?? null) ? trim($data['cancellation_policy_text']) : null,
            'house_rules' => $data['house_rules'] ?? null,
            'lease_enabled' => (bool) ($data['lease_enabled'] ?? false),
            'lease_application_fee_cents' => $leaseFeeCents,
            'lease_allow_passport' => (bool) ($data['lease_allow_passport'] ?? false),
            'lease_fee_refund_policy' => $data['lease_fee_refund_policy'] ?? 'unset',
            'lease_fee_refund_policy' => $data['lease_fee_refund_policy'] ?? 'unset',
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ]);

        $existingPhotos = $listing->exists ? $listing->photos()->get() : collect();
        $removeIds = array_map('intval', $data['remove_photo_ids'] ?? []);
        $remainingPhotos = $existingPhotos->reject(fn (ListingPhoto $photo) => in_array($photo->id, $removeIds, true));
        $photoCount = $remainingPhotos->count() + count($request->file('photos', []));
        $this->validatePublish(
            $published,
            $listing,
            $property,
            $photoCount,
            $methods,
        );

        $orderIds = array_map('intval', $data['photo_order'] ?? []);
        $existingIds = $existingPhotos->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (array_diff($orderIds, $existingIds) || array_diff($removeIds, $existingIds)) {
            throw ValidationException::withMessages(['photos' => 'The submitted photo order or removal list is invalid.']);
        }
        if (count($orderIds) !== count(array_unique($orderIds))) {
            throw ValidationException::withMessages(['photo_order' => 'A photo cannot appear more than once in the sort order.']);
        }

        $nextPhotos = [];
        $storedPaths = [];
        try {
            foreach ($request->file('photos', []) as $index => $file) {
                $alt = trim((string) ($data['new_photo_alt'][$index] ?? ''));
                $nextPhotos[] = $this->storePhoto($file, $property, $alt ?: ($title ?: $property->name).' photo', $storedPaths);
            }

            DB::transaction(function () use ($listing, $property, $published, $existingPhotos, $remainingPhotos, $removeIds, $orderIds, $data, $methods, $nextPhotos) {
                $listing->property()->associate($property);
                $listing->published_at = $published ? ($listing->published_at ?? now()) : null;
                $listing->save();

                $photosById = $existingPhotos->keyBy('id');
                $sort = 0;
                foreach ($orderIds as $photoId) {
                    if (! in_array($photoId, $removeIds, true) && $photosById->has($photoId)) {
                        $photosById->get($photoId)->update([
                            'sort_order' => $sort++,
                            'alt' => $data['photo_alt'][$photoId] ?? $photosById->get($photoId)->alt,
                        ]);
                    }
                }
                foreach ($remainingPhotos as $photo) {
                    if (! in_array($photo->id, $orderIds, true)) {
                        $photo->update([
                            'sort_order' => $sort++,
                            'alt' => $data['photo_alt'][$photo->id] ?? $photo->alt,
                        ]);
                    }
                }

                if ($removeIds) {
                    $listing->photos()->whereIn('id', $removeIds)->delete();
                }

                foreach ($nextPhotos as $photo) {
                    $listing->photos()->create($photo + ['sort_order' => $sort++]);
                }

                if ($property->owner_id) {
                    HostPaymentMethod::query()->where('owner_id', $property->owner_id)->delete();
                    foreach ($methods as $method) {
                        HostPaymentMethod::create($method + ['owner_id' => $property->owner_id]);
                    }
                }
            });
        } catch (\Throwable $exception) {
            if ($storedPaths) {
                Storage::disk('public')->delete($storedPaths);
                MediaFile::query()->whereIn('path', $storedPaths)->delete();
            }

            throw $exception;
        }

        if ($wasPublished !== $published) {
            app(IndexNowService::class)->notifyListing($listing->fresh());
        }

        $removed = $existingPhotos->whereIn('id', $removeIds);
        $removedPaths = $removed->flatMap(fn (ListingPhoto $photo) => [$photo->path, $photo->thumbnail_path])->all();
        if ($removedPaths) {
            Storage::disk('public')->delete($removedPaths);
            MediaFile::query()->whereIn('path', $removedPaths)->delete();
        }

        return back()->with('success', $published ? 'Listing saved and published.' : 'Listing saved as a draft.');
    }

    private function authorizeProperty(Request $request, Property $property): void
    {
        abort_unless(
            $request->user()->hasRole('admin') || Property::visibleTo($request->user())->whereKey($property->id)->exists(),
            403,
            'You do not have access to this property.',
        );
    }

    private function dollarsToCents(?string $amount, string $field): ?int
    {
        if ($amount === null || trim($amount) === '') {
            return null;
        }

        if (! preg_match('/\A(\d{1,8})(?:\.(\d{1,2}))?\z/', trim($amount), $matches)) {
            throw ValidationException::withMessages([$field => 'Enter a valid amount with no more than two decimal places.']);
        }

        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 2, '0');

        return $whole * 100 + $fraction;
    }

    private function paymentMethods(array $rows): array
    {
        $methods = [];
        foreach ($rows as $index => $row) {
            $type = trim((string) ($row['type'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            $handle = trim((string) ($row['handle'] ?? ''));
            $instructions = trim((string) ($row['instructions'] ?? ''));
            if ($type === '' && $label === '' && $handle === '' && $instructions === '') {
                continue;
            }
            if ($type === '' || $label === '') {
                throw ValidationException::withMessages([
                    "payment_methods.{$index}.label" => 'Choose a payment type and enter its display label.',
                ]);
            }
            $methods[] = [
                'type' => $type,
                'label' => $label,
                'handle' => $handle ?: null,
                'instructions' => $instructions ?: null,
                'enabled' => (bool) ($row['enabled'] ?? false),
            ];
        }

        return $methods;
    }

    private function validatePublish(bool $published, PropertyListing $listing, Property $property, int $photoCount, array $methods): void
    {
        if (! $published) {
            return;
        }

        $errors = [];
        $owner = $property->owner_id ? User::find($property->owner_id) : null;
        if (! $owner || ! FeatureGate::enabled($owner, 'direct_booking_site')) {
            $errors['published'] = 'The direct booking site feature is not enabled for this property owner.';
        }
        if (blank($listing->public_title)) {
            $errors['public_title'] = 'A public title is required to publish.';
        }
        if ($this->wordCount((string) $listing->public_description) < 150) {
            $errors['public_description'] = 'The public description must contain at least 150 words.';
        }
        if ($photoCount < 5) {
            $errors['photos'] = 'Add at least five listing photos before publishing.';
        }
        if ($listing->base_rate_cents === null) {
            $errors['base_rate'] = 'Set a base nightly rate before publishing.';
        }
        if (! $listing->max_guests) {
            $errors['max_guests'] = 'Set the maximum guest count before publishing.';
        }
        if ($property->latitude === null || $property->longitude === null) {
            $errors['coordinates'] = 'Add the property coordinates before publishing.';
        }

        $hasEnabledManualMethod = collect($methods)->contains(fn (array $method) => $method['enabled']);
        $hasConnectedStripe = $owner
            && filled($owner->stripe_account_id)
            && $owner->stripe_charges_enabled;
        $manualMethodsAvailable = $owner
            && Feature::query()->where('key', 'manual_payments')->exists()
            && FeatureGate::enabled($owner, 'manual_payments');
        if (! $hasConnectedStripe && ! ($hasEnabledManualMethod && $manualMethodsAvailable)) {
            $errors['payment_methods'] = 'Enable at least one manual payment method or connect a Stripe account before publishing.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function defaultTitle(Property $property): string
    {
        $beds = $property->beds ?: 1;
        $baths = $property->baths ?: 1;
        $city = $property->city ?: 'GuestHub';

        return "{$beds}-Bedroom, {$baths}-Bathroom Stay in {$city}";
    }

    private function defaultDescription(Property $property, string $title): string
    {
        $beds = $property->beds ?: 1;
        $baths = $property->baths ?: 1;
        $city = $property->city ?: 'the local area';
        $amenities = $property->amenities()->pluck('name')->filter()->implode(', ');
        $amenityCopy = $amenities !== ''
            ? "Amenities selected by the host for this property include: {$amenities}."
            : 'No amenities have been selected for display on this listing.';

        return implode("\n\n", [
            "{$title} is a {$beds}-bedroom, {$baths}-bathroom property in {$city}. This page presents the information the host has provided so guests can review the home and its booking details before choosing dates. The listing is part of GuestHub's direct booking service, where availability and pricing are shown for the dates selected. The host can update listing details as the property or its arrangements change.",
            $amenityCopy." Please review the amenity list on this page and contact the host if you need to confirm whether a particular item is available during your stay. The amenities displayed here come from the host's selected property details and should not be read as a guarantee beyond those details.",
            'Before submitting a booking, check the guest limit, minimum stay, house rules and cancellation policy shown on this listing. The quote is based on the dates and guest count entered, and the final total is presented before checkout. Availability can change while a reservation is being completed. Arrival instructions and access details are handled after a booking is confirmed; this public listing does not show the exact street address or private entry information.',
            "Use this listing to compare the property details with your travel plans in {$city}. If a detail important to your visit is not described, ask the host before booking. The host is responsible for keeping the listing accurate, responding to questions and maintaining the property information displayed through GuestHub.",
        ]);
    }

    private function wordCount(string $text): int
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[\'’][\p{L}\p{N}]+)*/u', strip_tags($text), $matches);

        return count($matches[0]);
    }

    private function availableSlug(string $slug, PropertyListing $listing): string
    {
        $slug = Str::slug($slug) ?: 'stay';
        $candidate = $slug;
        $suffix = 2;
        while (PropertyListing::query()
            ->where('listing_slug', $candidate)
            ->when($listing->exists, fn ($query) => $query->whereKeyNot($listing->getKey()))
            ->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }

    private function storePhoto($file, Property $property, string $alt, array &$storedPaths): array
    {
        $driver = extension_loaded('imagick')
            ? Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class;
        $manager = new ImageManager(new $driver);
        $large = $manager->read($file->getRealPath())->scaleDown(width: 1800, height: 1200);
        $thumbnail = $manager->read($file->getRealPath())->scaleDown(width: 600, height: 400);
        $basePath = 'direct-listings/'.$property->id.'/'.Str::uuid();
        $path = $basePath.'.webp';
        $thumbnailPath = $basePath.'-thumb.webp';

        foreach ([
            $path => (string) $large->toWebp(82),
            $thumbnailPath => (string) $thumbnail->toWebp(78),
        ] as $target => $contents) {
            if (! Storage::disk('public')->put($target, $contents)) {
                throw new \RuntimeException('Could not store the resized listing photo.');
            }
            $storedPaths[] = $target;
        }

        MediaService::register($path, $file->getClientOriginalName(), Storage::disk('public')->size($path), 'Direct Listing Photos');

        return [
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'alt' => $alt,
            'width' => $large->width(),
            'height' => $large->height(),
        ];
    }
}
