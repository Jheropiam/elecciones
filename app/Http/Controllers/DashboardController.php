<?php
namespace App\Http\Controllers;

use App\Support\SystemConfig;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public static function sidebarModules(): array
    {
        return [
            ['slug'=>'ambito','name'=>'Ámbito','icon'=>'<svg viewBox="0 0 24 24"><path d="M12 2 4 6v12l8 4 8-4V6l-8-4Zm0 2.2 5.8 2.9L12 10 6.2 7.1 12 4.2ZM6 9l5 2.5v7.3l-5-2.5V9Zm7 9.8v-7.3L18 9v7.3l-5 2.5Z"/></svg>'],
            ['slug'=>'personas','name'=>'Personas','icon'=>'<svg viewBox="0 0 24 24"><path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6.5 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM9 13c-4.42 0-8 2.24-8 5v2h16v-2c0-2.76-3.58-5-8-5Zm6.5 1c-.8 0-1.56.1-2.26.29 1.12.76 1.91 1.64 2.27 2.71h6.49v-1c0-1.1-2.92-2-6.5-2Z"/></svg>'],
            ['slug'=>'usuarios','name'=>'Usuarios','icon'=>'<svg viewBox="0 0 24 24"><path d="M12 12a4.2 4.2 0 1 0 0-8.4 4.2 4.2 0 0 0 0 8.4Zm0 2c-4.7 0-8.5 2.5-8.5 5.6V21h17v-1.4c0-3.1-3.8-5.6-8.5-5.6Z"/></svg>'],
            ['slug'=>'roles-permisos','name'=>'Roles y Permisos','icon'=>'<svg viewBox="0 0 24 24"><path d="M12 2 4 5v6c0 5 3.4 9.5 8 11 4.6-1.5 8-6 8-11V5l-8-3Zm0 3.1 5 1.9v4c0 3.5-2.1 6.8-5 8-2.9-1.2-5-4.5-5-8V7l5-1.9Zm-1 3.2v4.1l-2.2 2.2 1.4 1.4 2.2-2.2 3.6 3.6 1.4-1.4-3.6-3.6V8.3h-2.8Z"/></svg>'],
            ['slug'=>'partidos','name'=>'Partidos Políticos','icon'=>'<svg viewBox="0 0 24 24"><path d="M12 2a5 5 0 0 1 5 5v2h2a3 3 0 1 1 0 6h-2v2a5 5 0 0 1-10 0v-2H5a3 3 0 1 1 0-6h2V7a5 5 0 0 1 5-5Zm-3 7h6V7a3 3 0 1 0-6 0v2Zm0 6v2a3 3 0 1 0 6 0v-2H9Z"/></svg>'],
            ['slug'=>'locales','name'=>'Locales','icon'=>'<svg viewBox="0 0 24 24"><path d="M4 21V5h16v16h-2V7H6v14H4Zm4 0v-6h8v6H8Zm1-12h2v2H9V9Zm4 0h2v2h-2V9Zm-4 4h2v2H9v-2Zm4 0h2v2h-2v-2Z"/></svg>'],
            ['slug'=>'mesas','name'=>'Mesas','icon'=>'<svg viewBox="0 0 24 24"><path d="M3 5h18v2H3V5Zm2 4h14v2H5V9Zm-2 4h18v2H3v-2Zm2 4h14v2H5v-2Z"/></svg>'],
            ['slug'=>'personeros','name'=>'Personeros','icon'=>'<svg viewBox="0 0 24 24"><path d="M6 2h12v20H6V2Zm2 2v16h8V4H8Zm2 2h4v2h-4V6Zm0 4h4v2h-4v-2Zm0 4h4v2h-4v-2Z"/></svg>'],
            ['slug'=>'digitar-acta','name'=>'Digitación de Actas','icon'=>'<svg viewBox="0 0 24 24"><path d="m14.7 5.3 4 4L8.5 19.5 4 20l.5-4.5L14.7 5.3Zm1.4-1.4 1.1-1.1a2 2 0 0 1 2.8 0l1.2 1.2a2 2 0 0 1 0 2.8l-1.1 1.1-4-4ZM6 2h8v2H6v16h12v-6h2v8H4V2h2Z"/></svg>'],
            ['slug'=>'reporte','name'=>'Reporte','icon'=>'<svg viewBox="0 0 24 24"><path d="M4 19h16v2H4V19Zm1-2V9h3v8H5Zm5 0V4h3v13h-3Zm5 0v-6h3v6h-3Z"/></svg>'],
            ['slug'=>'auditoria','name'=>'Auditoría y Trazabilidad','icon'=>'<svg viewBox="0 0 24 24"><path d="M10 3a7 7 0 0 0 0 14c1.6 0 3.1-.54 4.3-1.45L19 20.25 20.25 19l-4.7-4.7A7 7 0 0 0 10 3Zm0 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10Z"/></svg>'],
            ['slug'=>'configuracion','name'=>'Configuración General','icon'=>'<svg viewBox="0 0 24 24"><path d="M19.4 13a7.9 7.9 0 0 0 0-2l2-1.55-2-3.46-2.35.95a8 8 0 0 0-1.73-1L15 3.5h-4l-.32 2.44a8 8 0 0 0-1.73 1L6.6 5.99l-2 3.46L6.6 11a7.9 7.9 0 0 0 0 2l-2 1.55 2 3.46 2.35-.95a8 8 0 0 0 1.73 1L11 20.5h4l.32-2.44a8 8 0 0 0 1.73-1l2.35.95 2-3.46-2-1.55ZM13 15.5A3.5 3.5 0 1 1 13 8a3.5 3.5 0 0 1 0 7.5Z"/></svg>'],
        ];
    }

    public function index()
    {
        $id = session('id_usuario');

        $user = DB::table('usuario as u')
            ->join('persona as p', 'p.id_persona', '=', 'u.id_persona')
            ->where('u.id_usuario', $id)
            ->first();

        $roles = DB::table('usuario_rol as ur')
            ->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')
            ->where('ur.id_usuario', $id)
            ->where('ur.estado', 1)
            ->pluck('r.nombre');

        $modules = self::sidebarModules();
        if (!\App\Support\AccessControl::isAdmin($id)) {
            $modules = array_values(array_filter($modules, fn($m) => \App\Support\AccessControl::canModule($m['name'], $id)));
        }
        $config = SystemConfig::all();

        return view('dashboard.index', compact('user', 'roles', 'modules', 'config'))
            ->with('sidebarModules', $modules)
            ->with('title', 'Panel principal');
    }
}
