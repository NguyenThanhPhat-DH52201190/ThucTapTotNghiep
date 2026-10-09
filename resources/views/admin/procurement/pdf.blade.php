<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Purchase Order {{ $po->po_number }}</title>
<style>
@page { margin: 22px 24px 28px; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #000; }
h1 { text-align:center; font-size:15px; margin:0 0 8px; }
.company-header { margin:0 0 8px; line-height:1.35; }
.company-logo { display:block; width:150px; height:auto; margin-bottom:4px; }
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
.goods th { border:1px solid #000; padding:4px 2px; font-size:7px; }
.goods td { border-left:1px solid #000; border-right:1px solid #000; padding:6px 3px; font-size:7px; }
.goods tr { page-break-inside:avoid; }
.goods thead { display:table-header-group; }
.goods .total td { border-top:1px solid #000; padding:4px 2px; font-weight:bold; }
.details td { padding:6px 3px; }
.details tr { page-break-inside:avoid; }
</style></head><body>
<div class="company-header">@if($logoDataUri)<img class="company-logo" src="{{ $logoDataUri }}" alt="Global Safewear">@endif<br>72 Anson Road<br>#07-04, Anson House, SINGAPORE 079911<br>www.globalsafewear.com</div>
<h1>Purchase Order</h1>
<table class="header">
<tr><td class="label">Purchase Order No.:</td><td>{{ $po->po_number }}</td><td class="right"><strong>Date:</strong> &nbsp; {{ $po->order_date ? \Carbon\Carbon::parse($po->order_date)->format('d/m/Y') : '-' }}</td></tr>
<tr><td class="label">Order to:</td><td><strong>{{ $supplier?->name ?: '-' }}</strong><div class="multiline">{{ $supplier?->address }}</div>@if($supplier?->contact_person)<div>Att: {{ $supplier->contact_person }}</div>@endif @if($supplier?->phone)<div>Tel: {{ $supplier->phone }}</div>@endif @if($supplier?->email)<div>Email: {{ $supplier->email }}</div>@endif</td><td class="right red">{{ !empty($settings['revised']) ? 'REVISED' : '' }}</td></tr>
</table>
<div><strong>Ref.</strong> &nbsp; {{ $settings['reference'] ?? '' }}</div>
@php
    $vatRate = (float) ($po->vat_percent ?? 0);
    $vatFactor = $vatRate / 100;
    $currency = $po->currency ?: 'USD';
    $showVatColumns = strtoupper(trim($currency)) === 'VND';
    $lineNo = 0;
    $vatTotal = (float) $po->total_amount * $vatFactor;
@endphp
<table class="goods">
<thead><tr>
<th style="width:3%">No.</th><th style="width:8%">Supplier Item<br>Code</th><th style="width:13%">Description</th><th style="width:6%">Color Code</th><th style="width:5%">Color</th><th style="width:8%">GSV Code</th><th style="width:5%">Qty</th><th style="width:4%">UOM</th><th style="width:6%">Size</th><th style="width:{{ $showVatColumns ? '7%' : '10%' }}">Unit Price<br>({{ $currency }})</th><th style="width:{{ $showVatColumns ? '7%' : '10%' }}">Amount<br>({{ $currency }})</th>@if($showVatColumns)<th style="width:5%">VAT Rate</th><th style="width:7%">VAT Amount<br>({{ $currency }})</th>@endif<th style="width:{{ $showVatColumns ? '9%' : '15%' }}">Total Amount<br>({{ $currency }})</th><th style="width:7%">Note</th>
</tr></thead>
<tbody>
@foreach($items as $item)<tr>
@php($amount = (float) $item->quantity * (float) $item->unit_price)
@php($lineVat = $amount * $vatFactor)
<td class="center">{{ ++$lineNo }}</td><td>{{ $item->vendor_item_code ?: '' }}</td><td class="multiline">{{ $item->supplier_description ?: $item->material_name }}</td><td>{{ $item->supplier_color_code ?: '' }}</td><td>{{ $item->color ?: $item->master_color }}</td><td>{{ $item->material_code }}</td><td class="center red">{{ rtrim(rtrim(number_format($item->quantity, 4, '.', ','), '0'), '.') }}</td><td class="center">{{ $item->unit }}</td><td>{{ $item->master_size ?: '' }}</td><td class="right">{{ number_format($item->unit_price, 4) }}</td><td class="right">{{ number_format($amount, 2) }}</td>@if($showVatColumns)<td class="center">{{ $vatRate > 0 ? number_format($vatRate, 2).'%' : '-' }}</td><td class="right">{{ $vatRate > 0 ? number_format($lineVat, 2) : '-' }}</td>@endif<td class="right">{{ number_format($amount + $lineVat, 2) }}</td><td class="multiline">{{ $item->notes ?: '' }}</td>
</tr>@endforeach
@foreach($surcharges as $surcharge)<tr>
@php($amount = (float) $surcharge->quantity * (float) $surcharge->unit_price)
@php($lineVat = $amount * $vatFactor)
<td class="center">{{ ++$lineNo }}</td><td></td><td class="red multiline">{{ $surcharge->description }}</td><td></td><td></td><td></td><td class="center red">{{ rtrim(rtrim(number_format($surcharge->quantity, 4, '.', ','), '0'), '.') }}</td><td class="center">{{ $surcharge->unit }}</td><td></td><td class="right">{{ number_format($surcharge->unit_price, 4) }}</td><td class="right">{{ number_format($amount, 2) }}</td>@if($showVatColumns)<td class="center">{{ $vatRate > 0 ? number_format($vatRate, 2).'%' : '-' }}</td><td class="right">{{ $vatRate > 0 ? number_format($lineVat, 2) : '-' }}</td>@endif<td class="right">{{ number_format($amount + $lineVat, 2) }}</td><td></td>
</tr>@endforeach
<tr class="total"><td colspan="10" class="right">TOTAL</td><td class="right">{{ number_format((float) $po->total_amount, 2) }}</td>@if($showVatColumns)<td></td><td class="right">{{ $vatRate > 0 ? number_format($vatTotal, 2) : '-' }}</td>@endif<td class="right">{{ number_format((float) $po->total_amount + $vatTotal, 2) }}</td><td></td></tr>
</tbody></table>
<table class="details">
<tr><td class="label">SHIPPING MARK</td><td class="multiline">{{ $settings['shipping_mark'] ?? '' }}</td></tr>
@if(!empty($settings['freight_terms']))<tr><td class="label">FREIGHT TERMS</td><td class="red multiline">{{ $settings['freight_terms'] }}</td></tr>@endif
<tr><td class="label">BUYER</td><td class="multiline">{{ $settings['buyer'] ?? '' }}</td></tr>
<tr><td class="label">CONSIGNEE</td><td class="multiline">{{ $settings['consignee'] ?? '' }}</td></tr>
<tr><td class="label">PAYMENT DETAILS</td><td class="multiline">{{ $settings['payment_details'] ?? '' }}</td></tr>
<tr><td class="label">SHIPMENT DATE</td><td class="red multiline">{{ $settings['shipment_date'] ?? '' }}</td></tr>
@if($po->notes)<tr><td class="label">NOTES</td><td class="multiline">{{ $po->notes }}</td></tr>@endif
</table>
</body></html>
