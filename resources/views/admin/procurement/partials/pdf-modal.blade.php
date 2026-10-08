@php
    $pdfSettings = json_decode($po->pdf_settings ?? '{}', true) ?: [];
    $pdfDefaults = [
        'reference' => '', 'shipping_mark' => $po->po_number,
        'buyer' => "GLOBAL SAFEWEAR SINGAPORE PTE LTD\n72 Anson Road\n#07-04 Anson House\nSINGAPORE 079911",
        'consignee' => "Global Safewear Viet Nam Company Ltd.\nUnit C1, C2, Lot G6-G7-G8-G9, Street N3, Dong Nam Industrial Park,\nBinh My Commune, Ho Chi Minh City, Vietnam.\nPostcode: 71611",
        'payment_details' => $po->supplier_payment_terms ?? '', 'freight_terms' => '',
        'shipment_date' => $po->expected_delivery ? \Carbon\Carbon::parse($po->expected_delivery)->format('d/m/Y') : '',
    ];
@endphp
<div class="modal fade" id="poPdfModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form method="POST" action="{{ route('admin.procurement.pdf', $po->id) }}" class="modal-content">@csrf
<div class="modal-header"><h5 class="modal-title">Export Purchase Order</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row g-3">
@foreach(['reference' => 'Ref.', 'shipping_mark' => 'Shipping Mark', 'buyer' => 'Buyer', 'consignee' => 'Consignee', 'payment_details' => 'Payment Details', 'freight_terms' => 'Freight Terms', 'shipment_date' => 'Shipment Date'] as $key => $label)
<div class="{{ in_array($key, ['buyer', 'consignee'], true) ? 'col-12' : 'col-md-6' }}"><label for="pdf_{{ $key }}" class="form-label">{{ $label }}</label><textarea id="pdf_{{ $key }}" name="{{ $key }}" class="form-control" rows="{{ in_array($key, ['buyer', 'consignee'], true) ? 4 : 2 }}">{{ old($key, $pdfSettings[$key] ?? $pdfDefaults[$key]) }}</textarea></div>
@endforeach
<div class="col-12"><div class="form-check"><input type="checkbox" name="revised" value="1" id="pdfRevised" class="form-check-input" @checked(old('revised', $pdfSettings['revised'] ?? false))><label for="pdfRevised" class="form-check-label">Show REVISED</label></div></div>
</div></div><div class="modal-footer"><button class="btn btn-outline-success" formaction="{{ route('admin.procurement.excel', $po->id) }}" formmethod="POST"><i class="bi bi-file-earmark-spreadsheet"></i> Save &amp; download Excel</button><button class="btn btn-danger"><i class="bi bi-file-earmark-pdf"></i> Save &amp; download PDF</button></div>
</form></div></div>
