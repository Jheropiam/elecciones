<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\SimpleXlsx;

class ReporteController extends Controller
{
    private const ELECCIONES = [
        'REGIONAL' => 'Elecciones Regionales',
        'CONSEJERO' => 'Elecciones de Consejero Regional',
        'PROVINCIAL' => 'Elecciones Provinciales',
        'DISTRITAL' => 'Elecciones Distritales',
    ];

    private function filters(Request $request): array
    {
        $eleccion = strtoupper((string) $request->get('eleccion', 'REGIONAL'));
        if ($eleccion === 'TODAS' || !array_key_exists($eleccion, self::ELECCIONES)) {
            $eleccion = 'REGIONAL';
        }

        $excludeMesas = collect((array) $request->get('exclude_mesas', []))
            ->map(fn ($v) => preg_replace('/\D/', '', (string) $v))
            ->filter(fn ($v) => strlen($v) === 6)
            ->unique()
            ->values()
            ->all();

        $includeMesas = collect((array) $request->get('include_mesas', []))
            ->map(fn ($v) => preg_replace('/\D/', '', (string) $v))
            ->filter(fn ($v) => strlen($v) === 6)
            ->unique()
            ->values()
            ->all();

        return [
            'eleccion' => $eleccion,
            'region' => $request->get('region'),
            'provincia' => $request->get('provincia'),
            'distrito' => $request->get('distrito'),
            'local' => $request->get('local'),
            'mesa' => $request->get('mesa'),
            'excluir_observadas' => $request->boolean('excluir_observadas'),
            'exclude_mesas' => $excludeMesas,
            'include_mesas' => $includeMesas,
        ];
    }

    /**
     * Restricciones del reporte según los roles y la ubicación registrada
     * en la persona. El Administrador no tiene restricciones.
     *
     * Importante: estas restricciones se aplican en el servidor, no solo
     * en los combos de la vista, para impedir que un usuario cambie los
     * parámetros GET manualmente y vea información fuera de su ámbito.
     */
    private function reportAccessContext(): array
    {
        $userId = (int) session('id_usuario');
        if (!$userId) {
            return [
                'admin' => false,
                'role' => null,
                'region' => null,
                'provincia' => null,
                'distrito' => null,
                'local' => null,
                'lock_region' => true,
                'lock_provincia' => false,
                'lock_distrito' => false,
                'lock_local' => false,
                'candidate_scope' => false,
                'allowed_elections' => array_keys(self::ELECCIONES),
            ];
        }

        $admin = DB::table('usuario_rol as ur')
            ->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')
            ->where('ur.id_usuario', $userId)
            ->where('ur.estado', 1)
            ->where('r.estado', 1)
            ->whereRaw('LOWER(r.nombre)=LOWER(?)', ['Administrador'])
            ->exists();

        if ($admin) {
            return [
                'admin' => true,
                'role' => 'Administrador',
                'region' => null,
                'provincia' => null,
                'distrito' => null,
                'local' => null,
                'lock_region' => false,
                'lock_provincia' => false,
                'lock_distrito' => false,
                'lock_local' => false,
                'candidate_scope' => false,
                'allowed_elections' => array_keys(self::ELECCIONES),
            ];
        }

        $roles = DB::table('usuario_rol as ur')
            ->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')
            ->where('ur.id_usuario', $userId)
            ->where('ur.estado', 1)
            ->where('r.estado', 1)
            ->pluck('r.nombre')
            ->map(fn ($v) => trim((string) $v))
            ->values()
            ->all();

        $rolePriority = [
            'Candidato Regional',
            'Candidato Provincial',
            'Candidato Distrital',
            'Personero de Local',
            'Personero Distrital',
            'Personero Provincial',
            'Personero Regional',
        ];
        $role = collect($rolePriority)->first(fn ($name) => in_array($name, $roles, true));
        if (!$role) $role = 'OTRO';

        $persona = DB::table('usuario as u')
            ->join('persona as p', 'p.id_persona', '=', 'u.id_persona')
            ->where('u.id_usuario', $userId)
            ->select('p.id_persona', 'p.id_region', 'p.id_provincia', 'p.id_distrito')
            ->first();

        $region = $persona?->id_region ? (int) $persona->id_region : null;
        $provincia = $persona?->id_provincia ? (int) $persona->id_provincia : null;
        $distrito = $persona?->id_distrito ? (int) $persona->id_distrito : null;
        $local = null;
        $allowedElections = array_keys(self::ELECCIONES);
        $lockProvincia = false;
        $lockDistrito = false;
        $lockLocal = false;
        $candidateScope = false;

        if ($role === 'Candidato Regional') {
            $allowedElections = ['REGIONAL', 'CONSEJERO'];
            $candidateScope = true;
        } elseif ($role === 'Candidato Provincial') {
            $allowedElections = ['PROVINCIAL'];
            $lockProvincia = true;
            $candidateScope = true;
        } elseif ($role === 'Candidato Distrital') {
            $allowedElections = ['DISTRITAL'];
            $lockProvincia = true;
            $lockDistrito = true;
            $candidateScope = true;
        } elseif ($role === 'Personero Provincial') {
            $lockProvincia = true;
        } elseif ($role === 'Personero Distrital') {
            $lockProvincia = true;
            $lockDistrito = true;
        } elseif ($role === 'Personero de Local') {
            $lockProvincia = true;
            $lockDistrito = true;
            $lockLocal = true;

            if ($persona?->id_persona) {
                $local = DB::table('personero_local')
                    ->where('id_persona', $persona->id_persona)
                    ->where('estado', 1)
                    ->orderBy('id_personero_local')
                    ->value('id_local');

                if (!$local) {
                    $local = DB::table('personero_local')
                        ->where('id_usuario', $userId)
                        ->where('estado', 1)
                        ->orderBy('id_personero_local')
                        ->value('id_local');
                }
                $local = $local ? (int) $local : null;
            }
        }

        return [
            'admin' => false,
            'role' => $role,
            'region' => $region,
            'provincia' => $provincia,
            'distrito' => $distrito,
            'local' => $local,
            'lock_region' => true,
            'lock_provincia' => $lockProvincia,
            'lock_distrito' => $lockDistrito,
            'lock_local' => $lockLocal,
            'candidate_scope' => $candidateScope,
            'allowed_elections' => $allowedElections,
        ];
    }

