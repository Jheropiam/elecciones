@extends('layouts.app')
@section('content')
<style>
.partido-page{max-width:1500px;margin:0 auto}.module-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 2px 10px rgba(15,23,42,.05);padding:18px}.page-head{display:flex;justify-content:space-between;gap:15px;align-items:center;margin-bottom:16px}.page-head h1{font-size:20px;margin:0}.page-head p{margin:5px 0 0;color:#6b7280;font-size:13px}.actions-top{display:flex;gap:8px;flex-wrap:wrap}.btn{border:1px solid #d1d5db;border-radius:7px;padding:9px 12px;font-size:13px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;background:#fff;color:#374151}.btn-primary{background:var(--orange);border-color:var(--orange);color:#fff}.btn-primary:hover{background:var(--orange-dark);border-color:var(--orange-dark)}.btn-success{background:var(--orange-dark);border-color:var(--orange-dark);color:#fff}.btn-success:hover{background:var(--orange);border-color:var(--orange)}.btn-light{background:#fff}.form-panel{margin:0 0 16px;padding:18px;border:1px solid color-mix(in srgb,var(--orange) 35%,#ffffff);border-radius:10px;background:var(--orange-soft)}.hidden{display:none!important}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.field label{display:block;font-size:12px;font-weight:600;margin-bottom:5px;color:#374151}.field label span{color:#dc2626}.field input,.field select{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:7px;padding:9px 10px;font-size:13px;background:#fff}.form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:15px}.filter-bar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.search-field{display:flex;align-items:center;border:1px solid #d1d5db;border-radius:7px;padding:0 10px;flex:1;min-width:240px}.search-field input{border:0;outline:0;padding:9px;width:100%}.table-wrap{overflow:auto}.data-table{width:100%;border-collapse:collapse;font-size:12px}.data-table th,.data-table td{padding:10px 8px;border-bottom:1px solid #edf0f3;text-align:left;vertical-align:middle}.data-table th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}.strong{font-weight:600}.status{padding:4px 8px;border-radius:20px;font-size:11px}.status-active{background:#dcfce7;color:#166534}.status-inactive{background:#fee2e2;color:#991b1b}.status-special{background:#e0e7ff;color:#3730a3}.icon-btn{border:1px solid #d1d5db;background:#fff;border-radius:6px;padding:6px 8px;cursor:pointer}.actions{display:flex;gap:5px}.alert{padding:10px 12px;border-radius:8px;margin-bottom:12px;font-size:13px}.alert-success{background:#dcfce7;color:#166534}.alert-error{background:#fee2e2;color:#991b1b}.import-summary{padding:12px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:9px;margin-bottom:14px}.import-grid{display:flex;gap:18px;flex-wrap:wrap;margin-top:8px}.logo-thumb{width:38px;height:38px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;background:#fff}.logo-placeholder{width:38px;height:38px;border:1px dashed #cbd5e1;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:10px}.help{font-size:11px;color:#6b7280;margin-top:4px}.pagination{margin-top:14px}
@media(max-width:900px){.grid{grid-template-columns:1fr}.page-head{align-items:flex-start;flex-direction:column}}
</style>
<div class="partido-page">
  <div class="page-head">
    <div><h1>Partidos Políticos</h1><p>Administración de partidos políticos y conceptos especiales utilizados por el sistema electoral.</p></div>
    <div class="actions-top">
      <button type="button" class="btn btn-primary" onclick="toggleForm('form-partido')">＋ Nuevo</button>
      <a class="btn" href="{{ route('partidos.template') }}">Plantilla Excel</a>
      <a class="btn" href="{{ route('partidos.template.zip') }}">Plantilla ZIP + logos</a>
      <a class="btn" href="{{ route('partidos.export', request()->query()) }}">Exportar Excel</a>
      <button type="button" class="btn btn-success" onclick="toggleForm('form-import')">Importar Excel / ZIP</button>
    </div>
  </div>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-error"><strong>Revise los datos:</strong><ul style="margin:6px 0 0 18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <div id="form-partido" class="form-panel hidden">
    <form id="partidoForm" method="POST" action="{{ route('partidos.store') }}" enctype="multipart/form-data">
      @csrf <input type="hidden" name="_method" id="partidoMethod"><input type="hidden" name="_form" value="partido"><input type="hidden" name="tipo_edicion" id="tipoEdicion" value="">
      <div class="grid">
        <div class="field"><label>Nombre <span>*</span></label><input id="nombre" name="nombre" maxlength="200" required value="{{ old('nombre') }}"><div class="help">No se permiten nombres duplicados.</div></div>
        <div class="field"><label>Tipo <span>*</span></label><select id="tipo" name="tipo" required><option value="POLITICO">POLÍTICO</option><option value="ESPECIAL">ESPECIAL</option></select></div>
        <div class="field"><label>Logo</label><input id="logo" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><div class="help">JPG, JPEG, PNG o WEBP. Máximo 2 MB. Los conceptos especiales no llevan logo.</div></div>
      </div>
      <div class="form-actions"><button type="button" class="btn btn-light" onclick="cancelPartidoForm()">Cancelar</button><button class="btn btn-primary" id="savePartido">Registrar partido</button></div>
    </form>
  </div>

  <div id="form-import" class="form-panel hidden">
    <form method="POST" action="{{ route('partidos.import') }}" enctype="multipart/form-data">
      @csrf <div class="grid"><div class="field"><label>Archivo Excel <span>*</span></label><input type="file" name="archivo" accept=".xlsx,.zip,application/zip" required><div class="help">Excel (.xlsx) sin logos o ZIP con <strong>partidos.xlsx</strong> y carpeta <strong>logos/</strong>. Máximo 20 MB.</div></div></div>
      <div class="form-actions"><button type="button" class="btn btn-light" onclick="document.getElementById('form-import').classList.add('hidden')">Cancelar</button><button class="btn btn-success">Importar</button></div>
    </form>
  </div>

  @if(session('import_summary'))<div class="import-summary"><b>Resultado de importación</b><div class="import-grid"><span>Procesados: <b>{{ session('import_summary.procesados') }}</b></span><span>Nuevos: <b>{{ session('import_summary.nuevos') }}</b></span><span>Duplicados: <b>{{ session('import_summary.duplicados') }}</b></span><span>Errores: <b>{{ session('import_summary.errores') }}</b></span></div>@if(session('import_error_file'))<div style="margin-top:8px"><a href="{{ route('partidos.import.errors', session('import_error_file')) }}">Descargar Excel de errores</a></div>@endif</div>@endif

  <div class="module-card">
    <form class="filter-bar" method="GET">
      <div class="search-field">⌕ <input name="q" value="{{ $q }}" placeholder="Buscar partido o concepto"></div>
      <select class="btn btn-light" name="tipo"><option value="">Todos los tipos</option><option value="POLITICO" @selected($tipo==='POLITICO')>Político</option><option value="ESPECIAL" @selected($tipo==='ESPECIAL')>Especial</option></select>
      <select class="btn btn-light" name="estado"><option value="">Todos</option><option value="1" @selected($estado===1)>Activos</option><option value="0" @selected($estado===0)>Inactivos</option></select>
      <button class="btn btn-primary">Filtrar</button><a class="btn" href="{{ route('partidos.index') }}">Limpiar</a>
    </form>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>N°</th><th>Logo</th><th>Nombre</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($partidos as $i=>$p)
      <tr><td>{{ ($partidos->currentPage()-1)*$partidos->perPage()+$i+1 }}</td><td>@if($p->logo)<img class="logo-thumb" src="{{ asset($p->logo) }}" alt="Logo">@else<div class="logo-placeholder">Sin logo</div>@endif</td><td class="strong">{{ $p->nombre }}</td><td><span class="status {{ $p->tipo==='ESPECIAL'?'status-special':'' }}">{{ $p->tipo==='POLITICO'?'Político':'Especial' }}</span></td><td><span class="status {{ $p->estado?'status-active':'status-inactive' }}">{{ $p->estado?'Activo':'Inactivo' }}</span></td><td class="actions">@if($p->tipo==='POLITICO')<button type="button" class="icon-btn edit-partido" title="Editar" data-partido='@json($p)'>✎</button><form method="POST" action="{{ route('partidos.toggle',$p->id_partido) }}">@csrf @method('PATCH')<button class="icon-btn" title="{{ $p->estado?'Deshabilitar':'Habilitar' }}">{{ $p->estado?'◉':'○' }}</button></form>@else<span class="help">Protegido</span>@endif</td></tr>
    @empty<tr><td colspan="7" style="text-align:center;padding:30px;color:#64748b">No se encontraron registros.</td></tr>@endforelse
    </tbody></table></div>{{ $partidos->withQueryString()->links('pagination.custom') }}
  </div>
</div>
<script>
function toggleForm(id){document.getElementById(id)?.classList.toggle('hidden')}
function cancelPartidoForm(){const f=document.getElementById('form-partido');f.classList.add('hidden');const form=document.getElementById('partidoForm');form.reset();document.getElementById('partidoMethod').value='';document.getElementById('tipoEdicion').value='';document.getElementById('partidoForm').action='{{ route('partidos.store') }}';document.getElementById('savePartido').textContent='Registrar partido';document.getElementById('tipo').disabled=false;document.getElementById('tipo').name='tipo';}
document.getElementById('tipo')?.addEventListener('change',()=>{const special=document.getElementById('tipo').value==='ESPECIAL';document.getElementById('logo').disabled=special;if(special)document.getElementById('logo').value='';});
document.querySelectorAll('.edit-partido').forEach(btn=>btn.addEventListener('click',()=>{const p=JSON.parse(btn.dataset.partido);document.getElementById('form-partido').classList.remove('hidden');document.getElementById('nombre').value=p.nombre;const tipo=document.getElementById('tipo');tipo.value='POLITICO';tipo.disabled=true;document.getElementById('tipoEdicion').value='POLITICO';document.getElementById('logo').disabled=false;document.getElementById('partidoMethod').value='PUT';document.getElementById('partidoForm').action='/partidos/'+p.id_partido;document.getElementById('savePartido').textContent='Guardar cambios';window.scrollTo({top:0,behavior:'smooth'});}));
</script>
@endsection
