<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Support\SimpleXlsx;

class PersonasController extends Controller
{
    private function norm(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $value = strtr($value, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        ]);
        return mb_strtolower($value, 'UTF-8');
    }

    private function audit(string $accion, int $id, $old = null, $new = null): void
    {
        DB::table('auditoria')->insert([
            'id_usuario' => session('id_usuario'),
            'modulo' => 'Personas',
            'tabla_afectada' => 'persona',
            'id_registro' => $id,
            'accion' => $accion,
            'valor_anterior' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'valor_nuevo' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    public function index(Request $request)
    {
        $q = trim((string)$request->get('q', ''));
        $region = $request->integer('region') ?: null;
        $provincia = $request->integer('provincia') ?: null;
        $distrito = $request->integer('distrito') ?: null;
        $estado = $request->has('estado') && $request->estado !== '' ? (int)$request->estado : null;

        $query = DB::table('persona as p')
            ->leftJoin('region as r','r.id_region','=','p.id_region')
            ->leftJoin('provincia as pr','pr.id_provincia','=','p.id_provincia')
            ->leftJoin('distrito as d','d.id_distrito','=','p.id_distrito')
            ->select('p.*','r.nombre as region_nombre','pr.nombre as provincia_nombre','d.nombre as distrito_nombre');

        if ($q !== '') {
            $like='%'.$q.'%';
            $query->where(function($w) use ($like) {
                $w->where('p.dni','like',$like)->orWhere('p.nombres','like',$like)
                  ->orWhere('p.apellido_paterno','like',$like)->orWhere('p.apellido_materno','like',$like)
                  ->orWhereRaw("CONCAT(p.nombres,' ',p.apellido_paterno,' ',COALESCE(p.apellido_materno,'')) LIKE ?",[$like]);
            });
        }
        if ($region) $query->where('p.id_region',$region);
        if ($provincia) $query->where('p.id_provincia',$provincia);
        if ($distrito) $query->where('p.id_distrito',$distrito);
        if ($estado !== null) $query->where('p.estado',$estado);

        $personas=$query->orderBy('p.apellido_paterno')->orderBy('p.nombres')->paginate(\App\Support\Pagination::perPage($request, 15))->withQueryString();
        $regiones=DB::table('region')->where('estado',1)->orderBy('nombre')->get();
        $provincias=$region ? DB::table('provincia')->where('id_region',$region)->where('estado',1)->orderBy('nombre')->get() : collect();
        $distritos=$provincia ? DB::table('distrito')->where('id_provincia',$provincia)->where('estado',1)->orderBy('nombre')->get() : collect();

        return view('personas.index', compact('personas','regiones','provincias','distritos','q','region','provincia','distrito','estado'))
            ->with('sidebarModules', DashboardController::sidebarModules())
            ->with('title','Personas');
    }

    public function buscarDni(string $dni)
    {
        $dni=trim($dni);
        if ($dni==='') return response()->json(['found'=>false]);
        $p=DB::table('persona')->where('dni',$dni)->first();
        if (!$p) return response()->json(['found'=>false]);
        return response()->json(['found'=>true,'persona'=>$p]);
    }

    public function provincias(int $region)
    {
        return response()->json(DB::table('provincia')->where('id_region',$region)->where('estado',1)->orderBy('nombre')->get(['id_provincia','nombre']));
    }

    public function distritos(int $provincia)
    {
        return response()->json(DB::table('distrito')->where('id_provincia',$provincia)->where('estado',1)->orderBy('nombre')->get(['id_distrito','nombre']));
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'dni'=>['required','string','max:20','regex:/^[0-9]+$/'],
            'nombres'=>['required','string','max:150'],
            'apellido_paterno'=>['required','string','max:100'],
            'apellido_materno'=>['nullable','string','max:100'],
            'celular'=>['nullable','string','max:30'],
            'correo'=>['nullable','email','max:150'],
            'id_region'=>['nullable','integer','exists:region,id_region'],
            'id_provincia'=>['nullable','integer','exists:provincia,id_provincia'],
            'id_distrito'=>['nullable','integer','exists:distrito,id_distrito'],
        ],[
            'dni.regex'=>'El DNI solo debe contener números.',
            'nombres.required'=>'Ingrese los nombres.',
            'apellido_paterno.required'=>'Ingrese el apellido paterno.',
            'correo.email'=>'Ingrese un correo válido.',
        ]);
        $data['dni']=trim($data['dni']);
        if(DB::table('persona')->where('dni',$data['dni'])->exists())
            return back()->withInput()->with('error','El DNI ya existe. Utilice la búsqueda para editar la persona.');

        $this->validateHierarchy($data);
        $id=DB::table('persona')->insertGetId([
            ...$data,'estado'=>1,'created_at'=>now(),'updated_at'=>now()
        ]);
        $this->audit('REGISTRAR',$id,null,$data);
        return redirect()->route('personas.index')->with('success','Persona registrada correctamente.');
    }

    public function update(Request $request,int $id)
    {
        $p=DB::table('persona')->where('id_persona',$id)->firstOrFail();
        $data=$request->validate([
            'dni'=>['required','string','max:20','regex:/^[0-9]+$/'],
            'nombres'=>['required','string','max:150'],
            'apellido_paterno'=>['required','string','max:100'],
            'apellido_materno'=>['nullable','string','max:100'],
            'celular'=>['nullable','string','max:30'],
            'correo'=>['nullable','email','max:150'],
            'id_region'=>['nullable','integer','exists:region,id_region'],
            'id_provincia'=>['nullable','integer','exists:provincia,id_provincia'],
            'id_distrito'=>['nullable','integer','exists:distrito,id_distrito'],
        ]);
        $data['dni']=trim($data['dni']);
        if(DB::table('persona')->where('dni',$data['dni'])->where('id_persona','!=',$id)->exists())
            return back()->withInput()->with('error','El DNI ya está registrado en otra persona.');
        $this->validateHierarchy($data);
        $old=(array)$p;
        DB::transaction(function() use($id,$data,$p) {
            DB::table('persona')->where('id_persona',$id)->update([...$data,'updated_at'=>now()]);
            if($p->dni!==$data['dni']) {
                DB::table('usuario')->where('id_persona',$id)->update(['usuario'=>$data['dni'],'updated_at'=>now()]);
            }
        });
        $this->audit('EDITAR',$id,$old,$data);
        if($p->dni!==$data['dni']) $this->audit('RECTIFICAR_DNI',$id,['dni'=>$p->dni],['dni'=>$data['dni']]);
        return redirect()->route('personas.index')->with('success','Persona actualizada correctamente.');
    }

    public function toggle(int $id)
    {
        $p=DB::table('persona')->where('id_persona',$id)->firstOrFail();
        DB::table('persona')->where('id_persona',$id)->update(['estado'=>!$p->estado,'updated_at'=>now()]);
        $this->audit($p->estado?'DESHABILITAR':'HABILITAR',$id,['estado'=>(int)$p->estado],['estado'=>(int)!$p->estado]);
        return back()->with('success',$p->estado?'Persona deshabilitada.':'Persona habilitada.');
    }

    private function validateHierarchy(array $data): void
    {
        $r=$data['id_region']??null; $p=$data['id_provincia']??null; $d=$data['id_distrito']??null;
        if ($p && (!$r || !DB::table('provincia')->where('id_provincia',$p)->where('id_region',$r)->exists()))
            abort(422,'La provincia seleccionada no pertenece a la región.');
        if ($d && (!$p || !DB::table('distrito')->where('id_distrito',$d)->where('id_provincia',$p)->exists()))
            abort(422,'El distrito seleccionado no pertenece a la provincia.');
    }

    public function export()
    {
        $rows=DB::table('persona as p')->leftJoin('region as r','r.id_region','=','p.id_region')
            ->leftJoin('provincia as pr','pr.id_provincia','=','p.id_provincia')
            ->leftJoin('distrito as d','d.id_distrito','=','p.id_distrito')
            ->select('p.*','r.nombre as rn','pr.nombre as pn','d.nombre as dn')
            ->orderBy('p.apellido_paterno')->orderBy('p.nombres')->get()
            ->map(fn($p,$i)=>[$i+1,$p->dni,$p->nombres,$p->apellido_paterno,$p->apellido_materno,$p->celular,$p->correo,$p->rn,$p->pn,$p->dn,$p->estado?'Activo':'Inactivo'])->all();
        return SimpleXlsx::download(['N°','DNI','Nombres','Apellido paterno','Apellido materno','Celular','Correo','Región','Provincia','Distrito','Estado'],$rows,'personas_'.now()->format('Ymd_His').'.xlsx','Personas');
    }

    public function template()
    {
        return SimpleXlsx::download(['DNI','Nombres','Apellido paterno','Apellido materno','Celular','Correo','Región','Provincia','Distrito'],[],'plantilla_personas.xlsx','Personas');
    }

    public function import(Request $request)
    {
        $request->validate(['archivo'=>['required','file','mimes:xlsx','max:5120']],['archivo.required'=>'Seleccione un archivo Excel.','archivo.mimes'=>'El archivo debe ser .xlsx.','archivo.max'=>'El archivo no debe superar 5 MB.']);
        try{$rows=SimpleXlsx::read($request->file('archivo')->getRealPath());}catch(\Throwable $e){return back()->with('error','No se pudo leer el Excel: '.$e->getMessage());}
        if(!$rows) return back()->with('error','El archivo Excel está vacío.');
        $h=array_map(fn($v)=>$this->norm((string)$v),$rows[0]);
        $expected=['dni','nombres','apellido paterno','apellido materno','celular','correo','region','provincia','distrito'];
        if(array_slice($h,0,9)!==$expected) return back()->with('error','La plantilla no es válida. Descargue la plantilla oficial.');
        $seen=[];$stats=['procesados'=>0,'nuevos'=>0,'duplicados'=>0,'errores'=>0];$errors=[];
        foreach(array_slice($rows,1) as $i=>$row){
            $n=$i+2;$stats['procesados']++;
            $v=array_map(fn($x)=>trim((string)$x),array_pad($row,9,''));
            [$dni,$nom,$ap,$am,$cel,$mail,$rn,$pn,$dn]=$v;
            if($dni===''||$nom===''||$ap===''){ $stats['errores']++;$errors[]=[$n,$dni,$nom,$ap,'DNI, nombres y apellido paterno son obligatorios.'];continue;}
            if(!preg_match('/^[0-9]+$/',$dni)||mb_strlen($dni)>20){$stats['errores']++;$errors[]=[$n,$dni,$nom,$ap,'DNI inválido.'];continue;}
            if($mail!==''&&!filter_var($mail,FILTER_VALIDATE_EMAIL)){$stats['errores']++;$errors[]=[$n,$dni,$nom,$ap,'Correo electrónico inválido.'];continue;}
            $key=$this->norm($dni); if(isset($seen[$key])){$stats['duplicados']++;$errors[]=[$n,$dni,$nom,$ap,'DNI repetido dentro del Excel.'];continue;} $seen[$key]=1;
            if(DB::table('persona')->where('dni',$dni)->exists()){$stats['duplicados']++;$errors[]=[$n,$dni,$nom,$ap,'El DNI ya existe en la base de datos.'];continue;}
            $rid=$rn!==''?DB::table('region')->whereRaw('LOWER(TRIM(nombre))=LOWER(?)',[$rn])->value('id_region'):null;
            $pid=$pn!==''?DB::table('provincia')->where('id_region',$rid)->whereRaw('LOWER(TRIM(nombre))=LOWER(?)',[$pn])->value('id_provincia'):null;
            $did=$dn!==''?DB::table('distrito')->where('id_provincia',$pid)->whereRaw('LOWER(TRIM(nombre))=LOWER(?)',[$dn])->value('id_distrito'):null;
            if($rn!==''&&!$rid){$stats['errores']++;$errors[]=[$n,$dni,$nom,$ap,'La región no existe.'];continue;}
            if($pn!==''&&!$pid){$stats['errores']++;$errors[]=[$n,$dni,$nom,$ap,'La provincia no existe o no pertenece a la región.'];continue;}
            if($dn!==''&&!$did){$stats['errores']++;$errors[]=[$n,$dni,$nom,$ap,'El distrito no existe o no pertenece a la provincia.'];continue;}
            $id=DB::table('persona')->insertGetId(['dni'=>$dni,'nombres'=>$nom,'apellido_paterno'=>$ap,'apellido_materno'=>$am?:null,'celular'=>$cel?:null,'correo'=>$mail?:null,'id_region'=>$rid,'id_provincia'=>$pid,'id_distrito'=>$did,'estado'=>1,'created_at'=>now(),'updated_at'=>now()]);
            $this->audit('IMPORTAR',$id,null,['dni'=>$dni]);$stats['nuevos']++;
        }
        $file=null;if($errors){$file='errores_importacion_personas_'.now()->format('Ymd_His').'_'.Str::random(5).'.xlsx';SimpleXlsx::write(storage_path('app/'.$file),['Fila','DNI','Nombres','Apellido paterno','Error'],$errors);}
        return back()->with('import_summary',$stats)->with('import_error_file',$file);
    }
    public function errors(string $filename)
    {
        if(!preg_match('/^errores_importacion_personas_[A-Za-z0-9_-]+\.xlsx$/',$filename)) abort(404);
        $path=storage_path('app/'.$filename); abort_unless(is_file($path),404);
        return response()->download($path,$filename)->deleteFileAfterSend(true);
    }
}