    /**
     * Determina qué tipos de elección se muestran según el nivel geográfico
     * que ya fue seleccionado. Los candidatos son una excepción: sus tipos
     * están fijados por el rol.
     */
    private function electionsForScope(array $f, array $access): array
    {
        if (!$access['admin'] && $access['candidate_scope']) {
            return $access['allowed_elections'];
        }

        if (empty($f['region'])) return [];
        if (empty($f['provincia'])) return ['REGIONAL', 'CONSEJERO'];
        if (empty($f['distrito'])) return ['REGIONAL', 'CONSEJERO', 'PROVINCIAL'];
        return array_keys(self::ELECCIONES);
    }

    private function applyReportAccess(array $f, array $access): array
    {
        if (!$access['admin']) {
            $f['region'] = $access['region'];
            $f['provincia'] = $access['lock_provincia'] ? $access['provincia'] : $f['provincia'];
            $f['distrito'] = $access['lock_distrito'] ? $access['distrito'] : $f['distrito'];
            $f['local'] = $access['lock_local'] ? $access['local'] : $f['local'];

            // Los candidatos no pueden utilizar niveles inferiores a su
            // ámbito aunque intenten enviarlos manualmente por GET.
            if ($access['candidate_scope']) {
                if ($access['role'] === 'Candidato Regional') {
                    $f['provincia'] = $f['distrito'] = $f['local'] = $f['mesa'] = null;
                } elseif ($access['role'] === 'Candidato Provincial') {
                    $f['distrito'] = $f['local'] = $f['mesa'] = null;
                } elseif ($access['role'] === 'Candidato Distrital') {
                    // El candidato distrital tiene bloqueados Región, Provincia y Distrito,
                    // pero puede seleccionar Local y Mesa dentro de su distrito.
                }
            }
        }

        // Evita combinaciones geográficas inconsistentes cuando se cambia
        // un nivel del selector. Los niveles inferiores se limpian.
        if ($f['region'] && $f['provincia']) {
            $valid = DB::table('provincia')->where('id_provincia', $f['provincia'])->where('id_region', $f['region'])->exists();
            if (!$valid) $f['provincia'] = $f['distrito'] = $f['local'] = $f['mesa'] = null;
        }
        if ($f['provincia'] && $f['distrito']) {
            $valid = DB::table('distrito')->where('id_distrito', $f['distrito'])->where('id_provincia', $f['provincia'])->exists();
            if (!$valid) $f['distrito'] = $f['local'] = $f['mesa'] = null;
        }
        if ($f['distrito'] && $f['local']) {
            $valid = DB::table('local')->where('id_local', $f['local'])->where('id_distrito', $f['distrito'])->exists();
            if (!$valid) $f['local'] = $f['mesa'] = null;
        }
        if ($f['local'] && $f['mesa']) {
            $valid = DB::table('mesa')->where('id_mesa', $f['mesa'])->where('id_local', $f['local'])->exists();
            if (!$valid) $f['mesa'] = null;
        }

        $allowed = $this->electionsForScope($f, $access);
        if (empty($allowed)) {
            // Internamente se mantiene Regional mientras todavía no se haya
            // elegido región; la interfaz oculta el selector de elección.
            $f['eleccion'] = 'REGIONAL';
        } elseif (!in_array($f['eleccion'], $allowed, true)) {
            $f['eleccion'] = $allowed[0];
        }

        return $f;
    }

