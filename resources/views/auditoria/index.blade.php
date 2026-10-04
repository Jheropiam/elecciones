@extends('layouts.app')

@section('content')
<div class="auditoria-page">
    <div class="auditoria-head">
        <div>
            <div class="breadcrumb">Inicio <span>›</span> Auditoría y Trazabilidad</div>
            <h1>Auditoría y Trazabilidad</h1>
            <p>Historial de operaciones realizadas en el sistema.</p>
        </div>
        <div class="auditoria-head-actions">
            <a class="auditoria-btn auditoria-btn-export" href="{{ route('auditoria.export', request()->query()) }}">▣ Exportar Excel</a>
        </div>
    </div>

    <div class="auditoria-summary">
        <div class="auditoria-summary-card blue"><span class="summary-icon">▤</span><div><small>Total de registros</small><strong>{{ number_format($resumen['total']) }}</strong></div></div>
        <div class="auditoria-summary-card orange"><span class="summary-icon">◷</span><div><small>Operaciones de hoy</small><strong>{{ number_format($resumen['hoy']) }}</strong></div></div>
        <div class="auditoria-summary-card green"><span class="summary-icon">●</span><div><small>Usuarios con actividad</small><strong>{{ number_format($resumen['usuarios']) }}</strong></div></div>
        <div class="auditoria-summary-card purple"><span class="summary-icon">✓</span><div><small>Tipos de acción</small><strong>{{ number_format($resumen['acciones']) }}</strong></div></div>
    </div>

    <div class="auditoria-card auditoria-filters">
        <div class="auditoria-card-title"><span class="audit-title-icon">⌕</span><div><h2>Filtros de auditoría</h2><p>Consulta el historial por usuario, módulo, acción, tabla o periodo.</p></div></div>
        <form method="GET" action="{{ route('auditoria.index') }}">
            <div class="auditoria-filter-grid">
                <label><span>Buscar</span><input type="text" name="q" value="{{ request('q') }}" placeholder="DNI, nombre, módulo, IP, registro..." maxlength="100"></label>
                <label><span>Usuario</span><select name="id_usuario"><option value="">Todos</option>@foreach($usuarios as $u)<option value="{{ $u->id_usuario }}" @selected((string)request('id_usuario')===(string)$u->id_usuario)>{{ $u->dni }} — {{ trim($u->nombres.' '.$u->apellido_paterno.' '.$u->apellido_materno) }}</option>@endforeach</select></label>
                <label><span>Módulo</span><select name="modulo"><option value="">Todos</option>@foreach($modulos as $m)<option value="{{ $m }}" @selected(request('modulo')===$m)>{{ $m }}</option>@endforeach</select></label>
                <label><span>Acción</span><select name="accion"><option value="">Todas</option>@foreach($acciones as $a)<option value="{{ $a }}" @selected(request('accion')===$a)>{{ $a }}</option>@endforeach</select></label>
                <label><span>Tabla afectada</span><select name="tabla"><option value="">Todas</option>@foreach($tablas as $t)<option value="{{ $t }}" @selected(request('tabla')===$t)>{{ $t }}</option>@endforeach</select></label>
                <label><span>ID de registro</span><input type="number" name="id_registro" value="{{ request('id_registro') }}" min="1" placeholder="Ej. 123"></label>
                <label><span>Fecha desde</span><input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"></label>
                <label><span>Fecha hasta</span><input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"></label>
            </div>
            <div class="auditoria-filter-actions">
                <a href="{{ route('auditoria.index') }}" class="auditoria-btn auditoria-btn-light">Limpiar filtros</a>
                <button type="submit" class="auditoria-btn auditoria-btn-primary">⌕ Aplicar filtros</button>
            </div>
        </form>
    </div>

    <div class="auditoria-card">
        <div class="auditoria-table-head">
            <div><h2>Historial de operaciones</h2><p>{{ $auditorias->total() }} registro(s) con los filtros actuales.</p></div>
            @if(request()->hasAny(['q','id_usuario','id_registro','modulo','accion','tabla','fecha_desde','fecha_hasta']))
                <span class="audit-filter-badge">Filtros aplicados</span>
            @endif
        </div>
        <div class="auditoria-table-wrap">
            <table class="auditoria-table">
                <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>Tabla</th><th>ID</th><th>IP</th><th>Detalle</th></tr></thead>
                <tbody>
                @forelse($auditorias as $a)
                    @php
                        $nombre = trim(implode(' ', array_filter([$a->nombres, $a->apellido_paterno, $a->apellido_materno])));
                        $usuario = $a->dni ?: ($a->usuario ?: 'Sistema');
                        $accionClass = strtolower(preg_replace('/[^a-z0-9]+/i','-', $a->accion));
                    @endphp
                    <tr>
                        <td class="audit-date">{{ \Carbon\Carbon::parse($a->created_at)->format('d/m/Y H:i:s') }}</td>
                        <td><strong>{{ $usuario }}</strong><small>{{ $nombre ?: '—' }}</small></td>
                        <td>{{ $a->modulo }}</td>
                        <td><span class="audit-action audit-action-{{ $accionClass }}">{{ $a->accion }}</span></td>
                        <td><code>{{ $a->tabla_afectada ?: '—' }}</code></td>
                        <td>{{ $a->id_registro ?? '—' }}</td>
                        <td>{{ $a->ip ?: '—' }}</td>
                        <td><button type="button" class="audit-detail-btn" data-audit-id="{{ $a->id_auditoria }}">Ver detalle</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="audit-empty">No se encontraron registros de auditoría con los filtros seleccionados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $auditorias->withQueryString()->links('pagination.custom') }}
    </div>
