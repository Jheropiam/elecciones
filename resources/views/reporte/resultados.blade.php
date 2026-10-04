@extends('layouts.app')

@section('content')
@php
    $reportAccess = $reportAccess ?? [
        'admin' => true, 'lock_region' => false, 'lock_provincia' => false,
        'lock_distrito' => false, 'lock_local' => false, 'role' => 'Administrador'
    ];
    $specific = true;
    $labelEleccion = $elecciones[$filters['eleccion']] ?? 'Elecciones Regionales';
    $chartRows = $rows->take(7);
    $otherVotes = $rows->slice(7)->sum(fn($r) => (int) $r->votos);
    $totalPartyVotes = max(1, $rows->sum(fn($r) => (int) $r->votos));
    $excludedSet = collect($filters['exclude_mesas'])->map(fn($v) => (string)$v)->flip();
    $includedSet = collect($filters['include_mesas'] ?? [])->map(fn($v) => (string)$v)->flip();
    // Los filtros geográficos visibles reflejan exactamente el nivel de
    // ámbito permitido por el rol. El servidor aplica la misma restricción.
    $showProvincia = $reportAccess['admin'] || $reportAccess['lock_provincia'];
    $showDistrito = $reportAccess['admin'] || $reportAccess['lock_distrito'];
    $showLocal = $reportAccess['admin'] || $reportAccess['lock_local'];
    $visibleFilterCount = 3 + ($showProvincia ? 1 : 0) + ($showDistrito ? 1 : 0) + ($showLocal ? 1 : 0);
@endphp

<style>
/* Solo para la opción seleccionada "Elecciones de Consejero Regional".
   Los otros tipos de elección conservan exactamente su comportamiento actual. */
#reporteFiltersForm .reporte-election-field.reporte-election-consejero{
    min-width:0;
    width:max-content;
    max-width:260px;
}
#reporteFiltersForm .reporte-election-field.reporte-election-consejero .reporte-cascade-trigger{
    min-width:0;
    width:max-content;
    max-width:260px;
    align-items:center;
    gap:7px;
    padding-bottom:9px;
}
#reporteFiltersForm .reporte-election-field.reporte-election-consejero .reporte-cascade-value{
    width:auto;
    max-width:225px;
    flex:0 1 auto;
    line-height:1.15;
    white-space:normal;
    overflow-wrap:normal;
    word-break:normal;
}
#reporteFiltersForm .reporte-election-field.reporte-election-consejero .reporte-cascade-chevron{
    flex:0 0 11px;
    align-self:center;
    margin:0;
    transform:rotate(45deg);
}
</style>

