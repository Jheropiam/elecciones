<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Validator;
use App\Support\SimpleXlsx;
use Throwable;

class MesasController extends Controller
{
    private function normalizeImportHeader(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $value = strtr($value, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        ]);
        return mb_strtolower($value, 'UTF-8');
    }

    public function index(Request $request)
    {
        $query = DB::table('mesa as m')
            ->join('local as l', 'l.id_local', '=', 'm.id_local')
            ->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')
            ->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')
            ->join('region as r', 'r.id_region', '=', 'p.id_region')
            ->select(
                'm.*',
                'l.nombre as local_nombre',
                'l.direccion as local_direccion',
                'd.id_distrito as id_distrito',
                'd.nombre as distrito_nombre',
                'p.id_provincia as id_provincia',
                'p.nombre as provincia_nombre',
                'r.id_region as id_region',
                'r.nombre as region_nombre'
            );

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($w) use ($q) {
                $w->where('m.numero_mesa', 'like', "%{$q}%")
                  ->orWhere('l.nombre', 'like', "%{$q}%");
            });
        }
        if ($request->filled('region')) $query->where('r.id_region', $request->region);
        if ($request->filled('provincia')) $query->where('p.id_provincia', $request->provincia);
        if ($request->filled('distrito')) $query->where('d.id_distrito', $request->distrito);
        if ($request->filled('local')) $query->where('l.id_local', $request->local);
        if ($request->filled('estado')) $query->where('m.estado', $request->estado);

        $mesas = $query->orderBy('r.nombre')->orderBy('p.nombre')->orderBy('d.nombre')
            ->orderBy('l.nombre')->orderBy('m.numero_mesa')->paginate(\App\Support\Pagination::perPage($request, 20))->withQueryString();

        $regiones = DB::table('region')->where('estado', 1)->orderBy('nombre')->get();
        $provincias = collect();
        $distritos = collect();
        $locales = DB::table('local')->where('estado', 1)->orderBy('nombre')->get();

        if ($request->filled('region')) {
            $provincias = DB::table('provincia')->where('id_region', $request->region)
                ->where('estado', 1)->orderBy('nombre')->get();
        }
        if ($request->filled('provincia')) {
            $distritos = DB::table('distrito')->where('id_provincia', $request->provincia)
                ->where('estado', 1)->orderBy('nombre')->get();
        }

        return view('mesas.index', compact('mesas','regiones','provincias','distritos','locales'));
    }

    public function provincias($region)
    {
        return response()->json(
            DB::table('provincia')->where('id_region', $region)->where('estado',1)
                ->orderBy('nombre')->get(['id_provincia','nombre'])
        );
    }

    public function distritos($provincia)
    {
        return response()->json(
            DB::table('distrito')->where('id_provincia', $provincia)->where('estado',1)
                ->orderBy('nombre')->get(['id_distrito','nombre'])
        );
    }

    public function locales($distrito)
    {
        return response()->json(
            DB::table('local')->where('id_distrito', $distrito)->where('estado',1)
                ->orderBy('nombre')->get(['id_local','nombre','direccion'])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_local' => ['required','integer'],
            'numero_mesa' => ['required','string','max:20'],
            'total_electores' => ['required','integer','min:1'],
        ]);

        $local = DB::table('local')->where('id_local',$data['id_local'])->where('estado',1)->first();
        if (!$local) return back()->withInput()->with('error','El local seleccionado no existe o está inactivo.');

        $exists = DB::table('mesa')->where('id_local',$data['id_local'])
            ->whereRaw('LOWER(TRIM(numero_mesa)) = ?', [mb_strtolower(trim($data['numero_mesa']) )])->exists();

        if ($exists) return back()->withInput()->with('error','La mesa ya existe en el local seleccionado.');

        $id = DB::table('mesa')->insertGetId([
            'id_local'=>$data['id_local'],
            'numero_mesa'=>trim($data['numero_mesa']),
            'total_electores'=>$data['total_electores'],
            'estado'=>1,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
        AuditLogger::log('Mesas','mesa',$id,'REGISTRAR',null,$data+['estado'=>1]);

        return redirect()->route('mesas.index')->with('success','Mesa registrada correctamente.');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'id_local' => ['required','integer'],
            'numero_mesa' => ['required','string','max:20'],
            'total_electores' => ['required','integer','min:1'],
        ]);

        $exists = DB::table('mesa')->where('id_local',$data['id_local'])
            ->whereRaw('LOWER(TRIM(numero_mesa)) = ?', [mb_strtolower(trim($data['numero_mesa']))])
            ->where('id_mesa','<>',$id)->exists();

        if ($exists) return back()->withInput()->with('error','La mesa ya existe en el local seleccionado.');

        $old = (array) DB::table('mesa')->where('id_mesa',$id)->firstOrFail();
        DB::table('mesa')->where('id_mesa',$id)->update([
            'id_local'=>$data['id_local'],
            'numero_mesa'=>trim($data['numero_mesa']),
            'total_electores'=>$data['total_electores'],
            'updated_at'=>now(),
        ]);
        AuditLogger::log('Mesas','mesa',$id,'EDITAR',$old,$data);

        return redirect()->route('mesas.index')->with('success','Mesa actualizada correctamente.');
    }

    public function toggle($id)
    {
        $mesa = DB::table('mesa')->where('id_mesa',$id)->first();
        if (!$mesa) return back()->with('error','Mesa no encontrada.');

        $estado = (int)$mesa->estado === 1 ? 0 : 1;
        DB::table('mesa')->where('id_mesa',$id)->update(['estado'=>$estado,'updated_at'=>now()]);
        AuditLogger::log('Mesas','mesa',$id,$estado?'HABILITAR':'DESHABILITAR',['estado'=>(int)$mesa->estado],['estado'=>$estado]);

        return back()->with('success','Estado de la mesa actualizado.');
    }

    private function xlsx($headers, $rows, $filename='mesas.xlsx', $sheet='Mesas')
    {
        return SimpleXlsx::download($headers, $rows, $filename, $sheet);
    }

    public function template()
    {
        return $this->xlsx(
            ['Region','Provincia','Distrito','Local','Numero Mesa','Total Electores','Estado'],
            [],
            'plantilla_mesas.xlsx',
            'Mesas'
        );
    }

    public function export(Request $request)
    {
        $rows = DB::table('mesa as m')
            ->join('local as l','l.id_local','=','m.id_local')
            ->join('distrito as d','d.id_distrito','=','l.id_distrito')
            ->join('provincia as p','p.id_provincia','=','d.id_provincia')
            ->join('region as r','r.id_region','=','p.id_region')
            ->select('r.nombre as region','p.nombre as provincia','d.nombre as distrito',
                     'l.nombre as local','m.numero_mesa','m.total_electores','m.estado',
                     'm.created_at','m.updated_at')
            ->orderBy('r.nombre')->orderBy('p.nombre')->orderBy('d.nombre')
            ->orderBy('l.nombre')->orderBy('m.numero_mesa')->get();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [$r->region,$r->provincia,$r->distrito,$r->local,$r->numero_mesa,
                       $r->total_electores,$r->estado,$r->created_at,$r->updated_at];
        }

        return $this->xlsx(
            ['Region','Provincia','Distrito','Local','Numero Mesa','Total Electores','Estado','Fecha Registro','Ultima Actualizacion'],
            $data,
            'mesas_'.now()->format('Ymd_His').'.xlsx',
            'Mesas'
        );
    }

    public function import(Request $request)
    {
        $request->validate(['archivo'=>['required','file','mimes:xlsx','max:5120']]);

        try {
            $rows = SimpleXlsx::read($request->file('archivo')->getRealPath());
            if (count($rows) < 2) return back()->with('error','El archivo Excel no contiene registros para importar.');

            $headers = array_map(fn($v)=>$this->normalizeImportHeader((string)$v), array_shift($rows));
            $required=['region','provincia','distrito','local','numero mesa','total electores'];
            foreach($required as $h) if(!in_array($h,$headers,true)) return back()->with('error','Falta la columna obligatoria: '.$h.'. Use la plantilla oficial.');
            $idx=[]; foreach($headers as $i=>$h) $idx[$h]=$i;
            $processed=$new=$duplicates=$errors=0; $errorRows=[]; $seen=[];
            $norm=fn($v)=>mb_strtolower(trim((string)$v));

            foreach($rows as $row){
                $processed++; $reason=''; $localId=null;
                $regionName=trim((string)($row[$idx['region']]??''));
                $provName=trim((string)($row[$idx['provincia']]??''));
                $distName=trim((string)($row[$idx['distrito']]??''));
                $localName=trim((string)($row[$idx['local']]??''));
                $numero=trim((string)($row[$idx['numero mesa']]??''));
                $electores=trim((string)($row[$idx['total electores']]??''));
                $estado=1;
                if(isset($idx['estado'])){
                    $ev=$norm($row[$idx['estado']]??'');
                    if(in_array($ev,['0','inactivo','inactiva','no','deshabilitado','deshabilitada'],true)) $estado=0;
                }
                if($regionName===''||$provName===''||$distName===''||$localName===''||$numero==='') $reason='Faltan datos obligatorios.';
                if(!$reason && (!preg_match('/^\d+$/',$electores) || (int)$electores<1)) $reason='Total de electores debe ser un entero mayor que cero.';
                if(!$reason){
                    $r=DB::table('region')->whereRaw('LOWER(TRIM(nombre)) = ?',[$norm($regionName)])->where('estado',1)->first();
                    if(!$r) $reason='La región no existe o está inactiva.';
                }
                if(!$reason){
                    $p=DB::table('provincia')->where('id_region',$r->id_region)->whereRaw('LOWER(TRIM(nombre)) = ?',[$norm($provName)])->where('estado',1)->first();
                    if(!$p) $reason='La provincia no pertenece a la región indicada o está inactiva.';
                }
                if(!$reason){
                    $d=DB::table('distrito')->where('id_provincia',$p->id_provincia)->whereRaw('LOWER(TRIM(nombre)) = ?',[$norm($distName)])->where('estado',1)->first();
                    if(!$d) $reason='El distrito no pertenece a la provincia indicada o está inactivo.';
                }
                if(!$reason){
                    $l=DB::table('local')->where('id_distrito',$d->id_distrito)->whereRaw('LOWER(TRIM(nombre)) = ?',[$norm($localName)])->where('estado',1)->first();
                    if(!$l) $reason='El local no pertenece al distrito indicado o está inactivo.'; else $localId=$l->id_local;
                }
                $key=$localId ? $localId.'|'.$norm($numero) : '';
                if(!$reason && isset($seen[$key])){ $reason='La mesa está duplicada dentro del archivo Excel.'; $duplicates++; }
                if(!$reason && DB::table('mesa')->where('id_local',$localId)->whereRaw('LOWER(TRIM(numero_mesa)) = ?',[$norm($numero)])->exists()){ $reason='La mesa ya existe en el local seleccionado.'; $duplicates++; }
                if(!$reason){
                    DB::table('mesa')->insert(['id_local'=>$localId,'numero_mesa'=>$numero,'total_electores'=>(int)$electores,'estado'=>$estado,'created_at'=>now(),'updated_at'=>now()]);
                    $seen[$key]=true; $new++;
                }else{ $errors++; $errorRows[]=array_merge($row,[$reason]); }
            }

            $errorFile=null;
            if($errorRows){
                $errorFile='errores_mesas_'.now()->format('Ymd_His').'_'.uniqid().'.xlsx';
                SimpleXlsx::write(storage_path('app/'.$errorFile),array_merge($headers,['error']),$errorRows,'Errores');
            }
            return back()->with('import_summary',['procesados'=>$processed,'nuevos'=>$new,'duplicados'=>$duplicates,'errores'=>$errors])->with('import_error_file',$errorFile);
        }catch(Throwable $e){
            return back()->with('error','No se pudo procesar el Excel: '.$e->getMessage());
        }
    }

    public function downloadImportErrors(string $filename)
    {
        $filename=basename($filename); $path=storage_path('app/'.$filename);
        abort_unless(is_file($path),404);
        return response()->download($path,$filename,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','X-Content-Type-Options'=>'nosniff'])->deleteFileAfterSend(true);
    }

}
