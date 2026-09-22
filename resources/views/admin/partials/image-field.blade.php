<div class="col-12">
    <label for="ocsImage" class="form-label">Image</label>
    <input type="file" id="ocsImage" name="image" class="form-control @error('image') is-invalid @enderror"
           accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="ocsImageHelp">
    <div id="ocsImageHelp" class="form-text">JPG, PNG, WebP or GIF. Maximum 2 MB. Leave empty to keep the current image.</div>
    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <img id="ocsImagePreview" class="img-thumbnail mt-2 {{ empty($imageUrl) ? 'd-none' : '' }}"
         @if(!empty($imageUrl)) src="{{ $imageUrl }}" @endif
         alt="Image preview" style="max-width: 240px; max-height: 200px; object-fit: contain;">
    <div id="ocsImageError" class="text-danger small mt-1" role="alert"></div>
</div>
@push('scripts')
<script>
(() => {
    const input = document.getElementById('ocsImage');
    const preview = document.getElementById('ocsImagePreview');
    const error = document.getElementById('ocsImageError');
    const original = preview.getAttribute('src');
    let temporaryUrl;
    input.addEventListener('change', () => {
        if (temporaryUrl) URL.revokeObjectURL(temporaryUrl);
        error.textContent = '';
        const file = input.files[0];
        if (file && (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 2 * 1024 * 1024)) {
            error.textContent = 'Please select a JPG, PNG, WebP or GIF image up to 2 MB.';
            input.value = '';
        }
        const selected = input.files[0];
        const source = selected ? (temporaryUrl = URL.createObjectURL(selected)) : original;
        if (source) preview.src = source;
        else preview.removeAttribute('src');
        preview.classList.toggle('d-none', !source);
    });
})();
</script>
@endpush
