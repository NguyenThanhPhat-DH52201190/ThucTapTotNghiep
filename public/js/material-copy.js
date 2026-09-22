(() => {
    const data = JSON.parse(document.getElementById('materialCopyData').textContent);
    const body = document.getElementById('materialCopyRows');
    const message = document.getElementById('copyRowMessage');
    const fields = {
        internal_code: ['New internal code', 191, true], old_code: ['Old code', 191, false],
        material_name: ['Name', 191, true], color: ['Color', 100, false],
        size: ['Size', 100, false], unit: ['Unit', 20, true],
    };
    const sourceById = new Map(data.sources.map(source => [String(source.id), source]));
    function refresh() {
        [...body.rows].forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach(input => input.name = `rows[${index}][${input.dataset.field}]`);
        });
        document.getElementById('copyRowCount').textContent = `${body.rows.length} / 100 rows`;
        document.getElementById('saveMaterialCopies').disabled = !body.rows.length;
    }
    function inputCell(row, field, value) {
        const input = document.createElement('input');
        const [label, limit, required] = fields[field];
        input.className = 'form-control'; input.dataset.field = field;
        input.value = value ?? ''; input.maxLength = limit; input.required = required;
        input.setAttribute('aria-label', label);
        input.style.minWidth = field === 'material_name' ? '280px' : field === 'unit' ? '80px' : '150px';
        row.insertCell().append(input);
    }
    function selectCell(row, field, options, selected, placeholder) {
        const select = document.createElement('select');
        select.className = 'form-select'; select.dataset.field = field;
        select.setAttribute('aria-label', field === 'category_id' ? 'Category' : 'Subcategory');
        select.add(new Option(placeholder, ''));
        options.forEach(option => select.add(new Option(option.name, option.id)));
        select.value = selected ?? ''; select.style.minWidth = '170px';
        row.insertCell().append(select); return select;
    }
    function values(row) {
        return Object.fromEntries([...row.querySelectorAll('[data-field]')].map(input => [input.dataset.field, input.value]));
    }
    function addRow(draft, after = null) {
        if (body.rows.length >= 100) { message.textContent = 'Maximum 100 rows per save.'; return; }
        message.textContent = '';
        const row = document.createElement('tr');
        const source = sourceById.get(String(draft.source_id));
        const sourceCell = row.insertCell();
        sourceCell.append(document.createTextNode(source?.internal_code || `#${draft.source_id}`));
        const sourceId = document.createElement('input');
        sourceId.type = 'hidden'; sourceId.dataset.field = 'source_id'; sourceId.value = draft.source_id;
        sourceCell.append(sourceId);
        ['internal_code', 'old_code', 'material_name'].forEach(field => inputCell(row, field, draft[field]));
        const category = selectCell(row, 'category_id', data.categories, draft.category_id, 'Select category');
        category.required = true;
        const subcategory = selectCell(row, 'subcategory_id', [], '', 'No subcategory');
        function filterSubcategories(selected = '') {
            subcategory.replaceChildren(new Option('No subcategory', ''));
            data.subcategories.filter(item => String(item.category_id) === category.value)
                .forEach(item => subcategory.add(new Option(item.name, item.id)));
            subcategory.value = selected;
            if (subcategory.selectedIndex < 0) subcategory.value = '';
        }
        filterSubcategories(String(draft.subcategory_id ?? ''));
        category.addEventListener('change', () => filterSubcategories());
        ['color', 'size', 'unit'].forEach(field => inputCell(row, field, draft[field]));
        const imageCell = row.insertCell();
        const imageValue = document.createElement('input');
        imageValue.type = 'hidden'; imageValue.dataset.field = 'copy_image';
        const imageCheck = document.createElement('input');
        imageCheck.type = 'checkbox'; imageCheck.className = 'form-check-input';
        imageCheck.setAttribute('aria-label', 'Copy source image');
        imageCheck.checked = !!source?.image_path && [true, 1, '1'].includes(draft.copy_image);
        imageCheck.disabled = !source?.image_path;
        imageValue.value = imageCheck.checked ? '1' : '0';
        imageCheck.addEventListener('change', () => imageValue.value = imageCheck.checked ? '1' : '0');
        imageCell.append(imageValue, imageCheck);
        const actions = row.insertCell(); const group = document.createElement('div');
        group.className = 'd-flex gap-2'; actions.append(group);
        const copy = document.createElement('button');
        copy.type = 'button'; copy.className = 'btn btn-outline-primary btn-sm'; copy.textContent = 'Copy';
        copy.addEventListener('click', () => {
            const clone = addRow({...values(row), internal_code: '', old_code: ''}, row);
            clone?.querySelector('[data-field="internal_code"]').focus();
        });
        const remove = document.createElement('button');
        remove.type = 'button'; remove.className = 'btn btn-outline-danger btn-sm'; remove.textContent = 'Remove';
        remove.addEventListener('click', () => { row.remove(); message.textContent = ''; refresh(); });
        group.append(copy, remove);
        if (after) after.after(row); else body.append(row);
        refresh(); return row;
    }
    Object.values(data.rows).forEach(row => addRow(row));
    document.getElementById('materialCopyForm').addEventListener('submit', event => {
        const seen = {internal_code: new Set(), old_code: new Set()};
        for (const row of body.rows) {
            for (const key of Object.keys(seen)) {
                const input = row.querySelector(`[data-field="${key}"]`);
                input.value = input.value.trim();
                const code = input.value.toLocaleLowerCase();
                if (code && seen[key].has(code)) {
                    event.preventDefault(); message.textContent = 'Duplicate code in draft rows: ' + input.value;
                    input.focus(); return;
                }
                if (code) seen[key].add(code);
            }
        }
    });
})();
