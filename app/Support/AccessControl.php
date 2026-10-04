<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccessControl
{
    public static function isAdmin(?int $userId = null): bool
    {
        $userId ??= session('id_usuario');
        return (bool) ($userId && DB::table('usuario_rol as ur')
            ->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')
            ->where('ur.id_usuario', $userId)
            ->where('ur.estado', 1)
            ->where('r.estado', 1)
            ->whereRaw('LOWER(r.nombre)=LOWER(?)', ['Administrador'])
            ->exists());
    }

    public static function canModule(string $module, ?int $userId = null): bool
    {
        if (self::isAdmin($userId)) return true;

        $userId ??= session('id_usuario');
        if (!$userId) return false;

        return DB::table('rol_permiso as rp')
            ->join('usuario_rol as ur', function ($j) {
                $j->on('ur.id_rol', '=', 'rp.id_rol')->where('ur.estado', 1);
            })
            ->join('permiso as p', 'p.id_permiso', '=', 'rp.id_permiso')
            ->join('modulo as m', 'm.id_modulo', '=', 'p.id_modulo')
            ->where('ur.id_usuario', $userId)
            ->where('rp.estado', 1)
            ->where('p.estado', 1)
            ->where('m.estado', 1)
            ->whereRaw('LOWER(m.nombre)=LOWER(?)', [$module])
            ->exists();
    }

    public static function canOption(string $module, string $option, ?int $userId = null): bool
    {
        if (self::isAdmin($userId)) return true;

        $userId ??= session('id_usuario');
        if (!$userId) return false;

        $direct = DB::table('rol_permiso as rp')
            ->join('usuario_rol as ur', function ($j) {
                $j->on('ur.id_rol', '=', 'rp.id_rol')->where('ur.estado', 1);
            })
            ->join('permiso as p', 'p.id_permiso', '=', 'rp.id_permiso')
            ->join('modulo as m', 'm.id_modulo', '=', 'p.id_modulo')
            ->join('opcion_modulo as o', 'o.id_opcion', '=', 'p.id_opcion')
            ->where('ur.id_usuario', $userId)
            ->where('rp.estado', 1)
            ->where('p.estado', 1)
            ->where('m.estado', 1)
            ->where('o.estado', 1)
            ->whereRaw('LOWER(m.nombre)=LOWER(?)', [$module])
            ->whereRaw('LOWER(o.nombre)=LOWER(?)', [$option])
            ->exists();

        if ($direct) return true;

        // Marcar el módulo completo (GENERAL) también habilita sus submódulos.
        return DB::table('rol_permiso as rp')
            ->join('usuario_rol as ur', function ($j) {
                $j->on('ur.id_rol', '=', 'rp.id_rol')->where('ur.estado', 1);
            })
            ->join('permiso as p', 'p.id_permiso', '=', 'rp.id_permiso')
            ->join('modulo as m', 'm.id_modulo', '=', 'p.id_modulo')
            ->join('opcion_modulo as o', 'o.id_opcion', '=', 'p.id_opcion')
            ->where('ur.id_usuario', $userId)
            ->where('rp.estado', 1)
            ->where('p.estado', 1)
            ->where('m.estado', 1)
            ->where('o.estado', 1)
            ->whereRaw('LOWER(m.nombre)=LOWER(?)', [$module])
            ->whereRaw('LOWER(o.nombre)=LOWER(?)', ['GENERAL'])
            ->exists();
    }

    /**
     * Authorize the current request using the module/submodule selections.
     * Administrators bypass this check. Candidate management is intentionally
     * disabled because the Candidatos module was removed from the system UI.
     */
    public static function authorizeRequest(Request $request): void
    {
        if (self::isAdmin()) {
            // Even administrators no longer have the removed Candidatos module.
            if ($request->routeIs('candidatos.*')) {
                abort(404);
            }
            return;
        }

        $route = $request->route()?->getName();
        if (!$route) return;

        $module = null;
        $option = null;

        if (str_starts_with($route, 'candidatos.')) {
            abort(404);
        } elseif (str_starts_with($route, 'ambito.')) {
            $module = 'Ámbito';
            if ($route === 'ambito.index') {
                $tab = (string) $request->query('tab', 'region');
                $option = match ($tab) {
                    'provincia' => 'Provincia',
                    'distrito' => 'Distrito',
                    default => 'Región',
                };
            } elseif (str_contains($route, 'provincia')) {
                $option = 'Provincia';
            } elseif (str_contains($route, 'distrito')) {
                $option = 'Distrito';
            } elseif (str_contains($route, 'region')) {
                $option = 'Región';
            }
        } elseif (str_starts_with($route, 'personeros.')) {
            $module = 'Personeros';
            $tipo = strtolower((string) $request->query('tipo', 'regional'));
            $option = match ($tipo) {
                'provincia' => 'Personero Provincial',
                'distrito' => 'Personero Distrital',
                'local' => 'Personero de Local',
                'mesa' => 'Personero de Mesa',
                'avance' => 'Avance',
                default => 'Personero Regional',
            };
        } elseif (str_starts_with($route, 'digitar-acta.')) {
            $module = 'Digitar Acta';
            $option = (str_starts_with($route, 'digitar-acta.registradas')
                    || str_starts_with($route, 'digitar-acta.editar')
                    || str_starts_with($route, 'digitar-acta.actualizar'))
                ? 'Ver Acta'
                : 'Digitar';
        } elseif (str_starts_with($route, 'reporte.')) {
            $module = 'Reporte';
            $option = $route === 'reporte.seguimiento' ? 'Seguimiento' : 'Resultados';
        } elseif (str_starts_with($route, 'roles.')) {
            $module = 'Roles y Permisos';
        } elseif (str_starts_with($route, 'usuarios.')) {
            $module = 'Usuarios';
        } elseif (str_starts_with($route, 'personas.')) {
            $module = 'Personas';
        } elseif (str_starts_with($route, 'partidos.')) {
            $module = 'Partidos Políticos';
        } elseif (str_starts_with($route, 'locales.')) {
            $module = 'Locales';
        } elseif (str_starts_with($route, 'mesas.')) {
            $module = 'Mesas';
        } elseif (str_starts_with($route, 'auditoria.')) {
            $module = 'Auditoría y Trazabilidad';
        } elseif (str_starts_with($route, 'configuracion.')) {
            $module = 'Configuración General';
        }

        if (!$module) return;

        if ($option !== null) {
            if (!self::canOption($module, $option)) abort(403, 'No tiene acceso a esta opción.');
        } elseif (!self::canModule($module)) {
            abort(403, 'No tiene acceso a este módulo.');
        }
    }

    public static function menuAccess(): array
    {
        if (self::isAdmin()) return ['modules' => null, 'options' => null];

        $userId = session('id_usuario');
        $rows = DB::table('rol_permiso as rp')
            ->join('usuario_rol as ur', function ($j) {
                $j->on('ur.id_rol', '=', 'rp.id_rol')->where('ur.estado', 1);
            })
            ->join('permiso as p', 'p.id_permiso', '=', 'rp.id_permiso')
            ->join('modulo as m', 'm.id_modulo', '=', 'p.id_modulo')
            ->join('opcion_modulo as o', 'o.id_opcion', '=', 'p.id_opcion')
            ->where('ur.id_usuario', $userId)
            ->where('rp.estado', 1)
            ->where('p.estado', 1)
            ->where('m.estado', 1)
            ->where('o.estado', 1)
            ->select('m.nombre as module','o.nombre as option')
            ->distinct()
            ->get();

        return [
            'modules' => $rows->pluck('module')->map(fn($v) => mb_strtolower($v,'UTF-8'))->unique()->values()->all(),
            'options' => $rows->map(fn($r) => mb_strtolower($r->module,'UTF-8').'|'.mb_strtolower($r->option,'UTF-8'))->unique()->values()->all(),
        ];
    }
}
