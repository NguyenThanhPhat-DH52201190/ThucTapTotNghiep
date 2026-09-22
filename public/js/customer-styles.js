(() => {
    function init() {
        const source = document.querySelector('.customer-style-options');
        if (!source) return;
        const styles = JSON.parse(source.textContent);
        document.querySelectorAll('select[data-customer-style]').forEach(select => {
            const form = select.closest('form');
            const customer = form.querySelector('[name="customer_id"]');
            const name = form.querySelector('[name="Sname"], [name="style_name"]');
            const bom = form.querySelector('[name="bom_header_id"]');
            const allBoms = bom ? Array.from(bom.options).map(o => o.cloneNode(true)) : [];
            const preview = document.createElement('div');
            preview.className = 'mt-2';
            select.after(preview);
            customer.required = true;
            if (name) name.readOnly = true;
            function refresh() {
                const style = styles.find(s => s.customer === customer.value && s.code === select.value);
                if (name) name.value = style?.name || '';
                preview.replaceChildren();
                if (style?.image) {
                    const link = document.createElement('a');
                    link.href = style.image; link.target = '_blank'; link.rel = 'noopener';
                    const img = document.createElement('img');
                    img.src = style.image; img.alt = style.code; img.className = 'img-thumbnail';
                    img.style.cssText = 'width:140px;height:140px;object-fit:contain';
                    link.append(img); preview.append(link);
                }
                if (bom) {
                    const previous = bom.value;
                    bom.replaceChildren(...allBoms.filter(o => !o.value || (o.dataset.customer === customer.value && o.dataset.styleNo === select.value)).map(o => o.cloneNode(true)));
                    bom.value = Array.from(bom.options).some(o => o.value === previous) ? previous : '';
                }
            }
            function populate(code = '') {
                const options = styles.filter(s => s.customer === customer.value);
                select.replaceChildren(new Option(customer.value ? '-- Select Style --' : '-- Select customer first --', ''));
                options.forEach(s => select.add(new Option(s.code + ' — ' + s.name, s.code)));
                select.value = options.some(s => s.code === code) ? code : '';
                refresh();
            }
            customer.addEventListener('change', () => populate());
            select.addEventListener('change', refresh);
            populate(select.dataset.current || '');
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
