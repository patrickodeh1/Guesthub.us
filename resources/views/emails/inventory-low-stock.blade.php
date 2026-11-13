@component('mail::message')
# Inventory running low

The following item at **{{ $item->property->name }}** has reached or fallen below its reorder level.

| Item | Current quantity | Reorder level |
|:--|--:|--:|
| {{ $item->name }} | {{ number_format($item->quantity_on_hand, 3) }} {{ $item->unit_of_measure }} | {{ number_format((float) $item->reorder_level, 3) }} {{ $item->unit_of_measure }} |

@if($item->supplier)
**Supplier:** {{ $item->supplier }}

@endif
@if($item->last_ordered_at)
**Last ordered:** {{ $item->last_ordered_at->format('M j, Y') }}

@endif
Please review the stock and arrange a reorder or transfer.

Thanks,
{{ config('app.name') }}
@endcomponent
