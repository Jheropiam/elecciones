@extends('layouts.app')
@section('content')
<div class="page personas-page">
  <div class="page-heading">
    <div>
      <div class="breadcrumb">Inicio <span>›</span> Personas</div>
      <h1>Personas</h1>
      <p class="page-subtitle">Registro y mantenimiento de las personas del sistema.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-card-header">
      <div>
        <h2>Personas</h2>
        <p>Administre el padrón de personas y sus datos de identificación.</p>
      </div>
      <div class="module-actions">
        <a class="btn btn-light" href="{{ route('personas.template') }}">Plantilla Excel</a>
        <a class="btn btn-light" href="{{ route('personas.export') }}">Exportar Excel</a>
        <button class="btn btn-light" type="button" onclick="toggleForm('import-persona')">Importar Excel</button>
        <button class="btn btn-primary" type="button" onclick="toggleForm('form-persona')">+ Nueva persona</button>
      </div>
    </div>

    <div id="import-persona" class="inline-form hidden">
      <form method="POST" action="{{ route('personas.import') }}" enctype="multipart/form-data" class="form-grid">
        @csrf
        <div class="field field-wide">
          <label for="archivo_personas">Archivo Excel (.xlsx) <span>*</span></label>
          <input id="archivo_personas" name="archivo" type="file" accept=".xlsx" required>
          <small>Use la plantilla descargable. Máximo 5 MB.</small>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Procesar importación</button>
          <button class="btn btn-light" type="button" onclick="toggleForm('import-persona')">Cancelar</button>
        </div>
      </form>
    </div>

    <div id="form-persona" class="inline-form {{ $errors->any() && old('_form') === 'persona' ? '' : 'hidden' }}">
      <form id="personaForm" method="POST" action="{{ route('personas.store') }}">
        @csrf
        <input type="hidden" name="_method" id="personaMethod" value="">
        <input type="hidden" name="_form" value="persona">
        <div class="form-grid">
          <div class="field">
            <label>DNI <span>*</span></label>
            <div style="display:flex;gap:7px">
              <input id="dni" name="dni" value="{{ old('dni') }}" maxlength="20" required>
              <button type="button" class="btn btn-light" id="buscarDni">Buscar</button>
            </div>
            <small id="dniMsg"></small>
          </div>
          <div class="field">
            <label>Nombres <span>*</span></label>
            <input id="nombres" name="nombres" value="{{ old('nombres') }}" required>
          </div>
          <div class="field">
            <label>Apellido paterno <span>*</span></label>
            <input id="apellido_paterno" name="apellido_paterno" value="{{ old('apellido_paterno') }}" required>
          </div>
          <div class="field">
            <label>Apellido materno</label>
            <input id="apellido_materno" name="apellido_materno" value="{{ old('apellido_materno') }}">
          </div>
          <div class="field">
            <label>Celular</label>
            <input id="celular" name="celular" value="{{ old('celular') }}">
          </div>
          <div class="field">
            <label>Correo</label>
            <input id="correo" type="email" name="correo" value="{{ old('correo') }}">
          </div>
          <div class="field">
            <label>Región</label>
            <select id="id_region" name="id_region">
              <option value="">Seleccione</option>
              @foreach($regiones as $r)
                <option value="{{ $r->id_region }}" @selected(old('id_region') == $r->id_region)>{{ $r->nombre }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label>Provincia</label>
            <select id="id_provincia" name="id_provincia">
              <option value="">Seleccione</option>
              @foreach($provincias as $p)
                <option value="{{ $p->id_provincia }}" @selected(old('id_provincia') == $p->id_provincia)>{{ $p->nombre }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label>Distrito</label>
            <select id="id_distrito" name="id_distrito">
              <option value="">Seleccione</option>
              @foreach($distritos as $d)
                <option value="{{ $d->id_distrito }}" @selected(old('id_distrito') == $d->id_distrito)>{{ $d->nombre }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-light" id="cancelEdit" onclick="cancelPersonaForm()">Cancelar</button>
          <button class="btn btn-primary" id="savePersona">Registrar persona</button>
        </div>
      </form>
    </div>

    @if($errors->any() && old('_form') === 'persona')
      <div class="validation-summary">
        <strong>Revise los datos:</strong>
        <ul style="margin:6px 0 0 18px">
          @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
      </div>
    @endif

    <div class="module-card" style="margin:18px 0 0;border-radius:0;border-left:0;border-right:0;box-shadow:none">
      <div class="module-card-header"><div><h2>Personas registradas</h2><p>Filtre y administre los registros.</p></div></div>
      <form class="filter-bar" method="GET">
        <div class="search-field"><span>⌕</span><input name="q" value="{{ $q }}" placeholder="DNI, nombres o apellidos"></div>
        <select class="btn btn-light" name="region" id="filterRegion"><option value="">Todas las regiones</option>@foreach($regiones as $r)<option value="{{ $r->id_region }}" @selected($region==$r->id_region)>{{ $r->nombre }}</option>@endforeach</select>
        <select class="btn btn-light" name="provincia" id="filterProvincia"><option value="">Todas las provincias</option>@foreach($provincias as $p)<option value="{{ $p->id_provincia }}" @selected($provincia==$p->id_provincia)>{{ $p->nombre }}</option>@endforeach</select>
        <select class="btn btn-light" name="distrito" id="filterDistrito"><option value="">Todos los distritos</option>@foreach($distritos as $d)<option value="{{ $d->id_distrito }}" @selected($distrito==$d->id_distrito)>{{ $d->nombre }}</option>@endforeach</select>
        <select class="btn btn-light" name="estado"><option value="">Todos</option><option value="1" @selected($estado===1)>Activos</option><option value="0" @selected($estado===0)>Inactivos</option></select>
        <button class="btn btn-primary">Filtrar</button><a class="clear-filter" href="{{ route('personas.index') }}">Limpiar</a>
      </form>

      @if(session('import_summary'))
        <div class="import-summary"><b>Resultado de importación</b><div class="import-summary-grid"><span>Procesados <b>{{ session('import_summary.procesados') }}</b></span><span>Nuevos <b>{{ session('import_summary.nuevos') }}</b></span><span>Duplicados <b>{{ session('import_summary.duplicados') }}</b></span><span>Errores <b>{{ session('import_summary.errores') }}</b></span></div>@if(session('import_error_file'))<a href="{{ route('personas.import.errors', session('import_error_file')) }}">Descargar Excel de errores</a>@endif</div>
      @endif

      <div class="table-wrap"><table class="data-table"><thead><tr><th>N°</th><th>DNI</th><th>Persona</th><th>Ubicación</th><th>Celular</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
      @forelse($personas as $i=>$p)
        <tr>
          <td>{{ ($personas->currentPage()-1)*$personas->perPage()+$i+1 }}</td>
          <td class="strong">{{ $p->dni }}</td>
          <td class="strong">{{ $p->nombres }} {{ $p->apellido_paterno }} {{ $p->apellido_materno }}</td>
          <td>{{ collect([$p->region_nombre,$p->provincia_nombre,$p->distrito_nombre])->filter()->implode(' / ') }}</td>
          <td>{{ $p->celular }}</td>
          <td><span class="status {{ $p->estado?'status-active':'status-inactive' }}">{{ $p->estado?'Activo':'Inactivo' }}</span></td>
          <td class="actions">
            <button class="icon-btn edit-persona" type="button" title="Editar" data-persona='@json($p)'>✎</button>
            <form method="POST" action="{{ route('personas.toggle',$p->id_persona) }}">@csrf @method('PATCH')<button class="icon-btn" title="{{ $p->estado?'Deshabilitar':'Habilitar' }}">{{ $p->estado?'◉':'○' }}</button></form>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="empty">No se encontraron personas.</td></tr>
      @endforelse
      </tbody></table></div>
      {{ $personas->withQueryString()->links('pagination.custom') }}
    </div>
  </div>
</div>

<script>
function toggleForm(id){document.getElementById(id)?.classList.toggle('hidden')}
function showForm(id){document.getElementById(id)?.classList.remove('hidden')}
const region=document.getElementById('id_region'),prov=document.getElementById('id_provincia'),dist=document.getElementById('id_distrito');
region?.addEventListener('change',async()=>{prov.innerHTML='<option value="">Seleccione</option>';dist.innerHTML='<option value="">Seleccione</option>';if(region.value){const r=await fetch('/personas/provincias/'+region.value);(await r.json()).forEach(x=>prov.insertAdjacentHTML('beforeend',`<option value="${x.id_provincia}">${x.nombre}</option>`));}});
prov?.addEventListener('change',async()=>{dist.innerHTML='<option value="">Seleccione</option>';if(prov.value){const r=await fetch('/personas/distritos/'+prov.value);(await r.json()).forEach(x=>dist.insertAdjacentHTML('beforeend',`<option value="${x.id_distrito}">${x.nombre}</option>`));}});
document.getElementById('buscarDni').onclick=async()=>{const dni=document.getElementById('dni').value.trim();if(!dni)return;showForm('form-persona');const r=await fetch('/personas/buscar-dni/'+encodeURIComponent(dni));const x=await r.json();const msg=document.getElementById('dniMsg');if(!x.found){msg.textContent='DNI disponible para registrar.';msg.style.color='#19713b';return;}const p=x.persona;document.getElementById('nombres').value=p.nombres||'';document.getElementById('apellido_paterno').value=p.apellido_paterno||'';document.getElementById('apellido_materno').value=p.apellido_materno||'';document.getElementById('celular').value=p.celular||'';document.getElementById('correo').value=p.correo||'';region.value=p.id_region||'';if(p.id_region){const rr=await fetch('/personas/provincias/'+p.id_region);const pp=await rr.json();prov.innerHTML='<option value="">Seleccione</option>';pp.forEach(v=>prov.insertAdjacentHTML('beforeend',`<option value="${v.id_provincia}">${v.nombre}</option>`));prov.value=p.id_provincia||'';}if(p.id_provincia){const rr=await fetch('/personas/distritos/'+p.id_provincia);const dd=await rr.json();dist.innerHTML='<option value="">Seleccione</option>';dd.forEach(v=>dist.insertAdjacentHTML('beforeend',`<option value="${v.id_distrito}">${v.nombre}</option>`));dist.value=p.id_distrito||'';}document.getElementById('personaForm').action='/personas/'+p.id_persona;document.getElementById('personaMethod').value='PUT';document.getElementById('savePersona').textContent='Guardar cambios';document.getElementById('cancelEdit').style.display='inline-flex';msg.textContent='Persona encontrada. Se cargaron sus datos.';msg.style.color='#19713b';};
document.querySelectorAll('.edit-persona').forEach(b=>b.onclick=()=>{showForm('form-persona');window.scrollTo({top:0,behavior:'smooth'});const p=JSON.parse(b.dataset.persona);document.getElementById('dni').value=p.dni;document.getElementById('buscarDni').click();});
function cancelPersonaForm(){location.href='{{ route('personas.index') }}'}
</script>
@endsection