    private function selectedActaTypes(string $eleccion): array
    {
        return match ($eleccion) {
            'REGIONAL', 'CONSEJERO' => [1],
            'PROVINCIAL', 'DISTRITAL' => [2],
            default => [1, 2],
        };
    }

    private function electionField(string $eleccion): ?string
    {
        return match ($eleccion) {
            'REGIONAL' => 'campo_1',
            'CONSEJERO' => 'campo_2',
            'PROVINCIAL' => 'campo_1',
            'DISTRITAL' => 'campo_2',
            default => null,
        };
    }

    private function mesaQuery(array $f)
    {
        $q = DB::table('mesa as m')
            ->join('local as l', 'l.id_local', '=', 'm.id_local')
            ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->where('m.estado', 1);

        if (!empty($f['region'])) $q->where('r.id_region', $f['region']);
        if (!empty($f['provincia'])) $q->where('p.id_provincia', $f['provincia']);
        if (!empty($f['distrito'])) $q->where('d.id_distrito', $f['distrito']);
        if (!empty($f['local'])) $q->where('l.id_local', $f['local']);
        if (!empty($f['mesa'])) $q->where('m.id_mesa', $f['mesa']);

        return $q;
    }

    private function actaQuery(array $f)
    {
        $types = $this->selectedActaTypes($f['eleccion']);

        $q = DB::table('digitacion_acta as da')
            ->join('mesa as m', 'm.id_mesa', '=', 'da.id_mesa')
            ->join('local as l', 'l.id_local', '=', 'm.id_local')
            ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->where('da.estado', 1)
            ->whereIn('da.tipo_acta', $types)
            ->where('m.estado', 1);

        if (!empty($f['region'])) $q->where('r.id_region', $f['region']);
        if (!empty($f['provincia'])) $q->where('p.id_provincia', $f['provincia']);
        if (!empty($f['distrito'])) $q->where('d.id_distrito', $f['distrito']);
        if (!empty($f['local'])) $q->where('l.id_local', $f['local']);
        if (!empty($f['mesa'])) $q->where('m.id_mesa', $f['mesa']);

        if ($f['excluir_observadas']) {
            // En modo "excluir todas", las observadas quedan fuera por defecto.
            // Las mesas incluidas manualmente desde el modal son la excepción.
            $q->where(function ($sub) use ($f) {
                $sub->where('da.estado_acta', 'CONSISTENTE');
                if (!empty($f['include_mesas'])) {
                    $sub->orWhere(function ($included) use ($f) {
                        $included->where('da.estado_acta', 'OBSERVADA')
                            ->whereIn('m.numero_mesa', $f['include_mesas']);
                    });
                }
            });
        } elseif (!empty($f['exclude_mesas'])) {
            // En modo normal, solo se excluyen las mesas marcadas manualmente.
            $q->whereNotIn('m.numero_mesa', $f['exclude_mesas']);
        }

        return $q;
    }

    private function observedMesas(array $f)
    {
        $types = $this->selectedActaTypes($f['eleccion']);
        return DB::table('digitacion_acta as da')
            ->join('mesa as m', 'm.id_mesa', '=', 'da.id_mesa')
            ->join('local as l', 'l.id_local', '=', 'm.id_local')
            ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->where('da.estado', 1)
            ->where('da.estado_acta', 'OBSERVADA')
            ->whereIn('da.tipo_acta', $types)
            ->where('m.estado', 1)
            ->when($f['region'], fn($q, $v) => $q->where('r.id_region', $v))
            ->when($f['provincia'], fn($q, $v) => $q->where('p.id_provincia', $v))
            ->when($f['distrito'], fn($q, $v) => $q->where('d.id_distrito', $v))
            ->when($f['local'], fn($q, $v) => $q->where('l.id_local', $v))
            ->when($f['mesa'], fn($q, $v) => $q->where('m.id_mesa', $v))
            ->select('m.id_mesa', 'm.numero_mesa', 'l.nombre as local_nombre', 'da.tipo_acta')
            ->distinct()
            ->orderBy('m.numero_mesa')
            ->get();
    }

