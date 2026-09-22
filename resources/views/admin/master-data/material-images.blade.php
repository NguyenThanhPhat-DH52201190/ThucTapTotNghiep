<div class="col-12">
    <label for="materialImage" class="form-label">Image</label>
    <input type="file" name="image" id="materialImage" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
    <div class="form-text">Image for this material only. JPG, PNG, WebP or GIF, up to 2 MB. Leave empty to keep the current image.</div>
    <img id="materialImagePreview" class="img-thumbnail mt-2 d-none" alt="Material image preview" style="max-width:240px;max-height:160px;object-fit:contain;">
    <div id="materialImageError" class="text-danger small mt-1" role="alert"></div>
</div>
@push('scripts')
<script>
(() => {
    const input = document.getElementById('materialImage');
    const preview = document.getElementById('materialImagePreview');
    const error = document.getElementById('materialImageError');
    let currentUrl = null;
    let temporaryUrl = null;
    function refresh() {
        if (temporaryUrl) URL.revokeObjectURL(temporaryUrl);
        const file = input.files[0];
        const source = file ? (temporaryUrl = URL.createObjectURL(file)) : currentUrl;
        if (source) preview.src = source;
        else preview.removeAttribute('src');
        preview.classList.toggle('d-none', !source);
    }
    window.resetMaterialImage = (url = null) => {
        input.value = '';
        error.textContent = '';
        currentUrl = url;
        refresh();
    };
    input.addEventListener('change', () => {
        error.textContent = '';
        const file = input.files[0];
        if (file && (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 2 * 1024 * 1024)) {
            input.value = '';
            error.textContent = 'Select a JPG, PNG, WebP or GIF image up to 2 MB.';
        }
        refresh();
    });
    preview.addEventListener('error', () => {
        preview.classList.add('d-none');
        error.textContent = 'Image is unavailable.';
    });
})();
</script>
@endpush