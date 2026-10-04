<?php

namespace App\Support;

use Illuminate\Http\Request;

class Pagination
{
    /**
     * Valores permitidos por el selector "Ver" de las tablas paginadas.
     */
    public const OPTIONS = [10, 20, 50, 100];

    public static function perPage(Request $request, int $default = 20): int
    {
        $value = (int) $request->query('per_page', $default);

        return in_array($value, self::OPTIONS, true) ? $value : $default;
    }
}
