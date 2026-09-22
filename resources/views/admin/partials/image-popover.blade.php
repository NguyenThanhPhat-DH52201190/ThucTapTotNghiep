@push('scripts')
<script>
// One floating preview outside the table so scrolling containers cannot clip it.
(() => {
    const triggers = document.querySelectorAll('.order-image-trigger');
    if (!triggers.length) return;
    const popup = document.createElement('div');
    popup.id = 'orderImagePopover';
    popup.className = 'bg-white border rounded shadow p-3';
    popup.setAttribute('role', 'tooltip');
    popup.style.cssText = 'position:fixed;z-index:1080;width:280px;max-width:calc(100vw - 24px);';
    popup.hidden = true;
    const title = document.createElement('div');
    title.className = 'fw-semibold mb-2';
    const gallery = document.createElement('div');
    gallery.style.cssText = 'display:flex;gap:12px;';
    popup.append(title, gallery);
    document.body.append(popup);
    let active;
    let closeTimer;
    function close() {
        clearTimeout(closeTimer);
        popup.hidden = true;
        active?.removeAttribute('aria-describedby');
        active = null;
    }
    function scheduleClose() { closeTimer = setTimeout(close, 150); }
    function position() {
        if (!active) return;
        const rect = active.getBoundingClientRect();
        const width = popup.offsetWidth;
        const height = popup.offsetHeight;
        let left = rect.right + 12;
        if (left + width > window.innerWidth - 12) left = rect.left - width - 12;
        popup.style.left = Math.max(12, Math.min(left, window.innerWidth - width - 12)) + 'px';
        popup.style.top = Math.max(12, Math.min(rect.top, window.innerHeight - height - 12)) + 'px';
    }
    function open(trigger) {
        clearTimeout(closeTimer);
        if (active === trigger) return;
        active?.removeAttribute('aria-describedby');
        active = trigger;
        active.setAttribute('aria-describedby', popup.id);
        title.textContent = trigger.textContent.trim();
        gallery.replaceChildren();
        const images = [['', trigger.dataset.imageUrl]];
        images.forEach(([label, url]) => {
            const panel = document.createElement('div');
            panel.style.cssText = 'flex:1;min-width:0;';
            if (label) {
                const heading = document.createElement('div');
                heading.className = 'small fw-semibold mb-2';
                heading.textContent = label;
                panel.append(heading);
            }
            const picture = document.createElement('img');
            picture.style.cssText = 'width:100%;height:200px;max-height:40vh;object-fit:contain;';
            picture.alt = (label || 'Image') + ': ' + title.textContent;
            picture.hidden = true;
            const message = document.createElement('div');
            message.className = 'text-muted small';
            message.setAttribute('aria-live', 'polite');
            message.textContent = url ? 'Loading image…' : 'No image uploaded.';
            panel.append(picture, message);
            gallery.append(panel);
            picture.addEventListener('load', () => {
                picture.hidden = false;
                message.hidden = true;
                position();
            });
            picture.addEventListener('error', () => {
                picture.hidden = true;
                message.hidden = false;
                message.textContent = 'Image is unavailable.';
                position();
            });
            if (url) picture.src = url;
        });
        popup.hidden = false;
        position();
    }
    triggers.forEach(trigger => {
        trigger.addEventListener('mouseenter', () => open(trigger));
        trigger.addEventListener('mouseleave', scheduleClose);
        trigger.addEventListener('focus', () => open(trigger));
        trigger.addEventListener('blur', scheduleClose);
        trigger.addEventListener('click', () => open(trigger));
    });
    popup.addEventListener('mouseenter', () => clearTimeout(closeTimer));
    popup.addEventListener('mouseleave', scheduleClose);
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    document.addEventListener('click', event => {
        if (!popup.contains(event.target) && !event.target.closest('.order-image-trigger')) close();
    });
    document.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
})();

</script>
@endpush
