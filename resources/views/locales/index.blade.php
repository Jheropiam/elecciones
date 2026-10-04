@extends('layouts.app')

@section('content')
<style>
.locales-page .page-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:18px}
.locales-page h1{margin:0;font-size:22px}.locales-page .subtitle{color:#64748b;font-size:13px;margin-top:4px}
.actions-top,.actions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}
.module-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px}
.card-head{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:16px}
.card-head h2{margin:0;font-size:16px}.card-head p{margin:3px 0 0;color:#64748b;font-size:12px}
.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #d1d5db;background:#fff;border-radius:7px;padding:8px 11px;font-size:12px;color:#334155;cursor:pointer}
.btn-primary{background:var(--orange);border-color:var(--orange);color:#fff}.btn-primary:hover{background:var(--orange-dark);border-color:var(--orange-dark)}.btn-success{background:var(--orange-dark);border-color:var(--orange-dark);color:#fff}.btn-success:hover{background:var(--orange);border-color:var(--orange)}.btn-light{background:#f8fafc}
.inline-form{border:1px solid #e2e8f0;background:#f8fafc;border-radius:9px;padding:15px;margin-bottom:16px}
.hidden{display:none!important}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.field label{display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:5px}.field label span{color:#dc2626}
.field input,.field select{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:7px;padding:8px 9px;background:#fff;font-size:13px}
.form-actions{display:flex;gap:7px;justify-content:flex-end;margin-top:13px}
.filter-bar{display:flex;gap:8px;flex-wrap:wrap;margin:15px 0}
.search-field{display:flex;align-items:center;border:1px solid #cbd5e1;border-radius:7px;background:#fff;flex:1;min-width:230px;padding:0 9px}.search-field input{border:0;outline:0;padding:8px;width:100%}
.data-table{width:100%;border-collapse:collapse;font-size:12px}.data-table th{background:#f8fafc;text-align:left;color:#475569;font-weight:600}.data-table th,.data-table td{padding:9px;border-bottom:1px solid #e5e7eb;vertical-align:top}
.strong{font-weight:600;color:#1e293b}.status{display:inline-block;padding:3px 7px;border-radius:999px;font-size:11px}.status-active{background:#dcfce7;color:#166534}.status-inactive{background:#fee2e2;color:#991b1b}
.icon-btn{border:1px solid #d1d5db;background:#fff;border-radius:6px;padding:6px 8px;cursor:pointer}.alert{padding:10px 12px;border-radius:8px;margin-bottom:12px;font-size:13px}.alert-success{background:#dcfce7;color:#166534}.alert-error{background:#fee2e2;color:#991b1b}
.import-summary{padding:12px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:9px;margin-bottom:14px}.summary-grid{display:flex;gap:20px;flex-wrap:wrap;margin-top:8px}.help{font-size:11px;color:#64748b;margin-top:4px}
@media(max-width:900px){.grid{grid-template-columns:1fr}.page-head,.card-head{flex-direction:column;align-items:flex-start}}
</style>

<div class="locales-page">
    <div class="page-head">
        <div>
            <h1>Locales</h1>
            <p class="subtitle">Administra los locales electorales asociados a cada distrito.</p>
        </div>
        <div class="actions-top">
            <a class="btn" href="{{ route('locales.template') }}">Plantilla Excel</a>
            <a class="btn" href="{{ route('locales.export', request()->query()) }}">Exportar Excel</a>
            <button class="btn btn-success" type="button" onclick="toggleLocalForm('import-local')">Importar Excel</button>
            <button class="btn btn-primary" type="button" onclick="toggleLocalForm('form-local')">＋ Nuevo</button>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-error"><strong>Revise los datos:</strong>
            <ul style="margin:5px 0 0 18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if(session('import_summary'))
        @php
            $s=session('import_summary')
        @endphp
        <div class="import-summary"><strong>Resultado de importación</strong>
            <div class="summary-grid">
                <span>Procesados: <b>{{ $s['procesados'] }}</b></span>
                <span>Nuevos: <b>{{ $s['nuevos'] }}</b></span>
                <span>Duplicados: <b>{{ $s['duplicados'] }}</b></span>
                <span>Errores: <b>{{ $s['errores'] }}</b></span>
            </div>
            @if(session('import_error_file'))
                <div style="margin-top:8px"><a href="{{ route('locales.import.errors', session('import_error_file')) }}">Descargar Excel de errores</a></div>
            @endif
        </div>
    @endif

    <div id="form-local" class="inline-form hidden">
        <form id="localForm" method="POST" action="{{ route('locales.store') }}">
            @csrf
            <input type="hidden" name="_method" id="localMethod">
            <input type="hidden" name="_form" value="local">
            <div class="grid">
                <div class="field"><label>Región <span>*</span></label>
                    <select id="id_region" name="id_region" required><option value="">Seleccione...</option>
                        @foreach($regions as $r)<option value="{{ $r->id_region }}">{{ $r->nombre }}</option>@endforeach
                    </select>
                </div>
                <div class="field"><label>Provincia <span>*</span></label>
                    <select id="id_provincia" name="id_provincia" required disabled><option value="">Seleccione región...</option></select>
                </div>
                <div class="field"><label>Distrito <span>*</span></label>
                    <select id="id_distrito" name="id_distrito" required disabled><option value="">Seleccione provincia...</option></select>
                </div>
                <div class="field"><label>Nombre del local <span>*</span></label><input id="local_nombre" name="nombre" maxlength="200" required></div>
                <div class="field"><label>Dirección</label><input id="local_direccion" name="direccion" maxlength="255"></div>
                <div class="field"><label>Referencia</label><input id="local_referencia" name="referencia" maxlength="255"></div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-light" onclick="cancelLocalForm()">Cancelar</button>
                <button class="btn btn-primary" id="saveLocal">Registrar local</button>
            </div>
        </form>
    </div>

    <div id="import-local" class="inline-form hidden">
        <form method="POST" action="{{ route('locales.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="grid">
                <div class="field"><label>Archivo Excel (.xlsx) <span>*</span></label><input type="file" name="archivo" accept=".xlsx" required>
                    <div class="help">Columnas: Región, Provincia, Distrito, Local, Dirección, Referencia, Estado. Máximo 5 MB.</div>
                </div>
            </div>
            <div class="form-actions"><button type="button" class="btn btn-light" onclick="toggleLocalForm('import-local')">Cancelar</button><button class="btn btn-success">Procesar importación</button></div>
        </form>
    </div>

    <section class="module-card">
        <div class="card-head">
            <div><h2>Locales registrados</h2><p>Consulta, filtra y administra los locales.</p></div>
        </div>

        <form method="GET" class="filter-bar">
            <div class="search-field">⌕ <input name="q" value="{{ $q }}" placeholder="Buscar local, dirección o referencia..."></div>
            <select class="btn btn-light" id="filter_region" name="id_region">
                <option value="">Todas las regiones</option>
                @foreach($regions as $r)<option value="{{ $r->id_region }}" @selected((string)$idRegion===(string)$r->id_region)>{{ $r->nombre }}</option>@endforeach
            </select>
            <select class="btn btn-light" id="filter_provincia" name="id_provincia">
                <option value="">Todas las provincias</option>
                @foreach($provincias as $p)<option value="{{ $p->id_provincia }}" @selected((string)$idProvincia===(string)$p->id_provincia)>{{ $p->nombre }}</option>@endforeach
            </select>
            <select class="btn btn-light" id="filter_distrito" name="id_distrito">
                <option value="">Todos los distritos</option>
                @foreach($distritos as $d)<option value="{{ $d->id_distrito }}" @selected((string)$idDistrito===(string)$d->id_distrito)>{{ $d->nombre }}</option>@endforeach
            </select>
            <select class="btn btn-light" name="estado">
                <option value="">Todos</option><option value="1" @selected($estado===1)>Activos</option><option value="0" @selected($estado===0)>Inactivos</option>
            </select>
            <button class="btn btn-primary">Filtrar</button>
            <a class="btn" href="{{ route('locales.index') }}">Limpiar</a>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>N°</th><th>Región</th><th>Provincia</th><th>Distrito</th><th>Local</th><th>Dirección</th><th>Referencia</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                @forelse($locales as $i=>$l)
                    <tr>
                        <td>{{ $locales->firstItem()+$i }}</td>
                        <td>{{ $l->region }}</td><td>{{ $l->provincia }}</td><td>{{ $l->distrito }}</td>
                        <td class="strong">{{ $l->nombre }}</td><td>{{ $l->direccion ?: '—' }}</td><td>{{ $l->referencia ?: '—' }}</td>
                        <td><span class="status {{ $l->estado?'status-active':'status-inactive' }}">{{ $l->estado?'Activo':'Inactivo' }}</span></td>
                        <td class="actions">
                            <button type="button" class="icon-btn edit-local" title="Editar" data-local='@json($l)'>✎</button>
                            <form method="POST" action="{{ route('locales.toggle',$l->id_local) }}">
                                @csrf @method('PATCH')
                                <button class="icon-btn" title="{{ $l->estado?'Deshabilitar':'Habilitar' }}">{{ $l->estado?'◉':'○' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align:center;padding:28px;color:#64748b">No se encontraron locales.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $locales->withQueryString()->links('pagination.custom') }}
    </section>
</div>

<script>
function toggleLocalForm(id){document.getElementById(id)?.classList.toggle('hidden');}
function fillSelect(el, items, placeholder){
    el.innerHTML='';
    const o=document.createElement('option');o.value='';o.textContent=placeholder;el.appendChild(o);
    items.forEach(x=>{const op=document.createElement('option');op.value=x.id_region??x.id_provincia??x.id_distrito;op.textContent=x.nombre;el.appendChild(op);});
    el.disabled=false;
}
async function loadProvincias(regionId, selectId='id_provincia', selected=''){
    const sel=document.getElementById(selectId); const dist=document.getElementById(selectId==='id_provincia'?'id_distrito':'filter_distrito');
    if(!regionId){sel.innerHTML='<option value="">Seleccione región...</option>';sel.disabled=true;if(dist){dist.innerHTML='<option value="">Seleccione provincia...</option>';dist.disabled=true;}return;}
    const r=await fetch('/locales/provincias/'+regionId); const data=await r.json(); fillSelect(sel,data,'Seleccione...');
    if(selected){sel.value=String(selected);}
}
async function loadDistritos(provinciaId, selectId='id_distrito', selected=''){
    const sel=document.getElementById(selectId); if(!provinciaId){sel.innerHTML='<option value="">Seleccione provincia...</option>';sel.disabled=true;return;}
    const r=await fetch('/locales/distritos/'+provinciaId); const data=await r.json(); fillSelect(sel,data,'Seleccione...');
    if(selected){sel.value=String(selected);}
}
document.getElementById('id_region')?.addEventListener('change',async e=>{
    const p=document.getElementById('id_provincia'),d=document.getElementById('id_distrito');
    p.innerHTML='<option value="">Cargando...</option>';p.disabled=true;d.innerHTML='<option value="">Seleccione provincia...</option>';d.disabled=true;
    await loadProvincias(e.target.value);
});
document.getElementById('id_provincia')?.addEventListener('change',e=>loadDistritos(e.target.value));

document.getElementById('filter_region')?.addEventListener('change',async e=>{
    const p=document.getElementById('filter_provincia'),d=document.getElementById('filter_distrito');
    p.innerHTML='<option value="">Todas las provincias</option>';d.innerHTML='<option value="">Todos los distritos</option>';
    p.disabled=true;d.disabled=true;
    if(!e.target.value){p.disabled=false;d.disabled=false;return;}
    const r=await fetch('/locales/provincias/'+e.target.value); const data=await r.json();
    data.forEach(x=>{const o=document.createElement('option');o.value=x.id_provincia;o.textContent=x.nombre;p.appendChild(o);});p.disabled=false;
});
document.getElementById('filter_provincia')?.addEventListener('change',async e=>{
    const d=document.getElementById('filter_distrito');d.innerHTML='<option value="">Todos los distritos</option>';d.disabled=true;
    if(!e.target.value){d.disabled=false;return;}
    const r=await fetch('/locales/distritos/'+e.target.value); const data=await r.json();
    data.forEach(x=>{const o=document.createElement('option');o.value=x.id_distrito;o.textContent=x.nombre;d.appendChild(o);});d.disabled=false;
});

function cancelLocalForm(){
    document.getElementById('form-local').classList.add('hidden');
    const f=document.getElementById('localForm');f.reset();document.getElementById('localMethod').value='';
    f.action='{{ route('locales.store') }}';document.getElementById('saveLocal').textContent='Registrar local';
    document.getElementById('id_provincia').innerHTML='<option value="">Seleccione región...</option>';document.getElementById('id_provincia').disabled=true;
    document.getElementById('id_distrito').innerHTML='<option value="">Seleccione provincia...</option>';document.getElementById('id_distrito').disabled=true;
}
document.querySelectorAll('.edit-local').forEach(btn=>btn.addEventListener('click',async()=>{
    const l=JSON.parse(btn.dataset.local);
    document.getElementById('form-local').classList.remove('hidden');
    document.getElementById('id_region').value=l.id_region;
    await loadProvincias(l.id_region,'id_provincia',l.id_provincia);
    await loadDistritos(l.id_provincia,'id_distrito',l.id_distrito);
    document.getElementById('local_nombre').value=l.nombre||'';
    document.getElementById('local_direccion').value=l.direccion||'';
    document.getElementById('local_referencia').value=l.referencia||'';
    document.getElementById('localMethod').value='PUT';
    document.getElementById('localForm').action='/locales/'+l.id_local;
    document.getElementById('saveLocal').textContent='Guardar cambios';
    window.scrollTo({top:0,behavior:'smooth'});
}));
</script>
@endsection
