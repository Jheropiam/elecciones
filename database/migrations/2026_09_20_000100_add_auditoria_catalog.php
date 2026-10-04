<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $module = DB::table('modulo')->whereRaw('LOWER(nombre)=LOWER(?)', ['Auditoría y Trazabilidad'])->first();
        $moduleId = $module?->id_modulo;
        if (!$moduleId) {
            $moduleId = DB::table('modulo')->insertGetId([
                'nombre' => 'Auditoría y Trazabilidad', 'estado' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (['GENERAL','Ver','Exportar Excel'] as $optionName) {
            $option = DB::table('opcion_modulo')->where('id_modulo',$moduleId)->whereRaw('LOWER(nombre)=LOWER(?)',[$optionName])->first();
            $optionId = $option?->id_opcion;
            if (!$optionId) {
                $optionId = DB::table('opcion_modulo')->insertGetId([
                    'id_modulo'=>$moduleId,'nombre'=>$optionName,'estado'=>1,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            foreach (['Ver','Exportar'] as $accion) {
                if (!DB::table('permiso')->where('id_opcion',$optionId)->whereRaw('LOWER(accion)=LOWER(?)',[$accion])->exists()) {
                    DB::table('permiso')->insert([
                        'id_modulo'=>$moduleId,'id_opcion'=>$optionId,
                        'accion'=>$accion,
                        'nombre'=>$accion.' - Auditoría y Trazabilidad - '.$optionName,
                        'estado'=>1,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $module = DB::table('modulo')->whereRaw('LOWER(nombre)=LOWER(?)', ['Auditoría y Trazabilidad'])->first();
        if (!$module) return;
        $optionIds = DB::table('opcion_modulo')->where('id_modulo',$module->id_modulo)->pluck('id_opcion');
        DB::table('rol_permiso')->whereIn('id_permiso', DB::table('permiso')->whereIn('id_opcion',$optionIds)->pluck('id_permiso'))->delete();
        DB::table('permiso')->whereIn('id_opcion',$optionIds)->delete();
        DB::table('opcion_modulo')->where('id_modulo',$module->id_modulo)->delete();
        DB::table('modulo')->where('id_modulo',$module->id_modulo)->delete();
    }
};
