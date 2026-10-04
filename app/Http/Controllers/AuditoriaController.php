<?php
namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\SimpleXlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditoriaController extends Controller
{
    private function isAdmin(): bool
    {
        $id = session('id_usuario');
        return $id && DB::table('usuario_rol as ur')
            ->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')
            ->where('ur.id_usuario', $id)
            ->where('ur.estado', 1)
            ->where('r.estado', 1)
            ->whereRaw('LOWER(r.nombre)=LOWER(?)', ['Administrador'])
            ->exists();
    }

    private function ensureAdmin(): void
    {
        abort_unless($this->isAdmin(), 403, 'No tiene permisos para consultar la auditoría y trazabilidad.');
    }

    private function baseQuery(Request $request)
    {
        $query = DB::table('auditoria as a')
            ->leftJoin('usuario as u', 'u.id_usuario', '=', 'a.id_usuario')
            ->leftJoin('persona as p', 'p.id_persona', '=', 'u.id_persona')
            ->select(
                'a.*',
                'u.usuario',
                'p.dni',
                'p.nombres',
                'p.apellido_paterno',
                'p.apellido_materno'
            );

        $q = trim((string) $request->query('q', ''));
        $modulo = trim((string) $request->query('modulo', ''));
        $accion = trim((string) $request->query('accion', ''));
        $tabla = trim((string) $request->query('tabla', ''));
        $idUsuario = $request->query('id_usuario');
        $idRegistro = trim((string) $request->query('id_registro', ''));
        $fechaDesde = trim((string) $request->query('fecha_desde', ''));
        $fechaHasta = trim((string) $request->query('fecha_hasta', ''));

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('a.accion', 'like', $like)
                    ->orWhere('a.tabla_afectada', 'like', $like)
                    ->orWhere('a.modulo', 'like', $like)
                    ->orWhere('a.ip', 'like', $like)
                    ->orWhere('a.id_registro', 'like', $like)
                    ->orWhere('p.dni', 'like', $like)
                    ->orWhere('p.nombres', 'like', $like)
                    ->orWhere('p.apellido_paterno', 'like', $like)
                    ->orWhere('p.apellido_materno', 'like', $like)
                    ->orWhere('u.usuario', 'like', $like);
            });
        }
        if ($modulo !== '') $query->where('a.modulo', $modulo);
        if ($accion !== '') $query->where('a.accion', $accion);
        if ($tabla !== '') $query->where('a.tabla_afectada', $tabla);
        if ($idUsuario !== '' && $idUsuario !== null) $query->where('a.id_usuario', (int) $idUsuario);
        if ($idRegistro !== '') $query->where('a.id_registro', (int) $idRegistro);
        if ($fechaDesde !== '') $query->whereDate('a.created_at', '>=', $fechaDesde);
        if ($fechaHasta !== '') $query->whereDate('a.created_at', '<=', $fechaHasta);

        return $query;
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $auditorias = $this->baseQuery($request)
            ->orderByDesc('a.created_at')
            ->orderByDesc('a.id_auditoria')
            ->paginate(\App\Support\Pagination::perPage($request, 20))
            ->withQueryString();

        $usuarios = DB::table('usuario as u')
            ->join('persona as p', 'p.id_persona', '=', 'u.id_persona')
            ->select('u.id_usuario', 'p.dni', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno')
            ->orderBy('p.apellido_paterno')->orderBy('p.nombres')->get();

        $modulos = DB::table('auditoria')->whereNotNull('modulo')->distinct()->orderBy('modulo')->pluck('modulo');
        $acciones = DB::table('auditoria')->whereNotNull('accion')->distinct()->orderBy('accion')->pluck('accion');
        $tablas = DB::table('auditoria')->whereNotNull('tabla_afectada')->distinct()->orderBy('tabla_afectada')->pluck('tabla_afectada');

        $resumen = [
            'total' => DB::table('auditoria')->count(),
            'hoy' => DB::table('auditoria')->whereDate('created_at', now()->toDateString())->count(),
            'usuarios' => DB::table('auditoria')->whereNotNull('id_usuario')->distinct('id_usuario')->count('id_usuario'),
            'acciones' => DB::table('auditoria')->distinct('accion')->count('accion'),
        ];

        return view('auditoria.index', compact(
            'auditorias', 'usuarios', 'modulos', 'acciones', 'tablas', 'resumen'
        ))
            ->with('sidebarModules', DashboardController::sidebarModules())
            ->with('title', 'Auditoría y Trazabilidad');
    }

    public function export(Request $request)
    {
        $this->ensureAdmin();

        $rows = $this->baseQuery($request)
            ->orderBy('a.created_at')
            ->orderBy('a.id_auditoria')
            ->get();

        $data = $rows->map(function ($a, $i) {
            $nombre = trim(implode(' ', array_filter([
                $a->nombres,
                $a->apellido_paterno,
                $a->apellido_materno,
            ])));
            return [
                $i + 1,
                $a->created_at ? \Carbon\Carbon::parse($a->created_at)->format('d/m/Y H:i:s') : '',
                $a->dni ?: ($a->usuario ?: 'Sistema'),
                $nombre,
                $a->modulo,
                $a->accion,
                $a->tabla_afectada,
                $a->id_registro,
                $a->ip,
                $a->valor_anterior,
                $a->valor_nuevo,
            ];
        })->all();

        AuditLogger::log('Auditoría y Trazabilidad', 'auditoria', null, 'EXPORTAR', null, [
            'registros_exportados' => count($data),
            'filtros' => $request->only(['q','modulo','accion','tabla','id_usuario','id_registro','fecha_desde','fecha_hasta']),
        ]);

        return SimpleXlsx::download([
            'N°','Fecha y hora','DNI/Usuario','Usuario','Módulo','Acción','Tabla','ID registro','IP','Valor anterior','Valor nuevo'
        ], $data, 'auditoria_' . now()->format('Ymd_His') . '.xlsx', 'Auditoría');
    }

    public function detail(int $id)
    {
        $this->ensureAdmin();

        $row = DB::table('auditoria as a')
            ->leftJoin('usuario as u', 'u.id_usuario', '=', 'a.id_usuario')
            ->leftJoin('persona as p', 'p.id_persona', '=', 'u.id_persona')
            ->select('a.*', 'u.usuario', 'p.dni', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno')
            ->where('a.id_auditoria', $id)
            ->first();

        abort_unless($row, 404);

        return response()->json([
            'id_auditoria' => $row->id_auditoria,
            'fecha' => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i:s') : '',
            'usuario' => trim(implode(' ', array_filter([$row->dni ?: $row->usuario, $row->nombres, $row->apellido_paterno, $row->apellido_materno]))) ?: 'Sistema',
            'modulo' => $row->modulo,
            'accion' => $row->accion,
            'tabla' => $row->tabla_afectada,
            'id_registro' => $row->id_registro,
            'ip' => $row->ip,
            'user_agent' => $row->user_agent,
            'valor_anterior' => $this->prettyJson($row->valor_anterior),
            'valor_nuevo' => $this->prettyJson($row->valor_nuevo),
        ]);
    }

    private function prettyJson($value): ?string
    {
        if ($value === null || $value === '') return null;
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $value;
    }
}
