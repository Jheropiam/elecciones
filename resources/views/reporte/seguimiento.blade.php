@extends('layouts.app')

@section('content')
<div class="page reporte-page">
    <div class="reporte-header">
        <div>
            <div class="breadcrumb">Inicio <span>›</span> Reporte <span>›</span> Seguimiento</div>
            <h1>Seguimiento</h1>
            <p class="page-subtitle">Control operativo del avance de las mesas, actas, personeros y digitación.</p>
        </div>
        <div class="reporte-header-actions">
            <button type="button" class="reporte-refresh-btn" id="seguimientoRefreshBtn">↻ Actualizar</button>
            <span class="reporte-last-update" id="seguimientoLastUpdate">Actualizado ahora</span>
        </div>
    </div>

    <div id="seguimientoModule">
        @include('reporte.partials.seguimiento_content')
    </div>
</div>

<script>
(() => {
    const module = document.getElementById('seguimientoModule');
    const refreshBtn = document.getElementById('seguimientoRefreshBtn');
    const lastUpdate = document.getElementById('seguimientoLastUpdate');
    let busy = false;

    // Cierre explícito para que ningún estilo display:flex anule el atributo hidden.
    function closeSeguimientoMenus() {
        module?.querySelectorAll('#seguimientoFilters .reporte-cascade-menu').forEach(menu => {
            menu.hidden = true;
            menu.style.display = 'none';
        });
        module?.querySelectorAll('#seguimientoFilters .reporte-cascade-trigger').forEach(button => button.setAttribute('aria-expanded', 'false'));
    }

    function queryFromForm() {
        const form = module?.querySelector('#seguimientoFilters');
        return form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();
    }

    async function refreshSeguimiento() {
        if (!module || busy) return;
        busy = true;
        if (refreshBtn) { refreshBtn.disabled = true; refreshBtn.classList.add('is-loading'); }
        try {
            const params = queryFromForm();
            params.set('partial', '1');
            const response = await fetch(`{{ route('reporte.seguimiento') }}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('No fue posible actualizar el seguimiento.');
            module.innerHTML = await response.text();
            // El contenido AJAX puede reconstruir los desplegables: todos deben iniciar cerrados.
            closeSeguimientoMenus();
            if (lastUpdate) lastUpdate.textContent = 'Actualizado ' + new Date().toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'});
        } catch (error) {
            window.alert(error.message || 'No fue posible actualizar el seguimiento.');
        } finally {
            busy = false;
            if (refreshBtn) { refreshBtn.disabled = false; refreshBtn.classList.remove('is-loading'); }
        }
    }

    module?.addEventListener('click', event => {
        const trigger = event.target.closest('#seguimientoFilters .reporte-cascade-trigger');
        if (trigger) {
            const field = trigger.closest('.reporte-cascade-field');
            const menu = field?.querySelector('.reporte-cascade-menu');
            const wasOpen = menu && !menu.hidden;
            closeSeguimientoMenus();
            if (menu && !wasOpen) {
                menu.hidden = false;
                menu.style.display = 'flex';
                trigger.setAttribute('aria-expanded', 'true');
                const search = menu.querySelector('.reporte-cascade-search');
                if (search) { search.value = ''; menu.querySelectorAll('.reporte-cascade-option[data-value]').forEach(option => option.hidden = false); search.focus(); }
                const options = menu.querySelector('.reporte-cascade-options');
                const selected = options?.querySelector('.reporte-cascade-option.is-selected');
                if (options) options.scrollTop = selected ? Math.max(0, selected.offsetTop - options.clientHeight / 2) : 0;
            }
            return;
        }

        const chosenOption = event.target.closest('#seguimientoFilters .reporte-cascade-option');
        if (chosenOption) {
            const field = chosenOption.closest('.reporte-cascade-field');
            const form = module.querySelector('#seguimientoFilters');
            const key = field?.dataset.filterKey;
            const input = key ? form?.querySelector(`input[name="${key}"]`) : null;
            if (!field || !input || !form) return;
            input.value = chosenOption.dataset.value || '';
            const valueLabel = field.querySelector('.reporte-cascade-value');
            if (valueLabel) valueLabel.textContent = chosenOption.dataset.label || chosenOption.textContent.trim();
            field.querySelectorAll('.reporte-cascade-option').forEach(option => option.classList.toggle('is-selected', option === chosenOption));
            const descendants = {region:['provincia','distrito','local','estado'], provincia:['distrito','local','estado'], distrito:['local','estado'], local:['estado']};
            (descendants[key] || []).forEach(name => {
                const child = form.querySelector(`input[name="${name}"]`);
                if (child) child.value = name === 'estado' ? 'TODAS' : '';
            });
            if (form.elements.page) form.elements.page.value = '1';
            refreshSeguimiento();
            return;
        }

        const clear = event.target.closest('#seguimientoClearBtn');
        if (clear) {
            const form = module.querySelector('#seguimientoFilters');
            if (!form) return;
            ['region', 'provincia', 'distrito', 'local'].forEach(name => { const input = form.querySelector(`input[name="${name}"]`); if (input) input.value = ''; });
            const state = form.querySelector('input[name="estado"]');
            if (state) state.value = 'TODAS';
            if (form.elements.per_page) form.elements.per_page.value = '10';
            if (form.elements.page) form.elements.page.value = '1';
            refreshSeguimiento();
            return;
        }
        const pageButton = event.target.closest('.seguimiento-pagination-actions [data-page]');
        if (pageButton && !pageButton.disabled) {
            const form = module.querySelector('#seguimientoFilters');
            if (form) { form.elements.page.value = pageButton.dataset.page; refreshSeguimiento(); }
        }
    });

    module?.addEventListener('change', event => {
        if (event.target.id !== 'seguimientoPageSize') return;
        const form = module.querySelector('#seguimientoFilters');
        if (form) {
            form.elements.per_page.value = event.target.value;
            form.elements.page.value = '1';
            refreshSeguimiento();
        }
    });

    module?.addEventListener('input', event => {
        const search = event.target.closest('#seguimientoFilters .reporte-cascade-search');
        if (!search) return;
        const term = search.value.trim().toLocaleLowerCase('es');
        const options = search.closest('.reporte-cascade-menu')?.querySelectorAll('.reporte-cascade-options .reporte-cascade-option') || [];
        options.forEach(option => { option.hidden = !String(option.dataset.label || option.textContent).toLocaleLowerCase('es').includes(term); });
    });

    document.addEventListener('click', event => {
        if (event.target.closest('#seguimientoFilters .reporte-cascade-field')) return;
        closeSeguimientoMenus();
    });

    // Estado inicial garantizado incluso si la página se restaura desde caché.
    closeSeguimientoMenus();

    refreshBtn?.addEventListener('click', () => {
        const form = module?.querySelector('#seguimientoFilters');
        if (form?.elements.page) form.elements.page.value = '1';
        refreshSeguimiento();
    });
})();
</script>
@endsection
