@extends('layouts.app')
@section('content')
<div class="page usuarios-page">
  <div class="page-heading">
    <div>
      <div class="breadcrumb">Inicio <span>›</span> Usuarios</div>
      <h1>Usuarios</h1>
      <p class="page-subtitle">Administración de accesos, roles y ámbito territorial.</p>
    </div>
  </div>

  <div class="module-card">
    <div class="module-card-header">
      <div>
        <h2>Usuarios</h2>
        <p>Administre los accesos al sistema a partir de personas registradas.</p>
      </div>
      <div class="module-actions">
        <a class="btn btn-light" href="{{ route('usuarios.template') }}">Plantilla Excel</a>
        <a class="btn btn-light" href="{{ route('usuarios.export') }}">Exportar Excel</a>
        <button class="btn btn-light" type="button" onclick="toggleForm('import-usuario')">Importar Excel</button>
        <button class="btn btn-primary" type="button" onclick="toggleForm('form-usuario')">+ Nuevo usuario</button>
      </div>
    </div>

    <div id="import-usuario" class="inline-form hidden">
      <form method="POST" action="{{ route('usuarios.import') }}" enctype="multipart/form-data" class="form-grid">
        @csrf
        <div class="field field-wide">
          <label for="archivo_usuarios">Archivo Excel (.xlsx) <span>*</span></label>
          <input id="archivo_usuarios" name="archivo" type="file" accept=".xlsx" required>
          <small>Use la plantilla descargable. Máximo 5 MB.</small>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Procesar importación</button>
          <button class="btn btn-light" type="button" onclick="toggleForm('import-usuario')">Cancelar</button>
        </div>
      </form>
    </div>

    <div id="form-usuario" class="inline-form {{ $errors->any() && old('_form') === 'usuario' ? '' : 'hidden' }}">
      <form id="userForm" method="POST" action="{{ route('usuarios.store') }}">
        @csrf
        <input type="hidden" name="_method" id="userMethod" value="">
        <input type="hidden" name="_form" value="usuario">
        <div class="form-grid">
          <div class="field field-wide">
            <label>DNI <span>*</span></label>
            <div style="display:flex;gap:7px">
              <input id="udni" maxlength="20">
              <button type="button" class="btn btn-light" id="buscarUsuario">Buscar persona</button>
            </div>
            <small id="uMsg"></small>
            <input type="hidden" name="dni" id="udniHidden">
          </div>
          <div class="field field-wide"><label>Persona</label><input id="upersona" readonly placeholder="Busque el DNI"></div>
          <div class="field">
            <label>Roles <span>*</span></label>
            <div class="role-picker" id="rolePicker">
              <button type="button" class="role-picker-button" id="rolePickerButton">
                <span id="rolePickerText">Seleccione uno o más roles</span>
                <span class="role-picker-arrow">⌄</span>
              </button>
              <div class="role-picker-menu" id="rolePickerMenu" hidden>
                <div class="role-picker-title">Seleccione los roles</div>
                @foreach($roles as $r)
                  <label class="role-picker-option">
                    <input type="checkbox" class="role-check" name="roles[]" value="{{ $r->id_rol }}" data-role-name="{{ $r->nombre }}">
                    <span class="role-check-box"></span>
                    <span>{{ $r->nombre }}</span>
                  </label>
                @endforeach
              </div>
            </div>
            <small id="rolePickerHint">Puede seleccionar uno o más roles.</small>
          </div>
          <div class="field">
            <label>Nivel de ámbito <span>*</span></label>
            <select name="nivel" id="nivel" required>
              <option value="">Seleccione según el rol</option>
            </select>
            <small id="nivelHint">Primero seleccione el rol.</small>
          </div>
          <div class="field"><label>Región</label><select name="id_region" id="uRegion"><option value="">Seleccione</option>@foreach(DB::table('region')->where('estado',1)->orderBy('nombre')->get() as $r)<option value="{{ $r->id_region }}">{{ $r->nombre }}</option>@endforeach</select></div>
          <div class="field"><label>Provincia</label><select name="id_provincia" id="uProvincia"><option value="">Seleccione</option></select></div>
          <div class="field"><label>Distrito</label><select name="id_distrito" id="uDistrito"><option value="">Seleccione</option></select></div>
          <div class="field"><label>Local</label><select name="id_local" id="uLocal"><option value="">Seleccione</option></select></div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-light" id="cancelUserEdit" onclick="cancelUserForm()">Cancelar</button>
          <button class="btn btn-primary" id="saveUser">Registrar usuario</button>
        </div>
      </form>
    </div>

    @if($errors->any() && old('_form') === 'usuario')
      <div class="validation-summary">
        <strong>Revise los datos:</strong>
        <ul style="margin:6px 0 0 18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="module-card" style="margin:18px 0 0;border-radius:0;border-left:0;border-right:0;box-shadow:none">
      <div class="module-card-header"><div><h2>Usuarios registrados</h2><p>Solo el Administrador puede gestionar esta sección.</p></div></div>
      <form class="filter-bar" method="GET">
        <div class="search-field"><span>⌕</span><input name="q" value="{{ $q }}" placeholder="DNI, usuario, nombres o apellidos"></div>
        <select class="btn btn-light" name="rol"><option value="">Todos los roles</option>@foreach($roles as $r)<option value="{{ $r->id_rol }}" @selected($rol==$r->id_rol)>{{ $r->nombre }}</option>@endforeach</select>
        <select class="btn btn-light" name="estado"><option value="">Todos</option><option value="1" @selected($estado===1)>Activos</option><option value="0" @selected($estado===0)>Inactivos</option></select>
        <button class="btn btn-primary">Filtrar</button><a class="clear-filter" href="{{ route('usuarios.index') }}">Limpiar</a>
      </form>

      @if(session('import_summary'))
        <div class="import-summary"><b>Resultado de importación</b><div class="import-summary-grid"><span>Procesados <b>{{ session('import_summary.procesados') }}</b></span><span>Nuevos <b>{{ session('import_summary.nuevos') }}</b></span><span>Duplicados <b>{{ session('import_summary.duplicados') }}</b></span><span>Errores <b>{{ session('import_summary.errores') }}</b></span></div>@if(session('import_error_file'))<a href="{{ route('usuarios.import.errors', session('import_error_file')) }}">Descargar Excel de errores</a>@endif</div>
      @endif

      <div class="table-wrap"><table class="data-table"><thead><tr><th>N°</th><th>Usuario</th><th>Persona</th><th>Roles</th><th>Ámbito</th><th>Estado</th><th>Contraseña</th><th>Acciones</th></tr></thead><tbody>
      @forelse($usuarios as $i=>$u)
        <tr>
          <td>{{ ($usuarios->currentPage()-1)*$usuarios->perPage()+$i+1 }}</td>
          <td class="strong">{{ $u->usuario }}</td>
          <td>{{ $u->nombres }} {{ $u->apellido_paterno }} {{ $u->apellido_materno }}</td>
          <td>{{ $u->roles ?: 'Sin rol' }}</td>
          <td>{{ $u->ambitos ?: 'Sin restricción' }}</td>
          <td><span class="status {{ $u->estado?'status-active':'status-inactive' }}">{{ $u->estado?'Activo':'Inactivo' }}</span></td>
          <td>{{ $u->debe_cambiar_password?'Debe cambiar':'Actualizada' }}</td>
          <td class="actions">
            <button type="button" class="icon-btn edit-user" title="Editar usuario" data-dni="{{ $u->dni }}">✎</button>
            <form method="POST" action="{{ route('usuarios.reset',$u->id_usuario) }}">@csrf @method('PATCH')<button class="icon-btn" title="Restablecer contraseña">↻</button></form>
            <form method="POST" action="{{ route('usuarios.toggle',$u->id_usuario) }}">@csrf @method('PATCH')<button class="icon-btn" title="{{ $u->estado?'Deshabilitar':'Habilitar' }}">{{ $u->estado?'◉':'○' }}</button></form>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="empty">No se encontraron usuarios.</td></tr>
      @endforelse
      </tbody></table></div>
      {{ $usuarios->withQueryString()->links('pagination.custom') }}
    </div>
  </div>
