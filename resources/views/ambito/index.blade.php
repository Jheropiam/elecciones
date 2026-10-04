@extends('layouts.app')

@section('content')
<div class="page ambito-page">
    <div class="page-heading">
        <div>
            <div class="breadcrumb">Administración <span>›</span> Ámbito</div>
            <h1>Ámbito</h1>
            <p class="page-subtitle">Gestiona la jerarquía territorial de regiones, provincias y distritos.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="validation-summary">
            <strong>Revise los datos ingresados:</strong>
            <ul>
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Región, Provincia y Distrito se navegan exclusivamente desde el menú lateral. --}}
    @if($tab === 'region')
        <section class="module-card">
            <div class="module-card-header">
                <div>
                    <h2>Regiones</h2>
                    <p>Registro y consulta de las regiones del sistema.</p>
                </div>
                <div class="module-actions">
                    <a class="btn btn-light" href="{{ route('ambito.region.export') }}">Exportar Excel</a>
                    <a class="btn btn-light" href="{{ route('ambito.region.template') }}">Plantilla Excel</a>
                    <button class="btn btn-light" type="button" onclick="toggleForm('import-region')">Importar Excel</button>
                    <button class="btn btn-primary" type="button" onclick="toggleForm('form-region')">+ Nueva región</button>
                </div>
            </div>

            @if(session('import_error'))
                <div class="validation-summary"><strong>Error de importación:</strong> {{ session('import_error') }}</div>
            @endif

            @if(session('import_summary'))
                @php
                    $summary = session('import_summary')
                @endphp
                <div class="import-summary">
                    <strong>Resultado de la importación</strong>
                    <div class="import-summary-grid">
                        <span>Procesados <b>{{ $summary['procesados'] }}</b></span>
                        <span>Nuevos <b>{{ $summary['nuevos'] }}</b></span>
                        <span>Duplicados <b>{{ $summary['duplicados'] }}</b></span>
                        <span>Errores <b>{{ $summary['errores'] }}</b></span>
                    </div>
                    @if(session('import_error_file'))
                        <a class="clear-filter" href="{{ route('ambito.region.import.errors', session('import_error_file')) }}">Descargar Excel de errores</a>
                    @endif
                </div>
            @endif

            <div id="import-region" class="inline-form hidden">
                <form method="POST" action="{{ route('ambito.region.import') }}" enctype="multipart/form-data" class="form-grid">
                    @csrf
                    <div class="field field-wide">
                        <label for="archivo_regiones">Archivo Excel (.xlsx) <span>*</span></label>
                        <input id="archivo_regiones" name="archivo_regiones" type="file" accept=".xlsx" required>
                        <small>Use la plantilla descargable. La primera columna debe ser <strong>Región</strong>. Máximo 5 MB.</small>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Procesar importación</button>
                        <button class="btn btn-light" type="button" onclick="toggleForm('import-region')">Cancelar</button>
                    </div>
                </form>
            </div>

            <div id="form-region" class="inline-form {{ $errors->any() && old('_form') === 'region' ? '' : 'hidden' }}">
                <form method="POST" action="{{ route('ambito.region.store') }}" class="form-grid">
                    @csrf
                    <input type="hidden" name="_form" value="region">
                    <div class="field field-wide">
                        <label for="region_nombre">Nombre de la región <span>*</span></label>
                        <input id="region_nombre" name="nombre" value="{{ old('nombre') }}" maxlength="150" required autofocus>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Guardar</button>
                        <button class="btn btn-light" type="button" onclick="toggleForm('form-region')">Cancelar</button>
                    </div>
                </form>
            </div>

            <form method="GET" class="filter-bar">
                <input type="hidden" name="tab" value="region">
                <div class="search-field"><span>⌕</span><input name="q_region" value="{{ $qRegion }}" placeholder="Buscar región..."></div>
                <button class="btn btn-light" type="submit">Buscar</button>
                @if($qRegion !== '')<a class="clear-filter" href="{{ route('ambito.index', ['tab'=>'region']) }}">Limpiar</a>@endif
            </form>

            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th style="width:70px">N°</th><th>Región</th><th style="width:130px">Estado</th><th style="width:190px">Acciones</th></tr></thead>
                    <tbody>
                    @forelse($regiones as $i => $region)
                        <tr>
                            <td>{{ $regiones->firstItem() + $i }}</td>
                            <td class="strong">{{ $region->nombre }}</td>
                            <td><span class="status {{ $region->estado ? 'status-active' : 'status-inactive' }}">{{ $region->estado ? 'Activo' : 'Inactivo' }}</span></td>
                            <td class="actions">
                                <button class="icon-btn" title="Editar" onclick='editRegion(@json($region))'>✎</button>
                                <form method="POST" action="{{ route('ambito.region.toggle', $region->id_region) }}" onsubmit="return confirm('¿Desea {{ $region->estado ? 'deshabilitar' : 'habilitar' }} esta región?')">
                                    @csrf @method('PATCH')
                                    <button class="icon-btn" title="{{ $region->estado ? 'Deshabilitar' : 'Habilitar' }}">{{ $region->estado ? '◉' : '○' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No hay regiones registradas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $regiones->withQueryString()->links('pagination.custom') }}
        </section>

        <div id="edit-region" class="modal-backdrop hidden">
            <div class="modal">
                <div class="modal-header"><h3>Editar región</h3><button onclick="closeModal('edit-region')">×</button></div>
                <form id="edit-region-form" method="POST">
                    @csrf @method('PUT')
                    <label>Nombre de la región <span>*</span></label>
                    <input id="edit_region_nombre" name="nombre" maxlength="150" required>
                    <div class="form-actions"><button class="btn btn-primary">Guardar cambios</button><button type="button" class="btn btn-light" onclick="closeModal('edit-region')">Cancelar</button></div>
                </form>
            </div>
        </div>
    @endif

    @if($tab === 'provincia')
        <section class="module-card">
            <div class="module-card-header">
                <div><h2>Provincias</h2><p>Cada provincia pertenece a una región.</p></div>
                <div class="module-actions">
                    <a class="btn btn-light" href="{{ route('ambito.provincia.export') }}">Exportar Excel</a>
                    <a class="btn btn-light" href="{{ route('ambito.provincia.template') }}">Plantilla Excel</a>
                    <button class="btn btn-light" type="button" onclick="toggleForm('import-provincia')">Importar Excel</button>
                    <button class="btn btn-primary" type="button" onclick="toggleForm('form-provincia')">+ Nueva provincia</button>
                </div>
            </div>

            @if(session('import_error_provincia'))
                <div class="validation-summary"><strong>Error de importación:</strong> {{ session('import_error_provincia') }}</div>
            @endif

            @if(session('import_summary_provincia'))
                @php
                    $summary = session('import_summary_provincia')
                @endphp
                <div class="import-summary">
                    <strong>Resultado de la importación</strong>
                    <div class="import-summary-grid">
                        <span>Procesados <b>{{ $summary['procesados'] }}</b></span>
                        <span>Nuevos <b>{{ $summary['nuevos'] }}</b></span>
                        <span>Duplicados <b>{{ $summary['duplicados'] }}</b></span>
                        <span>Errores <b>{{ $summary['errores'] }}</b></span>
                    </div>
                    @if(session('import_error_file_provincia'))
                        <a class="clear-filter" href="{{ route('ambito.provincia.import.errors', session('import_error_file_provincia')) }}">Descargar Excel de errores</a>
                    @endif
                </div>
            @endif

            <div id="import-provincia" class="inline-form hidden">
                <form method="POST" action="{{ route('ambito.provincia.import') }}" enctype="multipart/form-data" class="form-grid">
                    @csrf
                    <div class="field field-wide">
                        <label for="archivo_provincias">Archivo Excel (.xlsx) <span>*</span></label>
                        <input id="archivo_provincias" name="archivo_provincias" type="file" accept=".xlsx" required>
                        <small>Use la plantilla descargable. Columnas: <strong>Región, Provincia</strong>. Máximo 5 MB.</small>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit">Procesar importación</button><button class="btn btn-light" type="button" onclick="toggleForm('import-provincia')">Cancelar</button></div>
                </form>
            </div>

            <div id="form-provincia" class="inline-form {{ $errors->any() && old('_form') === 'provincia' ? '' : 'hidden' }}">
                <form method="POST" action="{{ route('ambito.provincia.store') }}" class="form-grid">
                    @csrf
                    <input type="hidden" name="_form" value="provincia">
                    <div class="field"><label>Región <span>*</span></label><select name="id_region" required><option value="">Seleccione...</option>@foreach($allRegions as $r)<option value="{{ $r->id_region }}" @selected(old('id_region') == $r->id_region)>{{ $r->nombre }}</option>@endforeach</select></div>
                    <div class="field"><label>Provincia <span>*</span></label><input name="nombre" value="{{ old('nombre') }}" maxlength="150" required></div>
                    <div class="form-actions"><button class="btn btn-primary">Guardar</button><button class="btn btn-light" type="button" onclick="toggleForm('form-provincia')">Cancelar</button></div>
                </form>
            </div>
            <form method="GET" class="filter-bar"><input type="hidden" name="tab" value="provincia"><div class="search-field"><span>⌕</span><input name="q_provincia" value="{{ $qProvincia }}" placeholder="Buscar provincia..."></div><button class="btn btn-light">Buscar</button>@if($qProvincia !== '')<a class="clear-filter" href="{{ route('ambito.index', ['tab'=>'provincia']) }}">Limpiar</a>@endif</form>
            <div class="table-wrap"><table class="data-table"><thead><tr><th style="width:70px">N°</th><th>Provincia</th><th>Región</th><th style="width:120px">Estado</th><th style="width:190px">Acciones</th></tr></thead><tbody>
            @forelse($provincias as $i => $p)
                <tr><td>{{ $provincias->firstItem()+$i }}</td><td class="strong">{{ $p->nombre }}</td><td>{{ $p->region_nombre }}</td><td><span class="status {{ $p->estado ? 'status-active':'status-inactive' }}">{{ $p->estado ? 'Activo':'Inactivo' }}</span></td><td class="actions"><button class="icon-btn" title="Editar" onclick='editProvincia(@json($p))'>✎</button><form method="POST" action="{{ route('ambito.provincia.toggle',$p->id_provincia) }}" onsubmit="return confirm('¿Desea {{ $p->estado ? 'deshabilitar':'habilitar' }} esta provincia?')">@csrf @method('PATCH')<button class="icon-btn">{{ $p->estado ? '◉':'○' }}</button></form></td></tr>
            @empty<tr><td colspan="5" class="empty">No hay provincias registradas.</td></tr>@endforelse
            </tbody></table></div>{{ $provincias->withQueryString()->links('pagination.custom') }}
        </section>
        <div id="edit-provincia" class="modal-backdrop hidden"><div class="modal"><div class="modal-header"><h3>Editar provincia</h3><button onclick="closeModal('edit-provincia')">×</button></div><form id="edit-provincia-form" method="POST">@csrf @method('PUT')<label>Región <span>*</span></label><select id="edit_provincia_region" name="id_region" required>@foreach($allRegions as $r)<option value="{{ $r->id_region }}">{{ $r->nombre }}</option>@endforeach</select><label>Provincia <span>*</span></label><input id="edit_provincia_nombre" name="nombre" maxlength="150" required><div class="form-actions"><button class="btn btn-primary">Guardar cambios</button><button type="button" class="btn btn-light" onclick="closeModal('edit-provincia')">Cancelar</button></div></form></div></div>
    @endif

    @if($tab === 'distrito')
        <section class="module-card">
            <div class="module-card-header">
                <div><h2>Distritos</h2><p>Cada distrito pertenece a una provincia.</p></div>
                <div class="module-actions">
                    <a class="btn btn-light" href="{{ route('ambito.distrito.export') }}">Exportar Excel</a>
                    <a class="btn btn-light" href="{{ route('ambito.distrito.template') }}">Plantilla Excel</a>
                    <button class="btn btn-light" type="button" onclick="toggleForm('import-distrito')">Importar Excel</button>
                    <button class="btn btn-primary" type="button" onclick="toggleForm('form-distrito')">+ Nuevo distrito</button>
                </div>
            </div>

            @if(session('import_error_distrito'))
                <div class="validation-summary"><strong>Error de importación:</strong> {{ session('import_error_distrito') }}</div>
            @endif

            @if(session('import_summary_distrito'))
                @php
                    $summary = session('import_summary_distrito')
                @endphp
                <div class="import-summary">
                    <strong>Resultado de la importación</strong>
                    <div class="import-summary-grid">
                        <span>Procesados <b>{{ $summary['procesados'] }}</b></span>
                        <span>Nuevos <b>{{ $summary['nuevos'] }}</b></span>
                        <span>Duplicados <b>{{ $summary['duplicados'] }}</b></span>
                        <span>Errores <b>{{ $summary['errores'] }}</b></span>
                    </div>
                    @if(session('import_error_file_distrito'))
                        <a class="clear-filter" href="{{ route('ambito.distrito.import.errors', session('import_error_file_distrito')) }}">Descargar Excel de errores</a>
                    @endif
                </div>
            @endif

            <div id="import-distrito" class="inline-form hidden">
                <form method="POST" action="{{ route('ambito.distrito.import') }}" enctype="multipart/form-data" class="form-grid">
                    @csrf
                    <div class="field field-wide">
                        <label for="archivo_distritos">Archivo Excel (.xlsx) <span>*</span></label>
                        <input id="archivo_distritos" name="archivo_distritos" type="file" accept=".xlsx" required>
                        <small>Use la plantilla descargable. Columnas: <strong>Región, Provincia, Distrito</strong>. Máximo 5 MB.</small>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit">Procesar importación</button><button class="btn btn-light" type="button" onclick="toggleForm('import-distrito')">Cancelar</button></div>
                </form>
            </div>

            <div id="form-distrito" class="inline-form {{ $errors->any() && old('_form') === 'distrito' ? '' : 'hidden' }}"><form method="POST" action="{{ route('ambito.distrito.store') }}" class="form-grid">@csrf<input type="hidden" name="_form" value="distrito"><div class="field"><label>Región <span>*</span></label><select id="new_distrito_region" name="_region_ui" required><option value="">Seleccione...</option>@foreach($allRegions as $r)<option value="{{ $r->id_region }}">{{ $r->nombre }}</option>@endforeach</select></div><div class="field"><label>Provincia <span>*</span></label><select id="new_distrito_provincia" name="id_provincia" required disabled><option value="">Seleccione región primero...</option></select></div><div class="field"><label>Distrito <span>*</span></label><input name="nombre" value="{{ old('nombre') }}" maxlength="150" required></div><div class="form-actions"><button class="btn btn-primary">Guardar</button><button class="btn btn-light" type="button" onclick="toggleForm('form-distrito')">Cancelar</button></div></form></div>
            <form method="GET" class="filter-bar"><input type="hidden" name="tab" value="distrito"><div class="search-field"><span>⌕</span><input name="q_distrito" value="{{ $qDistrito }}" placeholder="Buscar distrito..."></div><button class="btn btn-light">Buscar</button>@if($qDistrito !== '')<a class="clear-filter" href="{{ route('ambito.index', ['tab'=>'distrito']) }}">Limpiar</a>@endif</form>
            <div class="table-wrap"><table class="data-table"><thead><tr><th style="width:70px">N°</th><th>Distrito</th><th>Provincia</th><th>Región</th><th style="width:120px">Estado</th><th style="width:190px">Acciones</th></tr></thead><tbody>
            @forelse($distritos as $i => $d)<tr><td>{{ $distritos->firstItem()+$i }}</td><td class="strong">{{ $d->nombre }}</td><td>{{ $d->provincia_nombre }}</td><td>{{ $d->region_nombre }}</td><td><span class="status {{ $d->estado ? 'status-active':'status-inactive' }}">{{ $d->estado ? 'Activo':'Inactivo' }}</span></td><td class="actions"><button class="icon-btn" title="Editar" onclick='editDistrito(@json($d))'>✎</button><form method="POST" action="{{ route('ambito.distrito.toggle',$d->id_distrito) }}" onsubmit="return confirm('¿Desea {{ $d->estado ? 'deshabilitar':'habilitar' }} este distrito?')">@csrf @method('PATCH')<button class="icon-btn">{{ $d->estado ? '◉':'○' }}</button></form></td></tr>
            @empty<tr><td colspan="6" class="empty">No hay distritos registrados.</td></tr>@endforelse
            </tbody></table></div>{{ $distritos->withQueryString()->links('pagination.custom') }}
        </section>
        <div id="edit-distrito" class="modal-backdrop hidden"><div class="modal"><div class="modal-header"><h3>Editar distrito</h3><button onclick="closeModal('edit-distrito')">×</button></div><form id="edit-distrito-form" method="POST">@csrf @method('PUT')<label>Región <span>*</span></label><select id="edit_distrito_region" required><option value="">Seleccione...</option>@foreach($allRegions as $r)<option value="{{ $r->id_region }}">{{ $r->nombre }}</option>@endforeach</select><label>Provincia <span>*</span></label><select id="edit_distrito_provincia" name="id_provincia" required><option value="">Seleccione...</option></select><label>Distrito <span>*</span></label><input id="edit_distrito_nombre" name="nombre" maxlength="150" required><div class="form-actions"><button class="btn btn-primary">Guardar cambios</button><button type="button" class="btn btn-light" onclick="closeModal('edit-distrito')">Cancelar</button></div></form></div></div>
    @endif
