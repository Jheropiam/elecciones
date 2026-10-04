@extends('layouts.app')

@section('content')
<div class="page roles-page">
    <div class="page-heading">
        <div>
            <div class="breadcrumb">Inicio <span>›</span> Roles y Permisos</div>
            <h1>Roles y Permisos</h1>
            <p class="page-subtitle">Configure los roles del sistema y las acciones que cada rol puede realizar.</p>
        </div>
    </div>

    <div class="module-card">
        <div class="module-card-header">
            <div>
                <h2>Roles</h2>
                <p>Solo el Administrador puede gestionar roles y permisos.</p>
            </div>
            <div class="module-actions">
                <button class="btn btn-primary" type="button" onclick="toggleForm('form-rol')">+ Nuevo rol</button>
            </div>
        </div>

        <div id="form-rol" class="inline-form {{ session('show_role_form') || old('_form') === 'rol' ? '' : 'hidden' }}">
            <form method="POST" action="{{ old('id_rol') ? route('roles.update', old('id_rol')) : route('roles.store') }}">
                @csrf
                @if(old('id_rol'))
                    @method('PUT')
                @endif
                <input type="hidden" name="_form" value="rol">
                <input type="hidden" name="id_rol" value="{{ old('id_rol') }}">
                <div class="form-grid">
                    <div class="field field-wide">
                        <label>Nombre del rol <span>*</span></label>
                        <input name="nombre" value="{{ old('nombre') }}" maxlength="100" required autofocus>
                        <small>El nombre debe ser único.</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-light" onclick="location.href='{{ route('roles.index') }}'">Cancelar</button>
                    <button class="btn btn-primary">{{ old('id_rol') ? 'Guardar cambios' : 'Registrar rol' }}</button>
                </div>
            </form>
        </div>

        <div class="module-card" style="margin:18px 0 0;border-radius:0;border-left:0;border-right:0;box-shadow:none">
            <div class="module-card-header">
                <div>
                    <h2>Roles registrados</h2>
                    <p>Los roles determinan las capacidades del usuario mediante sus permisos.</p>
                </div>
            </div>

            <form class="filter-bar" method="GET">
                <div class="search-field">
                    <span>⌕</span>
                    <input name="q" value="{{ $q }}" placeholder="Buscar rol">
                </div>
                <select class="btn btn-light" name="estado">
                    <option value="">Todos</option>
                    <option value="1" @selected($estado === 1)>Activos</option>
                    <option value="0" @selected($estado === 0)>Inactivos</option>
                </select>
                <button class="btn btn-primary">Filtrar</button>
                <a class="clear-filter" href="{{ route('roles.index') }}">Limpiar</a>
            </form>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>N°</th>
                        <th>Rol</th>
                        <th>Usuarios</th>
                        <th>Permisos activos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($roles as $i => $role)
                        <tr>
                            <td>{{ ($roles->currentPage()-1)*$roles->perPage()+$i+1 }}</td>
                            <td class="strong">{{ $role->nombre }}</td>
                            <td>{{ $role->usuarios_count }}</td>
                            <td>{{ $role->permisos_count }}</td>
                            <td>
                                <span class="status {{ $role->estado ? 'status-active' : 'status-inactive' }}">
                                    {{ $role->estado ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="actions">
                                <a class="icon-btn" href="{{ route('roles.permissions', $role->id_rol) }}" title="Configurar permisos">⚙</a>

                                <button class="icon-btn edit-role" type="button"
                                        title="Editar rol"
                                        data-id="{{ $role->id_rol }}"
                                        data-name="{{ $role->nombre }}"
                                        {{ strtolower($role->nombre) === 'administrador' ? '' : '' }}>✎</button>

                                <form method="POST" action="{{ route('roles.toggle', $role->id_rol) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="icon-btn" title="{{ $role->estado ? 'Deshabilitar' : 'Habilitar' }}"
                                            {{ strtolower($role->nombre) === 'administrador' && $role->estado ? 'disabled' : '' }}>
                                        {{ $role->estado ? '◉' : '○' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">No se encontraron roles.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $roles->withQueryString()->links('pagination.custom') }}
        </div>
    </div>
</div>

<script>
function toggleForm(id) {
    document.getElementById(id)?.classList.toggle('hidden');
}

document.querySelectorAll('.edit-role').forEach(button => {
    button.addEventListener('click', () => {
        const form = document.getElementById('form-rol');
        form.classList.remove('hidden');

        const input = form.querySelector('input[name="nombre"]');
        const id = form.querySelector('input[name="id_rol"]');
        const method = form.querySelector('input[name="_method"]');

        input.value = button.dataset.name;
        id.value = button.dataset.id;

        if (!method) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = '_method';
            hidden.value = 'PUT';
            form.querySelector('form').prepend(hidden);
        } else {
            method.value = 'PUT';
        }

        form.querySelector('form').action = '/roles/' + button.dataset.id;
        form.querySelector('button.btn-primary').textContent = 'Guardar cambios';
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
});
</script>
@endsection
