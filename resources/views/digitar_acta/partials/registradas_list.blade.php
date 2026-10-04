<div class="actas-list-module" id="actasListModule">
    <div class="actas-list-meta">
        <span>{{ $totalActas }} {{ $totalActas === 1 ? 'acta registrada' : 'actas registradas' }}</span>
        @if($q !== '' || $tipo !== '' || $estado !== '')
            <span class="actas-filtered-note">Filtros aplicados</span>
        @endif
    </div>

    <div class="actas-cards">
        @forelse($actas as $acta)
            <article class="acta-record-card">
                <div class="acta-record-icon" aria-hidden="true">▤</div>

                <div class="acta-record-main">
                    <div class="acta-record-title-row">
                        <div class="acta-record-title">
                            <strong>Mesa {{ $acta->numero_acta }}</strong>
                            <span class="acta-record-type {{ $acta->tipo_acta == 1 ? 'regional' : 'municipal' }}">
                                {{ $acta->tipo_acta == 1 ? 'Acta Regional' : 'Acta Municipal' }}
                            </span>
                        </div>
                        <span class="acta-status-pill {{ $acta->estado_acta === 'OBSERVADA' ? 'observada' : 'consistente' }}">
                            {{ $acta->estado_acta === 'OBSERVADA' ? 'Observada' : 'Consistente' }}
                        </span>
                    </div>

                    <div class="acta-record-location">
                        {{ $acta->local_nombre }}
                        <span>·</span>
                        {{ number_format($acta->total_electores) }} electores
                    </div>

                    <div class="acta-record-scope">
                        {{ $acta->region_nombre }} · {{ $acta->provincia_nombre }} · {{ $acta->distrito_nombre }}
                        @if($acta->local_nombre)
                            <span>·</span> {{ $acta->local_nombre }}
                        @endif
                    </div>
                </div>

                <div class="acta-record-side">
                    <div class="acta-record-user">
                        <span class="acta-user-icon" aria-hidden="true">♙</span>
                        <span>{{ $acta->digitador }}</span>
                    </div>
                    <div class="acta-record-date">
                        {{ \Carbon\Carbon::parse($acta->fecha_registro)->format('d/m/Y H:i') }}
                    </div>
                </div>

                <div class="acta-record-actions">
                    <a class="acta-icon-button" href="{{ route('digitar-acta.editar', [$acta->numero_acta, $acta->tipo_acta]) }}" title="Ver y editar acta" aria-label="Ver y editar acta">✎</a>
                    <span class="acta-chevron" aria-hidden="true">⌄</span>
                </div>
            </article>
        @empty
            <div class="actas-empty-state">
                <div class="actas-empty-icon">▤</div>
                <strong>No hay actas registradas</strong>
                <p>No existen actas que coincidan con los filtros seleccionados.</p>
            </div>
        @endforelse
    </div>

    @if($actas->hasPages())
        <div class="actas-pagination" id="actasPagination">
            {{ $actas->withQueryString()->links('pagination.custom') }}
        </div>
    @endif
</div>
