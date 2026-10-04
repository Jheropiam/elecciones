<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Support\SimpleXlsx;
use Throwable;

class DigitarActaController extends Controller
{
    private const TIPOS = [
        1 => 'REGIONAL',
        2 => 'MUNICIPAL',
    ];

    private const ESPECIALES = [
        'VOTOS BLANCOS',
        'VOTOS NULOS',
        'VOTOS IMPUGNADOS',
        'TOTAL DE VOTOS EMITIDOS',
    ];

    public function registrar()
    {
        return view('digitar_acta.registrar')
            ->with('sidebarModules', DashboardController::sidebarModules())
            ->with('title', 'Registrar Acta');
    }

    public function registradas(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $estado = trim((string) $request->get('estado', ''));
        $tipo = trim((string) $request->get('tipo', ''));

        $query = DB::table('digitacion_acta as da')
            ->join('mesa as m', 'm.id_mesa', '=', 'da.id_mesa')
            ->join('local as l', 'l.id_local', '=', 'm.id_local')
            ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->join('usuario as u', 'u.id_usuario', '=', 'da.id_usuario_registro')
            ->join('persona as pe', 'pe.id_persona', '=', 'u.id_persona')
            ->select(
                'da.id_mesa',
                'da.numero_acta',
                'da.tipo_acta',
                'da.estado_acta',
                'da.fecha_registro',
                'da.fecha_modificacion',
                'm.total_electores',
                'l.nombre as local_nombre',
                'd.nombre as distrito_nombre',
                'p.nombre as provincia_nombre',
                'r.nombre as region_nombre',
                DB::raw("CONCAT(pe.nombres, ' ', pe.apellido_paterno, IFNULL(CONCAT(' ', pe.apellido_materno), '')) as digitador")
            )
            ->where('da.estado', 1)
            ->whereIn('da.id_digitacion_acta', function ($sub) {
                $sub->select(DB::raw('MIN(id_digitacion_acta)'))
                    ->from('digitacion_acta')
                    ->where('estado', 1)
                    ->groupBy('id_mesa', 'tipo_acta');
            });

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('da.numero_acta', 'like', "%{$q}%")
                  ->orWhere('m.numero_mesa', 'like', "%{$q}%")
                  ->orWhere('l.nombre', 'like', "%{$q}%")
                  ->orWhere('d.nombre', 'like', "%{$q}%");
            });
        }

        if ($estado !== '' && in_array($estado, ['CONSISTENTE', 'OBSERVADA'], true)) {
            $query->where('da.estado_acta', $estado);
        }

        if ($tipo !== '' && in_array($tipo, ['1', '2'], true)) {
            $query->where('da.tipo_acta', (int) $tipo);
        }

        $totalActas = (clone $query)->count();

        $actas = $query
            ->orderByDesc('da.fecha_registro')
            ->paginate(\App\Support\Pagination::perPage($request, 20))
            ->withQueryString();

        $viewData = compact('actas', 'q', 'estado', 'tipo', 'totalActas');

        if ($request->boolean('partial')) {
            return view('digitar_acta.partials.registradas_list', $viewData);
        }

        return view('digitar_acta.registradas', $viewData)
            ->with('sidebarModules', DashboardController::sidebarModules())
            ->with('title', 'Actas Registradas');
    }

    /**
     * Exporta las actas/mesas que aparecen como registradas, respetando los filtros.
     */
    public function exportRegistradas(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $estado = trim((string) $request->get('estado', ''));
        $tipo = trim((string) $request->get('tipo', ''));

        $query = DB::table('digitacion_acta as da')
            ->join('mesa as m', 'm.id_mesa', '=', 'da.id_mesa')
            ->join('local as l', 'l.id_local', '=', 'm.id_local')
            ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->join('usuario as u', 'u.id_usuario', '=', 'da.id_usuario_registro')
            ->join('persona as pe', 'pe.id_persona', '=', 'u.id_persona')
            ->select(
                'da.numero_acta', 'da.tipo_acta', 'da.estado_acta', 'da.fecha_registro', 'da.fecha_modificacion',
                'm.numero_mesa', 'm.total_electores', 'l.nombre as local_nombre', 'l.direccion as local_direccion',
                'd.nombre as distrito_nombre', 'p.nombre as provincia_nombre', 'r.nombre as region_nombre',
                DB::raw("CONCAT(pe.nombres, ' ', pe.apellido_paterno, IFNULL(CONCAT(' ', pe.apellido_materno), '')) as digitador")
            )
            ->where('da.estado', 1)
            ->whereIn('da.id_digitacion_acta', function ($sub) {
                $sub->select(DB::raw('MIN(id_digitacion_acta)'))
                    ->from('digitacion_acta')
                    ->where('estado', 1)
                    ->groupBy('id_mesa', 'tipo_acta');
            });

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('da.numero_acta', 'like', "%{$q}%")
                    ->orWhere('m.numero_mesa', 'like', "%{$q}%")
                    ->orWhere('l.nombre', 'like', "%{$q}%")
                    ->orWhere('d.nombre', 'like', "%{$q}%");
            });
        }
        if (in_array($estado, ['CONSISTENTE', 'OBSERVADA'], true)) {
            $query->where('da.estado_acta', $estado);
        }
        if (in_array($tipo, ['1', '2'], true)) {
            $query->where('da.tipo_acta', (int) $tipo);
        }

        $rows = $query->orderByDesc('da.fecha_registro')->get()->map(function ($a) {
            return [
                $a->numero_acta,
                $a->numero_mesa,
                $a->tipo_acta == 1 ? 'Regional' : 'Municipal',
                $a->region_nombre,
                $a->provincia_nombre,
                $a->distrito_nombre,
                $a->local_nombre,
                $a->local_direccion,
                $a->total_electores,
                $a->estado_acta,
                $a->digitador,
                optional($a->fecha_registro) ? \Carbon\Carbon::parse($a->fecha_registro)->format('d/m/Y H:i') : '',
                optional($a->fecha_modificacion) ? \Carbon\Carbon::parse($a->fecha_modificacion)->format('d/m/Y H:i') : '',
            ];
        })->all();

        return SimpleXlsx::download(
            ['N° Acta', 'Mesa', 'Tipo de Acta', 'Región', 'Provincia', 'Distrito', 'Local', 'Dirección', 'Total Electores', 'Estado', 'Digitador', 'Fecha Registro', 'Última Modificación'],
            $rows,
            'actas_registradas_' . now()->format('Ymd_His') . '.xlsx',
            'Actas Registradas'
        );
    }

    /**
     * Valida automáticamente una mesa al completar sus 6 dígitos.
     */
    public function buscarMesa(string $numero)
    {
        $numero = trim($numero);

        if (!preg_match('/^\d{6}$/', $numero)) {
            return response()->json([
                'ok' => false,
                'message' => 'Ingrese exactamente 6 dígitos.',
            ], 422);
        }

        $mesas = DB::table('mesa as m')
            ->join('local as l', 'l.id_local', '=', 'm.id_local')
            ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->where('m.numero_mesa', $numero)
            ->where('m.estado', 1)
            ->select(
                'm.id_mesa',
                'm.numero_mesa',
                'm.total_electores',
                'l.nombre as local_nombre',
                'l.direccion as local_direccion',
                'l.referencia as local_referencia',
                'd.nombre as distrito_nombre',
                'p.nombre as provincia_nombre',
                'r.nombre as region_nombre'
            )
            ->get();

        if ($mesas->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => "Mesa {$numero} no encontrada.",
            ], 404);
        }

        if ($mesas->count() > 1) {
            return response()->json([
                'ok' => false,
                'message' => "El número de mesa {$numero} aparece en más de un local. Revise la configuración de mesas.",
            ], 409);
        }

        $mesa = $mesas->first();

        $registradas = DB::table('digitacion_acta')
            ->where('id_mesa', $mesa->id_mesa)
            ->where('estado', 1)
            ->pluck('tipo_acta')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'message' => "Mesa {$numero} encontrada",
            'mesa' => [
                'id_mesa' => $mesa->id_mesa,
                'numero_mesa' => $mesa->numero_mesa,
                'total_electores' => (int) $mesa->total_electores,
                'local_nombre' => $mesa->local_nombre,
                'local_direccion' => $mesa->local_direccion,
                'local_referencia' => $mesa->local_referencia,
                'distrito_nombre' => $mesa->distrito_nombre,
                'provincia_nombre' => $mesa->provincia_nombre,
                'region_nombre' => $mesa->region_nombre,
            ],
            'registradas' => $registradas,
            'puede_registrar' => count($registradas) < 2,
        ]);
    }

    /**
     * Carga el acta solo después de validar la mesa y seleccionar el tipo.
     */
    public function formulario(string $numero, int $tipo)
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        $mesa = $this->findMesa($numero);

        $yaExiste = DB::table('digitacion_acta')
            ->where('id_mesa', $mesa->id_mesa)
            ->where('tipo_acta', $tipo)
            ->where('estado', 1)
            ->exists();

        if ($yaExiste) {
            return redirect()->route('digitar-acta.registrar')
                ->with('error', 'El acta seleccionada ya fue registrada para esta mesa.');
        }

        $partidos = DB::table('partido')
            ->where('tipo', 'POLITICO')
            ->where('estado', 1)
            ->orderBy('id_partido')
            ->get();

        $especiales = DB::table('partido')
            ->where('tipo', 'ESPECIAL')
            ->whereIn('nombre', self::ESPECIALES)
            ->get()
            ->keyBy('nombre');

        return view('digitar_acta.formulario', [
            'mesa' => $mesa,
            'tipo' => $tipo,
            'tipoNombre' => self::TIPOS[$tipo],
            'partidos' => $partidos,
            'especiales' => $especiales,
        ])->with('sidebarModules', DashboardController::sidebarModules())
          ->with('title', 'Registrar Acta');
    }

    /**
     * Muestra un acta registrada para su revisión/edición.
     * Administradores pueden editar cualquiera; los demás usuarios solo
     * pueden editar las actas que ellos mismos registraron.
     */
    public function editar(string $numero, int $tipo)
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        $mesa = $this->findMesa($numero);
        $rows = DB::table('digitacion_acta as da')
            ->join('partido as p', 'p.id_partido', '=', 'da.id_partido')
            ->where('da.id_mesa', $mesa->id_mesa)
            ->where('da.tipo_acta', $tipo)
            ->where('da.estado', 1)
            ->orderBy('da.id_digitacion_acta')
            ->get([
                'da.id_digitacion_acta', 'da.id_partido', 'da.campo_1', 'da.campo_2',
                'da.estado_acta', 'da.detalle_observacion', 'da.id_usuario_registro',
                'da.fecha_registro', 'da.fecha_modificacion', 'p.nombre',
                'p.tipo as partido_tipo', 'p.logo', 'p.estado as partido_estado',
            ]);

        abort_if($rows->isEmpty(), 404);

        $ownerId = (int) $rows->first()->id_usuario_registro;
        if (!\App\Support\AccessControl::isAdmin() && $ownerId !== (int) session('id_usuario')) {
            abort(403, 'Solo puede editar las actas que usted registró.');
        }

        $partidos = $rows->filter(fn ($row) => $row->partido_tipo === 'POLITICO')->values();
        $especialRows = $rows->filter(fn ($row) => $row->partido_tipo === 'ESPECIAL')->keyBy('nombre');

        // Se muestran exactamente las organizaciones que forman parte del acta.
        // Esto permite editar incluso si una organización fue deshabilitada después.

        $especiales = collect(self::ESPECIALES)->mapWithKeys(function ($name) use ($especialRows) {
            if ($especialRows->has($name)) return [$name => $especialRows->get($name)];
            $p = DB::table('partido')->where('tipo', 'ESPECIAL')->where('nombre', $name)->first();
            if ($p) {
                $p->campo_1 = 0;
                $p->campo_2 = 0;
                return [$name => $p];
            }
            return [$name => null];
        });

        $valores = [];
        foreach ($rows as $row) {
            $valores[(string) $row->id_partido] = [
                1 => (int) $row->campo_1,
                2 => (int) $row->campo_2,
            ];
        }
        foreach ($partidos as $partido) {
            $valores[(string) $partido->id_partido] ??= [1 => 0, 2 => 0];
        }
        foreach ($especiales as $especial) {
            if ($especial) $valores[(string) $especial->id_partido] ??= [1 => 0, 2 => 0];
        }

        $observaciones = [];
        $detalle = $rows->first()->detalle_observacion;
        if ($detalle) {
            $decoded = json_decode((string) $detalle, true);
            if (is_array($decoded)) $observaciones = $decoded;
        }

        return view('digitar_acta.formulario', [
            'mesa' => $mesa,
            'tipo' => $tipo,
            'tipoNombre' => self::TIPOS[$tipo],
            'partidos' => $partidos,
            'especiales' => $especiales,
            'valores' => $valores,
            'observaciones' => $observaciones,
            'modoEdicion' => true,
            'estadoActa' => $rows->first()->estado_acta,
            'fechaRegistro' => $rows->first()->fecha_registro,
            'fechaModificacion' => $rows->first()->fecha_modificacion,
        ])->with('sidebarModules', DashboardController::sidebarModules())
          ->with('title', 'Editar Acta');
    }

    /**
     * Actualiza todas las filas que componen un acta, manteniendo la
     * trazabilidad del usuario que la registró y del usuario que la modificó.
     */
    public function actualizar(Request $request, string $numero, int $tipo)
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        $validator = Validator::make($request->all(), [
            'votos' => ['required', 'array'],
        ]);
        if ($validator->fails()) return back()->withErrors($validator)->withInput();

        $mesa = $this->findMesa($numero);
        $rows = DB::table('digitacion_acta')
            ->where('id_mesa', $mesa->id_mesa)
            ->where('tipo_acta', $tipo)
            ->where('estado', 1)
            ->orderBy('id_digitacion_acta')
            ->get();
        abort_if($rows->isEmpty(), 404);

        $ownerId = (int) $rows->first()->id_usuario_registro;
        if (!\App\Support\AccessControl::isAdmin() && $ownerId !== (int) session('id_usuario')) {
            abort(403, 'Solo puede editar las actas que usted registró.');
        }

        $partidoIds = $rows->pluck('id_partido')->map(fn ($v) => (int) $v)->all();
        $partidos = DB::table('partido')->whereIn('id_partido', $partidoIds)->where('tipo', 'POLITICO')->get();
        $especiales = DB::table('partido')->whereIn('id_partido', $partidoIds)->where('tipo', 'ESPECIAL')->get()->keyBy('nombre');

        $idBlancos = (int) optional($especiales->get('VOTOS BLANCOS'))->id_partido;
        $idNulos = (int) optional($especiales->get('VOTOS NULOS'))->id_partido;
        $idImpugnados = (int) optional($especiales->get('VOTOS IMPUGNADOS'))->id_partido;
        $idTotal = (int) optional($especiales->get('TOTAL DE VOTOS EMITIDOS'))->id_partido;
        abort_if(!$idBlancos || !$idNulos || !$idImpugnados || !$idTotal, 422, 'El acta no contiene todos los conceptos especiales requeridos.');

        $clean = [];
        foreach ($partidoIds as $id) {
            $row = $request->input('votos.' . $id, []);
            foreach ([1, 2] as $campo) {
                $value = trim((string) ($row[$campo] ?? 0));
                if ($value === '') $value = '0';
                if (!preg_match('/^\d+$/', $value) || (int) $value > 999999) {
                    return back()->withInput()->with('error', 'Todos los votos deben ser números enteros mayores o iguales a cero.');
                }
                $clean[$id][$campo] = (int) $value;
            }
        }

        $observaciones = [];
        foreach ([1, 2] as $campo) {
            $sum = 0;
            foreach ($partidos as $partido) $sum += $clean[$partido->id_partido][$campo] ?? 0;
            $sum += $clean[$idBlancos][$campo] ?? 0;
            $sum += $clean[$idNulos][$campo] ?? 0;
            $sum += $clean[$idImpugnados][$campo] ?? 0;
            $total = $clean[$idTotal][$campo] ?? 0;
            if ($sum !== $total) $observaciones[$campo][] = 'La suma de votos no coincide con el Total de Votos Emitidos.';
            if ($total > (int) $mesa->total_electores) $observaciones[$campo][] = "El Total de Votos Emitidos no puede superar el total de electores de la mesa ({$mesa->total_electores}).";
        }

        $estadoActa = empty($observaciones) ? 'CONSISTENTE' : 'OBSERVADA';
        $suma1 = $suma2 = 0;
        foreach ($partidos as $partido) {
            $suma1 += $clean[$partido->id_partido][1] ?? 0;
            $suma2 += $clean[$partido->id_partido][2] ?? 0;
        }
        $suma1 += ($clean[$idBlancos][1] ?? 0) + ($clean[$idNulos][1] ?? 0) + ($clean[$idImpugnados][1] ?? 0);
        $suma2 += ($clean[$idBlancos][2] ?? 0) + ($clean[$idNulos][2] ?? 0) + ($clean[$idImpugnados][2] ?? 0);

        $usuarioId = (int) session('id_usuario');
        DB::transaction(function () use ($rows, $clean, $estadoActa, $observaciones, $suma1, $suma2, $usuarioId) {
            $now = now();
            foreach ($rows as $row) {
                DB::table('digitacion_acta')->where('id_digitacion_acta', $row->id_digitacion_acta)->update([
                    'campo_1' => $clean[$row->id_partido][1] ?? 0,
                    'campo_2' => $clean[$row->id_partido][2] ?? 0,
                    'estado_acta' => $estadoActa,
                    'suma_campo_1' => $suma1,
                    'suma_campo_2' => $suma2,
                    'detalle_observacion' => empty($observaciones) ? null : json_encode($observaciones, JSON_UNESCAPED_UNICODE),
                    'id_usuario_modificacion' => $usuarioId,
                    'fecha_modificacion' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('auditoria')->insert([
                'id_usuario' => $usuarioId,
                'modulo' => 'Digitar Acta',
                'tabla_afectada' => 'digitacion_acta',
                'id_registro' => $rows->first()->id_mesa,
                'accion' => 'EDITAR',
                'valor_anterior' => json_encode($rows->mapWithKeys(fn ($r) => [$r->id_partido => ['campo_1' => $r->campo_1, 'campo_2' => $r->campo_2, 'estado_acta' => $r->estado_acta]])->all(), JSON_UNESCAPED_UNICODE),
                'valor_nuevo' => json_encode(['estado_acta' => $estadoActa, 'observaciones' => $observaciones, 'suma_campo_1' => $suma1, 'suma_campo_2' => $suma2], JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'created_at' => $now,
            ]);
        });

        $mensaje = $estadoActa === 'CONSISTENTE'
            ? 'Acta actualizada correctamente como CONSISTENTE.'
            : 'Acta actualizada correctamente como OBSERVADA. Revise las validaciones.';
        return redirect()->route('digitar-acta.registradas')->with($estadoActa === 'CONSISTENTE' ? 'success' : 'warning', $mensaje);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_mesa' => ['required', 'integer'],
            'numero_acta' => ['required', 'digits:6'],
            'tipo_acta' => ['required', 'integer', 'in:1,2'],
            'votos' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $mesa = DB::table('mesa')->where('id_mesa', $data['id_mesa'])
            ->where('numero_mesa', $data['numero_acta'])
            ->where('estado', 1)
            ->first();

        if (!$mesa) {
            return back()->withInput()->with('error', 'La mesa indicada no existe, está inactiva o no coincide con el número de acta.');
        }

        $exists = DB::table('digitacion_acta')
            ->where('id_mesa', $mesa->id_mesa)
            ->where('tipo_acta', (int) $data['tipo_acta'])
            ->where('estado', 1)
            ->exists();

        if ($exists) {
            return redirect()->route('digitar-acta.registrar')
                ->with('error', 'El acta seleccionada ya fue registrada para esta mesa.');
        }

        $partidos = DB::table('partido')
            ->where('tipo', 'POLITICO')
            ->where('estado', 1)
            ->orderBy('id_partido')
            ->get();

        $especiales = DB::table('partido')
            ->where('tipo', 'ESPECIAL')
            ->whereIn('nombre', self::ESPECIALES)
            ->get()
            ->keyBy('nombre');

        foreach (self::ESPECIALES as $nombre) {
            if (!isset($especiales[$nombre])) {
                return back()->withInput()->with('error', "Falta el concepto especial {$nombre} en la tabla de partidos.");
            }
        }

        $expectedIds = $partidos->pluck('id_partido')
            ->merge($especiales->pluck('id_partido'))
            ->map(fn ($v) => (string) $v)
            ->all();

        $votes = $data['votos'];
        $clean = [];

        foreach ($expectedIds as $id) {
            $row = $votes[$id] ?? [];
            foreach ([1, 2] as $campo) {
                $value = $row[$campo] ?? 0;
                $value = trim((string) $value);
                if ($value === '') $value = '0';

                if (!preg_match('/^\d+$/', $value) || (int) $value > 999999) {
                    return back()->withInput()->with('error', 'Todos los votos deben ser números enteros mayores o iguales a cero.');
                }

                $clean[$id][$campo] = (int) $value;
            }
        }

        // Cualquier ID adicional enviado por el navegador se ignora.
        $idBlancos = (int) $especiales['VOTOS BLANCOS']->id_partido;
        $idNulos = (int) $especiales['VOTOS NULOS']->id_partido;
        $idImpugnados = (int) $especiales['VOTOS IMPUGNADOS']->id_partido;
        $idTotal = (int) $especiales['TOTAL DE VOTOS EMITIDOS']->id_partido;

        $observaciones = [];
        foreach ([1, 2] as $campo) {
            $sum = 0;
            foreach ($partidos as $partido) {
                $sum += $clean[$partido->id_partido][$campo] ?? 0;
            }
            $sum += $clean[$idBlancos][$campo] ?? 0;
            $sum += $clean[$idNulos][$campo] ?? 0;
            $sum += $clean[$idImpugnados][$campo] ?? 0;

            $total = $clean[$idTotal][$campo] ?? 0;

            if ($sum !== $total) {
                $observaciones[$campo][] = "La suma de votos no coincide con el Total de Votos Emitidos.";
            }

            if ($total > (int) $mesa->total_electores) {
                $observaciones[$campo][] = "El Total de Votos Emitidos no puede superar el total de electores de la mesa ({$mesa->total_electores}).";
            }
        }

        $estadoActa = empty($observaciones) ? 'CONSISTENTE' : 'OBSERVADA';
        $suma1 = 0;
        $suma2 = 0;

        foreach ($partidos as $partido) {
            $suma1 += $clean[$partido->id_partido][1];
            $suma2 += $clean[$partido->id_partido][2];
        }
        $suma1 += $clean[$idBlancos][1] + $clean[$idNulos][1] + $clean[$idImpugnados][1];
        $suma2 += $clean[$idBlancos][2] + $clean[$idNulos][2] + $clean[$idImpugnados][2];

        $usuarioId = (int) session('id_usuario');

        DB::transaction(function () use (
            $mesa, $data, $partidos, $especiales, $clean, $estadoActa,
            $observaciones, $suma1, $suma2, $usuarioId
        ) {
            $now = now();

            foreach ($partidos as $partido) {
                DB::table('digitacion_acta')->insert([
                    'id_mesa' => $mesa->id_mesa,
                    'numero_acta' => $data['numero_acta'],
                    'tipo_acta' => (int) $data['tipo_acta'],
                    'id_partido' => $partido->id_partido,
                    'campo_1' => $clean[$partido->id_partido][1],
                    'campo_2' => $clean[$partido->id_partido][2],
                    'estado_acta' => $estadoActa,
                    'suma_campo_1' => $suma1,
                    'suma_campo_2' => $suma2,
                    'detalle_observacion' => empty($observaciones) ? null : json_encode($observaciones, JSON_UNESCAPED_UNICODE),
                    'id_usuario_registro' => $usuarioId,
                    'fecha_registro' => $now,
                    'estado' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($especiales as $especial) {
                DB::table('digitacion_acta')->insert([
                    'id_mesa' => $mesa->id_mesa,
                    'numero_acta' => $data['numero_acta'],
                    'tipo_acta' => (int) $data['tipo_acta'],
                    'id_partido' => $especial->id_partido,
                    'campo_1' => $clean[$especial->id_partido][1],
                    'campo_2' => $clean[$especial->id_partido][2],
                    'estado_acta' => $estadoActa,
                    'suma_campo_1' => $suma1,
                    'suma_campo_2' => $suma2,
                    'detalle_observacion' => empty($observaciones) ? null : json_encode($observaciones, JSON_UNESCAPED_UNICODE),
                    'id_usuario_registro' => $usuarioId,
                    'fecha_registro' => $now,
                    'estado' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('auditoria')->insert([
                'id_usuario' => $usuarioId,
                'modulo' => 'Digitar Acta',
                'tabla_afectada' => 'digitacion_acta',
                'id_registro' => $mesa->id_mesa,
                'accion' => 'REGISTRAR',
                'valor_anterior' => null,
                'valor_nuevo' => json_encode([
                    'id_mesa' => $mesa->id_mesa,
                    'numero_acta' => $data['numero_acta'],
                    'tipo_acta' => (int) $data['tipo_acta'],
                    'estado_acta' => $estadoActa,
                    'observaciones' => $observaciones,
                ], JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        });

        $mensaje = $estadoActa === 'CONSISTENTE'
            ? 'Acta registrada correctamente como CONSISTENTE.'
            : 'Acta registrada correctamente como OBSERVADA. Revise las validaciones antes de contabilizarla.';

        return redirect()->route('digitar-acta.registradas')->with(
            $estadoActa === 'CONSISTENTE' ? 'success' : 'warning',
            $mensaje
        );
    }

    private function findMesa(string $numero)
    {
        abort_unless(preg_match('/^\d{6}$/', $numero), 404);

        $mesas = DB::table('mesa as m')
            ->join('local as l', 'l.id_local', '=', 'm.id_local')
            ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->where('m.numero_mesa', $numero)
            ->where('m.estado', 1)
            ->select(
                'm.*',
                'l.nombre as local_nombre',
                'l.direccion as local_direccion',
                'l.referencia as local_referencia',
                'd.nombre as distrito_nombre',
                'p.nombre as provincia_nombre',
                'r.nombre as region_nombre'
            )
            ->get();

        abort_if($mesas->count() !== 1, 404);

        return $mesas->first();
    }
}
