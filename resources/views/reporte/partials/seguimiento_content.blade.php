@php
    $seguimientoFilterDefinitions = [
        ['key' => 'region', 'label' => 'Región', 'placeholder' => 'Región', 'items' => $regiones, 'value' => 'id_region', 'text' => 'nombre'],
    ];
    if (!empty($filters['region'])) {
        $seguimientoFilterDefinitions[] = ['key' => 'provincia', 'label' => 'Provincia', 'placeholder' => 'Provincia', 'items' => $provincias, 'value' => 'id_provincia', 'text' => 'nombre'];
    }
    if (!empty($filters['provincia'])) {
        $seguimientoFilterDefinitions[] = ['key' => 'distrito', 'label' => 'Distrito', 'placeholder' => 'Distrito', 'items' => $distritos, 'value' => 'id_distrito', 'text' => 'nombre'];
    }
    if (!empty($filters['distrito'])) {
        $seguimientoFilterDefinitions[] = ['key' => 'local', 'label' => 'Local', 'placeholder' => 'Local', 'items' => $locales, 'value' => 'id_local', 'text' => 'nombre'];
    }
    if (!empty($filters['local'])) {
        $seguimientoEstados = collect($estadosSeguimiento)->map(fn($label, $key) => (object)['clave' => $key, 'nombre' => $label])->values();
        $seguimientoFilterDefinitions[] = ['key' => 'estado', 'label' => 'Estado', 'placeholder' => 'Estado', 'items' => $seguimientoEstados, 'value' => 'clave', 'text' => 'nombre'];
    }
@endphp

<form method="GET" action="{{ route('reporte.seguimiento') }}" class="reporte-filters-card seguimiento-filters-card" id="seguimientoFilters">
    <div class="reporte-cascade-filters seguimiento-cascade-filters">
        @foreach($seguimientoFilterDefinitions as $field)
            @php
                $fieldValue = (string)($filters[$field['key']] ?? ($field['key'] === 'estado' ? 'TODAS' : ''));
                $selectedItem = $field['items']->first(fn($item) => (string)$item->{$field['value']} === $fieldValue);
                $displayValue = $selectedItem?->{$field['text']} ?? $field['placeholder'];
                if ($field['key'] === 'estado' && $fieldValue === 'TODAS') $displayValue = 'Estado';
            @endphp
            <div class="reporte-cascade-field seguimiento-cascade-field seguimiento-cascade-{{ $field['key'] }}" data-filter-key="{{ $field['key'] }}" data-mode="select">
                <button type="button" class="reporte-cascade-trigger" aria-expanded="false" aria-label="Seleccionar {{ mb_strtolower($field['label'], 'UTF-8') }}">
                    <span class="reporte-cascade-value">{{ $displayValue }}</span>
                    <span class="reporte-cascade-chevron" aria-hidden="true"></span>
                </button>
                <div class="reporte-cascade-menu" hidden>
                    <div class="reporte-cascade-search-wrap">
                        <span class="reporte-cascade-search-icon" aria-hidden="true">⌕</span>
                        <input type="search" class="reporte-cascade-search" placeholder="Buscar {{ mb_strtolower($field['label'], 'UTF-8') }}..." autocomplete="off">
                    </div>
                    <div class="reporte-cascade-options">
                        @foreach($field['items'] as $item)
                            <button type="button" class="reporte-cascade-option" data-value="{{ $item->{$field['value']} }}" data-label="{{ $item->{$field['text']} }}">{{ $item->{$field['text']} }}</button>
                        @endforeach
                    </div>
                    @if($field['key'] !== 'region')
                        <button type="button" class="reporte-cascade-option reporte-cascade-clear" data-value="" data-label="{{ $field['placeholder'] }}">{{ $field['placeholder'] }}</button>
                    @endif
                </div>
                <input type="hidden" name="{{ $field['key'] }}" value="{{ $fieldValue }}">
            </div>
        @endforeach

        <input type="hidden" name="per_page" value="{{ $perPage ?? 10 }}">
        <input type="hidden" name="page" value="{{ $rows->currentPage() }}">
        <button type="button" class="reporte-clear-filters-btn seguimiento-clear-btn" id="seguimientoClearBtn" title="Limpiar filtros" aria-label="Limpiar filtros">
            <span class="reporte-clear-icon" aria-hidden="true">↻</span><span>Limpiar</span>
        </button>
    </div>
