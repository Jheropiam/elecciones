<?php

namespace App\Http\Controllers;

use App\Support\SimpleXlsx;
use App\Support\ImageWebp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class PartidosController extends Controller
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
        abort_unless($this->isAdmin(), 403, 'No tiene permisos para administrar partidos políticos.');
    }

    private function norm(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        return mb_strtolower($value, 'UTF-8');
    }

    private function audit(string $accion, ?int $id, $old = null, $new = null): void
    {
        DB::table('auditoria')->insert([
            'id_usuario' => session('id_usuario'), 'modulo' => 'Partidos Políticos',
            'tabla_afectada' => 'partido', 'id_registro' => $id, 'accion' => $accion,
            'valor_anterior' => $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'valor_nuevo' => $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(), 'user_agent' => substr((string)request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();
        $q = trim((string)$request->query('q', ''));
        $tipo = (string)$request->query('tipo', '');
        $estado = $request->has('estado') && $request->estado !== '' ? (int)$request->estado : null;

        $query = DB::table('partido as p')->select('p.*');
        if ($q !== '') $query->where('p.nombre', 'like', "%{$q}%");
        if ($tipo !== '') $query->where('p.tipo', $tipo);
        if ($estado !== null) $query->where('p.estado', $estado);
        $partidos = $query->orderBy('p.id_partido')->paginate(\App\Support\Pagination::perPage($request, 15))->withQueryString();

        return view('partidos.index', compact('partidos','q','tipo','estado'))
            ->with('sidebarModules', DashboardController::sidebarModules())->with('title','Partidos Políticos');
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'nombre' => ['required','string','max:200'],
            'tipo' => ['required','in:POLITICO,ESPECIAL'],
            'logo' => ['nullable','file','mimes:jpg,jpeg,png,webp','max:2048'],
            '_form' => ['nullable','string'],
        ]);
        $nombre = trim(preg_replace('/\s+/u', ' ', $data['nombre']) ?? $data['nombre']);
        $exists = DB::table('partido')->get()->first(fn($p) => $this->norm($p->nombre) === $this->norm($nombre));
        if ($exists) return back()->withInput()->with('error','El partido o concepto ya existe.');

        $logo = null;
        if (($data['tipo'] ?? '') === 'ESPECIAL' && $request->hasFile('logo')) {
            return back()->withInput()->with('error','Los conceptos especiales no utilizan logo.');
        }
        if ($request->hasFile('logo')) $logo = $this->saveLogo($request->file('logo'), $nombre);

        $id = DB::table('partido')->insertGetId([
            'nombre'=>$nombre,'tipo'=>$data['tipo'],'logo'=>$logo,'estado'=>1,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->audit('REGISTRAR',$id,null,['nombre'=>$nombre,'tipo'=>$data['tipo'],'logo'=>$logo,'estado'=>1]);
        return back()->with('success','Registro creado correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $this->ensureAdmin();
        $partido = DB::table('partido')->where('id_partido',$id)->first();
        abort_unless($partido,404);
        if ($partido->tipo === 'ESPECIAL') return back()->with('error','Los conceptos especiales son administrados por el sistema y no pueden editarse.');

        // El selector de tipo se muestra bloqueado durante la edición para evitar cambiar POLITICO a ESPECIAL.
        // Un campo disabled no se envía en HTML, por eso aceptamos el valor oculto de respaldo.
        $request->merge(['tipo' => $request->input('tipo') ?: $request->input('tipo_edicion')]);
        $data = $request->validate([
            'nombre'=>['required','string','max:200'],'tipo'=>['required','in:POLITICO'],
            'logo'=>['nullable','file','mimes:jpg,jpeg,png,webp','max:2048'],'_form'=>['nullable','string'],
        ], [
            'nombre.required' => 'El nombre del partido es obligatorio.',
            'tipo.required' => 'El tipo del partido es obligatorio.',
            'tipo.in' => 'El tipo de registro no es válido.',
            'logo.file' => 'El archivo seleccionado no es válido.',
            'logo.mimes' => 'El logo debe ser JPG, JPEG, PNG o WEBP.',
            'logo.max' => 'El logo no puede superar 2 MB.',
        ]);
        $nombre = trim(preg_replace('/\s+/u',' ', $data['nombre']) ?? $data['nombre']);
        $duplicate = DB::table('partido')->where('id_partido','<>',$id)->get()->first(fn($p)=>$this->norm($p->nombre)===$this->norm($nombre));
        if ($duplicate) return back()->withInput()->with('error','El partido ya existe.');
        $logo = $partido->logo;
        if ($request->hasFile('logo')) {
            if ($logo && File::exists(public_path($logo))) @File::delete(public_path($logo));
            $logo = $this->saveLogo($request->file('logo'), $nombre);
        }
        $new=['nombre'=>$nombre,'tipo'=>'POLITICO','logo'=>$logo];
        DB::table('partido')->where('id_partido',$id)->update(array_merge($new,['updated_at'=>now()]));
        $this->audit('EDITAR',$id,(array)$partido,$new);
        return back()->with('success','Partido actualizado correctamente.');
    }

    public function toggle(int $id)
    {
        $this->ensureAdmin();
        $p=DB::table('partido')->where('id_partido',$id)->first(); abort_unless($p,404);
        if ($p->tipo==='ESPECIAL') return back()->with('error','Los conceptos especiales no pueden deshabilitarse.');
        $new=(int)!$p->estado;
        DB::table('partido')->where('id_partido',$id)->update(['estado'=>$new,'updated_at'=>now()]);
        $this->audit($new?'HABILITAR':'DESHABILITAR',$id,['estado'=>(int)$p->estado],['estado'=>$new]);
        return back()->with('success',$new?'Partido habilitado.':'Partido deshabilitado.');
    }

    public function export(Request $request)
    {
        $this->ensureAdmin();
        $q=trim((string)$request->query('q','')); $tipo=(string)$request->query('tipo','');
        $estado=$request->has('estado')&&$request->estado!==''?(int)$request->estado:null;
        $query=DB::table('partido as p')->select('p.*');
        if($q!=='')$query->where('p.nombre','like',"%{$q}%"); if($tipo!=='')$query->where('p.tipo',$tipo); if($estado!==null)$query->where('p.estado',$estado);
        $rows=[];$i=1; foreach($query->orderBy('p.id_partido')->get() as $p){$rows[]=[ $i++,$p->nombre,$p->tipo,$p->logo??'', $p->estado?'Activo':'Inactivo',$p->created_at,$p->updated_at ];}
        return SimpleXlsx::download(['N°','Nombre','Tipo','Logo','Estado','Fecha de registro','Última actualización'],$rows,'partidos_politicos.xlsx','Partidos');
    }

    public function template(){ $this->ensureAdmin(); return SimpleXlsx::download(['Nombre','Tipo','Logo'],[['Ejemplo de partido','POLITICO','']], 'plantilla_partidos_politicos.xlsx','Partidos'); }

    public function import(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'archivo' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('archivo');
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, ['xlsx', 'zip'], true)) {
            return back()->with('error', 'El archivo debe ser un Excel (.xlsx) o un ZIP con Excel y logos.');
        }

        if ($extension === 'xlsx') {
            return $this->importExcelFile($file->getRealPath());
        }

        try {
            return $this->importZipFile($file->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo procesar el ZIP de partidos: ' . $e->getMessage());
        }
    }

    private function importExcelFile(string $xlsxPath)
    {
        try {
            $rows = SimpleXlsx::read($xlsxPath);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo leer el Excel: ' . $e->getMessage());
        }

        if (count($rows) < 2) {
            return back()->with('error', 'El archivo Excel no contiene registros para importar.');
        }

        return $this->processImportRows($rows, []);
    }

    /**
     * Importación masiva de partidos con logos:
     *
     * partidos.zip
     * ├── partidos.xlsx
     * └── logos/
     *     ├── partido-1.png
     *     └── partido-2.png
     *
     * En la columna Logo del Excel se coloca solamente el nombre del archivo,
     * por ejemplo: partido-1.png
     */
    private function importZipFile(string $zipPath)
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('El archivo no es un ZIP válido.');
        }

        $temporaryXlsx = null;

        try {
            $xlsxEntry = null;

            // Preferir partidos.xlsx; si no existe, aceptar el primer XLSX.
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) ($zip->getNameIndex($i) ?? '');
                $normalized = str_replace('\\', '/', $name);
                if (strtolower(basename($normalized)) === 'partidos.xlsx') {
                    $xlsxEntry = $name;
                    break;
                }
            }

            if ($xlsxEntry === null) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string) ($zip->getNameIndex($i) ?? '');
                    $normalized = str_replace('\\', '/', $name);
                    if (preg_match('/\.xlsx$/i', $normalized) && !str_contains($normalized, '__MACOSX/')) {
                        $xlsxEntry = $name;
                        break;
                    }
                }
            }

            if ($xlsxEntry === null) {
                throw new RuntimeException('El ZIP no contiene un archivo Excel (.xlsx).');
            }

            $xlsxBytes = $zip->getFromName($xlsxEntry);
            if ($xlsxBytes === false || $xlsxBytes === '') {
                throw new RuntimeException('No se pudo leer el archivo Excel dentro del ZIP.');
            }

            $temporaryXlsx = tempnam(sys_get_temp_dir(), 'partidos_') . '.xlsx';
            if (@file_put_contents($temporaryXlsx, $xlsxBytes) === false) {
                throw new RuntimeException('No se pudo preparar el Excel temporal para la importación.');
            }

            $rows = SimpleXlsx::read($temporaryXlsx);

            // Índice de logos: basename normalizado => entrada ZIP.
            $logoEntries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) ($zip->getNameIndex($i) ?? '');
                $normalized = str_replace('\\', '/', $name);

                if (!preg_match('#^logos/([^/]+)$#i', ltrim($normalized, '/'), $m)) {
                    continue;
                }

                $basename = strtolower($m[1]);
                $logoEntries[$basename] ??= $name;
            }

            return $this->processImportRows($rows, $logoEntries, $zip);
        } finally {
            if ($temporaryXlsx && is_file($temporaryXlsx)) {
                @unlink($temporaryXlsx);
            }
            $zip->close();
        }
    }

    private function processImportRows(array $rows, array $logoEntries, ?ZipArchive $zip = null)
    {
        if (count($rows) < 2) {
            return back()->with('error', 'El archivo Excel no contiene registros para importar.');
        }

        // Validar encabezados de forma tolerante a tildes/mayúsculas.
        $header = array_map(function ($value) {
            $value = trim((string) $value);
            $value = mb_strtolower($value, 'UTF-8');
            $value = strtr($value, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u']);
            return $value;
        }, $rows[0] ?? []);

        if (($header[0] ?? '') !== 'nombre' || ($header[1] ?? '') !== 'tipo' || ($header[2] ?? '') !== 'logo') {
            return back()->with('error', 'La plantilla no es válida. Las columnas deben ser "Nombre", "Tipo" y "Logo".');
        }

        $summary = ['procesados'=>0,'nuevos'=>0,'duplicados'=>0,'errores'=>0];
        $errors = [];
        $seen = [];
        $createdLogoPaths = [];

        foreach (array_slice($rows, 1) as $idx => $row) {
            $excelRow = $idx + 2;
            $summary['procesados']++;

            $nombre = trim((string) ($row[0] ?? ''));
            $tipo = strtoupper(trim((string) ($row[1] ?? 'POLITICO')));
            $logoName = trim((string) ($row[2] ?? ''));
            $reason = null;
            $logoPath = null;

            if ($nombre === '') {
                $reason = 'El nombre es obligatorio.';
            } elseif (mb_strlen($nombre) > 200) {
                $reason = 'El nombre supera 200 caracteres.';
            } elseif (!in_array($tipo, ['POLITICO', 'ESPECIAL'], true)) {
                $reason = 'Tipo inválido. Use POLITICO o ESPECIAL.';
            } elseif ($tipo === 'ESPECIAL' && $logoName !== '') {
                $reason = 'Los conceptos especiales no utilizan logo.';
            }

            $key = $this->norm($nombre);

            if (!$reason && isset($seen[$key])) {
                $reason = 'Registro duplicado dentro del Excel.';
            }
            $seen[$key] = true;

            $exists = DB::table('partido')->get()->first(fn($p) => $this->norm($p->nombre) === $key);

            if (!$reason && $exists) {
                $summary['duplicados']++;
                $errors[] = [$excelRow, $nombre, $tipo, $logoName, 'El registro ya existe en la base de datos.'];
                continue;
            }

            if (!$reason && $logoName !== '') {
                if ($zip === null) {
                    // En Excel simple se conserva la compatibilidad anterior:
                    // Logo puede contener una ruta ya existente.
                    $logoPath = $logoName;
                } else {
                    $logoKey = strtolower(basename(str_replace('\\', '/', $logoName)));

                    if (!isset($logoEntries[$logoKey])) {
                        $reason = "No se encontró el logo \"{$logoName}\" dentro de la carpeta logos/.";
                    } else {
                        $entryName = $logoEntries[$logoKey];
                        $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

                        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                            $reason = 'El logo debe ser JPG, JPEG, PNG o WEBP.';
                        } else {
                            $bytes = $zip->getFromName($entryName);

                            if ($bytes === false || $bytes === '') {
                                $reason = 'No se pudo leer el archivo del logo.';
                            } elseif (strlen($bytes) > 2 * 1024 * 1024) {
                                $reason = 'El logo supera el tamaño máximo de 2 MB.';
                            } elseif (@getimagesizefromstring($bytes) === false) {
                                $reason = 'El archivo indicado como logo no es una imagen válida.';
                            } else {
                                $logoPath = $this->saveLogoBytes($bytes, $nombre, $extension);
                                $createdLogoPaths[] = public_path($logoPath);
                            }
                        }
                    }
                }
            }

            if ($reason) {
                $summary['errores']++;
                $errors[] = [$excelRow, $nombre, $tipo, $logoName, $reason];
                continue;
            }

            try {
                $id = DB::table('partido')->insertGetId([
                    'nombre' => trim(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre),
                    'tipo' => $tipo,
                    'logo' => $logoPath,
                    'estado' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->audit('REGISTRAR', $id, null, [
                    'nombre' => $nombre,
                    'tipo' => $tipo,
                    'logo' => $logoPath,
                    'estado' => 1,
                ]);

                $summary['nuevos']++;
            } catch (\Throwable $e) {
                if ($logoPath && in_array(public_path($logoPath), $createdLogoPaths, true)) {
                    @File::delete(public_path($logoPath));
                }

                $summary['errores']++;
                $errors[] = [$excelRow, $nombre, $tipo, $logoName, 'No se pudo registrar: ' . $e->getMessage()];
            }
        }

        $resp = back()->with('import_summary', $summary);

        if ($errors) {
            $file = 'errores_partidos_' . date('Ymd_His') . '_' . Str::random(6) . '.xlsx';
            $dir = storage_path('app/import_errors');
            if (!is_dir($dir)) @mkdir($dir, 0775, true);

            SimpleXlsx::write(
                $dir . '/' . $file,
                ['Fila', 'Nombre', 'Tipo', 'Logo', 'Motivo'],
                $errors,
                'Errores'
            );

            $resp->with('import_error_file', $file);
        }

        return $resp;
    }

    public function templateZip()
    {
        $this->ensureAdmin();

        $tmpDir = storage_path('app/tmp_partidos_' . Str::random(10));
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $xlsx = $tmpDir . '/partidos.xlsx';
        $zipPath = $tmpDir . '/plantilla_partidos_con_logos.zip';

        try {
            SimpleXlsx::write(
                $xlsx,
                ['Nombre', 'Tipo', 'Logo'],
                [
                    ['Ejemplo de partido', 'POLITICO', 'ejemplo-partido.png'],
                    ['Otro partido', 'POLITICO', 'otro-partido.png'],
                    ['VOTOS BLANCOS', 'ESPECIAL', ''],
                ],
                'Partidos'
            );

            $readme = "PLANTILLA DE IMPORTACIÓN MASIVA DE PARTIDOS CON LOGOS\n\n"
                . "1. Complete partidos.xlsx.\n"
                . "2. En la columna Logo coloque SOLO el nombre del archivo de imagen.\n"
                . "   Ejemplo: fuerza-popular.png\n"
                . "3. Coloque esos archivos dentro de la carpeta logos/.\n"
                . "4. Comprima partidos.xlsx y logos/ en un único ZIP.\n\n"
                . "Estructura:\n"
                . "partidos.zip\n"
                . "  ├── partidos.xlsx\n"
                . "  └── logos/\n"
                . "      ├── fuerza-popular.png\n"
                . "      └── otro-partido.jpg\n\n"
                . "Formatos permitidos: JPG, JPEG, PNG y WEBP. Máximo 2 MB por logo.\n"
                . "Los conceptos ESPECIAL no utilizan logo.\n";

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear la plantilla ZIP.');
            }

            $zip->addFile($xlsx, 'partidos.xlsx');
            $zip->addFromString('logos/', '');
            $zip->addFromString('LEEME.txt', $readme);
            $zip->close();

            // El Excel es solo un componente del ZIP y ya no se necesita después de crearlo.
            @unlink($xlsx);

            return response()->download($zipPath, 'plantilla_partidos_con_logos.zip')->deleteFileAfterSend(true);
        } finally {
            // El ZIP se elimina automáticamente después de la descarga.
            if (is_file($xlsx)) @unlink($xlsx);
        }
    }

    public function errors(string $filename){$this->ensureAdmin();$path=storage_path('app/import_errors/'.$filename);abort_unless(is_file($path),404);return response()->download($path,'errores_partidos.xlsx');}

    private function saveLogo($file,string $name): string
    {
        $dir = public_path('uploads/partidos');
        $filename = ImageWebp::fromUploadedFile($file, $dir, Str::slug($name) ?: 'partido');
        return 'uploads/partidos/' . $filename;
    }

    private function saveLogoBytes(string $bytes, string $name, string $extension): string
    {
        $dir = public_path('uploads/partidos');
        $filename = ImageWebp::fromBytes($bytes, $dir, Str::slug($name) ?: 'partido');
        return 'uploads/partidos/' . $filename;
    }
}