    private function partyResults(array $f)
    {
        $types = $this->selectedActaTypes($f['eleccion']);
        $field = $this->electionField($f['eleccion']);

        $q = $this->actaQuery($f)
            ->join('partido as pa', 'pa.id_partido', '=', 'da.id_partido')
            ->where('pa.tipo', 'POLITICO');

        if ($field) {
            $q->select(
                'pa.id_partido',
                'pa.nombre',
                'pa.logo',
                DB::raw("SUM(da.{$field}) as votos")
            )->groupBy('pa.id_partido', 'pa.nombre', 'pa.logo');
        } else {
            $q->select(
                'pa.id_partido',
                'pa.nombre',
                'pa.logo',
                DB::raw('SUM(da.campo_1 + da.campo_2) as votos'),
                DB::raw('SUM(CASE WHEN da.tipo_acta = 1 THEN da.campo_1 ELSE 0 END) as votos_regional'),
                DB::raw('SUM(CASE WHEN da.tipo_acta = 1 THEN da.campo_2 ELSE 0 END) as votos_consejero'),
                DB::raw('SUM(CASE WHEN da.tipo_acta = 2 THEN da.campo_1 ELSE 0 END) as votos_provincial'),
                DB::raw('SUM(CASE WHEN da.tipo_acta = 2 THEN da.campo_2 ELSE 0 END) as votos_distrital')
            )->groupBy('pa.id_partido', 'pa.nombre', 'pa.logo');
        }

        $rows = $q->orderByDesc('votos')->orderBy('pa.nombre')->get();

        $existing = $rows->pluck('id_partido')->all();
        $allParties = DB::table('partido')
            ->where('tipo', 'POLITICO')
            ->where(function ($q) use ($existing) {
                if (!empty($existing)) $q->whereIn('id_partido', $existing);
                else $q->where('estado', 1);
            })
            ->get(['id_partido', 'nombre', 'logo']);

        // Si hubo datos, se mantienen las organizaciones que participaron.
        // Si no hubo datos, se muestran las organizaciones políticas activas.
        if ($rows->isEmpty()) {
            $rows = $allParties->map(fn($p) => (object)[
                'id_partido' => $p->id_partido,
                'nombre' => $p->nombre,
                'logo' => $p->logo,
                'votos' => 0,
                'votos_regional' => 0,
                'votos_consejero' => 0,
                'votos_provincial' => 0,
                'votos_distrital' => 0,
            ]);
        }

        return $rows->sortByDesc('votos')->values();
    }

    private function specialResults(array $f): array
    {
        $field = $this->electionField($f['eleccion']);
        $q = $this->actaQuery($f)
            ->join('partido as pa', 'pa.id_partido', '=', 'da.id_partido')
            ->where('pa.tipo', 'ESPECIAL');

        $names = ['VOTOS BLANCOS', 'VOTOS NULOS', 'VOTOS IMPUGNADOS', 'TOTAL DE VOTOS EMITIDOS'];
        $out = [];

        foreach ($names as $name) {
            $rowQuery = clone $q;
            if ($field) {
                $value = $rowQuery->where('pa.nombre', $name)->sum('da.' . $field);
            } else {
                $value = $rowQuery->where('pa.nombre', $name)
                    ->select(DB::raw('SUM(da.campo_1 + da.campo_2) as total'))
                    ->value('total');
            }
            $out[$name] = (int) ($value ?? 0);
        }

        return $out;
    }

    private function summary(array $f): array
    {
        $expected = $this->mesaQuery($f)->count();
        $types = $this->selectedActaTypes($f['eleccion']);
        $expectedActs = $expected * count($types);

        $base = $this->actaQuery(array_merge($f, ['excluir_observadas' => false, 'exclude_mesas' => []]));
        $countedAll = $base->select('da.id_mesa', 'da.tipo_acta')->distinct()->get()->count();
        $consistent = (clone $base)->where('da.estado_acta', 'CONSISTENTE')->select('da.id_mesa','da.tipo_acta')->distinct()->get()->count();
        $observed = (clone $base)->where('da.estado_acta', 'OBSERVADA')->select('da.id_mesa','da.tipo_acta')->distinct()->get()->count();

        $excluded = 0;
        if ($f['excluir_observadas']) {
            $excluded = max(0, $observed - (clone $base)
                ->where('da.estado_acta', 'OBSERVADA')
                ->whereIn('m.numero_mesa', $f['include_mesas'] ?? [])
                ->select('da.id_mesa','da.tipo_acta')->distinct()->get()->count());
        } elseif (!empty($f['exclude_mesas'])) {
            $excluded = (clone $base)->whereIn('m.numero_mesa', $f['exclude_mesas'])
                ->select('da.id_mesa','da.tipo_acta')->distinct()->get()->count();
        }

        $counted = $this->actaQuery($f)->select('da.id_mesa','da.tipo_acta')->distinct()->get()->count();
        $pending = max(0, $expectedActs - $counted);
        $percentage = $expectedActs > 0 ? round(($counted / $expectedActs) * 100, 1) : 0;

        return compact('expected', 'expectedActs', 'counted', 'consistent', 'observed', 'pending', 'percentage', 'excluded');
    }

