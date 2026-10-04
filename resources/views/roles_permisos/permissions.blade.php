@extends('layouts.app')

@section('content')
<div class="page roles-page">
    <div class="page-heading">
        <div>
            <div class="breadcrumb">
                Inicio <span>›</span>
                <a href="{{ route('roles.index') }}" style="color:inherit;text-decoration:none">Roles y Permisos</a>
                <span>›</span> Permisos
            </div>
            <h1>Accesos del rol</h1>
            <p class="page-subtitle">Seleccione qué módulos y submódulos aparecerán para <strong>{{ $role->nombre }}</strong>.</p>
        </div>
        <a class="btn btn-light" href="{{ route('roles.index') }}">← Volver a roles</a>
    </div>

    <form method="POST" action="{{ route('roles.permissions.save', $role->id_rol) }}" id="accessForm">
        @csrf

        <div class="module-card">
            <div class="module-card-header">
                <div>
                    <h2>{{ $role->nombre }}</h2>
                    <p>Marque un módulo para dar acceso completo. Si tiene submódulos, puede seleccionar solamente los que necesite.</p>
                </div>
                <div class="module-actions">
                    <button type="button" class="btn btn-light" id="selectAll">Seleccionar todo</button>
                    <button type="button" class="btn btn-light" id="clearAll">Limpiar todo</button>
                    <button type="submit" class="btn btn-primary">Guardar accesos</button>
                </div>
            </div>

            @if($role->estado == 0)
                <div class="alert warning" style="margin:15px 22px">Este rol está inactivo. Los accesos se conservan, pero el rol no podrá asignarse como rol activo.</div>
            @endif

            <div class="access-grid">
                @foreach($groups as $group)
                    @php
                        $moduleId = (int)$group['module']->id_modulo;
                        $moduleChecked = in_array($moduleId, $selectedModuleIds, true);
                    @endphp
                    <section class="access-card" data-module="{{ $moduleId }}">
                        <div class="access-card-header">
                            <label class="access-module">
                                <input type="checkbox"
                                       class="module-check"
                                       name="modules[]"
                                       value="{{ $moduleId }}"
                                       data-module="{{ $moduleId }}"
                                       @checked($moduleChecked)>
                                <span class="check-ui"></span>
                                <span class="access-module-name">{{ $group['module']->nombre }}</span>
                            </label>

                            @if($group['options'])
                                <button type="button" class="btn btn-light btn-select-children" data-module="{{ $moduleId }}">
                                    Seleccionar submódulos
                                </button>
                            @endif
                        </div>

                        @if($group['options'])
                            <div class="access-children">
                                @foreach($group['options'] as $option)
                                    <label class="access-child">
                                        <input type="checkbox"
                                               class="option-check"
                                               name="options[]"
                                               value="{{ $option->id_opcion }}"
                                               data-module="{{ $moduleId }}"
                                               @checked(in_array((int)$option->id_opcion, $selectedOptionIds, true))>
                                        <span class="check-ui"></span>
                                        <span>{{ $option->nombre === 'Ver Acta' ? 'Ver Actas Registradas' : $option->nombre }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="access-simple-note">Acceso al módulo completo</div>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="access-help">
                <strong>¿Cómo funciona?</strong>
                <ul>
                    <li><b>Módulo marcado:</b> el rol tendrá acceso a todo ese módulo.</li>
                    <li><b>Submódulo marcado:</b> el rol verá únicamente ese submódulo dentro del módulo.</li>
                    <li>Si marca todos los submódulos, el módulo queda automáticamente como acceso completo.</li>
                    <li>Las opciones seleccionadas se aplican al menú y también se verifican en el servidor.</li>
                </ul>
            </div>

            <div class="form-actions" style="padding:0 22px 20px">
                <a class="btn btn-light" href="{{ route('roles.index') }}">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar accesos</button>
            </div>
        </div>
    </form>
</div>

<script>
(() => {
    const moduleChecks = [...document.querySelectorAll('.module-check')];
    const optionChecks = [...document.querySelectorAll('.option-check')];

    const syncModule = (moduleId) => {
        const module = document.querySelector(`.module-check[data-module="${moduleId}"]`);
        const options = [...document.querySelectorAll(`.option-check[data-module="${moduleId}"]`)];
        if (!module || !options.length) return;
        const all = options.every(x => x.checked);
        module.checked = all;
        module.indeterminate = options.some(x => x.checked) && !all;
    };

    moduleChecks.forEach(module => {
        module.addEventListener('change', () => {
            const options = document.querySelectorAll(`.option-check[data-module="${module.dataset.module}"]`);
            options.forEach(x => x.checked = module.checked);
            module.indeterminate = false;
        });
    });

    optionChecks.forEach(option => {
        option.addEventListener('change', () => syncModule(option.dataset.module));
    });

    document.querySelectorAll('.btn-select-children').forEach(button => {
        button.addEventListener('click', () => {
            const options = [...document.querySelectorAll(`.option-check[data-module="${button.dataset.module}"]`)];
            const all = options.length && options.every(x => x.checked);
            options.forEach(x => x.checked = !all);
            syncModule(button.dataset.module);
        });
    });

    document.getElementById('selectAll')?.addEventListener('click', () => {
        moduleChecks.forEach(x => { x.checked = true; x.indeterminate = false; });
        optionChecks.forEach(x => x.checked = true);
    });

    document.getElementById('clearAll')?.addEventListener('click', () => {
        moduleChecks.forEach(x => { x.checked = false; x.indeterminate = false; });
        optionChecks.forEach(x => x.checked = false);
    });

    optionChecks.forEach(x => syncModule(x.dataset.module));
})();
</script>
@endsection
