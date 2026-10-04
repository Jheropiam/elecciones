<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResetActas extends Command
{
    protected $signature = 'electoral:reset-actas
                            {--force : Ejecuta el reinicio sin solicitar confirmación}
                            {--dry-run : Solo muestra lo que se reiniciaría, sin borrar datos}
                            {--usuario= : DNI/usuario que quedará registrado como responsable del reinicio}';

    protected $description = 'Reinicia las actas y resultados, conservando mesas, personeros, usuarios y datos maestros.';

    public function handle(): int
    {
        if (!DB::getSchemaBuilder()->hasTable('digitacion_acta')) {
            $this->error('La tabla digitacion_acta no existe. Ejecute primero las migraciones.');
            return self::FAILURE;
        }

        $stats = $this->getStats();

        $this->newLine();
        $this->line('<fg=cyan>==============================================</>');
        $this->line('<fg=cyan>       REINICIO DE ACTAS Y RESULTADOS        </>');
        $this->line('<fg=cyan>==============================================</>');
        $this->newLine();

        $this->info('Datos que serán eliminados:');
        $this->line('  • Filas de digitación: ' . number_format($stats['filas']));
        $this->line('  • Actas registradas (mesa + tipo): ' . number_format($stats['actas']));
        $this->line('  • Actas consistentes: ' . number_format($stats['consistentes']));
        $this->line('  • Actas observadas: ' . number_format($stats['observadas']));

        $this->newLine();
        $this->info('Datos que SE CONSERVARÁN:');
        $this->line('  ✓ Ámbito (regiones, provincias y distritos)');
        $this->line('  ✓ Locales');
        $this->line('  ✓ Mesas');
        $this->line('  ✓ Partidos políticos');
        $this->line('  ✓ Personeros');
        $this->line('  ✓ Personas');
        $this->line('  ✓ Usuarios y roles');
        $this->line('  ✓ Configuración general');
        $this->line('  ✓ Auditoría y trazabilidad');
        $this->line('  ✓ Notificaciones');

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment('Modo simulación: no se eliminó ningún dato.');
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            $this->newLine();
            $this->warn('Esta operación no puede deshacerse desde Laravel.');
            $this->warn('Se conservarán las mesas y personeros, pero se eliminarán todas las actas digitadas.');

            if ($this->input->isInteractive()) {
                $confirmation = $this->ask('Para confirmar escriba exactamente: REINICIAR ACTAS');
                if ($confirmation !== 'REINICIAR ACTAS') {
                    $this->warn('Operación cancelada. No se eliminó ningún dato.');
                    return self::SUCCESS;
                }
            } else {
                $this->error('La ejecución no interactiva requiere la opción --force.');
                return self::FAILURE;
            }
        }

        $idUsuario = null;
        if ($this->option('usuario') !== null && $this->option('usuario') !== '') {
            $idUsuario = DB::table('usuario')->where('usuario', (string) $this->option('usuario'))->value('id_usuario');
            if (!$idUsuario) {
                $this->error('El usuario indicado en --usuario no existe. No se realizó ningún cambio.');
                return self::FAILURE;
            }
        }

        try {
            DB::transaction(function () use ($stats, $idUsuario): void {
                DB::table('digitacion_acta')->delete();

                // La auditoría se conserva y registra el reinicio después del borrado.
                DB::table('auditoria')->insert([
                    'id_usuario' => $idUsuario,
                    'modulo' => 'Sistema',
                    'tabla_afectada' => 'digitacion_acta',
                    'id_registro' => null,
                    'accion' => 'REINICIO_ACTAS',
                    'valor_anterior' => json_encode([
                        'filas_digitacion' => $stats['filas'],
                        'actas_registradas' => $stats['actas'],
                        'consistentes' => $stats['consistentes'],
                        'observadas' => $stats['observadas'],
                    ], JSON_UNESCAPED_UNICODE),
                    'valor_nuevo' => json_encode([
                        'filas_digitacion' => 0,
                        'actas_registradas' => 0,
                        'consistentes' => 0,
                        'observadas' => 0,
                    ], JSON_UNESCAPED_UNICODE),
                    'ip' => null,
                    'user_agent' => 'Artisan: electoral:reset-actas',
                    'created_at' => now(),
                ]);
            });

            // ALTER TABLE hace commit implícito en MySQL, por eso se ejecuta
            // después de la transacción que protege el borrado y la auditoría.
            DB::statement('ALTER TABLE `digitacion_acta` AUTO_INCREMENT = 1');
        } catch (Throwable $e) {
            $this->error('No fue posible completar el reinicio.');
            $this->line($e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✓ Reinicio completado correctamente.');
        $this->line('  Actas registradas: 0');
        $this->line('  Actas consistentes: 0');
        $this->line('  Actas observadas: 0');
        $this->line('  Resultados: 0');
        $this->newLine();
        $this->comment('Las mesas, personeros, partidos, usuarios y demás datos maestros permanecen intactos.');
        $this->comment('La operación quedó registrada en Auditoría y Trazabilidad.');

        return self::SUCCESS;
    }

    private function getStats(): array
    {
        $filas = DB::table('digitacion_acta')->count();

        $actas = (int) DB::table('digitacion_acta')
            ->selectRaw("COUNT(DISTINCT CONCAT(id_mesa, ':', tipo_acta)) AS total")
            ->value('total');

        $consistentes = (int) DB::table('digitacion_acta')
            ->where('estado_acta', 'CONSISTENTE')
            ->selectRaw("COUNT(DISTINCT CONCAT(id_mesa, ':', tipo_acta)) AS total")
            ->value('total');

        $observadas = (int) DB::table('digitacion_acta')
            ->where('estado_acta', 'OBSERVADA')
            ->selectRaw("COUNT(DISTINCT CONCAT(id_mesa, ':', tipo_acta)) AS total")
            ->value('total');

        return compact('filas', 'actas', 'consistentes', 'observadas');
    }
}
