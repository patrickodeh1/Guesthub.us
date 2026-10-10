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
            'week' => $this->tab('Upcoming', $today, 3, self::LAST_DAY),
        ];

        $items = [];
        $cleanerRows = [];

        // ---- Checkouts ----
        foreach (($checkoutBoard['groups'] ?? []) as $rows) {
            foreach ($rows as $row) {
                $b = $row['booking'];
                $date = $row['date']->toDateString();
                $cleanerRows[$b->id] = $row;
                if (! isset($offsets[$date])) {
                    continue;
                }
                $offset = $offsets[$date];

                // Upcoming (day 3+) lists check-ins only; in-house guests appear under Currently hosting.
                // Check-outs live in Today only (Currently hosting covers the rest).
                // Exception: a pending late-checkout request still needs attention in Today.
                if ($offset >= 1 && $b->checkout_time_status !== 'pending') {
                    continue;
                }

                // Not arrived yet: the checkout shows once they are in-house.
                if ($b->check_in_date->toDateString() > $todayStr) {
                    continue;
                }

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

        foreach ($this->farAttention($user, $lastStr) as $x) {
            $items[] = $x;
        }

        $hostingRaw = $this->hosting($user, $today);

        $rows = collect($items)->map(fn ($i) => $this->decorate($i, $today));

        // Everything needing the owner's attention lives in Today, whatever day it relates to.
        $attention = $rows->filter(fn ($r) => $r['attention'])
            ->sortBy(fn ($r) => sprintf('%d|%s|%05d|%s', $r['urgent'] ? 0 : 1, $r['date'], $r['minutes'], strtolower($r['guest'])))
            ->groupBy(fn ($r) => $r['booking']->id)
            ->map(function ($g) {
                $first = $g->first();
                $first['reasons'] = $g->pluck('todo')->filter(fn ($t) => $t && ($t['action'] ?? null) !== null)->pluck('text')->unique()->values()->all();
                $first['assign'] = $g->pluck('assign')->filter()->first();
                $first['cleaner'] = $g->pluck('cleaner')->filter()->first();
                $first['urgent'] = $g->contains(fn ($r) => $r['urgent']);

                return $first;
            })
            ->values()->all();

        $cards = [];
        foreach ($tabs as $key => $tab) {
            $inTab = $rows->where('tab', $key);

            if ($key === 'today') {
                $plain = $inTab->reject(fn ($r) => $r['attention']);
                $checkIns = $plain->where('kind', 'arrival')->sortBy('minutes')->values()->all();
                $checkOuts = $plain->where('kind', 'checkout')->sortBy('minutes')->values()->all();
                $attIds = array_map(fn ($r) => $r['booking']->id, $attention);
                $hostRows = array_merge($checkOuts, array_map(
                    fn ($h) => $this->hostingRow($h, $cleanerRows[$h['booking']->id] ?? null),
                    array_values(array_filter($hostingRaw, fn ($h) => ! in_array($h['booking']->id, $attIds)))
                ));

                $sections = [
                    ['title' => 'Needs your attention', 'tone' => 'red', 'rows' => $attention],
                    ['title' => 'Checking in', 'tone' => 'green', 'rows' => $checkIns],
                    ['title' => 'Currently hosting', 'tone' => 'slate', 'rows' => $hostRows],
                ];
            } else {
                $sections = [[
                    'title' => null,
                    'tone' => 'slate',
                    'rows' => $inTab->reject(fn ($r) => $r['kind'] === 'checkout')->map(fn ($r) => array_merge($r, ['status' => $r['line'], 'meta' => null, 'hint' => null, 'attention' => false]))->sortBy(fn ($r) => sprintf('%s|%05d|%s', $r['date'], $r['minutes'], strtolower($r['guest'])))->values()->all(),
                ]];
            }

            $cards[$key] = [
                'label' => $tab['label'],
                'range' => $tab['range'],
                'sections' => array_values(array_filter($sections, fn ($sec) => count($sec['rows']))),
                'arrivals' => $inTab->where('kind', 'arrival')->count(),
                'checkouts' => $key === 'today' ? $inTab->where('kind', 'checkout')->count() : 0,
                'attention' => $key === 'today' ? count($attention) : 0,
                'grouped' => $key === 'week',
            ];
        }

        return ['cards' => $cards];
    }

    /** Needs the owner's attention: a submitted item to act on (any day), or a problem on a checkout today. */
    private function isAttention(array $i): bool
    {
        if (! $i['todo']) {
            return false;
        }

        return $i['kind'] === 'checkout'
            ? ($i['todo']['action'] ?? null) !== null
            : ($i['todo']['action'] ?? null) !== null;
    }

    /** Only the info relevant to this row. */
    private function decorate(array $i, Carbon $today): array
    {
        $b = $i['booking'];
        $nights = abs((int) $b->check_in_date->copy()->startOfDay()->diffInDays($b->check_out_date->copy()->startOfDay()));
        $nightsLabel = $nights.' '.($nights === 1 ? 'night' : 'nights');
        $when = match ($i['tab']) {
            'today' => ' today',
            'tomorrow' => ' tomorrow',
            'day2' => ' '.Carbon::parse($i['date'])->format('D, M j'),
            default => '',
        };
        $time = $i['time'] ? ' · '.$i['time'] : '';
        $isArrival = $i['kind'] === 'arrival';
        $line = $isArrival
            ? 'Checking in'.$when.$time.' · '.$nightsLabel
            : 'Checking out'.$when.$time;

        $attention = $this->isAttention($i);
        $status = $line;
        $tone = $isArrival ? 'green' : 'slate';
        $meta = null;
        $hint = null;

        if ($attention) {
            $status = 'Needs review';
            $tone = 'red';
            $meta = $isArrival
                ? $b->check_in_date->format('M j').' – '.$b->check_out_date->format('M j').' · '.$nightsLabel
                : 'Checks out '.Carbon::parse($i['date'])->format('M j').$time;
        } elseif ($isArrival && $i['todo']) {
            $hint = $i['todo']['text']; // e.g. "Guest hasn't registered yet"
        }

        $cleaner = null;
        if (! $isArrival) {
            if ($i['assign']) {
                $cleaner = ['text' => 'No cleaner assigned', 'tone' => 'red'];
            } elseif ($i['ok']) {
                $cleaner = ['text' => $i['ok'], 'tone' => 'slate'];
            }
        }

        return array_merge($i, [
            'line' => $line,
            'status' => $status,
            'status_tone' => $tone,
            'meta' => $meta,
            'hint' => $hint,
            'cleaner' => $cleaner,
            'parking' => $isArrival ? $b->parking_needed : null,
            'attention' => $attention,
        ]);
    }

    private function hostingRow(array $h, ?array $cr = null): array
    {
        $cleaner = null;
        $assign = null;
        if ($cr) {
            if (! $cr['covered']) {
                $cleaner = ['text' => 'No cleaner assigned', 'tone' => 'red'];
                $assign = $this->assignData($cr);
            } else {
                $cleaner = ['text' => 'Cleaner: '.$cr['cleaner'], 'tone' => 'slate'];
            }
        }

        return [
            'kind' => 'hosting',
            'booking' => $h['booking'],
            'guest' => $h['guest'],
            'property' => $h['property'],
            'status' => 'Currently hosting',
            'status_tone' => 'slate',
            'meta' => 'Checks out '.$h['when'].($h['time'] ? ' · '.$h['time'] : ''),
            'hint' => null, 'parking' => null, 'reasons' => [],
            'cleaner' => $cleaner, 'assign' => $assign,
            'todo' => null, 'notes' => [], 'ok' => null,
            'attention' => false, 'urgent' => false, 'date' => '', 'minutes' => 0,
        ];
    }

    private function assignData(array $row): array
    {
        $b = $row['booking'];
        $session = $row['session'] ?? null;

        return [
            'property_id' => $b->property_id,
            'property_name' => $row['property']?->name,
            'date' => $row['date']->toDateString(),
            'session_id' => $session?->id,
            'housekeeper_id' => $session?->housekeeper_id,
            'scheduled_time' => $session?->scheduled_time?->format('H:i'),
            'cleaners' => collect($row['cleaner_options'])->values()->all(),
        ];
    }

    /** Submitted items on bookings beyond the dashboard window: they still need attention today. */
    private function farAttention(User $user, string $lastStr): array
    {
        $out = [];
        $bookings = $this->active($user)
            ->whereNull('checked_out_at')
            ->whereDate('check_in_date', '>', $lastStr)
            ->get();

        foreach ($bookings as $b) {
            $todo = $this->arrivalTodo($b);
            if (! $todo || ($todo['action'] ?? null) === null) {
                continue;
            }
            $time = (string) $b->effectiveCheckinTimeFormatted();
            $out[] = [
                'kind' => 'arrival',
                'tab' => 'later',
                'date' => $b->check_in_date->toDateString(),
                'date_long' => $b->check_in_date->format('l, M j'),
                'time' => $time,
                'minutes' => $this->minutes($time),
                'guest' => $b->guest_name ?: 'Guest',
                'property' => $b->property?->name,
                'booking' => $b,
                'todo' => $todo,
                'notes' => [],
                'ok' => null,
                'assign' => null,
                'urgent' => $this->urgent($todo, [], 99),
            ];
        }

        return $out;
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

        if (! $todo && $b->effectiveStatus() === 'pending' && $b->isIdentityComplete()) {
            $todo = ['text' => 'Registration submitted – review & approve', 'cta' => 'Review', 'tone' => 'red', 'action' => 'approve'];
        }

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
            default => 'in '.$days.' days',
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