<div class="page reporte-page">
    @php
        $role = $reportAccess['role'] ?? null;
        $isCandidate = (bool)($reportAccess['candidate_scope'] ?? false);
        $isCandidateRegional = $role === 'Candidato Regional';
        $isCandidateProvincial = $role === 'Candidato Provincial';
        $isCandidateDistrital = $role === 'Candidato Distrital';

        $selectedRegion = $regiones->firstWhere('id_region', $filters['region']);
        $selectedProvincia = $provincias->firstWhere('id_provincia', $filters['provincia']);
        $selectedDistrito = $distritos->firstWhere('id_distrito', $filters['distrito']);
        $selectedLocal = $locales->firstWhere('id_local', $filters['local']);
        $selectedMesa = $mesas->firstWhere('id_mesa', $filters['mesa']);

        // El administrador avanza Región -> Provincia -> Distrito -> Local -> Mesa.
        // Los personeros comienzan desde su ámbito bloqueado y continúan el mismo flujo.
        $showProvince = $reportAccess['admin'] ? !empty($filters['region']) : true;
        $showDistrict = $reportAccess['admin'] ? !empty($filters['provincia']) : ($isCandidateProvincial || ($reportAccess['lock_provincia'] ?? false) || !empty($filters['provincia']));
        $showLocal = $reportAccess['admin'] ? !empty($filters['distrito']) : ($isCandidateDistrital || ($reportAccess['lock_distrito'] ?? false) || !empty($filters['distrito']));
        $showMesa = $reportAccess['admin'] ? !empty($filters['local']) : (($role === 'Personero de Local' || $isCandidateDistrital) ? !empty($filters['local']) : false);

        $provinceMode = ($isCandidateRegional || $isCandidateProvincial || ($reportAccess['lock_provincia'] ?? false)) ? 'locked' : 'select';
        $districtMode = ($isCandidateProvincial || $isCandidateDistrital || ($reportAccess['lock_distrito'] ?? false)) ? 'locked' : 'select';
        $localMode = (($reportAccess['lock_local'] ?? false)) ? 'locked' : 'select';

        // Para candidatos se muestra el siguiente nivel como referencia visual,
        // pero no se permite seleccionarlo.
        if ($isCandidateRegional) $provinceMode = 'placeholder';
        if ($isCandidateProvincial) $districtMode = 'placeholder';

        $filterDefinitions = [
            [
                'key' => 'region', 'label' => 'Región', 'mode' => $reportAccess['admin'] ? 'select' : 'locked',
                'collection' => $regiones, 'selected' => $selectedRegion, 'value' => 'id_region', 'text' => 'nombre', 'placeholder' => 'Región'
            ],
        ];
        if ($showProvince) $filterDefinitions[] = [
            'key' => 'provincia', 'label' => 'Provincia', 'mode' => $provinceMode,
            'collection' => $provincias, 'selected' => $selectedProvincia, 'value' => 'id_provincia', 'text' => 'nombre', 'placeholder' => 'Provincia'
        ];
        if ($showDistrict) $filterDefinitions[] = [
            'key' => 'distrito', 'label' => 'Distrito', 'mode' => $districtMode,
            'collection' => $distritos, 'selected' => $selectedDistrito, 'value' => 'id_distrito', 'text' => 'nombre', 'placeholder' => 'Distrito'
        ];
        if ($showLocal) $filterDefinitions[] = [
            'key' => 'local', 'label' => 'Local', 'mode' => $localMode,
            'collection' => $locales, 'selected' => $selectedLocal, 'value' => 'id_local', 'text' => 'nombre', 'placeholder' => 'Local'
        ];
        if ($showMesa) $filterDefinitions[] = [
            'key' => 'mesa', 'label' => 'Mesa', 'mode' => 'select',
            'collection' => $mesas, 'selected' => $selectedMesa, 'value' => 'id_mesa', 'text' => 'numero_mesa', 'placeholder' => 'Mesa'
        ];
    @endphp

    <form method="GET" action="{{ route('reporte.resultados') }}" class="reporte-filters-card" id="reporteFiltersForm">
        <div class="reporte-cascade-filters">
            @foreach($filterDefinitions as $field)
                <div class="reporte-cascade-field" data-filter-key="{{ $field['key'] }}" data-mode="{{ $field['mode'] }}">
                    @if($field['mode'] === 'select')
                        <button type="button" class="reporte-cascade-trigger" aria-expanded="false">
                            <span class="reporte-cascade-value">{{ $field['selected']?->{$field['text']} ?? $field['placeholder'] }}</span>
                            <span class="reporte-cascade-chevron" aria-hidden="true"></span>
                        </button>
                        <div class="reporte-cascade-menu" hidden>
                            <div class="reporte-cascade-search-wrap">
                                <span class="reporte-cascade-search-icon" aria-hidden="true">⌕</span>
                                <input type="search" class="reporte-cascade-search" placeholder="Buscar {{ mb_strtolower($field['label'], 'UTF-8') }}..." autocomplete="off">
                            </div>
                            <div class="reporte-cascade-options">
                                @foreach($field['collection'] as $item)
                                    <button type="button" class="reporte-cascade-option" data-value="{{ $item->{$field['value']} }}" data-label="{{ $item->{$field['text']} }}">
                                        {{ $item->{$field['text']} }}
                                    </button>
                                @endforeach
                            </div>
                            @if($field['key'] !== 'region')
                                <button type="button" class="reporte-cascade-option reporte-cascade-clear" data-value="" data-label="{{ $field['placeholder'] }}">{{ $field['placeholder'] }}</button>
                            @endif
                        </div>
                    @else
                        <div class="reporte-cascade-locked {{ $field['mode'] === 'placeholder' ? 'is-placeholder' : '' }}">
                            <span class="reporte-cascade-value">
                                @if($field['mode'] === 'placeholder')
                                    {{ $field['placeholder'] }}
                                @else
                                    {{ $field['selected']?->{$field['text']} ?? $field['placeholder'] }}
                                @endif
                            </span>
                            @if($field['mode'] !== 'placeholder')
                                <span class="reporte-cascade-chevron" aria-hidden="true"></span>
                            @endif
                        </div>
                    @endif
                    <input type="hidden" name="{{ $field['key'] }}" value="{{ $filters[$field['key']] ?? '' }}">
                </div>
            @endforeach

            @if($elecciones)
                <div class="reporte-cascade-field reporte-election-field {{ ($filters['eleccion'] ?? 'REGIONAL') === 'CONSEJERO' ? 'reporte-election-consejero' : '' }}" data-filter-key="eleccion" data-mode="select">
                    <button type="button" class="reporte-cascade-trigger" aria-expanded="false">
                        <span class="reporte-cascade-value">
                            @if(($filters['eleccion'] ?? 'REGIONAL') === 'CONSEJERO')
                                Elecciones de Consejero<br>Regional
                            @else
                                {{ $elecciones[$filters['eleccion']] ?? ($elecciones['REGIONAL'] ?? 'Elecciones Regionales') }}
                            @endif
                        </span>
                        <span class="reporte-cascade-chevron" aria-hidden="true"></span>
                    </button>
                    <div class="reporte-cascade-menu reporte-election-menu" hidden>
                        <div class="reporte-cascade-options">
                            @foreach($elecciones as $key => $label)
                                <button type="button" class="reporte-cascade-option" data-value="{{ $key }}" data-label="{{ $label }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <input type="hidden" name="eleccion" value="{{ $filters['eleccion'] }}">
                </div>
            @endif

            {{-- Limpiar siempre se mantiene después del último filtro visible. --}}
            <button
                type="button"
                class="reporte-clear-filters-btn"
                id="reporteClearFiltersBtn"
                title="Limpiar filtros"
                aria-label="Limpiar filtros">
                <span class="reporte-clear-icon" aria-hidden="true">↻</span>
                <span>Limpiar</span>
            </button>
        </div>

        <div class="reporte-filter-bottom">
            <div class="reporte-exclusion-controls">
                <label class="reporte-switch-line">
                    <input type="checkbox" name="excluir_observadas" value="1" {{ $filters['excluir_observadas'] ? 'checked' : '' }}>
                    <span class="reporte-switch"></span>
                    <span>Excluir todas las actas observadas</span>
                </label>
                @if($observedMesas->count())
                    <button type="button" class="reporte-help reporte-help-button" id="reporteObservedInfo" title="Ver y seleccionar las mesas observadas" aria-label="Ver mesas observadas">i</button>
                @else
                    <span class="reporte-help" title="No hay actas observadas en el ámbito seleccionado.">i</span>
                @endif
            </div>
        </div>

        @if($observedMesas->count())
            <div class="reporte-observed-modal" id="reporteObservedModal" hidden aria-hidden="true">
                <div class="reporte-observed-backdrop" data-observed-close></div>
                <section class="reporte-observed-dialog" role="dialog" aria-modal="true" aria-labelledby="reporteObservedTitle">
                    <header class="reporte-observed-dialog-head">
                        <div>
                            <span class="reporte-observed-dialog-icon">!</span>
                            <div>
                                <h3 id="reporteObservedTitle">Mesas con actas observadas</h3>
                                <p id="reporteObservedModeText"></p>
                            </div>
                        </div>
                        <button type="button" class="reporte-observed-close" data-observed-close aria-label="Cerrar">&times;</button>
                    </header>

                    <div class="reporte-observed-summary">
                        <strong>{{ $observedMesas->count() }}</strong>
                        <span>mesa(s) observada(s) encontradas en el ámbito seleccionado</span>
                        <b id="selectedExcludedCount"></b>
                    </div>

                    <div class="reporte-observed-search">
                        <span class="reporte-observed-search-icon" aria-hidden="true">⌕</span>
                        <input type="search" id="reporteObservedSearch" class="reporte-observed-search-input" placeholder="Buscar por número de mesa..." autocomplete="off" inputmode="numeric" aria-label="Buscar mesa observada por número">
                        <button type="button" id="reporteObservedSearchClear" class="reporte-observed-search-clear" aria-label="Limpiar búsqueda" title="Limpiar búsqueda" hidden>&times;</button>
                    </div>

                    <div class="reporte-observed-list" id="reporteObservedList">
                        @foreach($observedMesas as $obs)
                            @php
                                $mesaKey = (string) $obs->numero_mesa;
                                $isExcluded = $filters['excluir_observadas']
                                    ? !$includedSet->has($mesaKey)
                                    : $excludedSet->has($mesaKey);
                            @endphp
                            <label class="reporte-observed-row" data-mesa="{{ $obs->numero_mesa }}">
                                <input type="checkbox" class="reporte-observed-checkbox" {{ $isExcluded ? 'checked' : '' }} data-mesa="{{ $obs->numero_mesa }}">
                                <span class="reporte-check"></span>
                                <span class="reporte-observed-row-main">
                                    <strong>Mesa {{ $obs->numero_mesa }}</strong>
                                    <small>{{ $obs->local_nombre }}</small>
                                </span>
                                <em>{{ $obs->tipo_acta == 1 ? 'Regional' : 'Municipal' }}</em>
                                <span class="reporte-observed-state"></span>
                            </label>
                        @endforeach
                    </div>
                    <div class="reporte-observed-empty" id="reporteObservedEmpty" hidden>No se encontraron mesas observadas con ese número.</div>

                    <footer class="reporte-observed-dialog-foot">
                        <span class="reporte-observed-note">Los cambios solo afectan este reporte. No modifican las actas almacenadas.</span>
                        <div>
                            <button type="button" class="reporte-modal-cancel" data-observed-close>Cancelar</button>
                            <button type="button" class="reporte-modal-apply" id="reporteObservedApply">Aplicar selección</button>
                        </div>
                    </footer>
                </section>
            </div>
            <div id="reporteObservedOverrides"></div>
        @endif
    </form>

    @if($summary['excluded'] > 0)
        <div class="reporte-exclusion-alert">
            <strong>{{ $summary['excluded'] }} acta(s) excluida(s) temporalmente.</strong>
            <span>Las actas originales permanecen sin cambios.</span>
        </div>
    @endif

    <section class="reporte-card reporte-progress-results">
        @php
            $avance = min(100, max(0, (float) $summary['percentage']));
            $totalActs = max(0, (int) $summary['expectedActs']);
            $countedActs = max(0, (int) $summary['counted']);
            $pendingActs = max(0, (int) $summary['pending']);
            $consistentActs = max(0, (int) $summary['consistent']);
            $observedActs = max(0, (int) $summary['observed']);
        @endphp
        <div class="reporte-progress-top">
            <div class="reporte-progress-title">
                <span>Actas contabilizadas</span>
                <strong>{{ number_format($avance, 1) }}%</strong>
            </div>
            <div class="reporte-progress-total">Total de actas: <strong>{{ number_format($totalActs) }}</strong></div>
        </div>
        <div class="reporte-progress-track" aria-label="Avance de actas contabilizadas">
            <div class="reporte-progress-fill" style="width: {{ $avance }}%"></div>
        </div>
        <div class="reporte-progress-meta">
            
            <div class="reporte-progress-legend">
                <span><i class="progress-dot counted"></i> Contabilizadas ({{ number_format($countedActs) }})</span>
                <span><i class="progress-dot consistent"></i> Consistentes ({{ number_format($consistentActs) }})</span>
                <span><i class="progress-dot observed"></i> Observadas ({{ number_format($observedActs) }})</span>
                <span><i class="progress-dot pending"></i> Pendientes ({{ number_format($pendingActs) }})</span>
            </div>
        </div>
    </section>

    @php
        // El ranking muestra todas las organizaciones políticas en el carrusel,
        // pero solo cinco son visibles simultáneamente en escritorio.
        $chartRows = $rows->values();
        $totalPartyVotes = max(0, $chartRows->sum(fn($r) => (int) $r->votos));
        $totalEmitidosRanking = max(0, (int) ($special['TOTAL DE VOTOS EMITIDOS'] ?? 0));
        $rankingMaxVote = max(1, (int) ($chartRows->max('votos') ?? 0));
    @endphp
    <section class="reporte-card reporte-chart-card reporte-ranking-card">
        <div class="reporte-card-head reporte-ranking-head">
            <div>
                <h2>Resultados — {{ $labelEleccion }}</h2>
            </div>
            <button type="button" class="reporte-refresh-btn reporte-refresh-inline" id="reporteRefreshBtn" title="Actualizar solamente este reporte">↻ <span>Actualizar</span></button>
        </div>

        @if($chartRows->count())
            <div class="reporte-ranking-carousel" id="reporteRankingCarousel" data-total="{{ $chartRows->count() }}">
                <button type="button" class="reporte-ranking-nav reporte-ranking-nav-prev" id="reporteRankingPrev" aria-label="Retroceder en el ranking" hidden>‹</button>
                <div class="reporte-ranking-viewport">
                    <div class="reporte-ranking-track" id="reporteRankingTrack">
                        @foreach($chartRows as $index => $r)
                            @php
                                $votes = max(0, (int) $r->votos);
                                $validPct = $totalPartyVotes > 0 ? round(($votes / $totalPartyVotes) * 100, 1) : 0;
                                $emittedPct = $totalEmitidosRanking > 0 ? round(($votes / $totalEmitidosRanking) * 100, 1) : 0;
                                $height = max(7, round(($votes / $rankingMaxVote) * 100));
                            @endphp
                            <article class="reporte-ranking-slide" data-index="{{ $index }}">
                                <div class="reporte-ranking-stage">
                                    <div class="reporte-ranking-gridline"></div>
                                    <div class="reporte-ranking-gridline"></div>
                                    <div class="reporte-ranking-gridline"></div>
                                    <div class="reporte-ranking-gridline"></div>
                                    <div class="reporte-ranking-bar-wrap" style="--ranking-bar-height: {{ $height }}%;">
                                        <div class="reporte-ranking-bar" style="height:{{ $height }}%;"></div>
                                        <button type="button"
                                                class="reporte-ranking-logo-button"
                                                aria-expanded="false"
                                                aria-label="Ver detalle de {{ $r->nombre }}">
                                            @if(!empty($r->logo))
                                                <img src="{{ asset($r->logo) }}" alt="Logo {{ $r->nombre }}" class="reporte-ranking-logo">
                                            @else
                                                <span class="reporte-ranking-logo reporte-party-logo-fallback">{{ mb_strtoupper(mb_substr($r->nombre, 0, 1, 'UTF-8'), 'UTF-8') }}</span>
                                            @endif
                                            <span class="reporte-ranking-tooltip" role="dialog" aria-hidden="true">
                                                <strong>{{ $r->nombre }}</strong>
                                                <span><b>Cantidad de votos:</b> {{ number_format($votes) }}</span>
                                                <span><b>Votos válidos:</b> {{ number_format($validPct, 1) }}%</span>
                                                <span><b>Votos emitidos:</b> {{ number_format($emittedPct, 1) }}%</span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                                <div class="reporte-ranking-name" title="{{ $r->nombre }}">{{ \Illuminate\Support\Str::limit($r->nombre, 25) }}</div>
                            </article>
                        @endforeach
                    </div>
                </div>
                <button type="button" class="reporte-ranking-nav reporte-ranking-nav-next" id="reporteRankingNext" aria-label="Avanzar en el ranking" @if($chartRows->count() <= 5) hidden @endif>›</button>
            </div>
        @else
            <div class="reporte-no-data">No hay resultados disponibles para los filtros seleccionados.</div>
        @endif
    </section>

    @php
        $cuadroEleccion = match ($f['eleccion'] ?? '') {
            'REGIONAL' => 'Regionales',
            'CONSEJERO' => 'de Consejeros Regionales',
            'PROVINCIAL' => 'Provinciales',
            'DISTRITAL' => 'Distritales',
            default => 'Regionales',
        };
        $tableValidTotal = max(0, (int) $totalPartyVotes);
        $tableEmittedTotal = max(0, (int) ($special['TOTAL DE VOTOS EMITIDOS'] ?? 0));
    @endphp
    <section class="reporte-card reporte-table-card">
        <div class="reporte-card-head reporte-table-head">
            <div>
                <h2>Cuadro de Resultados de Elecciones {{ $cuadroEleccion }}</h2>
            </div>
            <a href="{{ route('reporte.export', request()->query()) }}" class="reporte-export-inline" title="Exportar resultados a Excel">
                <span aria-hidden="true">⇩</span> Exportar
            </a>
        </div>

        <div class="reporte-table-wrap">
            <table class="reporte-results-table reporte-results-party-table">
                <thead>
                    <tr>
                        <th class="number-cell">N°</th>
                        <th>Organización Política</th>
                        <th>Votos</th>
                        <th>Votos válidos</th>
                        <th>Votos emitidos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $index => $r)
                        @php
                            $displayVotes = max(0, (int) $r->votos);
                            $validPctTable = $tableValidTotal > 0
                                ? round(($displayVotes / $tableValidTotal) * 100, 1)
                                : 0;
                            $emittedPctTable = $tableEmittedTotal > 0
                                ? round(($displayVotes / $tableEmittedTotal) * 100, 1)
                                : 0;
                        @endphp
                        <tr>
                            <td class="number-cell">{{ $index + 1 }}</td>
                            <td class="reporte-party-cell">
                                <div class="reporte-party-cell-content">
                                    <span class="reporte-party-table-logo" aria-hidden="true">
                                        @if(!empty($r->logo))
                                            <img src="{{ asset($r->logo) }}" alt="" loading="lazy">
                                        @else
                                            <span class="reporte-party-table-fallback">{{ mb_strtoupper(mb_substr($r->nombre, 0, 1, 'UTF-8'), 'UTF-8') }}</span>
                                        @endif
                                    </span>
                                    <strong title="{{ $r->nombre }}">{{ $r->nombre }}</strong>
                                </div>
                            </td>
                            <td class="number-cell">{{ number_format($displayVotes) }}</td>
                            <td class="percentage-cell">{{ number_format($validPctTable, 1) }}%</td>
                            <td class="percentage-cell">{{ number_format($emittedPctTable, 1) }}%</td>
                        </tr>
                    @endforeach

                    @php
                        $specialRows = [
                            ['label' => 'Votos en Blanco', 'value' => (int) ($special['VOTOS BLANCOS'] ?? 0)],
                            ['label' => 'Votos Nulos', 'value' => (int) ($special['VOTOS NULOS'] ?? 0)],
                            ['label' => 'Votos Impugnados', 'value' => (int) ($special['VOTOS IMPUGNADOS'] ?? 0)],
                            ['label' => 'Total de Votos Emitidos', 'value' => (int) ($special['TOTAL DE VOTOS EMITIDOS'] ?? 0)],
                        ];
                        $specialStart = $rows->count() + 1;
                    @endphp
                    @foreach($specialRows as $specialIndex => $specialRow)
                        <tr class="reporte-special-result-row">
                            <td class="number-cell">{{ $specialStart + $specialIndex }}</td>
                            <td class="reporte-party-cell">
                                <div class="reporte-party-cell-content">
                                    <span class="reporte-party-table-logo reporte-special-icon" aria-hidden="true">•</span>
                                    <strong>{{ $specialRow['label'] }}</strong>
                                </div>
                            </td>
                            <td class="number-cell">{{ number_format($specialRow['value']) }}</td>
                            <td class="percentage-cell"></td>
                            <td class="percentage-cell"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>