    public function index(Request $request)
    {
        $access = $this->reportAccessContext();
        $f = $this->applyReportAccess($this->filters($request), $access);
        $summary = $this->summary($f);
        $rows = $this->partyResults($f);
        $special = $this->specialResults($f);
        $observedMesas = $this->observedMesas($f);

        $regionesQuery = DB::table('region')->where('estado', 1);
        if (!$access['admin']) $regionesQuery->where('id_region', $access['region'] ?? 0);
        $regiones = $regionesQuery->orderBy('nombre')->get();

        $provinciasQuery = DB::table('provincia')->where('estado', 1);
        if ($f['region']) $provinciasQuery->where('id_region', $f['region']);
        if (!$access['admin'] && $access['lock_provincia']) $provinciasQuery->where('id_provincia', $access['provincia'] ?? 0);
        $provincias = $f['region'] ? $provinciasQuery->orderBy('nombre')->get() : collect();

        $distritosQuery = DB::table('distrito')->where('estado', 1);
        if ($f['provincia']) $distritosQuery->where('id_provincia', $f['provincia']);
        if (!$access['admin'] && $access['lock_distrito']) $distritosQuery->where('id_distrito', $access['distrito'] ?? 0);
        $distritos = $f['provincia'] ? $distritosQuery->orderBy('nombre')->get() : collect();

        $localesQuery = DB::table('local')->where('estado', 1);
        if ($f['distrito']) $localesQuery->where('id_distrito', $f['distrito']);
        if (!$access['admin'] && $access['lock_local']) $localesQuery->where('id_local', $access['local'] ?? 0);
        $locales = $f['distrito'] ? $localesQuery->orderBy('nombre')->get() : collect();

        $mesas = $f['local'] ? DB::table('mesa')->where('estado', 1)->where('id_local', $f['local'])->orderBy('numero_mesa')->get() : collect();

        $visibleElectionKeys = $this->electionsForScope($f, $access);
        $elecciones = array_intersect_key(self::ELECCIONES, array_flip($visibleElectionKeys));

        return view('reporte.resultados', [
            'filters' => $f,
            'elecciones' => $elecciones,
            'summary' => $summary,
            'rows' => $rows,
            'special' => $special,
            'observedMesas' => $observedMesas,
            'regiones' => $regiones,
            'provincias' => $provincias,
            'distritos' => $distritos,
            'locales' => $locales,
            'mesas' => $mesas,
            'reportAccess' => $access,
        ])->with('sidebarModules', DashboardController::sidebarModules())
          ->with('title', 'Reporte');
    }


/**
 * Seguimiento operativo por mesa.
 *
 * A diferencia de Resultados, esta vista parte de todas las mesas
 * configuradas y determina para cada una si el acta seleccionada
 * está pendiente, consistente u observada.
 */
public function seguimiento(Request $request)
{
    $f = $this->filters($request);

    $estado = strtoupper((string) $request->get('estado', 'TODAS'));
    if (!in_array($estado, ['TODAS', 'PENDIENTE', 'CONSISTENTE', 'OBSERVADA'], true)) {
        $estado = 'TODAS';
    }
    $f['estado'] = $estado;

    $allRows = $this->seguimientoRows($f);

    $totales = [
        'mesas' => $allRows->count(),
        'pendientes' => $allRows->where('estado_seguimiento', 'PENDIENTE')->count(),
        'consistentes' => $allRows->where('estado_seguimiento', 'CONSISTENTE')->count(),
        'observadas' => $allRows->where('estado_seguimiento', 'OBSERVADA')->count(),
    ];
    $totales['avance'] = $totales['mesas'] > 0
        ? round((($totales['consistentes'] + $totales['observadas']) / $totales['mesas']) * 100, 1)
        : 0;

    // Paginación local de la colección ya filtrada, sin alterar la consulta de exportación.
    $perPage = (int) $request->get('per_page', 10);
    if (!in_array($perPage, [10, 25, 50, 100], true)) $perPage = 10;
    $currentPage = max(1, (int) $request->get('page', 1));
    $rows = new LengthAwarePaginator(
        $allRows->forPage($currentPage, $perPage)->values(),
        $allRows->count(),
        $perPage,
        $currentPage,
        ['path' => $request->url(), 'query' => $request->query()]
    );

    $regiones = DB::table('region')
        ->where('estado', 1)
        ->orderBy('nombre')
        ->get();

    $provincias = $f['region']
        ? DB::table('provincia')->where('estado', 1)->where('id_region', $f['region'])->orderBy('nombre')->get()
        : collect();

    $distritos = $f['provincia']
        ? DB::table('distrito')->where('estado', 1)->where('id_provincia', $f['provincia'])->orderBy('nombre')->get()
        : collect();

    $locales = $f['distrito']
        ? DB::table('local')->where('estado', 1)->where('id_distrito', $f['distrito'])->orderBy('nombre')->get()
        : collect();

    $mesas = $f['local']
        ? DB::table('mesa')->where('estado', 1)->where('id_local', $f['local'])->orderBy('numero_mesa')->get()
        : collect();

    $viewData = [
        'filters' => $f,
        'perPage' => $perPage,
        'elecciones' => self::ELECCIONES,
        'estadosSeguimiento' => [
            'TODAS' => 'Todos los estados',
            'PENDIENTE' => 'Pendientes',
            'CONSISTENTE' => 'Consistentes',
            'OBSERVADA' => 'Observadas',
        ],
        'totales' => $totales,
        'rows' => $rows,
        'regiones' => $regiones,
        'provincias' => $provincias,
        'distritos' => $distritos,
        'locales' => $locales,
        'mesas' => $mesas,
    ];

    if ($request->boolean('partial')) {
        return view('reporte.partials.seguimiento_content', $viewData);
    }

    return view('reporte.seguimiento', $viewData)
        ->with('sidebarModules', DashboardController::sidebarModules())
        ->with('title', 'Seguimiento');
}

/**
 * Devuelve una fila por mesa. Las subconsultas regional y municipal
 * condensan las múltiples filas de digitacion_acta (una por partido)
 * en el estado real del acta.
 */
private function seguimientoRows(array $f)
{
    $regional = DB::table('digitacion_acta as da')
        ->leftJoin('usuario as ur', 'ur.id_usuario', '=', 'da.id_usuario_registro')
        ->leftJoin('persona as pur', 'pur.id_persona', '=', 'ur.id_persona')
        ->where('da.estado', 1)
        ->where('da.tipo_acta', 1)
        ->select(
            'da.id_mesa',
            DB::raw("CASE WHEN SUM(CASE WHEN da.estado_acta = 'OBSERVADA' THEN 1 ELSE 0 END) > 0 THEN 'OBSERVADA' ELSE 'CONSISTENTE' END as estado_acta"),
            DB::raw('MIN(da.fecha_registro) as fecha_registro'),
            DB::raw('MAX(da.fecha_modificacion) as fecha_modificacion'),
            DB::raw("GROUP_CONCAT(DISTINCT CONCAT(pur.nombres, ' ', pur.apellido_paterno, IFNULL(CONCAT(' ', pur.apellido_materno), '')) SEPARATOR ' | ') as digitador")
        )
        ->groupBy('da.id_mesa');

    $municipal = DB::table('digitacion_acta as da')
        ->leftJoin('usuario as ur', 'ur.id_usuario', '=', 'da.id_usuario_registro')
        ->leftJoin('persona as pur', 'pur.id_persona', '=', 'ur.id_persona')
        ->where('da.estado', 1)
        ->where('da.tipo_acta', 2)
        ->select(
            'da.id_mesa',
            DB::raw("CASE WHEN SUM(CASE WHEN da.estado_acta = 'OBSERVADA' THEN 1 ELSE 0 END) > 0 THEN 'OBSERVADA' ELSE 'CONSISTENTE' END as estado_acta"),
            DB::raw('MIN(da.fecha_registro) as fecha_registro'),
            DB::raw('MAX(da.fecha_modificacion) as fecha_modificacion'),
            DB::raw("GROUP_CONCAT(DISTINCT CONCAT(pur.nombres, ' ', pur.apellido_paterno, IFNULL(CONCAT(' ', pur.apellido_materno), '')) SEPARATOR ' | ') as digitador")
        )
        ->groupBy('da.id_mesa');

    $personeroLocal = DB::table('personero_local as pl')
        ->join('persona as pe', 'pe.id_persona', '=', 'pl.id_persona')
        ->where('pl.estado', 1)
        ->select(
            'pl.id_local',
            DB::raw("GROUP_CONCAT(CONCAT(pe.nombres, ' ', pe.apellido_paterno, IFNULL(CONCAT(' ', pe.apellido_materno), '')) SEPARATOR ' | ') as personeros_local")
        )
        ->groupBy('pl.id_local');

    $personeroMesa = DB::table('personero_mesa as pm')
        ->join('persona as pe', 'pe.id_persona', '=', 'pm.id_persona')
        ->where('pm.estado', 1)
        ->select(
            'pm.id_mesa',
            DB::raw("GROUP_CONCAT(CONCAT(pe.nombres, ' ', pe.apellido_paterno, IFNULL(CONCAT(' ', pe.apellido_materno), '')) SEPARATOR ' | ') as personeros_mesa")
        )
        ->groupBy('pm.id_mesa');

    $q = DB::table('mesa as m')
        ->join('local as l', 'l.id_local', '=', 'm.id_local')
        ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
        ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
        ->join('region as r', 'r.id_region', '=', 'p.id_region')
        ->leftJoinSub($regional, 'ar', fn($join) => $join->on('ar.id_mesa', '=', 'm.id_mesa'))
        ->leftJoinSub($municipal, 'am', fn($join) => $join->on('am.id_mesa', '=', 'm.id_mesa'))
        ->leftJoinSub($personeroLocal, 'pl', fn($join) => $join->on('pl.id_local', '=', 'l.id_local'))
        ->leftJoinSub($personeroMesa, 'pm', fn($join) => $join->on('pm.id_mesa', '=', 'm.id_mesa'))
        ->where('m.estado', 1)
        ->select(
            'm.id_mesa',
            'm.numero_mesa',
            'm.total_electores',
            'r.nombre as region_nombre',
            'p.nombre as provincia_nombre',
            'd.nombre as distrito_nombre',
            'l.nombre as local_nombre',
            'pl.personeros_local',
            'pm.personeros_mesa',
            'ar.estado_acta as estado_regional',
            'am.estado_acta as estado_municipal',
            'ar.digitador as digitador_regional',
            'am.digitador as digitador_municipal',
            'ar.fecha_registro as fecha_registro_regional',
            'am.fecha_registro as fecha_registro_municipal',
            'ar.fecha_modificacion as fecha_modificacion_regional',
            'am.fecha_modificacion as fecha_modificacion_municipal'
        );

    if (!empty($f['region'])) $q->where('r.id_region', $f['region']);
    if (!empty($f['provincia'])) $q->where('p.id_provincia', $f['provincia']);
    if (!empty($f['distrito'])) $q->where('d.id_distrito', $f['distrito']);
    if (!empty($f['local'])) $q->where('l.id_local', $f['local']);
    if (!empty($f['mesa'])) $q->where('m.id_mesa', $f['mesa']);

    $rows = $q->orderBy('r.nombre')->orderBy('p.nombre')->orderBy('d.nombre')
        ->orderBy('l.nombre')->orderBy('m.numero_mesa')->get();

    return $rows->map(function ($row) use ($f) {
        // Seguimiento clasifica la mesa considerando las dos actas:
        // observada si alguna fue observada; consistente solo si ambas lo son;
        // pendiente si falta al menos una o aún no está consistente.
        $tipoEstado = $this->estadoGeneralMesa($row);

        $row->estado_seguimiento = $tipoEstado ?: 'PENDIENTE';

        $row->digitador = match ($f['eleccion']) {
            'REGIONAL', 'CONSEJERO' => $row->digitador_regional,
            'PROVINCIAL', 'DISTRITAL' => $row->digitador_municipal,
            default => collect([$row->digitador_regional, $row->digitador_municipal])
                ->filter()->unique()->implode(' | '),
        };

        $row->fecha_registro = match ($f['eleccion']) {
            'REGIONAL', 'CONSEJERO' => $row->fecha_registro_regional,
            'PROVINCIAL', 'DISTRITAL' => $row->fecha_registro_municipal,
            default => collect([$row->fecha_registro_regional, $row->fecha_registro_municipal])->filter()->min(),
        };

        $row->fecha_modificacion = match ($f['eleccion']) {
            'REGIONAL', 'CONSEJERO' => $row->fecha_modificacion_regional,
            'PROVINCIAL', 'DISTRITAL' => $row->fecha_modificacion_municipal,
            default => collect([$row->fecha_modificacion_regional, $row->fecha_modificacion_municipal])->filter()->max(),
        };

        return $row;
    })->filter(function ($row) use ($f) {
        return $f['estado'] === 'TODAS' || $row->estado_seguimiento === $f['estado'];
    })->values();
}

private function estadoGeneralMesa($row): string
{
    if ($row->estado_regional === 'OBSERVADA' || $row->estado_municipal === 'OBSERVADA') {
        return 'OBSERVADA';
    }

    if ($row->estado_regional === 'CONSISTENTE' && $row->estado_municipal === 'CONSISTENTE') {
        return 'CONSISTENTE';
    }

    return 'PENDIENTE';
}

public function exportSeguimiento(Request $request)
{
    $f = $this->filters($request);
    $estado = strtoupper((string) $request->get('estado', 'TODAS'));
    $f['estado'] = in_array($estado, ['TODAS', 'PENDIENTE', 'CONSISTENTE', 'OBSERVADA'], true) ? $estado : 'TODAS';

    $rows = $this->seguimientoRows($f);

    $data = $rows->map(function ($r) {
        return [
            $r->region_nombre,
            $r->provincia_nombre,
            $r->distrito_nombre,
            $r->local_nombre,
            $r->personeros_local ?? '',
            $r->numero_mesa,
            $r->personeros_mesa ?? '',
            $r->digitador ?? '',
            $r->estado_seguimiento,
            $r->estado_regional ?? 'PENDIENTE',
            $r->estado_municipal ?? 'PENDIENTE',
            $r->fecha_registro ? \Carbon\Carbon::parse($r->fecha_registro)->format('d/m/Y H:i') : '',
            $r->fecha_modificacion ? \Carbon\Carbon::parse($r->fecha_modificacion)->format('d/m/Y H:i') : '',
        ];
    })->all();

    return SimpleXlsx::download(
        [
            'Región', 'Provincia', 'Distrito', 'Local', 'Personero Local',
            'Mesa', 'Personero de Mesa', 'Digitador', 'Estado',
            'Acta Regional', 'Acta Municipal', 'Fecha Registro', 'Última Modificación'
        ],
        $data,
        'seguimiento_' . now()->format('Ymd_His') . '.xlsx',
        'Seguimiento'
    );
}

