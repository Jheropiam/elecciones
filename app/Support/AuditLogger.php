<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public static function log(
        string $modulo,
        ?string $tabla,
        $idRegistro,
        string $accion,
        $anterior = null,
        $nuevo = null,
        ?int $idUsuario = null
    ): void {
        try {
            DB::table('auditoria')->insert([
                'id_usuario' => $idUsuario ?? session('id_usuario'),
                'modulo' => $modulo,
                'tabla_afectada' => $tabla,
                'id_registro' => $idRegistro,
                'accion' => $accion,
                'valor_anterior' => $anterior !== null ? json_encode($anterior, JSON_UNESCAPED_UNICODE) : null,
                'valor_nuevo' => $nuevo !== null ? json_encode($nuevo, JSON_UNESCAPED_UNICODE) : null,
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe impedir que una operación funcional termine.
        }
    }
}
