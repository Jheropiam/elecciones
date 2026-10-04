@extends('layouts.app')
@section('content')

<style>
/* ===== MESAS - presentación ===== */
.mesas-page .module-card { overflow: hidden; }
.mesas-page .module-card-header {
    padding: 22px 28px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:24px;
    border-bottom:1px solid #edf0f4;
}
.mesas-page .module-card-header h2 { margin:0 0 5px; font-size:24px; }
.mesas-page .module-card-header p { margin:0; color:#718096; font-size:14px; }
.mesas-page .module-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.mesas-page .btn { border-radius:9px; font-weight:600; }
.mesas-page .inline-form {
    padding:24px 28px 26px;
    background:#fffaf5;
    border-bottom:1px solid #f1e5d8;
}
.mesas-page .inline-form.hidden { display:none !important; }
.mesas-page .form-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px 22px;
}
.mesas-page .field label {
    display:block;
    margin-bottom:7px;
    font-weight:600;
    color:#334155;
    font-size:14px;
}
.mesas-page .field label span { color:#e86f00; }
.mesas-page .field input,
.mesas-page .field select,
.mesas-page .filters input,
.mesas-page .filters select {
    width:100%;
    min-height:42px;
    box-sizing:border-box;
    border:1px solid #d7dde6;
    border-radius:8px;
    background:#fff;
    padding:9px 12px;
    font-size:14px;
    color:#26364a;
}
.mesas-page .field input:focus,
.mesas-page .field select:focus,
.mesas-page .filters input:focus,
.mesas-page .filters select:focus {
    outline:none;
    border-color:#ff8a1f;
    box-shadow:0 0 0 3px rgba(255,138,31,.12);
}
.mesas-page .form-actions {
    margin-top:22px;
    display:flex;
    justify-content:flex-end;
    gap:10px;
}
.mesas-page .filter-bar {
    padding:18px 28px;
    background:#fbfcfe;
    border-bottom:1px solid #edf0f4;
}
.mesas-page .filters {
    display:grid;
    grid-template-columns:1.5fr repeat(5, minmax(130px,1fr)) auto auto;
    gap:9px;
    align-items:center;
}
.mesas-page .table-wrap { overflow-x:auto; }
.mesas-page .data-table { width:100%; border-collapse:collapse; }
.mesas-page .data-table th {
    background:#f7f8fa;
    color:#526173;
    font-size:12px;
    letter-spacing:.03em;
    text-transform:uppercase;
    font-weight:700;
    padding:13px 12px;
    border-bottom:1px solid #e8ebef;
    white-space:nowrap;
}
.mesas-page .data-table td {
    padding:14px 12px;
    border-bottom:1px solid #eef1f4;
    color:#344255;
    font-size:14px;
    vertical-align:middle;
}
.mesas-page .data-table tbody tr:hover { background:#fffaf5; }
.mesas-page .data-table .strong { font-weight:700; color:#1f334d; }
.mesas-page .empty { text-align:center; padding:34px !important; color:#8793a3; }
.mesas-page .status {
    display:inline-flex;
    align-items:center;
    padding:5px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
}
.mesas-page .status-active { background:#e9f8ef; color:#18794e; }
.mesas-page .status-inactive { background:#f1f3f5; color:#6b7280; }
.mesas-page .actions { white-space:nowrap; }
.mesas-page .icon-btn {
    width:34px; height:34px;
    border:1px solid #dce2e9;
    background:#fff;
    border-radius:8px;
    cursor:pointer;
    color:#46566a;
    margin-right:4px;
}
.mesas-page .icon-btn:hover { border-color:#ff8a1f; color:#d86a00; background:#fffaf5; }
.mesas-page .pagination { padding:16px 24px; }
@media (max-width: 1100px) {
    .mesas-page .filters { grid-template-columns:repeat(3,1fr); }
}
@media (max-width: 760px) {
    .mesas-page .module-card-header { align-items:flex-start; flex-direction:column; }
    .mesas-page .form-grid { grid-template-columns:1fr; }
    .mesas-page .filters { grid-template-columns:1fr; }
}
</style>

<div class="page mesas-page">
  <div class="page-heading">
    <div>
      <div class="breadcrumb">Inicio <span>›</span> Mesas</div>
      <h1>Mesas</h1>
      <p class="page-subtitle">Administración de mesas electorales.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-card-header">
      <div>
        <h2>Mesas</h2>
        <p>Administre las mesas asociadas a cada local electoral.</p>
      </div>
      <div class="module-actions">
        <a class="btn btn-light" href="{{ route('mesas.template') }}">Plantilla Excel</a>
        <a class="btn btn-light" href="{{ route('mesas.export') }}">Exportar Excel</a>
        <button class="btn btn-light" type="button" id="btnImportarMesa">Importar Excel</button>
        <button class="btn btn-primary" type="button" id="btnNuevaMesa">+ Nuevo</button>
      </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <div id="importMesa" class="inline-form hidden">
      <form method="POST" action="{{ route('mesas.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
          <div class="field field-wide"><label>Archivo Excel (.xlsx) <span>*</span></label><input type="file" name="archivo" accept=".xlsx" required><small>Use la plantilla oficial de Mesas. Los registros válidos se guardan y los inválidos aparecen en un Excel de errores.</small></div>
        </div>
        <div class="form-actions"><button type="button" class="btn btn-light" id="btnCancelarImportMesa">Cancelar</button><button type="submit" class="btn btn-primary">Procesar importación</button></div>
      </form>
    </div>
    @if(session('import_summary'))
      <div class="import-summary"><strong>Resultado de importación</strong><div class="import-summary-grid"><span>Procesados <b>{{ session('import_summary.procesados') }}</b></span><span>Nuevos <b>{{ session('import_summary.nuevos') }}</b></span><span>Duplicados <b>{{ session('import_summary.duplicados') }}</b></span><span>Errores <b>{{ session('import_summary.errores') }}</b></span></div>@if(session('import_error_file'))<a href="{{ route('mesas.import.errors',session('import_error_file')) }}">Descargar Excel de errores</a>@endif</div>
    @endif

    <div id="formMesa" class="inline-form hidden">
      <form id="mesaForm" method="POST" action="{{ route('mesas.store') }}">
        @csrf
        <input type="hidden" name="_method" id="mesaMethod" value="">
        <input type="hidden" name="_form" value="mesa">
        <div class="form-grid">
          <div class="field">
            <label>Región <span>*</span></label>
            <select id="mesaRegion" required>
              <option value="">Seleccione</option>
              @foreach($regiones as $r)
                <option value="{{ $r->id_region }}">{{ $r->nombre }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label>Provincia <span>*</span></label>
            <select id="mesaProvincia" required><option value="">Seleccione</option></select>
          </div>
          <div class="field">
            <label>Distrito <span>*</span></label>
            <select id="mesaDistrito" required><option value="">Seleccione</option></select>
          </div>
          <div class="field">
            <label>Local <span>*</span></label>
            <select name="id_local" id="mesaLocal" required><option value="">Seleccione</option></select>
          </div>
          <div class="field">
            <label>Número de mesa <span>*</span></label>
            <input type="text" name="numero_mesa" id="numeroMesa" maxlength="20" required placeholder="Ej. 0001">
          </div>
          <div class="field">
            <label>Total de electores <span>*</span></label>
            <input type="number" name="total_electores" id="totalElectores" min="1" required>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-light" id="btnCancelarMesa">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="saveMesa">Registrar mesa</button>
        </div>
      </form>
    </div>

    <div class="filter-bar">
      <form method="GET" class="filters">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Mesa o local">
        <select name="region" id="fRegion">
          <option value="">Todas las regiones</option>
          @foreach($regiones as $r)<option value="{{ $r->id_region }}" @selected(request('region')==$r->id_region)>{{ $r->nombre }}</option>@endforeach
        </select>
        <select name="provincia" id="fProvincia">
          <option value="">Todas las provincias</option>
          @foreach($provincias as $p)<option value="{{ $p->id_provincia }}" @selected(request('provincia')==$p->id_provincia)>{{ $p->nombre }}</option>@endforeach
        </select>
        <select name="distrito" id="fDistrito">
          <option value="">Todos los distritos</option>
          @foreach($distritos as $d)<option value="{{ $d->id_distrito }}" @selected(request('distrito')==$d->id_distrito)>{{ $d->nombre }}</option>@endforeach
        </select>
        <select name="local">
          <option value="">Todos los locales</option>
          @foreach($locales as $l)<option value="{{ $l->id_local }}" @selected(request('local')==$l->id_local)>{{ $l->nombre }}</option>@endforeach
        </select>
        <select name="estado">
          <option value="">Todos</option>
          <option value="1" @selected(request('estado')==='1')>Activos</option>
          <option value="0" @selected(request('estado')==='0')>Inactivos</option>
        </select>
        <button class="btn btn-primary">Buscar</button>
        <a class="clear-filter" href="{{ route('mesas.index') }}">Limpiar</a>
      </form>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>N°</th><th>Región</th><th>Provincia</th><th>Distrito</th>
            <th>Local</th><th>Mesa</th><th>Electores</th><th>Estado</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($mesas as $i=>$m)
          <tr>
            <td>{{ ($mesas->currentPage()-1)*$mesas->perPage()+$i+1 }}</td>
            <td>{{ $m->region_nombre }}</td>
            <td>{{ $m->provincia_nombre }}</td>
            <td>{{ $m->distrito_nombre }}</td>
            <td>{{ $m->local_nombre }}</td>
            <td class="strong">{{ $m->numero_mesa }}</td>
            <td>{{ $m->total_electores }}</td>
            <td><span class="status {{ (int)$m->estado===1 ? 'status-active' : 'status-inactive' }}">{{ (int)$m->estado===1 ? 'Activo' : 'Inactivo' }}</span></td>
            <td class="actions">
              <button type="button" class="icon-btn edit-mesa" title="Editar"
                data-id="{{ $m->id_mesa }}"
                data-local="{{ $m->id_local }}"
                data-region="{{ $m->id_region }}"
                data-provincia="{{ $m->id_provincia }}"
                data-distrito="{{ $m->id_distrito }}"
                data-numero="{{ $m->numero_mesa }}"
                data-electores="{{ $m->total_electores }}">✎</button>
              <form method="POST" action="{{ route('mesas.toggle',$m->id_mesa) }}" style="display:inline">
                @csrf @method('PATCH')
                <button type="submit" class="icon-btn" title="{{ (int)$m->estado===1 ? 'Deshabilitar' : 'Habilitar' }}">
                  {{ (int)$m->estado===1 ? '◉' : '○' }}
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="empty">No hay mesas registradas.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    {{ $mesas->withQueryString()->links('pagination.custom') }}
  </div>
</div>

<script>
function initMesasModule() {
  const box = document.getElementById('formMesa');
  const btn = document.getElementById('btnNuevaMesa');
  const cancel = document.getElementById('btnCancelarMesa');
  const form = document.getElementById('mesaForm');
  const method = document.getElementById('mesaMethod');
  const save = document.getElementById('saveMesa');
  const importBox = document.getElementById('importMesa');
  const importBtn = document.getElementById('btnImportarMesa');
  const cancelImport = document.getElementById('btnCancelarImportMesa');
  const region = document.getElementById('mesaRegion');
  const prov = document.getElementById('mesaProvincia');
  const dist = document.getElementById('mesaDistrito');
  const local = document.getElementById('mesaLocal');

  function toggleMesaForm() {
    box.classList.toggle('hidden');
  }
  function resetMesaForm() {
    form.reset();
    method.value = '';
    form.action = '{{ route('mesas.store') }}';
    save.textContent = 'Registrar mesa';
    prov.innerHTML = '<option value="">Seleccione</option>';
    dist.innerHTML = '<option value="">Seleccione</option>';
    local.innerHTML = '<option value="">Seleccione</option>';
  }
  btn.addEventListener('click', function () {
    box.classList.toggle('hidden');
    if (importBox) importBox.classList.add('hidden');
  });
  importBtn?.addEventListener('click', function () {
    importBox?.classList.toggle('hidden');
    box.classList.add('hidden');
  });
  cancelImport?.addEventListener('click', function () { importBox?.classList.add('hidden'); });
  cancel.addEventListener('click', function () {
    resetMesaForm();
    box.classList.add('hidden');
  });

  region.addEventListener('change', async function () {
    prov.innerHTML='<option value="">Cargando...</option>';
    dist.innerHTML='<option value="">Seleccione</option>';
    local.innerHTML='<option value="">Seleccione</option>';
    if (!this.value) { prov.innerHTML='<option value="">Seleccione</option>'; return; }
    const r=await fetch('{{ url('/mesas/provincias') }}/'+this.value);
    if (!r.ok) throw new Error('No se pudieron cargar las provincias.');
    const data=await r.json();
    prov.innerHTML='<option value="">Seleccione</option>';
    data.forEach(x=>prov.insertAdjacentHTML('beforeend','<option value="'+x.id_provincia+'">'+x.nombre+'</option>'));
  });

  prov.addEventListener('change', async function () {
    dist.innerHTML='<option value="">Cargando...</option>';
    local.innerHTML='<option value="">Seleccione</option>';
    if (!this.value) { dist.innerHTML='<option value="">Seleccione</option>'; return; }
    const r=await fetch('{{ url('/mesas/distritos') }}/'+this.value);
    if (!r.ok) throw new Error('No se pudieron cargar los distritos.');
    const data=await r.json();
    dist.innerHTML='<option value="">Seleccione</option>';
    data.forEach(x=>dist.insertAdjacentHTML('beforeend','<option value="'+x.id_distrito+'">'+x.nombre+'</option>'));
  });

  dist.addEventListener('change', async function () {
    local.innerHTML='<option value="">Cargando...</option>';
    if (!this.value) { local.innerHTML='<option value="">Seleccione</option>'; return; }
    const r=await fetch('{{ url('/mesas/locales') }}/'+this.value);
    if (!r.ok) throw new Error('No se pudieron cargar los locales.');
    const data=await r.json();
    local.innerHTML='<option value="">Seleccione</option>';
    data.forEach(x=>local.insertAdjacentHTML('beforeend','<option value="'+x.id_local+'">'+x.nombre+'</option>'));
  });

  document.querySelectorAll('.edit-mesa').forEach(function (button) {
    button.addEventListener('click', async function () {
      box.classList.remove('hidden');
      form.action='{{ url('/mesas') }}/'+this.dataset.id;
      method.value='PUT';
      save.textContent='Guardar cambios';
      document.getElementById('numeroMesa').value=this.dataset.numero;
      document.getElementById('totalElectores').value=this.dataset.electores;
      region.value=this.dataset.region;

      const p=await fetch('{{ url('/mesas/provincias') }}/'+this.dataset.region).then(r=>r.json());
      prov.innerHTML='<option value="">Seleccione</option>';
      p.forEach(x=>prov.insertAdjacentHTML('beforeend','<option value="'+x.id_provincia+'">'+x.nombre+'</option>'));
      prov.value=this.dataset.provincia;

      const d=await fetch('{{ url('/mesas/distritos') }}/'+this.dataset.provincia).then(r=>r.json());
      dist.innerHTML='<option value="">Seleccione</option>';
      d.forEach(x=>dist.insertAdjacentHTML('beforeend','<option value="'+x.id_distrito+'">'+x.nombre+'</option>'));
      dist.value=this.dataset.distrito;

      const l=await fetch('{{ url('/mesas/locales') }}/'+this.dataset.distrito).then(r=>r.json());
      local.innerHTML='<option value="">Seleccione</option>';
      l.forEach(x=>local.insertAdjacentHTML('beforeend','<option value="'+x.id_local+'">'+x.nombre+'</option>'));
      local.value=this.dataset.local;
      window.scrollTo({top:0,behavior:'smooth'});
    });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initMesasModule, { once: true });
} else {
  initMesasModule();
}
</script>
@endsection
