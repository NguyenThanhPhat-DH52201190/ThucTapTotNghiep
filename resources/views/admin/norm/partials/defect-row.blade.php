<div class="defect-row border rounded p-3 mb-3">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Material *</label><select data-field="requirement_id" name="items[{{ $index }}][requirement_id]" class="form-select" required>
            <option value="">Select material</option>
            @foreach($materials as $material)<option value="{{ $material->id }}" @selected((string) ($item['requirement_id'] ?? '') === (string) $material->id)>{{ $material->material_code }} — {{ $material->material_name }} / {{ $material->material_color }} / {{ $material->material_size }} ({{ $material->unit }})</option>@endforeach
        </select></div>
        <div class="col-md-3"><label class="form-label">Defective qty *</label><input data-field="defect_qty" name="items[{{ $index }}][defect_qty]" value="{{ $item['defect_qty'] ?? '' }}" type="number" min="0.0001" max="99999999" step="0.0001" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Requested replacement *</label><input data-field="replacement_qty" name="items[{{ $index }}][replacement_qty]" value="{{ $item['replacement_qty'] ?? 0 }}" type="number" min="0" max="99999999" step="0.0001" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Disposition *</label><select data-field="disposition" name="items[{{ $index }}][disposition]" class="form-select" required>@foreach(['scrap' => 'Scrap', 'reuse' => 'Reuse', 'return_supplier' => 'Return to supplier'] as $value => $label)<option value="{{ $value }}" @selected(($item['disposition'] ?? 'scrap') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label">Reason *</label><textarea data-field="reason" name="items[{{ $index }}][reason]" class="form-control" maxlength="1000" required>{{ $item['reason'] ?? '' }}</textarea></div>
        <div class="col-md-4"><label class="form-label">Evidence image</label><input data-field="image" name="items[{{ $index }}][image]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control"><small class="text-muted">JPG, PNG, WebP, GIF. Max 2 MB.</small></div>
        <div class="col-12 text-end"><button type="button" class="btn btn-outline-danger btn-sm" data-remove-defect>Remove row</button></div>
    </div>
</div>
