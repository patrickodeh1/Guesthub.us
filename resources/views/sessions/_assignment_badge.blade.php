@php
    $as = $s->assignment_status;
    $st = $s->status;
    $progress = match ($st) {
        'pending' => 'Not started',
        default => ucfirst(str_replace('_', ' ', (string) $st)),
    };
@endphp
<div class="text-right">
    @if($st === 'in_progress')
        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">In progress</span>
    @elseif($st === 'completed')
        <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">Completed</span>
    @elseif($as === 'confirmed')
        <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Accepted</span>
    @elseif($as === 'pending_confirmation')
        <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Awaiting acceptance</span>
    @elseif($as === 'declined')
        <span class="inline-flex rounded-full bg-red-600 px-2.5 py-0.5 text-xs font-semibold text-white">Declined</span>
    @else
        <span class="mt-1 block text-xs text-gray-500">{{ $progress }}</span>
    @endif
</div>
