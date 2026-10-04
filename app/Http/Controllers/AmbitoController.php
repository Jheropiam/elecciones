<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Support\SimpleXlsx;
use Illuminate\Support\Str;
use App\Support\AuditLogger;

class AmbitoController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['region', 'provincia', 'distrito'], true)
            ? $request->query('tab') : 'region';

        $qRegion = trim((string) $request->query('q_region', ''));
        $qProvincia = trim((string) $request->query('q_provincia', ''));
        $qDistrito = trim((string) $request->query('q_distrito', ''));

        $regiones = DB::table('region')
            ->when($qRegion !== '', fn($q) => $q->where('nombre', 'like', "%{$qRegion}%"))
            ->orderBy('nombre')->paginate(\App\Support\Pagination::perPage($request, 10), ['*'], 'page_region')->withQueryString();

        $provincias = DB::table('provincia as p')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->select('p.*', 'r.nombre as region_nombre')
            ->when($qProvincia !== '', fn($q) => $q->where('p.nombre', 'like', "%{$qProvincia}%"))
            ->orderBy('r.nombre')->orderBy('p.nombre')
            ->paginate(\App\Support\Pagination::perPage($request, 10), ['*'], 'page_provincia')->withQueryString();

        $distritos = DB::table('distrito as d')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->select('d.*', 'p.nombre as provincia_nombre', 'r.nombre as region_nombre')
            ->when($qDistrito !== '', fn($q) => $q->where('d.nombre', 'like', "%{$qDistrito}%"))
            ->orderBy('r.nombre')->orderBy('p.nombre')->orderBy('d.nombre')
            ->paginate(\App\Support\Pagination::perPage($request, 10), ['*'], 'page_distrito')->withQueryString();

        $allRegions = DB::table('region')->where('estado', 1)->orderBy('nombre')->get();
        $allProvinces = DB::table('provincia')->where('estado', 1)->orderBy('nombre')->get();

        return view('ambito.index', compact(
            'tab', 'regiones', 'provincias', 'distritos',
            'allRegions', 'allProvinces', 'qRegion', 'qProvincia', 'qDistrito'
        ))->with('sidebarModules', DashboardController::sidebarModules())
          ->with('title', 'Ámbito');
    }


    public function exportRegiones()
    {
        $regiones = DB::table('region')->orderBy('nombre')->get();

        $rows = $regiones->map(function ($region, $index) {
            return [
                $index + 1,
                $region->nombre,
                $region->estado ? 'Activo' : 'Inactivo',
                ($region->created_at ? \Carbon\Carbon::parse($region->created_at)->format('d/m/Y H:i') : ''),
                ($region->updated_at ? \Carbon\Carbon::parse($region->updated_at)->format('d/m/Y H:i') : ''),
            ];
        })->all();

        return SimpleXlsx::download(
            ['N°', 'Región', 'Estado', 'Fecha de registro', 'Última actualización'],
            $rows,
            'regiones_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function plantillaRegiones()
    {
        return SimpleXlsx::download(
            ['Región'],
            [],
            'plantilla_regiones.xlsx'
        );
    }

    public function importRegiones(Request $request)
    {
        $request->validate([
            'archivo_regiones' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ], [
            'archivo_regiones.required' => 'Seleccione un archivo Excel.',
            'archivo_regiones.mimes' => 'El archivo debe estar en formato Excel .xlsx.',
            'archivo_regiones.max' => 'El archivo no debe superar los 5 MB.',
        ]);

        try {
            $rows = SimpleXlsx::read($request->file('archivo_regiones')->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('import_error', 'No se pudo leer el archivo Excel: ' . $e->getMessage());
        }

        if (count($rows) === 0) {
            return back()->with('import_error', 'El archivo Excel está vacío.');
        }

        $header = array_map(fn($v) => $this->normalizeImportValue($v), $rows[0]);
        if (!isset($header[0]) || !in_array($header[0], ['region', 'región'], true)) {
            return back()->with('import_error', 'La plantilla no es válida. La primera columna debe llamarse "Región".');
        }

        $procesados = 0;
        $nuevos = 0;
        $duplicados = 0;
        $errores = 0;
        $errorRows = [];
        $seen = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $excelRow = $index + 2;
            $nombre = trim((string) ($row[0] ?? ''));
            $procesados++;

            if ($nombre === '') {
                $errores++;
                $errorRows[] = [$excelRow, $nombre, 'El nombre de la región está vacío.'];
                continue;
            }

            if (mb_strlen($nombre) > 150) {
                $errores++;
                $errorRows[] = [$excelRow, $nombre, 'El nombre de la región supera los 150 caracteres.'];
                continue;
            }

            $key = $this->normalizeImportValue($nombre);
            if (isset($seen[$key])) {
                $duplicados++;
                $errorRows[] = [$excelRow, $nombre, 'La región está repetida dentro del archivo Excel.'];
                continue;
            }
            $seen[$key] = true;

            $exists = DB::table('region')
                ->whereRaw('LOWER(TRIM(nombre)) = LOWER(?)', [$nombre])
                ->exists();

            if ($exists) {
                $duplicados++;
                $errorRows[] = [$excelRow, $nombre, 'La región ya existe en la base de datos.'];
                continue;
            }

            DB::table('region')->insert([
                'nombre' => $nombre,
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $nuevos++;
        }

        $errorFile = null;
        if ($errorRows) {
            $errorFile = 'errores_importacion_regiones_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.xlsx';
            SimpleXlsx::write(
                storage_path('app/' . $errorFile),
                ['Fila Excel', 'Región', 'Error'],
                $errorRows
            );
        }

        return redirect()->route('ambito.index', ['tab' => 'region'])
            ->with('import_summary', [
                'procesados' => $procesados,
                'nuevos' => $nuevos,
                'duplicados' => $duplicados,
                'errores' => $errores,
            ])
            ->with('import_error_file', $errorFile);
    }

    public function downloadImportRegionErrors(string $filename)
    {
        if (!preg_match('/^errores_importacion_regiones_[A-Za-z0-9_-]+\.xlsx$/', $filename)) {
            abort(404);
        }

        $path = storage_path('app/' . $filename);
        abort_unless(is_file($path), 404);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function normalizeImportValue(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $value = strtr($value, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        ]);
        return mb_strtolower($value, 'UTF-8');
    }

    public function storeRegion(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
        ], [
            'nombre.required' => 'Ingrese el nombre de la región.',
        ]);

        $nombre = trim($data['nombre']);
        $exists = DB::table('region')
            ->whereRaw('LOWER(TRIM(nombre)) = LOWER(?)', [$nombre])
            ->exists();
        if ($exists) {
            return back()->withInput()->with('error', 'La región ya existe.');
        }

        $id = DB::table('region')->insertGetId([
            'nombre' => $nombre,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        AuditLogger::log('Ámbito','region',$id,'REGISTRAR',null,['nombre'=>$nombre,'estado'=>1]);

        return redirect()->route('ambito.index', ['tab' => 'region'])->with('success', 'Región registrada correctamente.');
    }

    public function updateRegion(Request $request, int $id)
    {
        $region = DB::table('region')->where('id_region', $id)->firstOrFail();
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150', Rule::unique('region', 'nombre')->ignore($region->id_region, 'id_region')],
        ], [
            'nombre.required' => 'Ingrese el nombre de la región.',
        ]);

        $nuevoNombre = trim($data['nombre']);
        DB::table('region')->where('id_region', $id)->update([
            'nombre' => $nuevoNombre, 'updated_at' => now()
        ]);
        AuditLogger::log('Ámbito','region',$id,'EDITAR',['nombre'=>$region->nombre],['nombre'=>$nuevoNombre]);

        return redirect()->route('ambito.index', ['tab' => 'region'])->with('success', 'Región actualizada correctamente.');
    }

    public function toggleRegion(int $id)
    {
        $region = DB::table('region')->where('id_region', $id)->firstOrFail();
        $nuevo = !$region->estado;
        DB::table('region')->where('id_region', $id)->update(['estado' => $nuevo, 'updated_at' => now()]);
        AuditLogger::log('Ámbito','region',$id,$nuevo?'HABILITAR':'DESHABILITAR',['estado'=>(int)$region->estado],['estado'=>(int)$nuevo]);
        return back()->with('success', $region->estado ? 'Región deshabilitada.' : 'Región habilitada.');
    }

    public function exportProvincias()
    {
        $provincias = DB::table('provincia as p')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->select('p.*', 'r.nombre as region_nombre')
            ->orderBy('r.nombre')->orderBy('p.nombre')->get();

        $rows = $provincias->map(function ($p, $index) {
            return [
                $index + 1,
                $p->region_nombre,
                $p->nombre,
                $p->estado ? 'Activo' : 'Inactivo',
                $p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('d/m/Y H:i') : '',
                $p->updated_at ? \Carbon\Carbon::parse($p->updated_at)->format('d/m/Y H:i') : '',
            ];
        })->all();

        return SimpleXlsx::download(
            ['N°', 'Región', 'Provincia', 'Estado', 'Fecha de registro', 'Última actualización'],
            $rows,
            'provincias_' . now()->format('Ymd_His') . '.xlsx',
            'Provincias'
        );
    }

    public function plantillaProvincias()
    {
        return SimpleXlsx::download(
            ['Región', 'Provincia'],
            [],
            'plantilla_provincias.xlsx',
            'Provincias'
        );
    }

    public function importProvincias(Request $request)
    {
        $request->validate([
            'archivo_provincias' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ], [
            'archivo_provincias.required' => 'Seleccione un archivo Excel.',
            'archivo_provincias.mimes' => 'El archivo debe estar en formato Excel .xlsx.',
            'archivo_provincias.max' => 'El archivo no debe superar los 5 MB.',
        ]);

        try {
            $rows = SimpleXlsx::read($request->file('archivo_provincias')->getRealPath());
        } catch (\Throwable $e) {
            return redirect()->route('ambito.index', ['tab' => 'provincia'])
                ->with('import_error_provincia', 'No se pudo leer el archivo Excel: ' . $e->getMessage());
        }

        if (count($rows) === 0) {
            return redirect()->route('ambito.index', ['tab' => 'provincia'])
                ->with('import_error_provincia', 'El archivo Excel está vacío.');
        }

        $header = array_map(fn($v) => $this->normalizeImportValue((string) $v), $rows[0]);
        if (($header[0] ?? '') !== 'region' || !in_array(($header[1] ?? ''), ['provincia', 'province'], true)) {
            return redirect()->route('ambito.index', ['tab' => 'provincia'])
                ->with('import_error_provincia', 'La plantilla no es válida. Las dos primeras columnas deben ser "Región" y "Provincia".');
        }

        $procesados = $nuevos = $duplicados = $errores = 0;
        $errorRows = [];
        $seen = [];
        $regions = DB::table('region')->get(['id_region', 'nombre', 'estado']);
        $provincias = DB::table('provincia')->get(['id_provincia', 'id_region', 'nombre']);

        foreach (array_slice($rows, 1) as $index => $row) {
            $excelRow = $index + 2;
            $regionNombre = trim((string) ($row[0] ?? ''));
            $provinciaNombre = trim((string) ($row[1] ?? ''));
            $procesados++;

            if ($regionNombre === '' || $provinciaNombre === '') {
                $errores++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, 'La región y la provincia son obligatorias.'];
                continue;
            }
            if (mb_strlen($regionNombre) > 150 || mb_strlen($provinciaNombre) > 150) {
                $errores++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, 'La región o la provincia supera los 150 caracteres.'];
                continue;
            }

            $regionKey = $this->normalizeImportValue($regionNombre);
            $provinciaKey = $this->normalizeImportValue($provinciaNombre);
            $region = $regions->first(fn($r) => $this->normalizeImportValue((string) $r->nombre) === $regionKey);
            if (!$region) {
                $errores++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, 'La región no existe en la base de datos.'];
                continue;
            }
            if (!(int) $region->estado) {
                $errores++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, 'La región está inactiva. Habilítela antes de importar provincias.'];
                continue;
            }

            $key = $region->id_region . '|' . $provinciaKey;
            if (isset($seen[$key])) {
                $duplicados++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, 'La provincia está repetida dentro del archivo Excel para la misma región.'];
                continue;
            }
            $seen[$key] = true;

            $exists = $provincias->first(fn($p) => (int) $p->id_region === (int) $region->id_region
                && $this->normalizeImportValue((string) $p->nombre) === $provinciaKey);
            if ($exists) {
                $duplicados++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, 'La provincia ya existe en la región seleccionada.'];
                continue;
            }

            DB::table('provincia')->insert([
                'id_region' => $region->id_region,
                'nombre' => $provinciaNombre,
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $nuevos++;
            $provincias->push((object) ['id_provincia' => 0, 'id_region' => $region->id_region, 'nombre' => $provinciaNombre]);
        }

        $errorFile = null;
        if ($errorRows) {
            $errorFile = 'errores_importacion_provincias_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.xlsx';
            SimpleXlsx::write(storage_path('app/' . $errorFile), ['Fila Excel', 'Región', 'Provincia', 'Error'], $errorRows);
        }

        return redirect()->route('ambito.index', ['tab' => 'provincia'])
            ->with('import_summary_provincia', compact('procesados', 'nuevos', 'duplicados', 'errores'))
            ->with('import_error_file_provincia', $errorFile);
    }

    public function downloadImportProvinciaErrors(string $filename)
    {
        if (!preg_match('/^errores_importacion_provincias_[A-Za-z0-9_-]+\.xlsx$/', $filename)) abort(404);
        $path = storage_path('app/' . $filename);
        abort_unless(is_file($path), 404);
        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function storeProvincia(Request $request)
    {
        $data = $request->validate([
            'id_region' => ['required', 'integer', 'exists:region,id_region'],
            'nombre' => ['required', 'string', 'max:150'],
        ], [
            'id_region.required' => 'Seleccione una región.',
            'id_region.exists' => 'La región seleccionada no existe.',
            'nombre.required' => 'Ingrese el nombre de la provincia.',
        ]);

        $exists = DB::table('provincia')->where('id_region', $data['id_region'])->whereRaw('LOWER(nombre) = LOWER(?)', [trim($data['nombre'])])->exists();
        if ($exists) return back()->withInput()->with('error', 'La provincia ya existe en la región seleccionada.');

        DB::table('provincia')->insert([
            'id_region' => $data['id_region'], 'nombre' => trim($data['nombre']), 'estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('ambito.index', ['tab' => 'provincia'])->with('success', 'Provincia registrada correctamente.');
    }

    public function updateProvincia(Request $request, int $id)
    {
        $provincia = DB::table('provincia')->where('id_provincia', $id)->firstOrFail();
        $data = $request->validate([
            'id_region' => ['required', 'integer', 'exists:region,id_region'],
            'nombre' => ['required', 'string', 'max:150'],
        ], [
            'id_region.required' => 'Seleccione una región.',
            'id_region.exists' => 'La región seleccionada no existe.',
            'nombre.required' => 'Ingrese el nombre de la provincia.',
        ]);

        $exists = DB::table('provincia')
            ->where('id_region', $data['id_region'])->where('id_provincia', '!=', $id)
            ->whereRaw('LOWER(nombre) = LOWER(?)', [trim($data['nombre'])])->exists();
        if ($exists) return back()->withInput()->with('error', 'La provincia ya existe en la región seleccionada.');

        DB::table('provincia')->where('id_provincia', $id)->update([
            'id_region' => $data['id_region'], 'nombre' => trim($data['nombre']), 'updated_at' => now()
        ]);
        return redirect()->route('ambito.index', ['tab' => 'provincia'])->with('success', 'Provincia actualizada correctamente.');
    }

    public function toggleProvincia(int $id)
    {
        $p = DB::table('provincia')->where('id_provincia', $id)->firstOrFail();
        DB::table('provincia')->where('id_provincia', $id)->update(['estado' => !$p->estado, 'updated_at' => now()]);
        return back()->with('success', $p->estado ? 'Provincia deshabilitada.' : 'Provincia habilitada.');
    }

    public function exportDistritos()
    {
        $distritos = DB::table('distrito as d')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->select('d.*', 'p.nombre as provincia_nombre', 'r.nombre as region_nombre')
            ->orderBy('r.nombre')->orderBy('p.nombre')->orderBy('d.nombre')->get();

        $rows = $distritos->map(function ($d, $index) {
            return [
                $index + 1,
                $d->region_nombre,
                $d->provincia_nombre,
                $d->nombre,
                $d->estado ? 'Activo' : 'Inactivo',
                $d->created_at ? \Carbon\Carbon::parse($d->created_at)->format('d/m/Y H:i') : '',
                $d->updated_at ? \Carbon\Carbon::parse($d->updated_at)->format('d/m/Y H:i') : '',
            ];
        })->all();

        return SimpleXlsx::download(
            ['N°', 'Región', 'Provincia', 'Distrito', 'Estado', 'Fecha de registro', 'Última actualización'],
            $rows,
            'distritos_' . now()->format('Ymd_His') . '.xlsx',
            'Distritos'
        );
    }

    public function plantillaDistritos()
    {
        return SimpleXlsx::download(
            ['Región', 'Provincia', 'Distrito'],
            [],
            'plantilla_distritos.xlsx',
            'Distritos'
        );
    }

    public function importDistritos(Request $request)
    {
        $request->validate([
            'archivo_distritos' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ], [
            'archivo_distritos.required' => 'Seleccione un archivo Excel.',
            'archivo_distritos.mimes' => 'El archivo debe estar en formato Excel .xlsx.',
            'archivo_distritos.max' => 'El archivo no debe superar los 5 MB.',
        ]);

        try {
            $rows = SimpleXlsx::read($request->file('archivo_distritos')->getRealPath());
        } catch (\Throwable $e) {
            return redirect()->route('ambito.index', ['tab' => 'distrito'])
                ->with('import_error_distrito', 'No se pudo leer el archivo Excel: ' . $e->getMessage());
        }

        if (count($rows) === 0) {
            return redirect()->route('ambito.index', ['tab' => 'distrito'])
                ->with('import_error_distrito', 'El archivo Excel está vacío.');
        }

        $header = array_map(fn($v) => $this->normalizeImportValue((string) $v), $rows[0]);
        if (($header[0] ?? '') !== 'region' || !in_array(($header[1] ?? ''), ['provincia', 'province'], true) || ($header[2] ?? '') !== 'distrito') {
            return redirect()->route('ambito.index', ['tab' => 'distrito'])
                ->with('import_error_distrito', 'La plantilla no es válida. Las tres primeras columnas deben ser "Región", "Provincia" y "Distrito".');
        }

        $procesados = $nuevos = $duplicados = $errores = 0;
        $errorRows = [];
        $seen = [];
        $regions = DB::table('region')->get(['id_region', 'nombre', 'estado']);
        $provincias = DB::table('provincia')->get(['id_provincia', 'id_region', 'nombre', 'estado']);
        $distritos = DB::table('distrito')->get(['id_distrito', 'id_provincia', 'nombre']);

        foreach (array_slice($rows, 1) as $index => $row) {
            $excelRow = $index + 2;
            $regionNombre = trim((string) ($row[0] ?? ''));
            $provinciaNombre = trim((string) ($row[1] ?? ''));
            $distritoNombre = trim((string) ($row[2] ?? ''));
            $procesados++;

            if ($regionNombre === '' || $provinciaNombre === '' || $distritoNombre === '') {
                $errores++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, $distritoNombre, 'Región, provincia y distrito son obligatorios.'];
                continue;
            }
            if (mb_strlen($regionNombre) > 150 || mb_strlen($provinciaNombre) > 150 || mb_strlen($distritoNombre) > 150) {
                $errores++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, $distritoNombre, 'Uno de los nombres supera los 150 caracteres.'];
                continue;
            }

            $regionKey = $this->normalizeImportValue($regionNombre);
            $provinciaKey = $this->normalizeImportValue($provinciaNombre);
            $distritoKey = $this->normalizeImportValue($distritoNombre);
            $region = $regions->first(fn($r) => $this->normalizeImportValue((string) $r->nombre) === $regionKey);
            if (!$region) {
                $errores++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, $distritoNombre, 'La región no existe en la base de datos.'];
                continue;
            }
            if (!(int) $region->estado) {
                $errores++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, $distritoNombre, 'La región está inactiva.'];
                continue;
            }

            $provincia = $provincias->first(fn($p) => (int) $p->id_region === (int) $region->id_region
                && $this->normalizeImportValue((string) $p->nombre) === $provinciaKey);
            if (!$provincia) {
                $errores++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, $distritoNombre, 'La provincia no existe en la región indicada.'];
                continue;
            }
            if (!(int) $provincia->estado) {
                $errores++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, $distritoNombre, 'La provincia está inactiva.'];
                continue;
            }

            $key = $provincia->id_provincia . '|' . $distritoKey;
            if (isset($seen[$key])) {
                $duplicados++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, $distritoNombre, 'El distrito está repetido dentro del archivo Excel para la misma provincia.'];
                continue;
            }
            $seen[$key] = true;

            $exists = $distritos->first(fn($d) => (int) $d->id_provincia === (int) $provincia->id_provincia
                && $this->normalizeImportValue((string) $d->nombre) === $distritoKey);
            if ($exists) {
                $duplicados++;
                $errorRows[] = [$excelRow, $regionNombre, $provinciaNombre, $distritoNombre, 'El distrito ya existe en la provincia seleccionada.'];
                continue;
            }

            DB::table('distrito')->insert([
                'id_provincia' => $provincia->id_provincia,
                'nombre' => $distritoNombre,
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $nuevos++;
            $distritos->push((object) ['id_distrito' => 0, 'id_provincia' => $provincia->id_provincia, 'nombre' => $distritoNombre]);
        }

        $errorFile = null;
        if ($errorRows) {
            $errorFile = 'errores_importacion_distritos_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.xlsx';
            SimpleXlsx::write(storage_path('app/' . $errorFile), ['Fila Excel', 'Región', 'Provincia', 'Distrito', 'Error'], $errorRows);
        }

        return redirect()->route('ambito.index', ['tab' => 'distrito'])
            ->with('import_summary_distrito', compact('procesados', 'nuevos', 'duplicados', 'errores'))
            ->with('import_error_file_distrito', $errorFile);
    }

    public function downloadImportDistritoErrors(string $filename)
    {
        if (!preg_match('/^errores_importacion_distritos_[A-Za-z0-9_-]+\.xlsx$/', $filename)) abort(404);
        $path = storage_path('app/' . $filename);
        abort_unless(is_file($path), 404);
        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function storeDistrito(Request $request)
    {
        $data = $request->validate([
            'id_provincia' => ['required', 'integer', 'exists:provincia,id_provincia'],
            'nombre' => ['required', 'string', 'max:150'],
        ], [
            'id_provincia.required' => 'Seleccione una provincia.',
            'id_provincia.exists' => 'La provincia seleccionada no existe.',
            'nombre.required' => 'Ingrese el nombre del distrito.',
        ]);

        $exists = DB::table('distrito')->where('id_provincia', $data['id_provincia'])->whereRaw('LOWER(nombre) = LOWER(?)', [trim($data['nombre'])])->exists();
        if ($exists) return back()->withInput()->with('error', 'El distrito ya existe en la provincia seleccionada.');

        DB::table('distrito')->insert([
            'id_provincia' => $data['id_provincia'], 'nombre' => trim($data['nombre']), 'estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('ambito.index', ['tab' => 'distrito'])->with('success', 'Distrito registrado correctamente.');
    }

    public function updateDistrito(Request $request, int $id)
    {
        $d = DB::table('distrito')->where('id_distrito', $id)->firstOrFail();
        $data = $request->validate([
            'id_provincia' => ['required', 'integer', 'exists:provincia,id_provincia'],
            'nombre' => ['required', 'string', 'max:150'],
        ], [
            'id_provincia.required' => 'Seleccione una provincia.',
            'id_provincia.exists' => 'La provincia seleccionada no existe.',
            'nombre.required' => 'Ingrese el nombre del distrito.',
        ]);

        $exists = DB::table('distrito')->where('id_provincia', $data['id_provincia'])->where('id_distrito', '!=', $id)
            ->whereRaw('LOWER(nombre) = LOWER(?)', [trim($data['nombre'])])->exists();
        if ($exists) return back()->withInput()->with('error', 'El distrito ya existe en la provincia seleccionada.');

        DB::table('distrito')->where('id_distrito', $id)->update([
            'id_provincia' => $data['id_provincia'], 'nombre' => trim($data['nombre']), 'updated_at' => now()
        ]);
        return redirect()->route('ambito.index', ['tab' => 'distrito'])->with('success', 'Distrito actualizado correctamente.');
    }

    public function toggleDistrito(int $id)
    {
        $d = DB::table('distrito')->where('id_distrito', $id)->firstOrFail();
        DB::table('distrito')->where('id_distrito', $id)->update(['estado' => !$d->estado, 'updated_at' => now()]);
        return back()->with('success', $d->estado ? 'Distrito deshabilitado.' : 'Distrito habilitado.');
    }

    public function provincesByRegion(int $region)
    {
        return response()->json(
            DB::table('provincia')->where('id_region', $region)->where('estado', 1)->orderBy('nombre')->get(['id_provincia', 'nombre'])
        );
    }
}
