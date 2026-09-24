<div class="modal fade" id="editMappingModal" tabindex="-1" aria-labelledby="editMappingTitle" aria-hidden="true">
    <div class="modal-dialog"><form method="POST" id="editMappingForm" class="modal-content">
        @csrf @method('PATCH')
        <input type="hidden" name="mapping_edit_id">
        <div class="modal-header"><h5 class="modal-title" id="editMappingTitle">Edit material–supplier mapping</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            @if(old('mapping_edit_id') && $errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="editMappingMaterial">Material *</label><select id="editMappingMaterial" name="material_id" class="form-select" required>@foreach($mappingMaterials as $material)<option value="{{ $material->id }}">{{ $material->internal_code }} — {{ $material->material_name }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label" for="editMappingSupplier">Supplier *</label><select id="editMappingSupplier" name="vendor_id" class="form-select" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="editMappingCode">Supplier item code</label><input id="editMappingCode" name="vendor_item_code" maxlength="191" class="form-control"></div>
                <div class="col-md-6"><label class="form-label" for="editMappingPrice">Unit price *</label><input id="editMappingPrice" name="unit_price" type="number" min="0" max="9999999999.9999" step="0.0001" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label" for="editMappingLead">Lead time (days) *</label><input id="editMappingLead" name="lead_time_days" type="number" min="0" max="65535" step="1" class="form-control" required></div>
                <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2"><input id="editMappingDefault" name="is_default_vendor" type="checkbox" value="1" class="form-check-input"><label for="editMappingDefault" class="form-check-label">Default supplier</label></div></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save changes</button></div>
    </form></div>
</div>
@push('scripts')
<script>
function editMaterialMapping(mapping) {
    const form = document.getElementById('editMappingForm');
    form.reset();
    form.action = @json(url('admin/master-data/material-vendors')) + '/' + mapping.id;
    form.elements.mapping_edit_id.value = mapping.id;
    form.querySelectorAll('[data-extra-supplier]').forEach(option => option.remove());
    const supplier = form.elements.vendor_id;
    if (![...supplier.options].some(option => option.value === String(mapping.vendor_id))) {
        const option = new Option(mapping.supplier_name ? `${mapping.supplier_code} — ${mapping.supplier_name}` : `Supplier #${mapping.vendor_id}`, mapping.vendor_id);
        option.dataset.extraSupplier = '1'; supplier.add(option);
    }
    for (const key of ['material_id', 'vendor_id', 'vendor_item_code', 'unit_price', 'lead_time_days']) form.elements[key].value = mapping[key] ?? '';
    form.elements.is_default_vendor.checked = Number(mapping.is_default_vendor) === 1;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editMappingModal')).show();
}
@if(old('mapping_edit_id') && $errors->any())
document.addEventListener('DOMContentLoaded', () => {
    const previous = @json(old());
    editMaterialMapping({...previous, id: previous.mapping_edit_id});
});
@endif
</script>
@endpush
