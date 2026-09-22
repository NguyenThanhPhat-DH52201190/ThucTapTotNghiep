(() => {
    const content = document.getElementById('mainContent');
    if (!content) return;

    // Older reports without a wrapper scroll locally instead of widening the page.
    content.querySelectorAll('table').forEach(table => {
        if (table.closest('.table-responsive, .masterplan-scroll') || table.parentElement.closest('table')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        table.before(wrapper);
        wrapper.append(table);
    });
    const updateRegion = region => {
        const overflowing = region.scrollWidth > region.clientWidth + 1;
        if (overflowing) {
            region.setAttribute('tabindex', '0');
            region.setAttribute('role', 'region');
            region.setAttribute('aria-label', 'Scrollable data table');
        } else {
            region.removeAttribute('tabindex');
            region.removeAttribute('role');
            region.removeAttribute('aria-label');
        }
    };
    const observer = new ResizeObserver(entries => entries.forEach(entry => {
        const region = entry.target.closest('.table-responsive, .masterplan-scroll');
        if (region) updateRegion(region);
    }));
    content.querySelectorAll('.table-responsive, .masterplan-scroll').forEach(region => {
        observer.observe(region);
        if (region.querySelector('table')) observer.observe(region.querySelector('table'));
        updateRegion(region);
    });
})();
