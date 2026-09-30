<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\PropertyCheckout;
use App\Models\User;
use App\Services\ICalService;
use App\Services\GuestPortalDashboardData;

class DashboardController extends Controller
{
    private $icalService;

    public function __construct(
        ICalService $icalService,
        private GuestPortalDashboardData $guestPortalDashboardData,
    )
    {
        $this->icalService = $icalService;
    }
    /** Resolve acting role (admin wins unless ?as=owner and user also has owner). */
    private function actingRole(Request $request): string
    {
        $u = $request->user();
        $isAdmin = $u?->hasRole('admin') ?? false;
        $isOwner = $u?->hasRole('owner') ?? false;
        $isCompany = $u?->hasRole('company') ?? false;
        $isHK    = $u?->hasRole('housekeeper') ?? false;

        if ($isAdmin) {
            if (($isOwner || $isCompany) && $request->query('as') === 'owner') return 'owner';
            return 'admin';
        }
        if ($isOwner || $isCompany) return 'owner';
        if ($isHK) return 'housekeeper';

        // Final fallback: if no role is found, treat as housekeeper to be safe
        return 'housekeeper';
    }

    /** Visible property ids for counts/lists. */
    private function visiblePropertyIds(string $acting, int $userId)
    {
        if ($acting === 'admin') {
            // null => unscoped (use carefully)
            return null;
        }
        if ($acting === 'owner') {
            $user = User::find($userId);
            if ($user && $user->hasRole('company')) {
                return Property::active()->where(function($q) use ($userId) {
                    $q->where('owner_id', $userId)
                      ->orWhereIn('owner_id', function($sub) use ($userId) {
                          $sub->select('id')->from('users')->where('owner_id', $userId);
                      })
                      ->orWhereIn('owner_id', function($sub) use ($userId) {
                          $sub->select('owner_id')
                              ->from('housekeeper_owner')
                              ->where('housekeeper_id', $userId);
                      })
                      ->orWhereHas('users', function($sub2) use ($userId) {
                          $sub2->where('users.id', $userId);
                      });
                })->pluck('id');
            }
            return Property::active()->where('owner_id', $userId)->pluck('id');
        }
        // housekeeper: distinct properties from their sessions (last 180d + next 180d for practicality)
        $from = Carbon::now()->subDays(180)->toDateString();
        $to   = Carbon::now()->addDays(180)->toDateString();
        return CleaningSession::where('housekeeper_id', $userId)
            ->whereBetween('scheduled_date', [$from, $to])
            ->distinct()
            ->pluck('property_id');
    }

    public function __invoke(Request $request)
    {
        $u = Auth::user();
        $canSeeCleaning = $u->hasAnyRole(['admin', 'owner', 'company', 'housekeeper']);
        $canSeeGuestPortal = $u->hasAnyRole(['admin', 'owner', 'company']);

        if (! $canSeeCleaning) {
            return view('dashboard', [
                'canSeeCleaning' => false,
                'canSeeGuestPortal' => false,
            ]);
        }

        $acting = $this->actingRole($request);
        abort_if($acting === 'forbidden', 403);

        // Unscheduled checkouts from iCal
        $unscheduledCheckouts = $this->getUnscheduledCheckouts($acting, $u->id);

        $guestPanelData = $canSeeGuestPortal
            ? $this->guestPortalDashboardData->get($u)
            : [];

        return view('dashboard', array_merge($guestPanelData, [
            'canSeeCleaning'   => $canSeeCleaning,
            'canSeeGuestPortal' => $canSeeGuestPortal,
            'unscheduledCheckouts' => $unscheduledCheckouts,
            // not used by the blade but handy for debugging/scope badges if needed
            'acting'           => $acting,
        ]));
    }
    /**
     * Get list of unscheduled checkouts from iCal feeds.
     */
    private function getUnscheduledCheckouts(string $acting, int $userId)
    {
        // Only owners/admins/companies manage scheduling based on bookings
        if ($acting === 'housekeeper') {
            return collect();
        }

        $propertyIds = $this->visiblePropertyIds($acting, $userId);

        $properties = Property::query()->active()
            ->when(!is_null($propertyIds), fn($q) => $q->whereIn('id', $propertyIds))
            ->where(function($q) {
                $q->where(function ($q) {
                    $q->whereNotNull('ical_url')
                        ->where('ical_url', '!=', '');
                })->orWhere(function ($q) {
                    $q->whereNotNull('airbnb_ical_url')
                        ->where('airbnb_ical_url', '!=', '');
                })->orWhere(function ($q) {
                    $q->whereNotNull('vrbo_ical_url')
                        ->where('vrbo_ical_url', '!=', '');
                });
            })
            ->get(['id', 'name', 'ical_url', 'airbnb_ical_url', 'vrbo_ical_url']);

        $today = Carbon::today();
        $limitDate = Carbon::today()->addMonths(1);

        foreach ($properties as $property) {
            $urls = [
                ['url' => $property->ical_url, 'source' => 'General'],
                ['url' => $property->airbnb_ical_url, 'source' => 'Airbnb'],
                ['url' => $property->vrbo_ical_url, 'source' => 'Vrbo'],
            ];

            foreach ($urls as $item) {
                if (empty($item['url'])) continue;

                $events = $this->icalService->fetchEvents($item['url'], $property->id . '_' . $item['source'], $item['source']);

                foreach ($events as $event) {
                    PropertyCheckout::updateOrCreate(
                        ['property_id' => $property->id, 'uid' => $event['uid']],
                        [
                            'checkout_date' => $event['end_date'],
                            'guest_name'    => $event['summary'] ?? 'Guest',
                            'source'        => $event['source'] ?? $item['source']
                        ]
                    );
                }
            }
        }

        // Now fetch from DB and filter by existing sessions
        $checkouts = PropertyCheckout::with('property')
            ->whereBetween('checkout_date', [$today->toDateString(), $limitDate->toDateString()])
            ->when(!is_null($propertyIds), fn($q) => $q->whereIn('property_id', $propertyIds))
            ->get();
        $unscheduled = collect();
        $previousLogin = session('previous_login_at');

        foreach ($checkouts as $checkout) {
            // Check if a session exists for this date and property
            $exists = CleaningSession::query()
                ->where('property_id', $checkout->property_id)
                ->whereDate('scheduled_date', $checkout->checkout_date)
                ->exists();

            if (!$exists) {
                $unscheduled->push([
                    'property_id'   => $checkout->property_id,
                    'property_name' => $checkout->property->name,
                    'checkout_date' => $checkout->checkout_date,
                    'guest_name'    => $checkout->guest_name,
                    'uid'           => $checkout->uid,
                    'source'        => $checkout->source,
                    'is_new'        => $previousLogin ? $checkout->first_seen_at > $previousLogin : false
                ]);
            }
        }

        // Sort by date soonest first
        return $unscheduled->sortBy('checkout_date')->values();
    }
}