    private function normalizedRowsForExport(array $f)
    {
        $rows = $this->partyResults($f);
        $special = $this->specialResults($f);
        $totalValid = max(0, (int) $rows->sum(fn($r) => (int) $r->votos));
        $totalEmitted = max(0, (int) ($special['TOTAL DE VOTOS EMITIDOS'] ?? 0));

        $data = [];
        foreach ($rows as $index => $r) {
            $votes = max(0, (int) $r->votos);
            $data[] = [
                $index + 1,
                $r->nombre,
                $votes,
                $totalValid > 0 ? round(($votes / $totalValid) * 100, 1) . '%' : '0%',
                $totalEmitted > 0 ? round(($votes / $totalEmitted) * 100, 1) . '%' : '0%',
            ];
        }

        $special = $this->specialResults($f);
        $specialRows = [
            'Votos en Blanco' => (int) ($special['VOTOS BLANCOS'] ?? 0),
            'Votos Nulos' => (int) ($special['VOTOS NULOS'] ?? 0),
            'Votos Impugnados' => (int) ($special['VOTOS IMPUGNADOS'] ?? 0),
            'Total de Votos Emitidos' => (int) ($special['TOTAL DE VOTOS EMITIDOS'] ?? 0),
        ];
        $number = count($data) + 1;
        foreach ($specialRows as $label => $value) {
            $data[] = [$number++, $label, $value, '', ''];
        }

        return $data;
    }

