<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;

/**
 * Owner dashboard: one prioritised list per day tab, plus the in-house guests.
 * Each row carries ONE "what to do" item (todo), optional notes, and nothing else.
 */
class DashboardDayService
{
    private const LAST_DAY = 10;

    private const EXCLUDED_STATUSES = ['cancelled', 'canceled', 'declined', 'rejected', 'expired', 'no_show'];

    public function get(User $user, array $checkoutBoard): array
    {
        $tz = config('app.display_timezone');
        $today = Carbon::now($tz)->startOfDay();
        $todayStr = $today->toDateString();
        $lastStr = $today->copy()->addDays(self::LAST_DAY)->toDateString();

        $offsets = [];
        for ($i = 0; $i <= self::LAST_DAY; $i++) {
            $offsets[$today->copy()->addDays($i)->toDateString()] = $i;
        }

        $tabs = [
            'today' => $this->tab('Today', $today, 0, 0),
            'tomorrow' => $this->tab('Tomorrow', $today, 1, 1),
            'day2' => $this->tab('In 2 days', $today, 2, 2),
            'day3' => $this->tab('In 3 days', $today, 3, 3),
            'week' => $this->tab('Next week', $today, 4, self::LAST_DAY),
        ];

        $items = [];

        // ---- Checkouts ----
        foreach (($checkoutBoard['groups'] ?? []) as $rows) {
            foreach ($rows as $row) {
                $b = $row['booking'];
                $date = $row['date']->toDateString();
                if (! isset($offsets[$date])) {
                    continue;
                }
                $offset = $offsets[$date];

                $todo = null;
                $notes = [];
                $assign = null;

                if (! $row['covered']) {
                    $todo = ['text' => 'Cleaner needed', 'cta' => 'Assign cleaner', 'tone' => 'red'];
                    $session = $row['session'];
                    $assign = [
                        'property_id' => $b->property_id,
                        'property_name' => $row['property']?->name,
                        'date' => $date,
                        'session_id' => $session?->id,
                        'housekeeper_id' => $session?->housekeeper_id,
                        'scheduled_time' => $session?->scheduled_time?->format('H:i'),
                        'cleaners' => collect($row['cleaner_options'])->values()->all(),
                    ];
                }
                if ($b->checkout_time_status === 'pending') {
                    $late = ['text' => 'Late checkout request to answer', 'cta' => 'Respond', 'tone' => 'amber', 'action' => 'time_checkout'];
                    $todo ? $notes[] = $late : $todo = $late;
                }
                if ($row['overlap']) {
                    $notes[] = ['text' => 'Overlapping bookings ('.count($row['guest_names']).')', 'tone' => 'red'];
                }
                if ($row['same_day'] && $row['next']) {
                    $notes[] = ['text' => 'Same-day turnover', 'detail' => 'Next guest checks in '.($row['next_time'] ?: 'today'), 'tone' => 'amber'];
                }

                $items[] = [
                    'kind' => 'checkout',
                    'tab' => $this->tabKey($offset),
                    'date' => $date,
                    'date_long' => $row['date']->format('l, M j'),
                    'time' => $row['checkout_time'],
                    'minutes' => $this->minutes($row['checkout_time']),
                    'guest' => $b->guest_name ?: 'Guest',
                    'property' => $row['property']?->name,
                    'booking' => $b,
                    'todo' => $todo,
                    'notes' => $notes,
                    'ok' => $row['covered'] ? 'Cleaner: '.$row['cleaner'] : null,
                    'assign' => $assign,
                    'urgent' => $this->urgent($todo, $notes, $offset),
                ];
            }
        }

        // ---- Arrivals ----
        $arrivals = $this->active($user)
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '>=', $todayStr)
            ->whereDate('check_in_date', '<=', $lastStr)
            ->get();

        foreach ($arrivals as $b) {
            $date = $b->check_in_date->toDateString();
            if (! isset($offsets[$date])) {
                continue;
            }
            $offset = $offsets[$date];

            // Already arrived today: belongs under "Currently hosting".
            if ($offset === 0 && ($b->isCheckedIn() || $b->manually_checked_in || $b->gps_verified)) {
                continue;
            }

            $todo = $this->arrivalTodo($b);
            $notes = [];
            $acct = CleanerAccountability::forBooking($b, $todayStr);
            if ($acct && ($acct['tone'] ?? null) === 'warn') {
                $notes[] = ['text' => $acct['text'], 'tone' => 'red'];
            }

            $time = (string) $b->effectiveCheckinTimeFormatted();
            $items[] = [
                'kind' => 'arrival',
                'tab' => $this->tabKey($offset),
                'date' => $date,
                'date_long' => $b->check_in_date->format('l, M j'),
                'time' => $time,
                'minutes' => $this->minutes($time),
                'guest' => $b->guest_name ?: 'Guest',
                'property' => $b->property?->name,
                'booking' => $b,
                'todo' => $todo,
                'notes' => $notes,
                'ok' => null,
                'assign' => null,
                'urgent' => $this->urgent($todo, $notes, $offset),
            ];
        }

