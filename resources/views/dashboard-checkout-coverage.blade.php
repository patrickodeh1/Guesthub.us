@if($summary['total'] > 0)
    @if($summary['uncovered'] > 0)
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border-2 border-red-200 bg-red-50 px-4 py-3">
            <p class="font-bold text-red-800">
                {{ $summary['uncovered'] }} of {{ $summary['total'] }} checkout{{ $summary['total'] === 1 ? '' : 's' }} without a cleaner
                @if($summary['today_uncovered'] > 0)
                    <span class="ml-1 rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">{{ $summary['today_uncovered'] }} today</span>
                @endif
            </p>
            <p class="text-sm font-semibold text-amber-800">
                @if($summary['same_day'] > 0) {{ $summary['same_day'] }} same-day turnover{{ $summary['same_day'] === 1 ? '' : 's' }} @endif
                @if($summary['same_day'] > 0 && $summary['overlap'] > 0) &middot; @endif
                @if($summary['overlap'] > 0) {{ $summary['overlap'] }} overlapping @endif
            </p>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
            <p class="font-bold text-emerald-800">All {{ $summary['total'] }} checkout{{ $summary['total'] === 1 ? ' is' : 's are' }} covered</p>
            <p class="text-sm font-semibold text-amber-800">
                @if($summary['same_day'] > 0) {{ $summary['same_day'] }} same-day turnover{{ $summary['same_day'] === 1 ? '' : 's' }} @endif
                @if($summary['same_day'] > 0 && $summary['overlap'] > 0) &middot; @endif
                @if($summary['overlap'] > 0) {{ $summary['overlap'] }} overlapping @endif
            </p>
        </div>
    @endif
@endif
