@extends('layouts.app')

@section('content')
<div class="page digitar-acta-page">
    @php
        $modoEdicion = $modoEdicion ?? false;
        $valores = $valores ?? [];
        $observaciones = $observaciones ?? [];
    @endphp
    @if($errors->any())
        <div class="validation-summary">
            <strong>Revise los datos ingresados:</strong>
            <ul>
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if(session('error'))
        <div class="validation-summary">{{ session('error') }}</div>
    @endif

    <section class="acta-info-card">
        <div class="acta-info-title">
            <div>
                <span class="acta-kicker">{{ $modoEdicion ? 'EDITAR ACTA' : 'ACTA' }} {{ $tipoNombre }}</span>
                <h2>Mesa {{ $mesa->numero_mesa }}</h2>
            </div>
            <a class="btn btn-light" href="{{ $modoEdicion ? route('digitar-acta.registradas') : route('digitar-acta.registrar') }}">← {{ $modoEdicion ? 'Volver a actas' : 'Cambiar mesa / acta' }}</a>
        </div>

        <div class="acta-info-grid">
            <div><span>Región</span><strong>{{ $mesa->region_nombre }}</strong></div>
            <div><span>Provincia</span><strong>{{ $mesa->provincia_nombre }}</strong></div>
            <div><span>Distrito</span><strong>{{ $mesa->distrito_nombre }}</strong></div>
            <div><span>Local</span><strong>{{ $mesa->local_nombre }}</strong></div>
            <div><span>Dirección</span><strong>{{ $mesa->local_direccion ?: '—' }}</strong></div>
            <div><span>Total de electores</span><strong>{{ number_format($mesa->total_electores) }}</strong></div>
        </div>
    </section>

    @if($modoEdicion && !empty($observaciones))
        <section class="validation-summary acta-observation-panel">
            <strong>Observaciones registradas en el acta</strong>
            <ul>
                @foreach($observaciones as $campo => $mensajes)
                    <li><strong>{{ $campo == 1 ? 'Columna 1' : 'Columna 2' }}:</strong> {{ implode(' ', $mensajes) }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <form method="POST" action="{{ $modoEdicion ? route('digitar-acta.actualizar', [$mesa->numero_mesa, $tipo]) : route('digitar-acta.store') }}" id="actaForm">
        @csrf
        @if($modoEdicion) @method('PUT') @endif
        <input type="hidden" name="id_mesa" value="{{ $mesa->id_mesa }}">
        <input type="hidden" name="numero_acta" value="{{ $mesa->numero_mesa }}">
        <input type="hidden" name="tipo_acta" value="{{ $tipo }}">

        <section class="module-card acta-entry-card">
            <div class="module-card-header">
                <div>
                    <h2>{{ $modoEdicion ? 'Edición de resultados del acta' : 'Resultados del acta' }}</h2>
                    <p>Solo se aceptan números enteros mayores o iguales a cero.</p>
                </div>
            </div>

            <div class="acta-table-wrap">
                <table class="acta-vote-table">
                    <thead>
                        <tr>
                            <th>Partido / Organización Política</th>
                            <th>{{ $tipo === 1 ? 'Gobernador y Vicegobernador' : 'Alcalde Provincial' }}</th>
                            <th>{{ $tipo === 1 ? 'Consejero Regional' : 'Alcalde Distrital' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($partidos as $partido)
                        <tr>
                            <td>
                                <div class="party-cell">
                                    @if($partido->logo)
                                        <img src="{{ asset($partido->logo) }}" alt="" class="party-logo">
                                    @else
                                        <span class="party-logo party-logo-placeholder">P</span>
                                    @endif
                                    <span>{{ $partido->nombre }}</span>
                                </div>
                            </td>
                            @for($campo = 1; $campo <= 2; $campo++)
                                <td>
                                    <input
                                        class="vote-input"
                                        type="text"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        maxlength="6"
                                        name="votos[{{ $partido->id_partido }}][{{ $campo }}]"
                                        value="{{ old('votos.'.$partido->id_partido.'.'.$campo, $valores[(string)$partido->id_partido][$campo] ?? '') }}"
                                        autocomplete="off"
                                        aria-label="{{ $partido->nombre }}"
                                    >
                                </td>
                            @endfor
                        </tr>
                    @endforeach

                    <tr class="special-start">
                        <td colspan="3"><span>CONCEPTOS ESPECIALES</span></td>
                    </tr>

                    @php
                        $specialOrder = [
                            'VOTOS BLANCOS',
                            'VOTOS NULOS',
                            'VOTOS IMPUGNADOS',
                            'TOTAL DE VOTOS EMITIDOS',
                        ];
                    @endphp

                    @foreach($specialOrder as $specialName)
                        @php
                            $special = $especiales[$specialName]
                        @endphp
                        <tr class="{{ $specialName === 'TOTAL DE VOTOS EMITIDOS' ? 'total-row' : 'special-row' }}">
                            <td><strong>{{ $specialName }}</strong></td>
                            @for($campo = 1; $campo <= 2; $campo++)
                                <td>
                                    <input
                                        class="vote-input {{ $specialName === 'TOTAL DE VOTOS EMITIDOS' ? 'total-input' : '' }}"
                                        type="text"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        maxlength="6"
                                        name="votos[{{ $special->id_partido }}][{{ $campo }}]"
                                        value="{{ old('votos.'.$special->id_partido.'.'.$campo, $valores[(string)$special->id_partido][$campo] ?? '') }}"
                                        autocomplete="off"
                                        aria-label="{{ $specialName }}"
                                    >
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="acta-validation-help">
                <div><span class="help-icon">✓</span><strong>Validación automática al guardar</strong></div>
                <p>La suma de los votos de organizaciones + blancos + nulos + impugnados debe coincidir con el Total de Votos Emitidos y este no puede superar {{ number_format($mesa->total_electores) }} electores.</p>
                <p>Si existe una diferencia, el acta se guardará como <strong>OBSERVADA</strong> para su revisión.</p>
            </div>

            <div class="acta-form-actions">
                <a class="btn btn-light" href="{{ $modoEdicion ? route('digitar-acta.registradas') : route('digitar-acta.registrar') }}">Cancelar</a>
                <button type="submit" class="btn btn-primary">{{ $modoEdicion ? 'Guardar cambios' : 'Guardar acta' }}</button>
            </div>
        </section>
    </form>
</div>

<script>
(() => {
    const inputs = Array.from(document.querySelectorAll('.vote-input'));

    // Al entrar al acta, el cursor queda directamente en el primer campo de votos.
    if (inputs.length) {
        window.requestAnimationFrame(() => {
            inputs[0].focus();
            inputs[0].select();
        });
    }

    const moveFocus = (current, key) => {
        const row = current.closest('tr');
        if (!row) return false;

        const rowInputs = Array.from(row.querySelectorAll('.vote-input'));
        const column = rowInputs.indexOf(current);
        const rows = Array.from(document.querySelectorAll('.acta-vote-table tbody tr'))
            .filter(r => r.querySelector('.vote-input'));
        const rowIndex = rows.indexOf(row);

        let target = null;
        if (key === 'ArrowLeft' && column > 0) target = rowInputs[column - 1];
        if (key === 'ArrowRight' && column < rowInputs.length - 1) target = rowInputs[column + 1];
        if (key === 'ArrowUp' && rowIndex > 0) {
            const previous = Array.from(rows[rowIndex - 1].querySelectorAll('.vote-input'));
            target = previous[column] || previous[previous.length - 1];
        }
        if (key === 'ArrowDown' && rowIndex < rows.length - 1) {
            const next = Array.from(rows[rowIndex + 1].querySelectorAll('.vote-input'));
            target = next[column] || next[0];
        }

        if (target) {
            target.focus();
            target.select();
            return true;
        }
        return false;
    };

    inputs.forEach((input) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, 6);
        });

        input.addEventListener('keydown', (event) => {
            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
                event.preventDefault();
                moveFocus(input, event.key);
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                const currentIndex = inputs.indexOf(input);
                const next = inputs[currentIndex + 1];
                if (next) next.focus();
            }
        });
    });
})();
</script>
@endsection