        foreach ($tabs as $key => &$tab) {
            $list = collect($items)->where('tab', $key)
                ->sortBy(fn ($i) => sprintf(
                    '%s|%d|%05d|%s',
                    $key === 'week' ? $i['date'] : '',
                    $i['urgent'] ? 0 : 1,
                    $i['minutes'],
                    strtolower($i['guest'])
                ))->values();

            $tab['items'] = $list->all();
            $tab['arrivals'] = $list->where('kind', 'arrival')->count();
            $tab['checkouts'] = $list->where('kind', 'checkout')->count();
            $tab['attention'] = $list->where('urgent', true)->count();
        }
        unset($tab);

        return ['tabs' => $tabs, 'hosting' => $this->hosting($user, $today)];
    }

    /** One plain-language "what to do" for an arriving guest, or null if nothing is needed. */
    private function arrivalTodo(Booking $b): ?array
    {
        $reason = $b->priorityReason();

        $todo = match ($reason) {
            'ID needs approval', 'ID scan needs manual review' => ['text' => 'Review guest ID', 'cta' => 'Review ID', 'tone' => 'red', 'action' => 'id'],
            'Awaiting approval' => ['text' => 'Approve guest', 'cta' => 'Approve', 'tone' => 'red', 'action' => 'approve'],
            'Awaiting deposit' => ['text' => 'Waiting on guest deposit', 'cta' => 'Check deposit', 'tone' => 'amber', 'action' => 'deposit'],
            'Background check pending' => ['text' => 'Background check pending', 'cta' => 'Mark passed', 'tone' => 'amber', 'action' => 'background'],
            'Deposit not verified' => ['text' => 'Verify deposit', 'cta' => 'Verify', 'tone' => 'red', 'action' => 'deposit'],
            'Early check-in request pending' => ['text' => 'Early check-in request to answer', 'cta' => 'Respond', 'tone' => 'amber', 'action' => 'time_checkin'],
            'Late checkout request pending' => ['text' => 'Late checkout request to answer', 'cta' => 'Respond', 'tone' => 'amber', 'action' => 'time_checkout'],
            default => null,
        };

        if (! $todo && $b->effectiveStatus() === 'pending') {
            $todo = ['text' => "Guest hasn't registered yet", 'cta' => 'View', 'tone' => 'amber', 'action' => null];
        }

        return $todo;
    }

    private function urgent(?array $todo, array $notes, int $offset): bool
    {
        if ($todo && ($todo['tone'] === 'red' || $offset <= 1)) {
            return true;
        }

        return collect($notes)->contains(fn ($n) => $n['tone'] === 'red');
    }

    private function hosting(User $user, Carbon $today): array
    {
        $todayStr = $today->toDateString();

        return $this->active($user)
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '<=', $todayStr)
            ->whereDate('check_out_date', '>', $todayStr)
            ->get()
            ->filter(fn (Booking $b) => $b->check_in_date->toDateString() < $todayStr
                || $b->isCheckedIn() || $b->manually_checked_in || $b->gps_verified)
            ->sortBy(fn (Booking $b) => $b->check_out_date->toDateString().'|'.strtolower((string) $b->guest_name))
            ->values()
            ->map(fn (Booking $b) => [
                'booking' => $b,
                'guest' => $b->guest_name ?: 'Guest',
                'property' => $b->property?->name,
                'when' => $this->checkoutWhen($today, $b),
                'time' => (string) $b->effectiveCheckoutTimeFormatted(),
            ])->all();
    }

    private function checkoutWhen(Carbon $today, Booking $b): string
    {
        $out = Carbon::parse($b->check_out_date->toDateString(), $today->getTimezone())->startOfDay();
        $days = (int) round($today->diffInDays($out, false));

        return match (true) {
            $days <= 1 => 'tomorrow',
            $days === 2 => 'in 2 days',
            $days <= 6 => $out->format('l'),
            default => $out->format('M j'),
        };
    }

    private function active(User $user)
    {
        return Booking::with('property')
            ->notArchived()
            ->whereNull('cancelled_at')
            ->where(fn ($w) => $w->whereNull('status')->orWhereNotIn('status', self::EXCLUDED_STATUSES))
            ->whereHas('property', fn ($q) => $q->visibleTo($user));
    }

    private function tab(string $label, Carbon $today, int $from, int $to): array
    {
        $start = $today->copy()->addDays($from);
        $end = $today->copy()->addDays($to);

        return [
            'label' => $label,
            'sub' => $from === $to ? $start->format('D, M j') : $start->format('M j').' – '.$end->format('M j'),
            'range' => $from === $to ? $start->format('l, M j') : $start->format('M j').' – '.$end->format('M j'),
            'items' => [], 'arrivals' => 0, 'checkouts' => 0, 'attention' => 0,
        ];
    }

    private function tabKey(int $offset): string
    {
        return match (true) {
            $offset === 0 => 'today',
            $offset === 1 => 'tomorrow',
            $offset === 2 => 'day2',
            $offset === 3 => 'day3',
            default => 'week',
        };
    }

    private function minutes(?string $label): int
    {
        if (! $label) {
            return 24 * 60;
        }
        try {
            $c = Carbon::parse($label);

            return $c->hour * 60 + $c->minute;
        } catch (\Throwable) {
            return 24 * 60;
        }
    }
}