<style id="reporte-resultados-filters-fixes">
/* Correcciones exclusivas de Reporte > Resultados. */
.reporte-page #reporteFiltersForm .reporte-cascade-trigger,
.reporte-page #reporteFiltersForm .reporte-cascade-locked{
    overflow: visible !important;
    line-height: 1.22 !important;
}
.reporte-page #reporteFiltersForm .reporte-cascade-value{
    overflow: visible !important;
    line-height: 1.22 !important;
}

/* El buscador queda fuera del área que se desplaza.
   Solo las opciones tienen scroll, evitando que aparezcan letras
   por encima del cuadro de búsqueda. */
.reporte-page #reporteFiltersForm .reporte-cascade-menu{
    flex-direction:column !important;
    overflow:hidden !important;
}
.reporte-page #reporteFiltersForm .reporte-cascade-menu:not([hidden]){
    display:flex !important;
}
.reporte-page #reporteFiltersForm .reporte-cascade-menu[hidden]{
    display:none !important;
}
.reporte-page #reporteFiltersForm .reporte-cascade-search-wrap{
    position:relative !important;
    top:auto !important;
    flex:0 0 auto !important;
    z-index:3 !important;
    background:#fff !important;
}
.reporte-page #reporteFiltersForm .reporte-cascade-options{
    flex:1 1 auto !important;
    min-height:0 !important;
    overflow-y:auto !important;
    overflow-x:hidden !important;
    padding-top:6px !important;
    scrollbar-gutter:stable;
}
.reporte-page #reporteFiltersForm .reporte-cascade-clear{
    flex:0 0 auto !important;
    background:#fff !important;
}

