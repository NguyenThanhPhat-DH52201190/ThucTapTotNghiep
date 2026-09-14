<style>
    .material-suggestions {
        position: fixed;
        z-index: 1080;
        overflow-y: auto;
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
    }

    .material-unit-input {
        pointer-events: none;
        background-color: var(--bs-secondary-bg);
    }
</style>
<script>
(() => {
    const endpoint = @json(route('admin.bom.material-suggestions'));
    const panel = document.createElement('div');
    panel.className = 'material-suggestions list-group d-none';
    document.body.appendChild(panel);

    let activeInput = null;
    let requestController = null;
    let debounceTimer = null;

    function closeSuggestions() {
        panel.classList.add('d-none');
        panel.replaceChildren();
        activeInput = null;
    }

    function positionPanel(input) {
        const rect = input.getBoundingClientRect();
        const margin = 8;
        const preferredHeight = 260;
        const spaceBelow = window.innerHeight - rect.bottom - margin;
        const spaceAbove = rect.top - margin;
        const openAbove = spaceBelow < 180 && spaceAbove > spaceBelow;
        const availableHeight = Math.max(100, openAbove ? spaceAbove : spaceBelow);

        panel.style.left = `${rect.left}px`;
        panel.style.width = `${Math.max(rect.width, 320)}px`;
        panel.style.maxHeight = `${Math.min(preferredHeight, availableHeight)}px`;
        panel.style.top = openAbove ? 'auto' : `${rect.bottom + 2}px`;
        panel.style.bottom = openAbove ? `${window.innerHeight - rect.top + 2}px` : 'auto';
    }

    function selectMaterial(input, material) {
        input.value = material.internal_code;
        const row = input.closest('tr');
        const description = row?.querySelector('.material-description-input');
        const colour = row?.querySelector('.material-colour-input');
        const size = row?.querySelector('.material-size-input');
        const unit = row?.querySelector('.material-unit-input');
        if (description) description.value = material.material_name;
        if (colour) colour.value = material.color || '';
        if (size) size.value = material.size || '';
        if (unit) unit.value = material.unit || '';
        input.setCustomValidity('');
        closeSuggestions();
    }

    function renderSuggestions(input, materials) {
        if (activeInput !== input) return;
        panel.replaceChildren();

        if (!materials.length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item small text-muted';
            empty.textContent = 'No matching material code';
            panel.appendChild(empty);
        } else {
            materials.forEach(material => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'list-group-item list-group-item-action py-2';
                const code = document.createElement('strong');
                code.textContent = material.internal_code;
                const name = document.createElement('small');
                name.className = 'd-block text-muted';
                name.textContent = material.material_name;
                option.append(code, name);
                option.addEventListener('mousedown', event => {
                    event.preventDefault();
                    selectMaterial(input, material);
                });
                panel.appendChild(option);
            });
        }

        positionPanel(input);
        panel.classList.remove('d-none');
    }

    document.addEventListener('input', event => {
        const input = event.target.closest('.material-code-input');
        if (!input) return;

        const row = input.closest('tr');
        const description = row?.querySelector('.material-description-input');
        const colour = row?.querySelector('.material-colour-input');
        const size = row?.querySelector('.material-size-input');
        const unit = row?.querySelector('.material-unit-input');
        if (description) description.value = '';
        if (colour) colour.value = '';
        if (size) size.value = '';
        if (unit) unit.value = '';
        input.setCustomValidity('Please select a material code from Material Master.');
        activeInput = input;
        clearTimeout(debounceTimer);
        requestController?.abort();

        const query = input.value.trim();
        if (!query) {
            closeSuggestions();
            input.setCustomValidity('');
            return;
        }

        debounceTimer = setTimeout(async () => {
            requestController = new AbortController();
            try {
                const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: requestController.signal,
                });
                if (!response.ok) throw new Error('Unable to load materials');
                renderSuggestions(input, await response.json());
            } catch (error) {
                if (error.name !== 'AbortError') closeSuggestions();
            }
        }, 150);
    });

    document.addEventListener('focusout', event => {
        if (event.target.closest('.material-code-input')) setTimeout(closeSuggestions, 150);
    });
    window.addEventListener('resize', closeSuggestions);
    document.addEventListener('scroll', closeSuggestions, true);
})();
</script>