</div>

<div class="audit-modal-backdrop" id="auditDetailModal" hidden>
    <div class="audit-modal" role="dialog" aria-modal="true" aria-labelledby="auditModalTitle">
        <div class="audit-modal-head"><div><h3 id="auditModalTitle">Detalle de auditoría</h3><p id="auditModalMeta"></p></div><button type="button" id="auditModalClose" aria-label="Cerrar">×</button></div>
        <div class="audit-modal-body">
            <div class="audit-detail-grid">
                <div><span>Usuario</span><strong id="auditUser">—</strong></div><div><span>Módulo</span><strong id="auditModule">—</strong></div>
                <div><span>Acción</span><strong id="auditAction">—</strong></div><div><span>Tabla</span><strong id="auditTable">—</strong></div>
                <div><span>ID registro</span><strong id="auditRecord">—</strong></div><div><span>IP</span><strong id="auditIp">—</strong></div>
            </div>
            <div class="audit-json-grid">
                <section><h4>Valor anterior</h4><pre id="auditOld">Sin datos</pre></section>
                <section><h4>Valor nuevo</h4><pre id="auditNew">Sin datos</pre></section>
            </div>
            <section class="audit-user-agent"><h4>Agente del navegador</h4><p id="auditAgent">—</p></section>
        </div>
        <div class="audit-modal-footer"><button type="button" class="auditoria-btn auditoria-btn-light" id="auditModalClose2">Cerrar</button></div>
    </div>
</div>

<script>
(() => {
    const modal = document.getElementById('auditDetailModal');
    const close = () => { modal.hidden = true; document.body.classList.remove('audit-modal-open'); };
    document.getElementById('auditModalClose')?.addEventListener('click', close);
    document.getElementById('auditModalClose2')?.addEventListener('click', close);
    modal?.addEventListener('click', e => { if (e.target === modal) close(); });

    document.querySelectorAll('[data-audit-id]').forEach(btn => btn.addEventListener('click', async () => {
        const id = btn.dataset.auditId;
        btn.disabled = true;
        try {
            const response = await fetch(`{{ url('/auditoria') }}/${id}`, {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if (!response.ok) throw new Error('No se pudo consultar el registro.');
            const d = await response.json();
            document.getElementById('auditModalMeta').textContent = `Registro #${d.id_auditoria} · ${d.fecha}`;
            document.getElementById('auditUser').textContent = d.usuario || 'Sistema';
            document.getElementById('auditModule').textContent = d.modulo || '—';
            document.getElementById('auditAction').textContent = d.accion || '—';
            document.getElementById('auditTable').textContent = d.tabla || '—';
            document.getElementById('auditRecord').textContent = d.id_registro ?? '—';
            document.getElementById('auditIp').textContent = d.ip || '—';
            document.getElementById('auditOld').textContent = d.valor_anterior || 'Sin datos';
            document.getElementById('auditNew').textContent = d.valor_nuevo || 'Sin datos';
            document.getElementById('auditAgent').textContent = d.user_agent || '—';
            modal.hidden = false; document.body.classList.add('audit-modal-open');
        } catch (e) { alert(e.message); }
        finally { btn.disabled = false; }
    }));
})();
</script>
@endsection