/* Opción actualmente seleccionada. */
.reporte-page #reporteFiltersForm .reporte-cascade-option.is-selected{
    background:var(--orange-soft) !important;
    color:var(--orange-dark) !important;
    font-weight:700 !important;
}
.reporte-page #reporteFiltersForm .reporte-cascade-option.is-selected:hover,
.reporte-page #reporteFiltersForm .reporte-cascade-option.is-selected:focus-visible{
    background:var(--orange-soft) !important;
    color:var(--orange-dark) !important;
}
</style>

<script>
(() => {
    const getForm = () => document.getElementById('reporteFiltersForm');

    /*
     * Estado visual de los filtros:
     * - resalta la opción actualmente seleccionada;
     * - al abrir un filtro, lleva la lista a la opción seleccionada;
     * - conserva la posición del listado entre aperturas cuando no hay selección.
     */
    const cascadeScrollKey = (key) => `reporte_resultados_filter_scroll_${key}`;

    const syncSelectedCascadeOption = (field) => {
        const form = getForm();
        if (!form || !field) return;

        const key = field.dataset.filterKey;
        const input = form.querySelector(`input[name="${key}"]`);
        const selectedValue = input ? String(input.value || '') : '';

        field.querySelectorAll('.reporte-cascade-option[data-value]').forEach(option => {
            const isSelected = selectedValue !== '' && String(option.dataset.value || '') === selectedValue;
            option.classList.toggle('is-selected', isSelected);
            if (isSelected) option.setAttribute('aria-current', 'true');
            else option.removeAttribute('aria-current');
        });
    };

    const restoreCascadeScroll = (field) => {
        if (!field) return;
        const key = field.dataset.filterKey;
        const menu = field.querySelector('.reporte-cascade-menu');
        const optionsBox = menu?.querySelector('.reporte-cascade-options');
        if (!menu || !optionsBox) return;

        syncSelectedCascadeOption(field);

        const selected = optionsBox.querySelector('.reporte-cascade-option.is-selected');
        requestAnimationFrame(() => {
            if (selected) {
                const top = selected.offsetTop - (optionsBox.clientHeight / 2) + (selected.offsetHeight / 2);
                optionsBox.scrollTop = Math.max(0, top);
                return;
            }

            const saved = Number(sessionStorage.getItem(cascadeScrollKey(key)));
            if (Number.isFinite(saved) && saved >= 0) {
                optionsBox.scrollTop = saved;
            }
        });
    };

    const bindCascadeScrollMemory = () => {
        document.querySelectorAll('#reporteFiltersForm .reporte-cascade-field').forEach(field => {
            const optionsBox = field.querySelector('.reporte-cascade-options');
            if (!optionsBox || optionsBox.dataset.scrollBound === '1') return;

            optionsBox.dataset.scrollBound = '1';
            optionsBox.addEventListener('scroll', () => {
                try {
                    sessionStorage.setItem(
                        cascadeScrollKey(field.dataset.filterKey),
                        String(optionsBox.scrollTop)
                    );
                } catch (_) {}
            }, { passive: true });
        });
    };

    const closeMenus = (except = null) => {
        document.querySelectorAll('#reporteFiltersForm .reporte-cascade-field[data-mode="select"]').forEach(field => {
            const menu = field.querySelector('.reporte-cascade-menu');
            const trigger = field.querySelector('.reporte-cascade-trigger');
            if (menu && field !== except) {
                menu.hidden = true;
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
        });
    };

    const submitSelection = (field, value) => {
        const form = getForm();
        if (!form) return;
        const key = field.dataset.filterKey;
        const input = form.querySelector(`input[name="${key}"]`);
        if (!input) return;

        const chosenOption = field.querySelector(`.reporte-cascade-option[data-value="${CSS.escape(String(value))}"]`);
        if (chosenOption) {
            const optionsBox = field.querySelector('.reporte-cascade-options');
            if (optionsBox) {
                try {
                    sessionStorage.setItem(
                        cascadeScrollKey(key),
                        String(Math.max(0, chosenOption.offsetTop - (optionsBox.clientHeight / 2) + (chosenOption.offsetHeight / 2)))
                    );
                } catch (_) {}
            }
        }

        input.value = value;

        const order = ['region', 'provincia', 'distrito', 'local', 'mesa'];
        const idx = order.indexOf(key);
        if (idx >= 0) {
            order.slice(idx + 1).forEach(lower => {
                const lowerInput = form.querySelector(`input[name="${lower}"]`);
                if (lowerInput) lowerInput.value = '';
            });
        }
        closeMenus();
        form.submit();
    };

    const formatLongCascadeLabels = (root = document) => {
        root.querySelectorAll('#reporteFiltersForm .reporte-cascade-field:not(.reporte-election-field) .reporte-cascade-value').forEach(value => {
            // El Local de votación debe conservar el ajuste natural de palabras.
            // No forzar saltos de línea palabra por palabra en este filtro.
            if (value.closest('.reporte-cascade-field')?.dataset.filterKey === 'local') return;
            if (value.dataset.wrapped === '1') return;
            const text = value.textContent.trim();
            // A partir de 16 caracteres, baja la última palabra para evitar
            // que los filtros crezcan demasiado en horizontal.
            if (text.length <= 15 || !text.includes(' ')) return;
            const lastSpace = text.lastIndexOf(' ');
            if (lastSpace <= 0 || lastSpace >= text.length - 1) return;
            const first = text.slice(0, lastSpace);
            const last = text.slice(lastSpace + 1);
            value.textContent = '';
            value.append(document.createTextNode(first), document.createElement('br'), document.createTextNode(last));
            value.dataset.wrapped = '1';
        });
    };

    const bindClearFilters = () => {
        const form = getForm();
        const button = document.getElementById('reporteClearFiltersBtn');
        if (!form || !button || button.dataset.bound === '1') return;

        button.dataset.bound = '1';

        button.addEventListener('click', () => {
            // Reinicia la navegación territorial al primer nivel.
            ['region', 'provincia', 'distrito', 'local', 'mesa'].forEach(key => {
                const input = form.querySelector(`input[name="${key}"]`);
                if (input) input.value = '';
            });

            // El tipo de elección vuelve a la opción inicial del reporte.
            const election = form.querySelector('input[name="eleccion"]');
            if (election) election.value = 'REGIONAL';

            // Limpia el estado de exclusión de actas observadas.
            const exclusion = form.querySelector('input[name="excluir_observadas"]');
            if (exclusion) exclusion.checked = false;

            // Elimina selecciones manuales de mesas observadas.
            form.querySelectorAll('input[name="exclude_mesas[]"], input[name="include_mesas[]"]')
                .forEach(input => input.remove());

            form.submit();
        });
    };

    const bindReporteControls = () => {
        const modal = document.getElementById('reporteObservedModal');
        const infoButton = document.getElementById('reporteObservedInfo');
        const applyButton = document.getElementById('reporteObservedApply');
        const list = document.getElementById('reporteObservedList');
        const modeText = document.getElementById('reporteObservedModeText');
        const summaryCount = document.getElementById('selectedExcludedCount');
        const searchInput = document.getElementById('reporteObservedSearch');
        const searchClear = document.getElementById('reporteObservedSearchClear');
        const emptyMessage = document.getElementById('reporteObservedEmpty');
        const globalSwitch = document.querySelector('#reporteFiltersForm input[name="excluir_observadas"]');
        if (!modal || !list) return;

        const boxes = () => [...list.querySelectorAll('.reporte-observed-checkbox')];
        const rows = () => [...list.querySelectorAll('.reporte-observed-row')];
        const isGlobalExclude = () => !!globalSwitch?.checked;

        const filterObservedRows = () => {
            if (!searchInput) return;
            const term = searchInput.value.trim().toLowerCase();
            let visible = 0;
            rows().forEach(row => {
                const mesa = String(row.dataset.mesa || '').toLowerCase();
                const local = String(row.querySelector('.reporte-observed-row-main small')?.textContent || '').toLowerCase();
                const matches = !term || mesa.includes(term) || local.includes(term);
                row.hidden = !matches;
                row.style.display = matches ? '' : 'none';
                if (matches) visible++;
            });
            if (emptyMessage) emptyMessage.hidden = visible !== 0;
            if (searchClear) searchClear.hidden = !term;
        };

        const updateObservedModal = () => {
            const all = boxes();
            const excluded = all.filter(b => b.checked).length;
            const included = all.length - excluded;
            if (summaryCount) summaryCount.textContent = isGlobalExclude()
                ? `${included} se contabilizarán`
                : `${excluded} se excluirán`;
            if (modeText) modeText.textContent = isGlobalExclude()
                ? 'Todas están excluidas por defecto. Quite el check de las mesas que sí desea contabilizar.'
                : 'Las actas observadas se contabilizan por defecto. Marque las mesas que no desea contabilizar.';
            all.forEach(box => {
                const row = box.closest('.reporte-observed-row');
                const state = row?.querySelector('.reporte-observed-state');
                if (state) state.textContent = box.checked ? 'No contabilizar' : 'Contabilizar';
                row?.classList.toggle('is-excluded', box.checked);
            });
            filterObservedRows();
        };

        const openModal = () => {
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('reporte-modal-open');
            updateObservedModal();
            if (searchInput) {
                searchInput.value = '';
                filterObservedRows();
                setTimeout(() => searchInput.focus(), 20);
            } else {
                const first = list.querySelector('.reporte-observed-checkbox');
                if (first) setTimeout(() => first.focus(), 20);
            }
        };

        const closeModal = () => {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('reporte-modal-open');
        };

        const applyObservedSelection = () => {
            const form = getForm();
            const overrides = document.getElementById('reporteObservedOverrides');
            if (!form || !overrides) return;
            overrides.innerHTML = '';
            boxes().forEach(box => {
                const name = isGlobalExclude()
                    ? (box.checked ? null : 'include_mesas[]')
                    : (box.checked ? 'exclude_mesas[]' : null);
                if (!name) return;
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = box.dataset.mesa || '';
                overrides.appendChild(input);
            });
            closeModal();
            form.submit();
        };

        if (infoButton && !infoButton.dataset.bound) {
            infoButton.dataset.bound = '1';
            infoButton.addEventListener('click', openModal);
        }
        if (applyButton && !applyButton.dataset.bound) {
            applyButton.dataset.bound = '1';
            applyButton.addEventListener('click', applyObservedSelection);
        }
        if (searchInput && !searchInput.dataset.bound) {
            searchInput.dataset.bound = '1';
            searchInput.addEventListener('input', filterObservedRows);
        }
        if (searchClear && !searchClear.dataset.bound) {
            searchClear.dataset.bound = '1';
            searchClear.addEventListener('click', () => {
                if (!searchInput) return;
                searchInput.value = '';
                filterObservedRows();
                searchInput.focus();
            });
        }
        modal.querySelectorAll('[data-observed-close]').forEach(button => {
            if (button.dataset.bound) return;
            button.dataset.bound = '1';
            button.addEventListener('click', closeModal);
        });
        list.querySelectorAll('.reporte-observed-checkbox').forEach(box => {
            if (box.dataset.bound) return;
            box.dataset.bound = '1';
            box.addEventListener('change', updateObservedModal);
        });
        updateObservedModal();
    };

    const initRankingCarousel = () => {
        const carousel = document.getElementById('reporteRankingCarousel');
        const viewport = carousel?.querySelector('.reporte-ranking-viewport');
        const track = document.getElementById('reporteRankingTrack');
        const prev = document.getElementById('reporteRankingPrev');
        const next = document.getElementById('reporteRankingNext');
        if (!carousel || !viewport || !track) return;
        if (carousel.dataset.bound === '1') return;
        carousel.dataset.bound = '1';

        const slides = [...track.querySelectorAll('.reporte-ranking-slide')];
        let start = 0;
        let activeButton = null;
        let activeTooltip = null;

        const visibleCount = () => {
            const value = parseInt(getComputedStyle(carousel).getPropertyValue('--ranking-visible'), 10);
            return Number.isFinite(value) && value > 0 ? Math.min(value, slides.length || value) : 5;
        };

        const maxStart = () => Math.max(0, slides.length - visibleCount());

        const closeTooltip = () => {
            if (activeButton) {
                activeButton.classList.remove('is-open');
                activeButton.setAttribute('aria-expanded', 'false');
            }
            if (activeTooltip) activeTooltip.remove();
            activeButton = null;
            activeTooltip = null;
        };

        const update = (animate = true) => {
            const visible = visibleCount();
            const width = viewport.clientWidth;
            const slideWidth = visible > 0 ? width / visible : width;

            // Se fija el ancho real de cada slide para que el último grupo
            // quede siempre completo, sin mostrar media tarjeta.
            track.style.width = `${Math.max(0, slides.length * slideWidth)}px`;
            slides.forEach(slide => {
                slide.style.flex = `0 0 ${slideWidth}px`;
                slide.style.width = `${slideWidth}px`;
            });

            start = Math.min(start, maxStart());
            track.style.transition = animate ? 'transform .28s ease' : 'none';
            track.style.transform = `translate3d(${-start * slideWidth}px,0,0)`;
            if (prev) prev.hidden = start <= 0;
            if (next) next.hidden = start >= maxStart();
        };

        const positionTooltip = (button, tooltip) => {
            if (!button || !tooltip) return;
            const rect = button.getBoundingClientRect();
            const margin = 12;
            const gap = 12;
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            tooltip.style.width = `${Math.min(260, Math.max(210, viewportWidth - (margin * 2)))}px`;
            tooltip.style.left = '0px';
            tooltip.style.top = '0px';
            tooltip.classList.remove('is-below');
            tooltip.classList.add('reporte-ranking-tooltip-floating');

            const measured = tooltip.getBoundingClientRect();
            let left = rect.left + (rect.width / 2) - (measured.width / 2);
            left = Math.max(margin, Math.min(left, viewportWidth - measured.width - margin));

            const spaceAbove = rect.top - gap;
            const spaceBelow = viewportHeight - rect.bottom - gap;
            const showBelow = spaceAbove < measured.height && spaceBelow >= spaceAbove;
            let top = showBelow ? rect.bottom + gap : rect.top - measured.height - gap;

            if (showBelow) tooltip.classList.add('is-below');
            top = Math.max(margin, Math.min(top, viewportHeight - measured.height - margin));

            tooltip.style.left = `${Math.round(left)}px`;
            tooltip.style.top = `${Math.round(top)}px`;
        };

        slides.forEach(slide => {
            const button = slide.querySelector('.reporte-ranking-logo-button');
            if (!button) return;
            const originalTooltip = button.querySelector('.reporte-ranking-tooltip');
            if (!originalTooltip) return;

            button.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();

                // Siempre existe como máximo un detalle abierto.
                const wasOpen = activeButton === button;
                closeTooltip();
                if (wasOpen) return;

                button.classList.add('is-open');
                button.setAttribute('aria-expanded', 'true');
                const tooltip = originalTooltip.cloneNode(true);
                tooltip.classList.add('reporte-ranking-tooltip-floating');
                tooltip.setAttribute('aria-hidden', 'false');
                document.body.appendChild(tooltip);
                activeButton = button;
                activeTooltip = tooltip;
                requestAnimationFrame(() => positionTooltip(button, tooltip));
            });
        });

        prev?.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            closeTooltip();
            start = Math.max(0, start - 1);
            update();
        });

        next?.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            closeTooltip();
            start = Math.min(maxStart(), start + 1);
            update();
        });

        carousel.addEventListener('keydown', event => {
            if (event.key === 'ArrowLeft' && start > 0) {
                closeTooltip();
                start -= 1;
                update();
            } else if (event.key === 'ArrowRight' && start < maxStart()) {
                closeTooltip();
                start += 1;
                update();
            }
        });

        document.addEventListener('click', event => {
            if (activeTooltip && !activeTooltip.contains(event.target) && !activeButton?.contains(event.target)) {
                closeTooltip();
            }
        });

        const onResize = () => {
            update(false);
            if (activeButton && activeTooltip) requestAnimationFrame(() => positionTooltip(activeButton, activeTooltip));
        };
        window.addEventListener('resize', onResize, { passive: true });
        window.addEventListener('scroll', () => {
            if (activeButton && activeTooltip) positionTooltip(activeButton, activeTooltip);
        }, { passive: true });
        update(false);
    };

    const refreshReporte = async () => {
        const button = document.getElementById('reporteRefreshBtn');
        const page = document.querySelector('.reporte-page');
        if (!button || !page || button.dataset.loading === '1') return;

        const original = button.innerHTML;
        button.dataset.loading = '1';
        button.disabled = true;
        button.classList.add('is-loading');
        button.innerHTML = '↻ <span>Actualizando...</span>';

        try {
            const response = await fetch(window.location.href, {
                method: 'GET',
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('No se pudo actualizar el reporte.');

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const freshPage = doc.querySelector('.reporte-page');
            if (!freshPage) throw new Error('No se encontró el contenido del reporte.');
            page.replaceWith(freshPage);
            bindReporteControls();
            initRankingCarousel();
            formatLongCascadeLabels(document);
        } catch (error) {
            console.error(error);
            alert('No fue posible actualizar el reporte. Verifica la conexión e inténtalo nuevamente.');
        } finally {
            const currentButton = document.getElementById('reporteRefreshBtn');
            if (currentButton) {
                currentButton.dataset.loading = '0';
                currentButton.disabled = false;
                currentButton.classList.remove('is-loading');
                currentButton.innerHTML = original;
            }
        }
    };

    document.addEventListener('click', (event) => {
        const refreshButton = event.target.closest('#reporteRefreshBtn');
        if (refreshButton) {
            event.preventDefault();
            refreshReporte();
            return;
        }

        const trigger = event.target.closest('#reporteFiltersForm .reporte-cascade-trigger');
        if (trigger) {
            event.preventDefault();
            const field = trigger.closest('.reporte-cascade-field');
            const menu = field?.querySelector('.reporte-cascade-menu');
            if (!menu) return;
            const opening = menu.hidden;
            closeMenus(opening ? field : null);
            menu.hidden = !opening;
            trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
            if (opening) {
                restoreCascadeScroll(field);
                const search = menu.querySelector('.reporte-cascade-search');
                if (search) setTimeout(() => search.focus(), 0);
            }
            return;
        }

        const option = event.target.closest('#reporteFiltersForm .reporte-cascade-option');
        if (option) {
            event.preventDefault();
            const field = option.closest('.reporte-cascade-field');
            if (field) submitSelection(field, option.dataset.value || '');
            return;
        }

        if (!event.target.closest('#reporteFiltersForm .reporte-cascade-field')) closeMenus();
    });

    document.addEventListener('input', (event) => {
        const search = event.target.closest('#reporteFiltersForm .reporte-cascade-search');
        if (!search) return;
        const field = search.closest('.reporte-cascade-field');
        const needle = search.value.trim().toLocaleLowerCase('es');
        field?.querySelectorAll('.reporte-cascade-option:not(.reporte-cascade-clear)').forEach(option => {
            const text = (option.dataset.label || '').toLocaleLowerCase('es');
            option.hidden = needle !== '' && !text.includes(needle);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && event.target.closest('#reporteFiltersForm .reporte-cascade-search')) closeMenus();
        if (event.key === 'Escape') {
            const modal = document.getElementById('reporteObservedModal');
            if (modal && !modal.hidden) {
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('reporte-modal-open');
            }
        }
    });

    document.addEventListener('change', (event) => {
        const form = getForm();
        if (!form) return;
        if (event.target.matches('#reporteFiltersForm input[name="excluir_observadas"]')) {
            // El interruptor global conserva su comportamiento automático.
            // La selección individual de mesas se aplica desde el modal.
            const overrides = document.getElementById('reporteObservedOverrides');
            if (overrides) overrides.innerHTML = '';
            form.submit();
        }
    });

    /*
     * Cerrar filtros únicamente cuando se desplaza la página principal.
     * El scroll interno de .reporte-cascade-options no dispara este cierre.
     */
    const closeMenusOnPageScroll = (event) => {
        const target = event?.target;
        if (target && target.closest && target.closest('#reporteFiltersForm .reporte-cascade-menu')) return;
        closeMenus();
    };

    window.addEventListener('scroll', closeMenusOnPageScroll, { passive: true });

    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.addEventListener('scroll', closeMenusOnPageScroll, { passive: true });
    }

    bindClearFilters();
    bindReporteControls();
    initRankingCarousel();
    formatLongCascadeLabels(document);
    bindCascadeScrollMemory();

    document.querySelectorAll('#reporteFiltersForm .reporte-cascade-field').forEach(field => {
        syncSelectedCascadeOption(field);
    });
})();
</script>
@endsection