</div>

<script>
function toggleForm(id){document.getElementById(id)?.classList.toggle('hidden')}
function closeModal(id){document.getElementById(id)?.classList.add('hidden')}
function editRegion(r){document.getElementById('edit_region_nombre').value=r.nombre;document.getElementById('edit-region-form').action='{{ url('/ambito/region') }}/'+r.id_region;document.getElementById('edit-region').classList.remove('hidden')}
function editProvincia(p){document.getElementById('edit_provincia_nombre').value=p.nombre;document.getElementById('edit_provincia_region').value=p.id_region;document.getElementById('edit-provincia-form').action='{{ url('/ambito/provincia') }}/'+p.id_provincia;document.getElementById('edit-provincia').classList.remove('hidden')}
async function loadProvinces(regionId, selectId, selected=''){
    const select=document.getElementById(selectId); if(!select)return;
    select.innerHTML='<option value="">Cargando...</option>'; select.disabled=true;
    if(!regionId){select.innerHTML='<option value="">Seleccione región primero...</option>';return;}
    try{const res=await fetch('{{ url('/ambito/provincias') }}/'+regionId);const rows=await res.json();select.innerHTML='<option value="">Seleccione...</option>';rows.forEach(p=>{const o=document.createElement('option');o.value=p.id_provincia;o.textContent=p.nombre;if(String(p.id_provincia)===String(selected))o.selected=true;select.appendChild(o)});select.disabled=false}catch(e){select.innerHTML='<option value="">No se pudieron cargar</option>'}
}
document.getElementById('new_distrito_region')?.addEventListener('change',e=>loadProvinces(e.target.value,'new_distrito_provincia'));
async function editDistrito(d){document.getElementById('edit_distrito_nombre').value=d.nombre;document.getElementById('edit_distrito_region').value=d.id_region;await loadProvinces(d.id_region,'edit_distrito_provincia',d.id_provincia);document.getElementById('edit-distrito-form').action='{{ url('/ambito/distrito') }}/'+d.id_distrito;document.getElementById('edit-distrito').classList.remove('hidden')}
document.getElementById('edit_distrito_region')?.addEventListener('change',e=>loadProvinces(e.target.value,'edit_distrito_provincia'));
</script>
@endsection
