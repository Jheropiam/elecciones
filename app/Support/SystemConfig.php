<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

class SystemConfig
{
    public static function all(): array
    {
        try {
            return DB::table('configuracion_sistema')
                ->where('estado', 1)
                ->pluck('valor', 'clave')
                ->map(fn($v) => $v === null ? '' : (string) $v)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function get(string $key, $default = null)
    {
        try {
            $value = DB::table('configuracion_sistema')
                ->where('clave', $key)
                ->where('estado', 1)
                ->value('valor');
            return $value === null ? $default : $value;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function put(string $key, $value, string $type = 'STRING', ?string $description = null, int $critical = 0): void
    {
        $payload = [
            'valor' => $value === null ? null : (string) $value,
            'tipo_valor' => $type,
            'updated_at' => now(),
        ];
        if ($description !== null) $payload['descripcion'] = $description;
        DB::table('configuracion_sistema')->updateOrInsert(
            ['clave' => $key],
            array_merge($payload, ['es_critica' => $critical, 'estado' => 1])
        );
    }
}
