<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\User;
use App\Services\GuestPortalDashboardData;
use App\Services\CheckoutOverviewService;

class DashboardController extends Controller
{
    public function __construct(
        private GuestPortalDashboardData $guestPortalDashboardData,
    ) {
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

        if ($acting === 'housekeeper') {
            $today = Carbon::today()->toDateString();
            $base = CleaningSession::query()
                ->with('property:id,name,address,latitude,longitude')
                ->where('housekeeper_id', $u->id);

            return view('dashboard-housekeeper', [
                'todaySessions' => (clone $base)->whereDate('scheduled_date', $today)->orderBy('scheduled_time')->get(),
                'upcomingSessions' => (clone $base)->whereDate('scheduled_date', '>', $today)
                    ->whereDate('scheduled_date', '<=', Carbon::today()->addDays(14)->toDateString())
                    ->orderBy('scheduled_date')->get(),
                'overdueSessions' => (clone $base)->whereDate('scheduled_date', '<', $today)
                    ->whereIn('status', ['pending', 'in_progress'])->orderBy('scheduled_date')->get(),
                'completed30' => (clone $base)->where('status', 'completed')
                    ->whereBetween('scheduled_date', [Carbon::today()->subDays(30)->toDateString(), $today])->count(),
            ]);
        }

        // Unscheduled checkouts from iCal
        $unscheduledCheckouts = $this->getUnscheduledCheckouts($acting, $u->id);

        $guestPanelData = $canSeeGuestPortal
            ? $this->guestPortalDashboardData->get($u)
            : [];

        $checkoutBoard = $canSeeGuestPortal ? app(CheckoutOverviewService::class)->get($u) : null;
        $dayBoard = $checkoutBoard ? app(\App\Services\DashboardDayService::class)->get($u, $checkoutBoard) : null;

        return view('dashboard', array_merge($guestPanelData, [
            'canSeeCleaning'   => $canSeeCleaning,
            'canSeeGuestPortal' => $canSeeGuestPortal,
            'unscheduledCheckouts' => $unscheduledCheckouts,
            'checkoutBoard' => $checkoutBoard,
            'dayBoard' => $dayBoard,
            // not used by the blade but handy for debugging/scope badges if needed
            'acting'           => $acting,
        ]));
    }
    /**
     * Unscheduled checkouts: upcoming bookings synced from the booking
     * platform that have no cleaning session on their checkout date.
     */
    private function getUnscheduledCheckouts(string $acting, int $userId)
    {
        if ($acting === 'housekeeper') {
            return collect();
        }

        $propertyIds = $this->visiblePropertyIds($acting, $userId);

        $names = Property::query()->active()
            ->when(! is_null($propertyIds), fn ($q) => $q->whereIn('id', $propertyIds))
            ->pluck('name', 'id');

        $bookings = \App\Models\Booking::query()
            ->whereIn('property_id', $names->keys())
            ->whereBetween('check_out_date', [Carbon::today()->toDateString(), Carbon::today()->addMonth()->toDateString()])
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'status'),
                fn ($q) => $q->whereNotIn('status', ['cancelled', 'canceled']))
            ->orderBy('check_out_date')
            ->get();

        $previousLogin = session('previous_login_at');
        $unscheduled = collect();

        foreach ($bookings as $b) {
            $exists = CleaningSession::query()
                ->where('property_id', $b->property_id)
                ->whereDate('scheduled_date', $b->check_out_date)
                ->exists();

            if (! $exists) {
                $unscheduled->push([
                    'property_id'   => $b->property_id,
                    'property_name' => $names[$b->property_id] ?? '',
                    'checkout_date' => $b->check_out_date,
                    'guest_name'    => $b->guest_name ?: 'Guest',
                    'uid'           => (string) $b->id,
                    'source'        => $b->booking_platform ?: ($b->source ?: 'Booking'),
                    'is_new'        => $previousLogin ? $b->created_at > $previousLogin : false,
                ]);
            }
        }

        return $unscheduled->sortBy('checkout_date')->values();
    }
}
