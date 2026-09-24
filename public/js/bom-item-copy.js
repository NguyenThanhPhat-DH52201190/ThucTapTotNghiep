(() => {
    const body = document.getElementById('itemsBody');
    if (!body) return;
    const table = body.closest('table');
    const toolbar = document.getElementById('bomCopyToolbarTemplate').content.cloneNode(true);
    table.closest('.table-responsive').before(toolbar);
    const selectAll = document.getElementById('bomSelectAll');
    const copySelected = document.getElementById('bomCopySelected');
    const copies = document.getElementById('bomCopyCount');
    const feedback = document.getElementById('bomCopyFeedback');

    function updateSelection() {
        const checks = [...body.querySelectorAll('[data-bom-copy-select]')];
        const count = checks.filter(input => input.checked).length;
        document.getElementById('bomSelectedCount').textContent = count;
        copySelected.disabled = count === 0;
        selectAll.disabled = checks.length === 0;
        selectAll.checked = count > 0 && count === checks.length;
        selectAll.indeterminate = count > 0 && count < checks.length;
    }

    function decorateRows() {
        [...body.rows].forEach((row, index) => {
            if (!row.querySelector('[data-bom-copy-select]')) {
                const number = document.createElement('span');
                number.dataset.bomRowNumber = '';
                const select = document.createElement('input');
                select.type = 'checkbox'; select.className = 'form-check-input me-2';
                select.dataset.bomCopySelect = '';
                row.cells[0].replaceChildren(select, number);
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary me-1';
                button.dataset.bomCopyRow = ''; button.textContent = 'Copy';
                row.lastElementChild.prepend(button);
            }
            row.querySelector('[data-bom-row-number]').textContent = index + 1;
            row.querySelector('[data-bom-copy-select]').setAttribute('aria-label', `Select BOM item ${index + 1}`);
            row.lastElementChild.style.whiteSpace = 'nowrap';
        });
        updateSelection();
    }

    function copyRows(sources) {
        if (!copies.checkValidity()) { copies.reportValidity(); return; }
        const count = Number(copies.value);
        if (sources.length * count > 100) {
            feedback.textContent = 'Copy up to 100 new rows at a time. Reduce the selection or number of copies.';
            return;
        }
        const maxInputs = Number(document.getElementById('bomOldItems').dataset.maxInputs);
        const currentInputs = [...new FormData(body.closest('form')).keys()].length;
        const extraInputs = sources.reduce((sum, row) => sum + [...row.querySelectorAll('[name]')].reduce((total, field) =>
            total + (field.name.endsWith('[id]') ? 0 : field.multiple ? field.selectedOptions.length : 1), 0), 0) * count;
        if (maxInputs > 0 && currentInputs + extraInputs > maxInputs - 20) {
            feedback.textContent = 'Too many fields for one BOM submission. Reduce the number of copies.';
            return;
        }
        let first;
        for (const source of sources) {
            for (let n = 0; n < count; n++) {
                const clone = source.cloneNode(true);
                const index = ++itemCount;
                clone.id = `itemRow${index}`;
                // Selected options and live input values must be copied, not initial HTML attributes.
                const originals = [...source.querySelectorAll('input, select, textarea')];
                [...clone.querySelectorAll('input, select, textarea')].forEach((field, i) => {
                    const original = originals[i];
                    if (field.name?.endsWith('[id]')) { field.remove(); return; }
                    if (field.name) field.name = field.name.replace(/^items\[\d+\]/, `items[${index}]`);
                    if (field.tagName === 'SELECT') {
                        [...field.options].forEach((option, j) => option.selected = original.options[j].selected);
                    } else {
                        field.value = original.value;
                        field.checked = original.checked;
                    }
                });
                clone.querySelector('[data-bom-copy-select]').checked = false;
                const remove = clone.querySelector('button:not([data-bom-copy-row])');
                remove.removeAttribute('onclick');
                remove.onclick = () => {
                    if (body.rows.length > 1) { clone.remove(); decorateRows(); }
                    else alert('Need at least 1 item');
                };
                body.append(clone);
                first ??= clone;
            }
        }
        body.querySelectorAll('[data-bom-copy-select]').forEach(input => input.checked = false);
        decorateRows();
        feedback.textContent = `Copied ${sources.length * count} new item(s). Edit the new rows, then save BOM.`;
        first?.scrollIntoView({behavior: 'smooth', block: 'center'});
        first?.querySelector('.material-code-input')?.focus({preventScroll: true});
    }

    // Validation errors must keep draft copies and their edited values.
    const previous = JSON.parse(document.getElementById('bomOldItems').textContent);
    if (previous) {
        body.replaceChildren();
        for (const data of Object.values(previous)) {
            addItem();
            const row = body.lastElementChild;
            for (const field of row.querySelectorAll('[name]')) {
                const key = field.name.match(/^items\[\d+\]\[([^\]]+)\]/)?.[1];
                if (field.multiple) {
                    const values = (data[key] || ['all']).map(String);
                    [...field.options].forEach(option => option.selected = values.includes(option.value));
                } else field.value = data[key] ?? '';
            }
            if (data.id) {
                const id = document.createElement('input'); id.type = 'hidden';
                id.name = `items[${itemCount}][id]`; id.value = data.id;
                row.cells[2].append(id);
            }
        }
    }
    body.addEventListener('change', event => { if (event.target.matches('[data-bom-copy-select]')) updateSelection(); });
    body.addEventListener('click', event => {
        const button = event.target.closest('[data-bom-copy-row]');
        if (button) copyRows([button.closest('tr')]);
    });
    selectAll.addEventListener('change', () => {
        body.querySelectorAll('[data-bom-copy-select]').forEach(input => input.checked = selectAll.checked);
        updateSelection();
    });
    copySelected.addEventListener('click', () => copyRows([...body.rows].filter(row => row.querySelector('[data-bom-copy-select]').checked)));
    new MutationObserver(decorateRows).observe(body, {childList: true});
    decorateRows();
})();
