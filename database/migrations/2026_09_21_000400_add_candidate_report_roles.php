<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Los roles de candidato son roles de acceso al Reporte, no un módulo
        // Candidatos. El módulo/tablas de candidatos permanecen eliminados.
        foreach (['Candidato Regional', 'Candidato Provincial', 'Candidato Distrital'] as $nombre) {
            $role = DB::table('rol')->whereRaw('LOWER(nombre)=LOWER(?)', [$nombre])->first();
            if (!$role) {
                DB::table('rol')->insert([
                    'nombre' => $nombre,
                    'estado' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('rol')->where('id_rol', $role->id_rol)->update([
                    'estado' => 1,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('rol')
            ->whereIn('nombre', ['Candidato Regional', 'Candidato Provincial', 'Candidato Distrital'])
            ->update(['estado' => 0, 'updated_at' => now()]);
    }
};
