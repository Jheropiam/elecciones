<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolesPermisosController extends Controller
{
    private function isAdmin(): bool
    {
        $id = session('id_usuario');
        return $id && DB::table('usuario_rol as ur')
            ->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')
            ->where('ur.id_usuario', $id)
            ->where('ur.estado', 1)
            ->where('r.estado', 1)
            ->whereRaw('LOWER(r.nombre)=LOWER(?)', ['Administrador'])
            ->exists();
    }

    private function ensureAdmin(): void
    {
        abort_unless($this->isAdmin(), 403, 'No tiene permisos para administrar roles y permisos.');
    }

    private function audit(string $accion, ?int $id, $old = null, $new = null): void
    {
        DB::table('auditoria')->insert([
            'id_usuario' => session('id_usuario'),
            'modulo' => 'Roles y Permisos',
            'tabla_afectada' => $id ? 'rol' : 'rol_permiso',
            'id_registro' => $id,
            'accion' => $accion,
            'valor_anterior' => $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'valor_nuevo' => $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    /**
     * Catálogo de módulos/opciones del sistema.
     * Es idempotente para que una instalación que ya tiene algunos módulos
     * pueda completar el catálogo sin duplicar registros.
     */
    private function menuDefinition(): array
    {
        return [
            'Ámbito' => ['Región', 'Provincia', 'Distrito'],
            'Personas' => [],
            'Usuarios' => [],
            'Roles y Permisos' => [],
            'Partidos Políticos' => [],
            'Locales' => [],
            'Mesas' => [],
            'Personeros' => ['Personero Regional', 'Personero Provincial', 'Personero Distrital', 'Personero de Local', 'Personero de Mesa', 'Avance'],
            'Digitar Acta' => ['Digitar', 'Ver Acta'],
            'Reporte' => ['Resultados', 'Seguimiento'],
            'Auditoría y Trazabilidad' => [],
            'Configuración General' => [],
        ];
    }

    private function ensurePermissionCatalog(): void
    {
        $catalog = [
            'Ámbito' => ['GENERAL','Región','Provincia','Distrito'],
            'Personas' => ['GENERAL'],
            'Usuarios' => ['GENERAL'],
            'Roles y Permisos' => ['GENERAL'],
            'Partidos Políticos' => ['GENERAL'],
            'Locales' => ['GENERAL'],
            'Mesas' => ['GENERAL'],
            'Personeros' => ['GENERAL','Personero Regional','Personero Provincial','Personero Distrital','Personero de Local','Personero de Mesa','Avance'],
            'Digitar Acta' => ['GENERAL','Digitar','Ver Acta'],
            'Reporte' => ['GENERAL','Resultados','Seguimiento'],
            'Auditoría y Trazabilidad' => ['GENERAL'],
            'Configuración General' => ['GENERAL'],
        ];

        $actions = ['Ver','Registrar','Editar','Habilitar','Deshabilitar','Importar','Exportar'];

        // El módulo Candidatos fue retirado del sistema. Se desactiva también
        // si todavía existe en una instalación anterior para que no reaparezca.
        $oldCandidate = DB::table('modulo')->whereRaw('LOWER(nombre)=LOWER(?)', ['Candidatos'])->first();
        if ($oldCandidate) {
            DB::table('permiso')->where('id_modulo', $oldCandidate->id_modulo)->update(['estado' => 0]);
            DB::table('opcion_modulo')->where('id_modulo', $oldCandidate->id_modulo)->update(['estado' => 0]);
            DB::table('modulo')->where('id_modulo', $oldCandidate->id_modulo)->update(['estado' => 0, 'updated_at' => now()]);
        }

        foreach ($catalog as $moduleName => $options) {
            $module = DB::table('modulo')->whereRaw('LOWER(nombre)=LOWER(?)', [$moduleName])->first();

            if (!$module) {
                $moduleId = DB::table('modulo')->insertGetId([
                    'nombre' => $moduleName,
                    'estado' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $moduleId = $module->id_modulo;
            }

            foreach ($options as $optionName) {
                $option = DB::table('opcion_modulo')
                    ->where('id_modulo', $moduleId)
                    ->whereRaw('LOWER(nombre)=LOWER(?)', [$optionName])
                    ->first();

                if (!$option) {
                    $optionId = DB::table('opcion_modulo')->insertGetId([
                        'id_modulo' => $moduleId,
                        'nombre' => $optionName,
                        'estado' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $optionId = $option->id_opcion;
                }

                foreach ($actions as $action) {
                    $exists = DB::table('permiso')
                        ->where('id_opcion', $optionId)
                        ->whereRaw('LOWER(accion)=LOWER(?)', [$action])
                        ->exists();

                    if (!$exists) {
                        DB::table('permiso')->insert([
                            'id_modulo' => $moduleId,
                            'id_opcion' => $optionId,
                            'accion' => $action,
                            'nombre' => $action . ' - ' . $moduleName . ' - ' . $optionName,
                            'estado' => 1,
                        ]);
                    }
                }
            }
        }

        // El Administrador debe conservar acceso integral.
        $admin = DB::table('rol')->whereRaw('LOWER(nombre)=LOWER(?)', ['Administrador'])->first();
        if ($admin) {
            $permissionIds = DB::table('permiso')->where('estado', 1)->pluck('id_permiso');
            foreach ($permissionIds as $permissionId) {
                DB::table('rol_permiso')->updateOrInsert(
                    ['id_rol' => $admin->id_rol, 'id_permiso' => $permissionId],
                    ['estado' => 1, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();
        $this->ensurePermissionCatalog();

        $q = trim((string) $request->get('q', ''));
        $estado = $request->has('estado') && $request->estado !== ''
            ? (int) $request->estado
            : null;

        $query = DB::table('rol')->select('rol.*');

        if ($q !== '') {
            $query->where('nombre', 'like', '%' . $q . '%');
        }

        if ($estado !== null) {
            $query->where('estado', $estado);
        }

        $roles = $query->orderByRaw("CASE WHEN LOWER(nombre)='administrador' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->paginate(\App\Support\Pagination::perPage($request, 15))
            ->withQueryString();

        foreach ($roles as $role) {
            $role->usuarios_count = DB::table('usuario_rol')
                ->where('id_rol', $role->id_rol)
                ->where('estado', 1)
                ->count();

            $role->permisos_count = DB::table('rol_permiso as rp')
                ->join('permiso as p', 'p.id_permiso', '=', 'rp.id_permiso')
                ->where('rp.id_rol', $role->id_rol)
                ->where('rp.estado', 1)
                ->where('p.estado', 1)
                ->count();
        }

        $definition = $this->menuDefinition();
        $modules = DB::table('modulo')
            ->where('estado', 1)
            ->whereIn('nombre', array_keys($definition))
            ->orderBy('id_modulo')
            ->get();

        $permissionGroups = [];
        foreach ($modules as $module) {
            $allowedOptions = $definition[$module->nombre] ?? [];
            if (!$allowedOptions) {
                $permissionGroups[] = [
                    'module' => $module,
                    'options' => [],
                    'permissions' => DB::table('permiso')
                        ->where('id_modulo', $module->id_modulo)
                        ->where('estado', 1)
                        ->get(),
                ];
                continue;
            }

            $options = DB::table('opcion_modulo')
                ->where('id_modulo', $module->id_modulo)
                ->whereIn('nombre', $allowedOptions)
                ->where('estado', 1)
                ->orderBy('id_opcion')
                ->get();

            $permissionGroups[] = [
                'module' => $module,
                'options' => $options,
                'permissions' => $options->flatMap(fn($o) => DB::table('permiso')
                    ->where('id_opcion', $o->id_opcion)
                    ->where('estado', 1)
                    ->get())->values(),
            ];
        }

        $selectedRole = null;
        $selectedPermissionIds = [];
        if ($request->filled('permissions_role')) {
            $selectedRole = DB::table('rol')->where('id_rol', (int) $request->permissions_role)->first();
            if ($selectedRole) {
                $selectedPermissionIds = DB::table('rol_permiso as rp')
                    ->join('permiso as p', 'p.id_permiso', '=', 'rp.id_permiso')
                    ->where('rp.id_rol', $selectedRole->id_rol)
                    ->where('rp.estado', 1)
                    ->where('p.estado', 1)
                    ->pluck('rp.id_permiso')
                    ->map(fn($id) => (int) $id)
                    ->all();
            }
        }

        return view('roles_permisos.index', compact(
            'roles',
            'q',
            'estado',
            'permissionGroups',
            'selectedRole',
            'selectedPermissionIds'
        ))
            ->with('sidebarModules', DashboardController::sidebarModules())
            ->with('title', 'Roles y Permisos');
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            '_form' => 'nullable|string',
        ]);

        $name = trim($data['nombre']);

        if (DB::table('rol')->whereRaw('LOWER(nombre)=LOWER(?)', [$name])->exists()) {
            return back()->withInput()->with('error', 'El rol ya existe.')->with('show_role_form', true);
        }

        $id = DB::table('rol')->insertGetId([
            'nombre' => $name,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit('REGISTRAR', $id, null, ['nombre' => $name]);

        return redirect()->route('roles.index')->with('success', 'Rol registrado correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $this->ensureAdmin();

        $role = DB::table('rol')->where('id_rol', $id)->firstOrFail();

        $data = $request->validate([
            'nombre' => 'required|string|max:100',
        ]);

        $name = trim($data['nombre']);

        if (mb_strtolower($role->nombre, 'UTF-8') === 'administrador' &&
            mb_strtolower($name, 'UTF-8') !== 'administrador') {
            return back()->with('error', 'El rol Administrador no puede renombrarse.');
        }

        $duplicate = DB::table('rol')
            ->where('id_rol', '<>', $id)
            ->whereRaw('LOWER(nombre)=LOWER(?)', [$name])
            ->exists();

        if ($duplicate) {
            return back()->withInput()->with('error', 'El rol ya existe.')->with('show_role_form', true);
        }

        DB::table('rol')->where('id_rol', $id)->update([
            'nombre' => $name,
            'updated_at' => now(),
        ]);

        $this->audit('EDITAR', $id, ['nombre' => $role->nombre], ['nombre' => $name]);

        return redirect()->route('roles.index')->with('success', 'Rol actualizado correctamente.');
    }

    public function toggle(int $id)
    {
        $this->ensureAdmin();

        $role = DB::table('rol')->where('id_rol', $id)->firstOrFail();

        if ((int) $role->estado === 1 &&
            mb_strtolower($role->nombre, 'UTF-8') === 'administrador') {
            return back()->with('error', 'El rol Administrador no puede deshabilitarse.');
        }

        if ((int) $role->estado === 1) {
            $activeUsers = DB::table('usuario_rol')->where('id_rol', $id)->where('estado', 1)->count();
            if ($activeUsers > 0) {
                return back()->with('error', 'No se puede deshabilitar el rol porque tiene usuarios asignados. Primero retire el rol de esos usuarios.');
            }
        }

        $new = !(bool) $role->estado;
        DB::table('rol')->where('id_rol', $id)->update([
            'estado' => $new ? 1 : 0,
            'updated_at' => now(),
        ]);

        $this->audit($new ? 'HABILITAR' : 'DESHABILITAR', $id, ['estado' => (int) $role->estado], ['estado' => $new ? 1 : 0]);

        return back()->with('success', $new ? 'Rol habilitado.' : 'Rol deshabilitado.');
    }

    public function permissions(Request $request, int $id)
    {
        $this->ensureAdmin();
        $this->ensurePermissionCatalog();

        $role = DB::table('rol')->where('id_rol', $id)->firstOrFail();

        $permissionIds = DB::table('rol_permiso as rp')
            ->join('permiso as p', 'p.id_permiso', '=', 'rp.id_permiso')
            ->where('rp.id_rol', $id)
            ->where('rp.estado', 1)
            ->where('p.estado', 1)
            ->pluck('rp.id_permiso')
            ->map(fn($v) => (int) $v)
            ->all();

        $definition = $this->menuDefinition();
        $modules = DB::table('modulo')
            ->where('estado', 1)
            ->whereIn('nombre', array_keys($definition))
            ->orderBy('id_modulo')
            ->get();

        $groups = [];
        foreach ($modules as $module) {
            $allowedOptions = $definition[$module->nombre] ?? [];
            if (!$allowedOptions) {
                $groups[] = [
                    'module' => $module,
                    'options' => [],
                    'permissions' => DB::table('permiso')
                        ->where('id_modulo', $module->id_modulo)
                        ->where('estado', 1)
                        ->get(),
                ];
                continue;
            }

            $options = DB::table('opcion_modulo')
                ->where('id_modulo', $module->id_modulo)
                ->whereIn('nombre', $allowedOptions)
                ->where('estado', 1)
                ->orderBy('id_opcion')
                ->get();

            $groups[] = [
                'module' => $module,
                'options' => $options,
                'permissions' => $options->flatMap(fn($o) => DB::table('permiso')
                    ->where('id_opcion', $o->id_opcion)
                    ->where('estado', 1)
                    ->get())->values(),
            ];
        }

        $selectedModuleIds = [];
        $selectedOptionIds = [];

        foreach ($groups as $group) {
            $modulePermissionIds = collect($group['permissions'])->pluck('id_permiso')->map(fn($v)=>(int)$v)->all();
            if (!$group['options']) {
                if ($modulePermissionIds && !array_diff($modulePermissionIds, $permissionIds)) {
                    $selectedModuleIds[] = (int)$group['module']->id_modulo;
                }
            } else {
                $allOptionIds = [];
                foreach ($group['options'] as $option) {
                    $optionPermissionIds = DB::table('permiso')
                        ->where('id_opcion', $option->id_opcion)
                        ->where('estado', 1)
                        ->pluck('id_permiso')->map(fn($v)=>(int)$v)->all();
                    if ($optionPermissionIds && !array_diff($optionPermissionIds, $permissionIds)) {
                        $selectedOptionIds[] = (int)$option->id_opcion;
                        $allOptionIds[] = (int)$option->id_opcion;
                    }
                }
                if ($allOptionIds && count($allOptionIds) === count($group['options'])) {
                    $selectedModuleIds[] = (int)$group['module']->id_modulo;
                }
            }
        }

        return view('roles_permisos.permissions', compact(
            'role', 'permissionIds', 'groups', 'selectedModuleIds', 'selectedOptionIds'
        ))
            ->with('sidebarModules', DashboardController::sidebarModules())
            ->with('title', 'Permisos - ' . $role->nombre);
    }

    public function savePermissions(Request $request, int $id)
    {
        $this->ensureAdmin();
        $role = DB::table('rol')->where('id_rol', $id)->firstOrFail();
        $this->ensurePermissionCatalog();

        $selectedModules = array_values(array_unique(array_map('intval', (array) $request->input('modules', []))));
        $selectedOptions = array_values(array_unique(array_map('intval', (array) $request->input('options', []))));

        $allowedModuleNames = array_keys($this->menuDefinition());
        $validModules = DB::table('modulo')
            ->where('estado', 1)
            ->whereIn('nombre', $allowedModuleNames)
            ->whereIn('id_modulo', $selectedModules)
            ->pluck('id_modulo')
            ->map(fn($v) => (int) $v)
            ->all();

        $validOptions = DB::table('opcion_modulo as o')
            ->join('modulo as m', 'm.id_modulo', '=', 'o.id_modulo')
            ->where('m.estado', 1)
            ->where('o.estado', 1)
            ->whereIn('id_opcion', $selectedOptions)
            ->whereIn('m.nombre', $allowedModuleNames)
            ->pluck('o.id_opcion')
            ->map(fn($v) => (int) $v)
            ->all();

        $permissionIds = DB::table('permiso')
            ->where('estado', 1)
            ->where(function ($q) use ($validModules, $validOptions) {
                if ($validModules) $q->whereIn('id_modulo', $validModules);
                if ($validOptions) {
                    $method = $validModules ? 'orWhereIn' : 'whereIn';
                    $q->{$method}('id_opcion', $validOptions);
                }
            })
            ->pluck('id_permiso')
            ->map(fn($v) => (int) $v)
            ->all();

        $before = DB::table('rol_permiso')->where('id_rol', $id)->where('estado', 1)
            ->pluck('id_permiso')->map(fn($v) => (int)$v)->all();

        DB::transaction(function () use ($id, $permissionIds) {
            DB::table('rol_permiso')->where('id_rol', $id)->update(['estado' => 0, 'updated_at' => now()]);
            foreach ($permissionIds as $permissionId) {
                DB::table('rol_permiso')->updateOrInsert(
                    ['id_rol' => $id, 'id_permiso' => $permissionId],
                    ['estado' => 1, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        });

        $this->audit('ACTUALIZAR_PERMISOS', $id, ['permisos' => $before], [
            'modulos' => $validModules,
            'opciones' => $validOptions,
            'permisos' => $permissionIds,
        ]);

        return redirect()->route('roles.permissions', $id)
            ->with('success', 'Accesos del rol actualizados correctamente.');
    }

}