    public function export(Request $request)
    {
        $access = $this->reportAccessContext();
        $f = $this->applyReportAccess($this->filters($request), $access);
        $data = $this->normalizedRowsForExport($f);

        return SimpleXlsx::download(
            ['N°', 'Organización Política', 'Votos', 'Votos válidos', 'Votos emitidos'],
            $data,
            'reporte_resultados_' . now()->format('Ymd_His') . '.xlsx',
            'Resultados'
        );
    }

    public function dashboardData(Request $request)
    {
        $access = $this->reportAccessContext();
        $f = $this->applyReportAccess($this->filters($request), $access);
        $rows = $this->partyResults($f);
        $special = $this->specialResults($f);

        $data = $rows->map(fn($r) => [
            $r->nombre,
            (int)($r->votos_regional ?? 0),
            (int)($r->votos_consejero ?? 0),
            (int)($r->votos_provincial ?? 0),
            (int)($r->votos_distrital ?? 0),
        ])->all();

        $data[] = ['VOTOS EN BLANCO', $special['VOTOS BLANCOS'], 0, 0, 0];
        $data[] = ['VOTOS NULOS', $special['VOTOS NULOS'], 0, 0, 0];
        $data[] = ['VOTOS IMPUGNADOS', $special['VOTOS IMPUGNADOS'], 0, 0, 0];
        $data[] = ['TOTAL DE VOTOS EMITIDOS', $special['TOTAL DE VOTOS EMITIDOS'], 0, 0, 0];

        return SimpleXlsx::download(
            ['Organización/Concepto', 'Elecciones Regionales', 'Consejero Regional', 'Elecciones Provinciales', 'Elecciones Distritales'],
            $data,
            'datos_dashboard_' . now()->format('Ymd_His') . '.xlsx',
            'Dashboard'
        );
    }
}
