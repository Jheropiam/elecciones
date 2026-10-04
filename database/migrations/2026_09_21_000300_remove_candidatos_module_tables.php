<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // El módulo Candidatos fue retirado. En instalaciones existentes
        // eliminamos primero la tabla dependiente y luego su catálogo de cargos.
        Schema::disableForeignKeyConstraints();
        try {
            if (Schema::hasTable('candidato')) Schema::dropIfExists('candidato');
            if (Schema::hasTable('cargo_candidatura')) Schema::dropIfExists('cargo_candidatura');
            if (Schema::hasTable('rol')) {
                \Illuminate\Support\Facades\DB::table('rol')
                    ->whereIn('nombre', ['Candidato Regional', 'Candidato Provincial-Distrital', 'Candidato Provincial', 'Candidato Distrital'])
                    ->update(['estado' => 0, 'updated_at' => now()]);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // No se reconstruyen tablas del módulo retirado al hacer rollback.
    }
};
