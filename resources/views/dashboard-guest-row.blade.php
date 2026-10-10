@php
    $b = $row['booking'];
    $gName = $row['guest'];
    $kind = $row['kind'] ?? '';
    $parts = preg_split('/\s+/', trim($gName));
    $initials = strtoupper(mb_substr($parts[0] ?? 'G', 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    $openGuest = ['url' => route('admin.guests.show', $b).'?embed=1', 'title' => $gName];
    $cl = $row['cleaner'] ?? null;
    $assign = $row['assign'] ?? null;

    $tab = $row['tab'] ?? '';
    $whenWord = match ($tab) { 'today' => ' today', 'tomorrow' => ' tomorrow', default => '' };
    $time = $row['time'] ?? '';
    $nights = abs((int) $b->check_in_date->copy()->startOfDay()->diffInDays($b->check_out_date->copy()->startOfDay()));
    $nightsLabel = $nights.' '.($nights === 1 ? 'night' : 'nights');

    if (! empty($row['attention'])) {
        $pill = 'Needs attention'; $pillClass = 'bg-red-100 text-red-700';
        $detail = $row['meta'] ?? null;
    } elseif ($kind === 'arrival') {
        $pill = 'Check-in'.$whenWord; $pillClass = 'bg-emerald-100 text-emerald-800';
        $detail = trim(($time ? $time.' · ' : '').$nightsLabel);
    } elseif ($kind === 'checkout') {
        $pill = 'Check-out'.$whenWord; $pillClass = 'bg-slate-100 text-slate-600';
        $detail = $time ?: null;
    } else {
        $pill = 'Hosting'; $pillClass = 'bg-slate-100 text-slate-600';
        $detail = $row['meta'] ?? null;
    }
@endphp
<div class="px-4 py-3 md:px-5">
<div class="flex items-start gap-2 sm:gap-3">
    <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 sm:h-10 sm:w-10 text-sm font-bold text-slate-700" @click="$dispatch('open-guest', @js($openGuest))" aria-label="Open {{ $gName }}">{{ $initials }}</button>

    <div class="min-w-0 flex-1">
        <button type="button" class="block max-w-full break-words text-left text-base font-semibold leading-tight text-slate-950 hover:underline" @click="$dispatch('open-guest', @js($openGuest))">{{ $gName }}</button>
        <p class="break-words text-sm text-slate-500">{{ $row['property'] }}</p>
    </div>

    <div class="flex shrink-0 items-start gap-0.5 sm:gap-1">
        <span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold {{ $pillClass }}">{{ $pill }}</span>
        <div class="relative" x-data="{ m: false }" @click.outside="m = false" @keydown.escape.window="m = false">
            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" @click="m = !m" aria-label="Guest actions" :aria-expanded="m">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="10" cy="4" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="10" cy="16" r="1.6"/></svg>
            </button>
            <div x-show="m" x-cloak x-transition.opacity class="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                <button type="button" class="block w-full px-4 py-2 text-left text-sm font-medium text-slate-800 hover:bg-slate-50" @click="m = false; $dispatch('open-guest', @js($openGuest))">View guest</button>
                @if($assign)
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm font-medium text-slate-800 hover:bg-slate-50" @click="m = false; openQuickAssign(@js($assign))">Assign cleaner</button>
                @endif
            </div>
        </div>
    </div>
</div>
<div class="pl-11 sm:pl-[3.25rem]">
        @if($detail)
            <p class="text-sm text-slate-500">{{ $detail }}</p>
        @endif
        @if(! empty($row['hint']))
            <p class="text-sm font-semibold text-blue-700">{{ $row['hint'] }}</p>
        @endif
        @if($cl)
            <p class="text-sm {{ ($cl['tone'] ?? '') === 'red' ? 'font-semibold text-red-600' : 'text-slate-500' }}">{{ $cl['text'] }}</p>
        @endif
        @if($kind === 'arrival' && ! empty($row['parking']))
            <span class="mt-1 inline-flex items-center rounded-md px-2 py-0.5 text-xs font-bold {{ $row['parking'] ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-600' }}">{{ $row['parking'] ? '🅿 Parking' : 'No parking' }}</span>
        @endif
</div>
</div>
