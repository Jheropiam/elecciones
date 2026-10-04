<?php

namespace App\Http\Controllers;

use App\Support\SimpleXlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalesController extends Controller
{
    private function isAdmin(): bool
    {
        $id = session('id_usuario');
        return $id && DB::table('usuario_rol as ur')
            ->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')
            ->where('ur.id_usuario', $id)->where('ur.estado', 1)->where('r.estado', 1)
            ->whereRaw('LOWER(r.nombre)=LOWER(?)', ['Administrador'])->exists();
    }

    private function ensureAdmin(): void
    {
        abort_unless($this->isAdmin(), 403, 'No tiene permisos para administrar locales.');
    }

    private function norm(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        return mb_strtolower($value, 'UTF-8');
    }

    private function audit(string $accion, ?int $id, $old = null, $new = null): void
    {
        DB::table('auditoria')->insert([
            'id_usuario' => session('id_usuario'),
            'modulo' => 'Locales',
            'tabla_afectada' => 'local',
            'id_registro' => $id,
            'accion' => $accion,
            'valor_anterior' => $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'valor_nuevo' => $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $q = trim((string) $request->query('q', ''));
        $idRegion = $request->query('id_region', '');
        $idProvincia = $request->query('id_provincia', '');
        $idDistrito = $request->query('id_distrito', '');
        $estado = $request->has('estado') && $request->estado !== '' ? (int) $request->estado : null;

        $regions = DB::table('region')->where('estado', 1)->orderBy('nombre')->get();

        $provincias = collect();
        if ($idRegion !== '') {
            $provincias = DB::table('provincia')->where('id_region', $idRegion)->where('estado', 1)->orderBy('nombre')->get();
        }

        $distritos = collect();
        if ($idProvincia !== '') {
            $distritos = DB::table('distrito')->where('id_provincia', $idProvincia)->where('estado', 1)->orderBy('nombre')->get();
        }

        $query = DB::table('local as l')
            ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->select('l.*', 'd.nombre as distrito', 'd.id_distrito', 'p.nombre as provincia',
                'p.id_provincia', 'r.nombre as region', 'r.id_region');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('l.nombre', 'like', "%{$q}%")
                  ->orWhere('l.direccion', 'like', "%{$q}%")
                  ->orWhere('l.referencia', 'like', "%{$q}%");
            });
        }
        if ($idRegion !== '') $query->where('r.id_region', $idRegion);
        if ($idProvincia !== '') $query->where('p.id_provincia', $idProvincia);
        if ($idDistrito !== '') $query->where('d.id_distrito', $idDistrito);
        if ($estado !== null) $query->where('l.estado', $estado);

        $locales = $query->orderBy('r.nombre')->orderBy('p.nombre')->orderBy('d.nombre')->orderBy('l.nombre')
            ->paginate(\App\Support\Pagination::perPage($request, 15))->withQueryString();

        return view('locales.index', compact(
            'locales', 'regions', 'provincias', 'distritos', 'q',
            'idRegion', 'idProvincia', 'idDistrito', 'estado'
        ))->with('sidebarModules', DashboardController::sidebarModules())
          ->with('title', 'Locales');
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'id_region' => ['required', 'integer', 'exists:region,id_region'],
            'id_provincia' => ['required', 'integer', 'exists:provincia,id_provincia'],
            'id_distrito' => ['required', 'integer', 'exists:distrito,id_distrito'],
            'nombre' => ['required', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            '_form' => ['nullable', 'string'],
        ]);

        $territory = DB::table('distrito as d')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->where('d.id_distrito', $data['id_distrito'])
            ->where('p.id_provincia', $data['id_provincia'])
            ->where('p.id_region', $data['id_region'])
            ->exists();

        if (!$territory) {
            return back()->withInput()->with('error', 'La Región, Provincia y Distrito no corresponden entre sí.');
        }

        $nombre = trim(preg_replace('/\s+/u', ' ', $data['nombre']) ?? $data['nombre']);

        $duplicate = DB::table('local')->where('id_distrito', $data['id_distrito'])->get()
            ->first(fn ($l) => $this->norm($l->nombre) === $this->norm($nombre));

        if ($duplicate) {
            return back()->withInput()->with('error', 'El local ya existe en el distrito seleccionado.');
        }

        $id = DB::table('local')->insertGetId([
            'id_distrito' => $data['id_distrito'],
            'nombre' => $nombre,
            'direccion' => $data['direccion'] ?? null,
            'referencia' => $data['referencia'] ?? null,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit('REGISTRAR', $id, null, $data + ['nombre' => $nombre, 'estado' => 1]);

        return redirect()->route('locales.index')->with('success', 'Local registrado correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $this->ensureAdmin();

        $local = DB::table('local')->where('id_local', $id)->first();
        abort_unless($local, 404);

        $data = $request->validate([
            'id_region' => ['required', 'integer', 'exists:region,id_region'],
            'id_provincia' => ['required', 'integer', 'exists:provincia,id_provincia'],
            'id_distrito' => ['required', 'integer', 'exists:distrito,id_distrito'],
            'nombre' => ['required', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            '_form' => ['nullable', 'string'],
        ]);

        $territory = DB::table('distrito as d')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->where('d.id_distrito', $data['id_distrito'])
            ->where('p.id_provincia', $data['id_provincia'])
            ->where('p.id_region', $data['id_region'])
            ->exists();

        if (!$territory) {
            return back()->withInput()->with('error', 'La Región, Provincia y Distrito no corresponden entre sí.');
        }

        $nombre = trim(preg_replace('/\s+/u', ' ', $data['nombre']) ?? $data['nombre']);
        $duplicate = DB::table('local')->where('id_distrito', $data['id_distrito'])
            ->where('id_local', '<>', $id)->get()
            ->first(fn ($l) => $this->norm($l->nombre) === $this->norm($nombre));

        if ($duplicate) {
            return back()->withInput()->with('error', 'El local ya existe en el distrito seleccionado.');
        }

        $new = [
            'id_distrito' => $data['id_distrito'],
            'nombre' => $nombre,
            'direccion' => $data['direccion'] ?? null,
            'referencia' => $data['referencia'] ?? null,
        ];

        DB::table('local')->where('id_local', $id)->update($new + ['updated_at' => now()]);
        $this->audit('EDITAR', $id, (array) $local, $new);

        return redirect()->route('locales.index')->with('success', 'Local actualizado correctamente.');
    }

    public function toggle(int $id)
    {
        $this->ensureAdmin();

        $local = DB::table('local')->where('id_local', $id)->first();
        abort_unless($local, 404);

        $new = (int) !$local->estado;
        DB::table('local')->where('id_local', $id)->update(['estado' => $new, 'updated_at' => now()]);
        $this->audit($new ? 'HABILITAR' : 'DESHABILITAR', $id,
            ['estado' => (int) $local->estado], ['estado' => $new]);

        return back()->with('success', $new ? 'Local habilitado.' : 'Local deshabilitado.');
    }

    public function provincias(int $region)
    {
        $this->ensureAdmin();
        return response()->json(
            DB::table('provincia')->where('id_region', $region)->where('estado', 1)
                ->orderBy('nombre')->get(['id_provincia', 'nombre'])
        );
    }

    public function distritos(int $provincia)
    {
        $this->ensureAdmin();
        return response()->json(
            DB::table('distrito')->where('id_provincia', $provincia)->where('estado', 1)
                ->orderBy('nombre')->get(['id_distrito', 'nombre'])
        );
    }

    public function export(Request $request)
    {
        $this->ensureAdmin();

        $q = trim((string) $request->query('q', ''));
        $idRegion = $request->query('id_region', '');
        $idProvincia = $request->query('id_provincia', '');
        $idDistrito = $request->query('id_distrito', '');
        $estado = $request->has('estado') && $request->estado !== '' ? (int) $request->estado : null;

        $query = DB::table('local as l')
            ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->select('l.*', 'd.nombre as distrito', 'p.nombre as provincia', 'r.nombre as region');

        if ($q !== '') $query->where(function ($w) use ($q) {
            $w->where('l.nombre', 'like', "%{$q}%")->orWhere('l.direccion', 'like', "%{$q}%")
              ->orWhere('l.referencia', 'like', "%{$q}%");
        });
        if ($idRegion !== '') $query->where('r.id_region', $idRegion);
        if ($idProvincia !== '') $query->where('p.id_provincia', $idProvincia);
        if ($idDistrito !== '') $query->where('d.id_distrito', $idDistrito);
        if ($estado !== null) $query->where('l.estado', $estado);

        $rows = [];
        $i = 1;
        foreach ($query->orderBy('r.nombre')->orderBy('p.nombre')->orderBy('d.nombre')->orderBy('l.nombre')->get() as $l) {
            $rows[] = [$i++, $l->region, $l->provincia, $l->distrito, $l->nombre,
                $l->direccion ?? '', $l->referencia ?? '', $l->estado ? 'Activo' : 'Inactivo',
                $l->created_at, $l->updated_at];
        }

        return SimpleXlsx::download(
            ['N°', 'Región', 'Provincia', 'Distrito', 'Local', 'Dirección', 'Referencia', 'Estado', 'Fecha de registro', 'Última actualización'],
            $rows, 'locales.xlsx', 'Locales'
        );
    }

    public function template()
    {
        $this->ensureAdmin();
        return SimpleXlsx::download(
            ['Región', 'Provincia', 'Distrito', 'Local', 'Dirección', 'Referencia', 'Estado'],
            [['Loreto', 'Maynas', 'Belén', 'Ejemplo de local', 'Av. Ejemplo 123', 'Referencia opcional', 'Activo']],
            'plantilla_locales.xlsx', 'Locales'
        );
    }

    public function import(Request $request)
    {
        $this->ensureAdmin();

        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);

        try {
            $rows = SimpleXlsx::read($request->file('archivo')->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo leer el Excel: ' . $e->getMessage());
        }

        $summary = ['procesados' => 0, 'nuevos' => 0, 'duplicados' => 0, 'errores' => 0];
        $errors = [];
        $seen = [];

        foreach (array_slice($rows, 1) as $idx => $row) {
            $excelRow = $idx + 2;
            $summary['procesados']++;

            $regionName = trim((string) ($row[0] ?? ''));
            $provinceName = trim((string) ($row[1] ?? ''));
            $districtName = trim((string) ($row[2] ?? ''));
            $nombre = trim((string) ($row[3] ?? ''));
            $direccion = trim((string) ($row[4] ?? ''));
            $referencia = trim((string) ($row[5] ?? ''));
            $estadoText = trim((string) ($row[6] ?? 'Activo'));
            $reason = null;
            $isDuplicate = false;

            if ($regionName === '' || $provinceName === '' || $districtName === '' || $nombre === '') {
                $reason = 'Región, Provincia, Distrito y Local son obligatorios.';
            } elseif (mb_strlen($nombre) > 200) {
                $reason = 'El nombre del local supera 200 caracteres.';
            } elseif (mb_strlen($direccion) > 255) {
                $reason = 'La dirección supera 255 caracteres.';
            } elseif (mb_strlen($referencia) > 255) {
                $reason = 'La referencia supera 255 caracteres.';
            } elseif (!in_array(mb_strtolower($estadoText, 'UTF-8'), ['activo', 'inactivo', '1', '0'], true)) {
                $reason = 'Estado inválido. Use Activo o Inactivo.';
            }

            $region = $province = $district = null;
            if (!$reason) {
                $region = DB::table('region')->whereRaw('LOWER(TRIM(nombre)) = LOWER(TRIM(?))', [$regionName])->first();
                if (!$region) $reason = 'La región no existe.';
            }
            if (!$reason) {
                $province = DB::table('provincia')->where('id_region', $region->id_region)
                    ->whereRaw('LOWER(TRIM(nombre)) = LOWER(TRIM(?))', [$provinceName])->first();
                if (!$province) $reason = 'La provincia no existe o no pertenece a la región indicada.';
            }
            if (!$reason) {
                $district = DB::table('distrito')->where('id_provincia', $province->id_provincia)
                    ->whereRaw('LOWER(TRIM(nombre)) = LOWER(TRIM(?))', [$districtName])->first();
                if (!$district) $reason = 'El distrito no existe o no pertenece a la provincia indicada.';
            }

            $key = '';
            if ($district) $key = $district->id_distrito . '|' . $this->norm($nombre);

            if (!$reason && isset($seen[$key])) {
                $reason = 'Registro duplicado dentro del Excel.';
                $isDuplicate = true;
            }
            if ($key !== '') $seen[$key] = true;

            if (!$reason && $district) {
                $exists = DB::table('local')->where('id_distrito', $district->id_distrito)->get()
                    ->first(fn ($l) => $this->norm($l->nombre) === $this->norm($nombre));
                if ($exists) {
                    $reason = 'El local ya existe en la base de datos para el distrito indicado.';
                    $isDuplicate = true;
                }
            }

            if ($reason) {
                if ($isDuplicate) $summary['duplicados']++;
                else $summary['errores']++;
                $errors[] = [$excelRow, $regionName, $provinceName, $districtName, $nombre, $direccion, $referencia, $estadoText, $reason];
                continue;
            }

            $estado = in_array(mb_strtolower($estadoText, 'UTF-8'), ['inactivo', '0'], true) ? 0 : 1;

            $id = DB::table('local')->insertGetId([
                'id_distrito' => $district->id_distrito,
                'nombre' => trim(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre),
                'direccion' => $direccion !== '' ? $direccion : null,
                'referencia' => $referencia !== '' ? $referencia : null,
                'estado' => $estado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit('IMPORTAR', $id, null, [
                'region' => $regionName, 'provincia' => $provinceName, 'distrito' => $districtName,
                'nombre' => $nombre, 'estado' => $estado
            ]);
            $summary['nuevos']++;
        }

        $response = back()->with('import_summary', $summary);
        if ($errors) {
            $file = 'errores_locales_' . date('Ymd_His') . '_' . Str::random(6) . '.xlsx';
            $dir = storage_path('app/import_errors');
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            SimpleXlsx::write($dir . '/' . $file,
                ['Fila', 'Región', 'Provincia', 'Distrito', 'Local', 'Dirección', 'Referencia', 'Estado', 'Motivo'],
                $errors, 'Errores');
            $response->with('import_error_file', $file);
        }
        return $response;
    }

    public function errors(string $filename)
    {
        $this->ensureAdmin();
        $path = storage_path('app/import_errors/' . basename($filename));
        abort_unless(is_file($path), 404);
        return response()->download($path, 'errores_locales.xlsx');
    }
}
