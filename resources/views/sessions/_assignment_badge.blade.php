@php
    $as = $s->assignment_status;
    $progress = match ($s->status) {
        'pending' => 'Not started',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        default => ucfirst(str_replace('_', ' ', (string) $s->status)),
    };
@endphp
<div class="text-right">
    @if($as === 'confirmed')
        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Accepted</span>
    @elseif($as === 'pending_confirmation')
        <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Awaiting acceptance</span>
    @endif
    <span class="mt-1 block text-xs text-gray-500">{{ $progress }}</span>
</div>
