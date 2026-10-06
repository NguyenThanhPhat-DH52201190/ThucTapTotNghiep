<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Purchase Order {{ $po->po_number }}</title>
<style>
@page { margin: 22px 30px 30px; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #000; }
h1 { text-align:center; font-size:15px; margin:0 0 8px; }
table { width:100%; border-collapse:collapse; }
td, th { vertical-align:top; overflow-wrap:break-word; }
.header { border-top:3px solid #000; border-bottom:3px solid #000; margin-bottom:12px; }
.header td { padding:7px 3px; }
.label { font-weight:bold; width:125px; }
.multiline { white-space:pre-line; }
.red { color:#ed0000; }
.right { text-align:right; }
.center { text-align:center; }
.goods { table-layout:fixed; border:2px solid #000; margin:12px 0; }
.goods th { border:2px solid #000; padding:5px 3px; font-size:9px; }
.goods td { border-left:2px solid #000; border-right:2px solid #000; padding:10px 6px; }
.goods tr { page-break-inside:avoid; }
.goods thead { display:table-header-group; }
.goods .total td { border-top:2px solid #000; padding:4px; font-weight:bold; }
.details td { padding:6px 3px; }
.details tr { page-break-inside:avoid; }
</style></head><body>
<h1>Purchase Order</h1>
<table class="header">
<tr><td class="label">Purchase Order No.:</td><td>{{ $po->po_number }}</td><td class="right"><strong>Date:</strong> &nbsp; {{ $po->order_date ? \Carbon\Carbon::parse($po->order_date)->format('d/m/Y') : '-' }}</td></tr>
<tr><td class="label">Order to:</td><td><strong>{{ $supplier?->name ?: '-' }}</strong><div class="multiline">{{ $supplier?->address }}</div>@if($supplier?->contact_person)<div>Att: {{ $supplier->contact_person }}</div>@endif @if($supplier?->phone)<div>Tel: {{ $supplier->phone }}</div>@endif @if($supplier?->email)<div>Email: {{ $supplier->email }}</div>@endif</td><td class="right red">{{ !empty($settings['revised']) ? 'REVISED' : '' }}</td></tr>
</table>
<div><strong>Ref.</strong> &nbsp; {{ $settings['reference'] ?? '' }}</div>
<table class="goods">
<thead><tr><th style="width:40%">Description of goods</th><th style="width:10%">Colour</th><th style="width:6%">Qty</th><th style="width:6%">Unit</th><th style="width:10%">Unit Price<br>({{ $po->currency ?: 'USD' }})</th><th style="width:12%">Total<br>({{ $po->currency ?: 'USD' }}, incl. VAT)</th><th style="width:10%">Exchange Rate</th><th style="width:6%">VAT %</th></tr></thead>
<tbody>
@foreach($items as $item)<tr>
<td><div>{{ $item->material_code }}</div><div class="multiline">{{ $item->material_name }}</div>@if($item->master_size)<div>Size: {{ $item->master_size }}</div>@endif @if($item->notes)<div class="multiline">{{ $item->notes }}</div>@endif</td>
<td class="center multiline">{{ $item->color ?: $item->master_color }}</td><td class="center red">{{ rtrim(rtrim(number_format($item->quantity, 4, '.', ','), '0'), '.') }}</td><td class="center">{{ $item->unit }}</td><td class="right">{{ number_format($item->unit_price, 4) }}</td><td class="right">{{ number_format((float) $item->total_price * (1 + (float) ($po->vat_percent ?? 0) / 100), 2) }}</td><td class="right">{{ number_format((float) ($po->exchange_rate ?? 1), 6) }}</td><td class="right">{{ number_format((float) ($po->vat_percent ?? 0), 2) }}%</td>
</tr>@endforeach
@foreach($surcharges as $surcharge)<tr>
<td class="red">{{ $surcharge->description }}</td><td></td><td class="center red">{{ rtrim(rtrim(number_format($surcharge->quantity, 4, '.', ','), '0'), '.') }}</td><td class="center">{{ $surcharge->unit }}</td><td class="right">{{ number_format($surcharge->unit_price, 4) }}</td><td class="right">{{ number_format((float) $surcharge->total_price * (1 + (float) ($po->vat_percent ?? 0) / 100), 2) }}</td><td class="right">{{ number_format((float) ($po->exchange_rate ?? 1), 6) }}</td><td class="right">{{ number_format((float) ($po->vat_percent ?? 0), 2) }}%</td>
</tr>@endforeach
@if(!empty($settings['freight_terms']))<tr><td class="red multiline">{{ $settings['freight_terms'] }}</td>@for($i = 0; $i < 7; $i++)<td></td>@endfor</tr>@endif
<tr class="total"><td colspan="4"></td><td class="center">Total</td><td class="right">{{ number_format((float) $po->total_amount * (1 + (float) ($po->vat_percent ?? 0) / 100), 2) }}</td><td></td><td></td></tr>
</tbody></table>
<table class="details">
<tr><td class="label">SHIPPING MARK</td><td class="multiline">{{ $settings['shipping_mark'] ?? '' }}</td></tr>
<tr><td class="label">CONSIGNEE AND<br>DELIVER TO</td><td class="multiline">{{ $settings['consignee'] ?? '' }}</td></tr>
<tr><td class="label">PAYMENT DETAILS</td><td class="multiline">{{ $settings['payment_details'] ?? '' }}</td></tr>
<tr><td class="label">TOTAL PRICE {{ $po->currency ?: 'USD' }} (INCLUDING VAT)</td><td><strong>{{ number_format((float) $po->total_amount * (1 + (float) ($po->vat_percent ?? 0) / 100), 2) }}</strong></td></tr>
<tr><td class="label">EXCHANGE RATE</td><td>{{ number_format((float) ($po->exchange_rate ?? 1), 6) }}</td></tr>
<tr><td class="label">VAT</td><td>{{ number_format((float) ($po->vat_percent ?? 0), 2) }}%</td></tr>
<tr><td class="label">SHIPMENT DATE</td><td class="red multiline">{{ $settings['shipment_date'] ?? '' }}</td></tr>
@if($po->notes)<tr><td class="label">NOTES</td><td class="multiline">{{ $po->notes }}</td></tr>@endif
</table>
</body></html>
