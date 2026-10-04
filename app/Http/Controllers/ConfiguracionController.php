<?php
namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\ImageWebp;
use App\Support\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ConfiguracionController extends Controller
{
    private function ensureAdmin(): void
    {
        $id = session('id_usuario');
        $isAdmin = $id && DB::table('usuario_rol as ur')
            ->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')
            ->where('ur.id_usuario', $id)
            ->where('ur.estado', 1)
            ->where('r.estado', 1)
            ->whereRaw('LOWER(r.nombre)=LOWER(?)', ['Administrador'])
            ->exists();
        abort_unless($isAdmin, 403, 'No tiene permisos para administrar la configuración general.');
    }

    public function index()
    {
        $this->ensureAdmin();
        $config = SystemConfig::all();
        return view('configuracion.index', compact('config'))
            ->with('sidebarModules', DashboardController::sidebarModules())
            ->with('title', 'Configuración General');
    }

    public function update(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'nombre_sistema' => ['required','string','max:150'],
            'institucion' => ['nullable','string','max:200'],
            'periodo_electoral' => ['nullable','string','max:100'],
            'zona_horaria' => ['required','timezone'],
            'formato_fecha' => ['required','string','max:30'],
            'formato_fecha_hora' => ['required','string','max:40'],
            'max_intentos_login' => ['required','integer','min:1','max:20'],
            'minutos_bloqueo_login' => ['required','integer','min:1','max:1440'],
            'dias_expiracion_password' => ['required','integer','min:0','max:3650'],
            'max_tamano_archivo_mb' => ['required','integer','min:1','max:1000'],
            'decimales_resultados' => ['required','integer','min:0','max:6'],
            'theme_primary' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_primary_dark' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_topbar' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_sidebar' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_sidebar_end' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_soft' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
        ]);

        $keys = [
            'nombre_sistema'=>'STRING','institucion'=>'STRING','periodo_electoral'=>'STRING',
            'zona_horaria'=>'STRING','formato_fecha'=>'STRING','formato_fecha_hora'=>'STRING',
            'max_intentos_login'=>'INTEGER','minutos_bloqueo_login'=>'INTEGER',
            'dias_expiracion_password'=>'INTEGER','max_tamano_archivo_mb'=>'INTEGER','decimales_resultados'=>'INTEGER',
            'theme_primary'=>'STRING','theme_primary_dark'=>'STRING','theme_topbar'=>'STRING',
            'theme_sidebar'=>'STRING','theme_sidebar_end'=>'STRING','theme_soft'=>'STRING',
        ];

        $before = SystemConfig::all();
        foreach ($keys as $key => $type) {
            SystemConfig::put($key, $data[$key], $type);
        }

        if ($request->hasFile('logo')) {
            $old = SystemConfig::get('logo_path');
            if ($old && preg_match('#^uploads/configuracion/[A-Za-z0-9._-]+$#', $old)) {
                $oldPath = public_path($old);
                if (is_file($oldPath)) @unlink($oldPath);
            }
            $dir = public_path('uploads/configuracion');
            $filename = ImageWebp::fromUploadedFile($request->file('logo'), $dir, 'logo_sistema');
            SystemConfig::put('logo_path', 'uploads/configuracion/'.$filename, 'STRING');
        }

        AuditLogger::log('Configuración General','configuracion_sistema',null,'EDITAR',$before,SystemConfig::all());
        return redirect()->route('configuracion.index')->with('success','Configuración general actualizada correctamente.');
    }


    /**
     * Elimina todas las actas digitadas y reinicia su AUTO_INCREMENT.
     * Solo está disponible para Administrador.
     */
    public function puestaEnCero(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'confirmacion' => ['required', 'in:PUESTA EN CERO'],
        ], [
            'confirmacion.in' => 'Debe escribir exactamente PUESTA EN CERO para confirmar.',
        ]);

        if (!Schema::hasTable('digitacion_acta')) {
            return redirect()->route('configuracion.index')
                ->with('error', 'No fue posible realizar la puesta en cero: la tabla digitacion_acta no existe.');
        }

        // No permitimos TRUNCATE si alguna tabla externa depende de digitacion_acta.
        // Así evitamos romper relaciones existentes.
        try {
            $referencias = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                ->where('REFERENCED_TABLE_NAME', 'digitacion_acta')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->count();

            if ($referencias > 0) {
                return redirect()->route('configuracion.index')
                    ->with('error', 'No fue posible realizar la puesta en cero porque existen relaciones que dependen de digitacion_acta.');
            }

            $stats = [
                'filas' => DB::table('digitacion_acta')->count(),
                'actas' => (int) DB::table('digitacion_acta')
                    ->selectRaw("COUNT(DISTINCT CONCAT(id_mesa, ':', tipo_acta)) AS total")
                    ->value('total'),
            ];

            // TRUNCATE elimina los registros y reinicia el AUTO_INCREMENT a 1
            // en MySQL/InnoDB. Los datos maestros permanecen intactos.
            DB::statement('TRUNCATE TABLE `digitacion_acta`');

            AuditLogger::log(
                'Configuración General',
                'digitacion_acta',
                null,
                'PUESTA_EN_CERO',
                [
                    'filas_digitacion' => $stats['filas'],
                    'actas' => $stats['actas'],
                ],
                [
                    'filas_digitacion' => 0,
                    'actas' => 0,
                    'auto_increment' => 1,
                ]
            );

            return redirect()->route('configuracion.index')
                ->with('success', 'Puesta en cero completada correctamente. Las actas digitadas fueron eliminadas y el correlativo vuelve a iniciar en 1.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('configuracion.index')
                ->with('error', 'No fue posible completar la puesta en cero. No se realizaron cambios parciales.');
        }
    }

    public function removeLogo()
    {
        $this->ensureAdmin();
        $old = SystemConfig::get('logo_path');
        if ($old && preg_match('#^uploads/configuracion/[A-Za-z0-9._-]+$#', $old)) {
            $path = public_path($old);
            if (is_file($path)) @unlink($path);
        }
        SystemConfig::put('logo_path', null, 'STRING');
        AuditLogger::log('Configuración General','configuracion_sistema',null,'ELIMINAR_LOGO',['logo_path'=>$old],['logo_path'=>null]);
        return redirect()->route('configuracion.index')->with('success','Logo eliminado correctamente.');
    }
}
