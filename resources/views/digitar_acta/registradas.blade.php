@extends('layouts.app')

@section('content')
<div class="page digitar-acta-page actas-registered-page" id="actasRegisteredPage">
    <div class="actas-header">
        <div class="actas-header-left">
            <div class="actas-header-icon" aria-hidden="true">▤</div>
            <div>
                <h1>Actas</h1>
                <p>Consulta y seguimiento de las actas registradas.</p>
            </div>
        </div>

        <div class="actas-header-actions">
            <a class="actas-top-button" id="exportActasButton"
               href="{{ route('digitar-acta.registradas.export', request()->query()) }}">
                <span aria-hidden="true">⇩</span> Exportar
            </a>
            <button type="button" class="actas-top-button" id="refreshActasButton">
                <span aria-hidden="true">↻</span> Actualizar
            </button>
        </div>
    </div>

    <section class="actas-panel" id="actasModulePanel">
        <div class="actas-filters">
            <div class="actas-search-control">
                <span aria-hidden="true">⌕</span>
                <input id="actasSearchInput" name="q" value="{{ $q }}" placeholder="Buscar mesa..." autocomplete="off">
            </div>

            <select id="actasTypeFilter" class="actas-select" aria-label="Tipo de acta">
                <option value="" {{ $tipo === '' ? 'selected' : '' }}>Todas las configuraciones</option>
                <option value="1" {{ $tipo === '1' ? 'selected' : '' }}>Acta Regional</option>
                <option value="2" {{ $tipo === '2' ? 'selected' : '' }}>Acta Municipal</option>
            </select>

            <div class="actas-status-tabs" role="tablist" aria-label="Estado de actas">
                <button type="button" class="actas-status-tab {{ $estado === '' ? 'active' : '' }}" data-estado="">Todos</button>
                <button type="button" class="actas-status-tab {{ $estado === 'OBSERVADA' ? 'active' : '' }}" data-estado="OBSERVADA">Observados</button>
                <button type="button" class="actas-status-tab {{ $estado === 'CONSISTENTE' ? 'active' : '' }}" data-estado="CONSISTENTE">Sin obs.</button>
            </div>
        </div>

        <div id="actasModuleContent">
            @include('digitar_acta.partials.registradas_list')
        </div>
    </section>
</div>

<script>
(() => {
    const page = document.getElementById('actasRegisteredPage');
    if (!page) return;

    const content = document.getElementById('actasModuleContent');
    const search = document.getElementById('actasSearchInput');
    const type = document.getElementById('actasTypeFilter');
    const refresh = document.getElementById('refreshActasButton');
    const exportButton = document.getElementById('exportActasButton');
    const tabs = Array.from(document.querySelectorAll('.actas-status-tab'));
    let estado = @json($estado);
    let timer = null;
    let controller = null;

    const buildUrl = (extra = {}) => {
        const params = new URLSearchParams();
        const q = extra.q ?? search.value.trim();
        const t = extra.tipo ?? type.value;
        const e = extra.estado ?? estado;
        const p = extra.page ?? '';
        if (q) params.set('q', q);
        if (t) params.set('tipo', t);
        if (e) params.set('estado', e);
        if (p) params.set('page', p);
        params.set('partial', '1');
        return '{{ route('digitar-acta.registradas') }}?' + params.toString();
    };

    const updateExportUrl = () => {
        const params = new URLSearchParams();
        const q = search.value.trim();
        const t = type.value;
        if (q) params.set('q', q);
        if (t) params.set('tipo', t);
        if (estado) params.set('estado', estado);
        exportButton.href = '{{ route('digitar-acta.registradas.export') }}' + (params.toString() ? '?' + params.toString() : '');
    };

    const loadModule = async (url = buildUrl()) => {
        if (controller) controller.abort();
        controller = new AbortController();
        page.classList.add('is-refreshing');

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                signal: controller.signal,
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('No se pudo actualizar el módulo.');
            content.innerHTML = await response.text();
            updateExportUrl();
            bindPagination();
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.alert(error.message || 'No se pudo actualizar el módulo.');
            }
        } finally {
            page.classList.remove('is-refreshing');
        }
    };

    const scheduleSearch = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => loadModule(), 280);
    };

    const bindPagination = () => {
        content.querySelectorAll('.pagination a, .actas-pagination a').forEach(link => {
            link.addEventListener('click', event => {
                event.preventDefault();
                const target = new URL(link.href, window.location.origin);
                loadModule(buildUrl({ page: target.searchParams.get('page') || '' }));
            });
        });
    };

    search.addEventListener('input', scheduleSearch);
    type.addEventListener('change', () => loadModule());

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            estado = tab.dataset.estado || '';
            tabs.forEach(item => item.classList.toggle('active', item === tab));
            loadModule();
        });
    });

    refresh.addEventListener('click', () => loadModule());

    bindPagination();
    updateExportUrl();
})();
</script>
@endsection