</form>

<section class="reporte-card reporte-table-card seguimiento-table-card">
    <div class="reporte-card-head seguimiento-table-head">
        <div>
            <h2>Seguimiento por mesa</h2>
            <p>Estado de las actas regional y municipal por local y mesa.</p>
        </div>
        <div class="reporte-header-actions">
            <a class="reporte-export-btn" href="{{ route('reporte.seguimiento.export', request()->except(['partial', 'page', 'per_page'])) }}">⇩ Exportar Excel</a>
        </div>
    </div>
    <div class="reporte-table-wrap">
        <table class="reporte-results-table reporte-seguimiento-table seguimiento-compact-table">
            <thead>
                <tr>
                    <th>Región</th><th>Provincia</th><th>Distrito</th><th>Local</th><th>Mesa</th><th>Estado</th><th>Acta Regional</th><th>Acta Municipal</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                @php
                    $statusClass = match($r->estado_seguimiento) { 'CONSISTENTE' => 'consistent', 'OBSERVADA' => 'observed', default => 'pending' };
                    $regionalClass = $r->estado_regional === 'CONSISTENTE' ? 'consistent' : ($r->estado_regional === 'OBSERVADA' ? 'observed' : 'pending');
                    $municipalClass = $r->estado_municipal === 'CONSISTENTE' ? 'consistent' : ($r->estado_municipal === 'OBSERVADA' ? 'observed' : 'pending');
                @endphp
                <tr>
                    <td>{{ $r->region_nombre }}</td>
                    <td>{{ $r->provincia_nombre }}</td>
                    <td>{{ $r->distrito_nombre }}</td>
                    <td class="seguimiento-local-cell">{{ $r->local_nombre }}</td>
                    <td class="number-cell strong">{{ $r->numero_mesa }}</td>
                    <td><span class="reporte-status-pill {{ $statusClass }}">{{ $r->estado_seguimiento }}</span></td>
                    <td><span class="reporte-status-pill {{ $regionalClass }}">{{ $r->estado_regional ?: 'PENDIENTE' }}</span></td>
                    <td><span class="reporte-status-pill {{ $municipalClass }}">{{ $r->estado_municipal ?: 'PENDIENTE' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="reporte-no-data">No hay mesas que coincidan con los filtros seleccionados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="seguimiento-pagination">
        <div class="seguimiento-pagination-info">
            <span>Mostrando {{ $rows->count() ? $rows->firstItem() : 0 }} a {{ $rows->lastItem() ?? 0 }} de {{ $rows->total() }} registros</span>
            <label for="seguimientoPageSize">Ver</label>
            <select id="seguimientoPageSize" aria-label="Cantidad de registros por página">
                @foreach([10,25,50,100] as $size)
                    <option value="{{ $size }}" {{ (int)($perPage ?? 10) === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="seguimiento-pagination-actions">
            <button type="button" data-page="{{ max(1, $rows->currentPage() - 1) }}" {{ $rows->onFirstPage() ? 'disabled' : '' }}>‹ Anterior</button>
            @for($page = 1; $page <= $rows->lastPage(); $page++)
                @if($page === 1 || $page === $rows->lastPage() || abs($page - $rows->currentPage()) <= 1)
                    <button type="button" data-page="{{ $page }}" class="{{ $page === $rows->currentPage() ? 'active' : '' }}">{{ $page }}</button>
                @elseif(abs($page - $rows->currentPage()) === 2)
                    <span class="seguimiento-page-ellipsis">…</span>
                @endif
            @endfor
            <button type="button" data-page="{{ min($rows->lastPage(), $rows->currentPage() + 1) }}" {{ !$rows->hasMorePages() ? 'disabled' : '' }}>Siguiente ›</button>
        </div>
    </div>
</section>