</div>

<script>
function toggleForm(id){document.getElementById(id)?.classList.toggle('hidden')}
function showForm(id){document.getElementById(id)?.classList.remove('hidden')}
const uRegion=document.getElementById('uRegion'),uProv=document.getElementById('uProvincia'),uDist=document.getElementById('uDistrito'),uLocal=document.getElementById('uLocal'),nivel=document.getElementById('nivel');
const rolePicker=document.getElementById('rolePicker'),rolePickerButton=document.getElementById('rolePickerButton'),rolePickerMenu=document.getElementById('rolePickerMenu'),rolePickerText=document.getElementById('rolePickerText');
const roleChecks=[...document.querySelectorAll('.role-check')];
const roleScopeMap={
  'Administrador':['ADMIN'],
  'Personero Regional':['REGION'],
  'Personero Provincial':['PROVINCIA'],
  'Personero Distrital':['DISTRITO'],
  'Personero de Local':['LOCAL'],
  'Personero de Mesa':['LOCAL'],
  'Digitador':['REGION','PROVINCIA','DISTRITO']
};
const scopeLabels={ADMIN:'Administrador / sin restricción',REGION:'Región',PROVINCIA:'Provincia',DISTRITO:'Distrito',LOCAL:'Local de votación'};
function selectedRoleNames(){return roleChecks.filter(x=>x.checked).map(x=>x.dataset.roleName);}
function allowedScopes(){
 const names=selectedRoleNames();
 if(!names.length)return [];
 const values=[...new Set(names.flatMap(n=>roleScopeMap[n]||['REGION','PROVINCIA','DISTRITO']))];
 if(values.includes('ADMIN')) return ['ADMIN'];
 return values;
}
function refreshRolePicker(){
 const names=selectedRoleNames();
 rolePickerText.textContent=names.length?names.join(', '):'Seleccione uno o más roles';
 rolePickerText.classList.toggle('has-selection',names.length>0);
 refreshScopeOptions();
}
function refreshScopeOptions(preferred=null){
 const allowed=allowedScopes(), current=preferred||nivel.value;
 nivel.innerHTML='<option value="">Seleccione nivel de ámbito</option>';
 allowed.forEach(v=>nivel.insertAdjacentHTML('beforeend',`<option value="${v}">${scopeLabels[v]}</option>`));
 if(allowed.length===1){nivel.value=allowed[0];}
 else if(current&&allowed.includes(current)){nivel.value=current;}
 else if(allowed.includes('REGION')){nivel.value='REGION';}
 setVisibility();
 const hint=document.getElementById('nivelHint');
 hint.textContent=allowed.length===1
   ? `El rol seleccionado requiere ámbito: ${scopeLabels[allowed[0]]}.`
   : (allowed.length?'Seleccione el nivel territorial que tendrá el usuario.':'Primero seleccione el rol.');
}
function setVisibility(){
 const n=nivel.value;
 const showRegion=n!=='ADMIN'&&n!=='';
 const showProv=['PROVINCIA','DISTRITO','LOCAL'].includes(n);
 const showDist=['DISTRITO','LOCAL'].includes(n);
 const showLocal=n==='LOCAL';
 uRegion.parentElement.style.display=showRegion?'':'none';
 uProv.parentElement.style.display=showProv?'':'none';
 uDist.parentElement.style.display=showDist?'':'none';
 uLocal.parentElement.style.display=showLocal?'':'none';
 uRegion.required=showRegion;
 uProv.required=showProv;
 uDist.required=showDist;
 uLocal.required=showLocal;
}
nivel.onchange=setVisibility;
rolePickerButton?.addEventListener('click',e=>{e.stopPropagation();rolePickerMenu.hidden=!rolePickerMenu.hidden;});
document.addEventListener('click',e=>{if(rolePicker && !rolePicker.contains(e.target))rolePickerMenu.hidden=true;});
roleChecks.forEach(c=>c.addEventListener('change',refreshRolePicker));
refreshRolePicker();
uRegion.onchange=async()=>{uProv.innerHTML='<option value="">Seleccione</option>';uDist.innerHTML='<option value="">Seleccione</option>';uLocal.innerHTML='<option value="">Seleccione</option>';if(uRegion.value){const r=await fetch('/usuarios/provincias/'+uRegion.value);(await r.json()).forEach(x=>uProv.insertAdjacentHTML('beforeend',`<option value="${x.id_provincia}">${x.nombre}</option>`));}};
uProv.onchange=async()=>{uDist.innerHTML='<option value="">Seleccione</option>';uLocal.innerHTML='<option value="">Seleccione</option>';if(uProv.value){const r=await fetch('/usuarios/distritos/'+uProv.value);(await r.json()).forEach(x=>uDist.insertAdjacentHTML('beforeend',`<option value="${x.id_distrito}">${x.nombre}</option>`));}};
uDist.onchange=async()=>{uLocal.innerHTML='<option value="">Seleccione</option>';if(uDist.value){const r=await fetch('/usuarios/locales/'+uDist.value);(await r.json()).forEach(x=>uLocal.insertAdjacentHTML('beforeend',`<option value="${x.id_local}">${x.nombre}</option>`));}};
async function cargarUsuario(dni,editar=false){
 const r=await fetch('/usuarios/buscar-persona/'+encodeURIComponent(dni)); const x=await r.json(); const msg=document.getElementById('uMsg');
 if(!x.found){msg.textContent='La persona no existe. Regístrela primero en Personas.';msg.style.color='#b42318';return;}
 document.getElementById('udni').value=dni;document.getElementById('udniHidden').value=dni;
 document.getElementById('upersona').value=[x.persona.nombres,x.persona.apellido_paterno,x.persona.apellido_materno].filter(Boolean).join(' ');
 if(x.usuario && !editar){msg.textContent='Esta persona ya tiene un usuario.';msg.style.color='#b42318';return;}
 if(x.usuario && editar){
   document.getElementById('userForm').action='/usuarios/'+x.usuario.id_usuario;document.getElementById('userMethod').value='PUT';document.getElementById('saveUser').textContent='Guardar cambios';
   roleChecks.forEach(c=>c.checked=(x.roles||[]).map(Number).includes(Number(c.value)));
   refreshRolePicker();
   const s=x.scope;if(s){refreshScopeOptions(s.nivel||'REGION');nivel.value=s.nivel||'REGION';setVisibility();uRegion.value=s.id_region||'';if(s.id_region){let rr=await fetch('/usuarios/provincias/'+s.id_region);let pp=await rr.json();uProv.innerHTML='<option value="">Seleccione</option>';pp.forEach(v=>uProv.insertAdjacentHTML('beforeend',`<option value="${v.id_provincia}">${v.nombre}</option>`));uProv.value=s.id_provincia||'';}if(s.id_provincia){let rr=await fetch('/usuarios/distritos/'+s.id_provincia);let dd=await rr.json();uDist.innerHTML='<option value="">Seleccione</option>';dd.forEach(v=>uDist.insertAdjacentHTML('beforeend',`<option value="${v.id_distrito}">${v.nombre}</option>`));uDist.value=s.id_distrito||'';}if(s.id_distrito){let rr=await fetch('/usuarios/locales/'+s.id_distrito);let ll=await rr.json();uLocal.innerHTML='<option value="">Seleccione</option>';ll.forEach(v=>uLocal.insertAdjacentHTML('beforeend',`<option value="${v.id_local}">${v.nombre}</option>`));uLocal.value=s.id_local||'';}}
   msg.textContent='Usuario cargado para edición.';msg.style.color='#19713b';
 } else {msg.textContent='Persona disponible para crear usuario.';msg.style.color='#19713b';}
}
document.getElementById('buscarUsuario').onclick=()=>cargarUsuario(document.getElementById('udni').value.trim(),false);
document.querySelectorAll('.edit-user').forEach(b=>b.onclick=()=>{showForm('form-usuario');window.scrollTo({top:0,behavior:'smooth'});cargarUsuario(b.dataset.dni,true);});
function cancelUserForm(){location.href='{{ route('usuarios.index') }}'}
</script>
@endsection
