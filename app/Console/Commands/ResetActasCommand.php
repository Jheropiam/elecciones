<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ResetActasCommand extends Command
{
    protected $signature = 'electoral:reset-actas
                            {--force : Ejecuta el reinicio sin pedir confirmación}
                            {--dry-run : Solo muestra qué se reiniciaría, sin modificar datos}';

    protected $description = 'Reinicia únicamente las actas digitadas y sus resultados, conservando mesas, personeros y demás datos maestros.';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<info>REINICIO DE ACTAS Y RESULTADOS</info>');
        $this->newLine();

        if (!Schema::hasTable('digitacion_acta')) {
            $this->line('<error>La tabla digitacion_acta no existe. Ejecute primero las migraciones.</error>');
            return self::FAILURE;
        }

        $filas = DB::table('digitacion_acta')->count();
        $actas = DB::table('digitacion_acta')
            ->select('id_mesa', 'tipo_acta')
            ->distinct()
            ->count();
        $mesas = DB::table('digitacion_acta')
            ->distinct('id_mesa')
            ->count('id_mesa');

        $conservados = [
            'Regiones / Provincias / Distritos',
            'Locales',
            'Mesas',
            'Partidos políticos',
            'Personeros',
            'Personas y Usuarios',
            'Roles y Permisos',
            'Configuración general',
            'Auditoría y Trazabilidad',
        ];

        $this->line('Filas de digitacion_acta : ' . number_format($filas));
        $this->line('Actas (mesa + tipo)      : ' . number_format($actas));
        $this->line('Mesas con actas digitadas: ' . number_format($mesas));

        $this->newLine();
        $this->line('<fg=yellow>Se eliminarán únicamente:</>');
        $this->line('  • Todas las filas de <fg=yellow>digitacion_acta</>.');
        $this->line('  • Los resultados quedarán en 0 porque el módulo Reporte calcula sobre las actas digitadas.');
        $this->line('  • El contador AUTO_INCREMENT de digitacion_acta volverá a comenzar desde 1.');

        $this->newLine();
        $this->line('<fg=green>Se conservarán:</>');
        foreach ($conservados as $item) {
            $this->line("  ✓ {$item}");
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->line('<info>DRY-RUN: no se modificó ninguna información.</info>');
            return self::SUCCESS;
        }

        // Evita que una futura relación FK hacia digitacion_acta haga un borrado
        // parcial o inesperado. En el esquema actual no existen referencias hacia ella.
        $referencias = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('REFERENCED_TABLE_NAME', 'digitacion_acta')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->count();

        if ($referencias > 0) {
            $this->newLine();
            $this->line('<error>No se realizó el reinicio: existen tablas con claves foráneas que apuntan a digitacion_acta.</error>');
            $this->line('Revise esas relaciones antes de ejecutar el reinicio.');
            return self::FAILURE;
        }

        if (!$this->option('force')) {
            $this->newLine();
            $confirmado = $this->confirm(
                '¿Desea eliminar definitivamente las actas digitadas mostradas arriba?',
                false
            );

            if (!$confirmado) {
                $this->line('<comment>Operación cancelada. No se modificó ninguna información.</comment>');
                return self::SUCCESS;
            }
        }

        try {
            DB::statement('TRUNCATE TABLE `digitacion_acta`');

            // Deja constancia de la operación sin depender de una sesión HTTP.
            // id_usuario es NULL porque el comando se ejecuta desde la terminal.
            if (Schema::hasTable('auditoria')) {
                DB::table('auditoria')->insert([
                    'id_usuario' => null,
                    'modulo' => 'Sistema',
                    'tabla_afectada' => 'digitacion_acta',
                    'id_registro' => null,
                    'accion' => 'REINICIO_ACTAS',
                    'valor_anterior' => json_encode([
                        'filas_digitacion' => $filas,
                        'actas' => $actas,
                        'mesas_con_actas' => $mesas,
                    ], JSON_UNESCAPED_UNICODE),
                    'valor_nuevo' => json_encode([
                        'filas_digitacion' => 0,
                        'actas' => 0,
                        'mesas_con_actas' => 0,
                    ], JSON_UNESCAPED_UNICODE),
                    'ip' => null,
                    'user_agent' => 'Artisan CLI',
                    'created_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            $this->newLine();
            $this->line('<error>No fue posible completar el reinicio.</error>');
            $this->line($e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->line('<info>Actas digitadas reiniciadas correctamente.</info>');
        $this->line('Actas registradas       : 0');
        $this->line('Filas de digitacion_acta: 0');
        $this->line('Resultados              : 0');
        $this->line('Mesas                   : CONSERVADAS');
        $this->line('Personeros              : CONSERVADOS');
        $this->line('Auditoría               : CONSERVADA');
        $this->newLine();
        $this->line('<fg=green>El sistema queda listo para iniciar una nueva jornada de digitación.</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
