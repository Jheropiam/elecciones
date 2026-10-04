<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $defaults = [
            ['nombre_sistema','Sistema de Gestión, Digitación y Resultados Electorales','STRING','Nombre del sistema',1],
            ['institucion','','STRING','Institución propietaria del sistema',1],
            ['periodo_electoral','','STRING','Periodo electoral',1],
            ['zona_horaria','America/Lima','STRING','Zona horaria del sistema',1],
            ['formato_fecha','DD/MM/YYYY','STRING','Formato de presentación de fecha',0],
            ['formato_fecha_hora','DD/MM/YYYY HH:MM:SS','STRING','Formato de presentación de fecha y hora',0],
            ['max_intentos_login','5','INTEGER','Máximo de intentos fallidos antes de bloqueo',1],
            ['minutos_bloqueo_login','15','INTEGER','Duración del bloqueo por intentos fallidos',1],
            ['dias_expiracion_password','0','INTEGER','0 = sin expiración automática',1],
            ['max_tamano_archivo_mb','10','INTEGER','Tamaño máximo permitido para archivos',0],
            ['decimales_resultados','2','INTEGER','Cantidad de decimales en porcentajes/resultados',0],
            ['theme_primary','#f28c28','STRING','Color principal de la interfaz',0],
            ['theme_primary_dark','#e87d15','STRING','Color principal oscuro',0],
            ['theme_topbar','#20242b','STRING','Color de la barra superior',0],
            ['theme_sidebar','#18202d','STRING','Color inicial del menú lateral',0],
            ['theme_sidebar_end','#101722','STRING','Color final del menú lateral',0],
            ['theme_soft','#fff3e8','STRING','Color suave asociado al tema',0],
            ['logo_path','','STRING','Ruta pública del logo del sistema',0],
        ];
        foreach ($defaults as [$clave,$valor,$tipo,$descripcion,$critica]) {
            if (!DB::table('configuracion_sistema')->where('clave',$clave)->exists()) {
                DB::table('configuracion_sistema')->insert([
                    'clave'=>$clave,'valor'=>$valor,'tipo_valor'=>$tipo,'descripcion'=>$descripcion,
                    'es_critica'=>$critica,'estado'=>1,'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        }

        $module = DB::table('modulo')->whereRaw('LOWER(nombre)=LOWER(?)',['Configuración General'])->first();
        $moduleId = $module?->id_modulo;
        if (!$moduleId) {
            $moduleId = DB::table('modulo')->insertGetId(['nombre'=>'Configuración General','estado'=>1,'created_at'=>now(),'updated_at'=>now()]);
        }
        foreach (['GENERAL','Ver','Editar'] as $optionName) {
            $option = DB::table('opcion_modulo')->where('id_modulo',$moduleId)->whereRaw('LOWER(nombre)=LOWER(?)',[$optionName])->first();
            $optionId = $option?->id_opcion;
            if (!$optionId) {
                $optionId = DB::table('opcion_modulo')->insertGetId(['id_modulo'=>$moduleId,'nombre'=>$optionName,'estado'=>1,'created_at'=>now(),'updated_at'=>now()]);
            }
            $actions = $optionName === 'Ver' ? ['Ver'] : ($optionName === 'Editar' ? ['Editar'] : ['Ver','Editar']);
            foreach ($actions as $accion) {
                if (!DB::table('permiso')->where('id_opcion',$optionId)->whereRaw('LOWER(accion)=LOWER(?)',[$accion])->exists()) {
                    DB::table('permiso')->insert([
                        'id_modulo'=>$moduleId,'id_opcion'=>$optionId,'accion'=>$accion,
                        'nombre'=>$accion.' - Configuración General - '.$optionName,'estado'=>1,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $module = DB::table('modulo')->whereRaw('LOWER(nombre)=LOWER(?)',['Configuración General'])->first();
        if ($module) {
            $optionIds = DB::table('opcion_modulo')->where('id_modulo',$module->id_modulo)->pluck('id_opcion');
            $permIds = DB::table('permiso')->whereIn('id_opcion',$optionIds)->pluck('id_permiso');
            DB::table('rol_permiso')->whereIn('id_permiso',$permIds)->delete();
            DB::table('permiso')->whereIn('id_permiso',$permIds)->delete();
            DB::table('opcion_modulo')->whereIn('id_opcion',$optionIds)->delete();
            DB::table('modulo')->where('id_modulo',$module->id_modulo)->delete();
        }
    }
};
