<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Support\SimpleXlsx;

class UsuariosController extends Controller
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

    private function admin(): bool
    {
        $id=session('id_usuario');
        return $id && DB::table('usuario_rol as ur')->join('rol as r','r.id_rol','=','ur.id_rol')
            ->where('ur.id_usuario',$id)->where('ur.estado',1)->where('r.estado',1)
            ->whereRaw('LOWER(r.nombre)=LOWER(?)',['Administrador'])->exists();
    }

    private function ensureAdmin(){ abort_unless($this->admin(),403,'No tiene permisos para administrar usuarios.'); }

    private function audit(string $accion,int $id,$old=null,$new=null):void
    {
        DB::table('auditoria')->insert([
            'id_usuario'=>session('id_usuario'),'modulo'=>'Usuarios','tabla_afectada'=>'usuario','id_registro'=>$id,
            'accion'=>$accion,'valor_anterior'=>$old?json_encode($old,JSON_UNESCAPED_UNICODE):null,
            'valor_nuevo'=>$new?json_encode($new,JSON_UNESCAPED_UNICODE):null,'ip'=>request()->ip(),
            'user_agent'=>substr((string)request()->userAgent(),0,500),'created_at'=>now()
        ]);
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();
        $q=trim((string)$request->get('q','')); $rol=$request->integer('rol')?:null;
        $estado=$request->has('estado')&&$request->estado!==''?(int)$request->estado:null;
        $query=DB::table('usuario as u')->join('persona as p','p.id_persona','=','u.id_persona')
            ->select('u.*','p.dni','p.nombres','p.apellido_paterno','p.apellido_materno');
        if($q!==''){$like='%'.$q.'%';$query->where(fn($w)=>$w->where('u.usuario','like',$like)->orWhere('p.dni','like',$like)->orWhere('p.nombres','like',$like)->orWhere('p.apellido_paterno','like',$like));}
        if($estado!==null)$query->where('u.estado',$estado);
        if($rol)$query->whereExists(fn($w)=>$w->select(DB::raw(1))->from('usuario_rol as ur')->whereColumn('ur.id_usuario','u.id_usuario')->where('ur.id_rol',$rol)->where('ur.estado',1));
        $usuarios=$query->orderBy('p.apellido_paterno')->paginate(\App\Support\Pagination::perPage($request, 15))->withQueryString();
        foreach($usuarios as $u){
            $u->roles=DB::table('usuario_rol as ur')->join('rol as r','r.id_rol','=','ur.id_rol')->where('ur.id_usuario',$u->id_usuario)->where('ur.estado',1)->pluck('r.nombre')->implode(', ');
            $u->ambitos=DB::table('usuario_ambito as a')->where('a.id_usuario',$u->id_usuario)->where('a.estado',1)->get()->map(function($a){
                if($a->nivel==='REGION')$name=DB::table('region')->where('id_region',$a->id_region)->value('nombre');
                elseif($a->nivel==='PROVINCIA')$name=DB::table('provincia')->where('id_provincia',$a->id_provincia)->value('nombre');
                elseif($a->nivel==='DISTRITO')$name=DB::table('distrito')->where('id_distrito',$a->id_distrito)->value('nombre');
                else $name=DB::table('local')->where('id_local',$a->id_local)->value('nombre');
                return $a->nivel.': '.$name;
            })->implode(' | ');
        }
        $roles=DB::table('rol')->where('estado',1)->orderBy('nombre')->get();
        return view('usuarios.index',compact('usuarios','roles','q','rol','estado'))->with('sidebarModules',DashboardController::sidebarModules())->with('title','Usuarios');
    }

    public function buscarPersona(string $dni)
    {
        $this->ensureAdmin(); $p=DB::table('persona')->where('dni',trim($dni))->first();
        if(!$p)return response()->json(['found'=>false]);
        $u=DB::table('usuario')->where('id_persona',$p->id_persona)->first();
        $roles=$u ? DB::table('usuario_rol')->where('id_usuario',$u->id_usuario)->where('estado',1)->pluck('id_rol')->values() : collect();
        $scope=$u ? DB::table('usuario_ambito')->where('id_usuario',$u->id_usuario)->where('estado',1)->first() : null;
        return response()->json(['found'=>true,'persona'=>$p,'usuario'=>$u,'roles'=>$roles,'scope'=>$scope]);
    }

    public function data()
    {
        $this->ensureAdmin();
        return response()->json([
            'roles'=>DB::table('rol')->where('estado',1)->orderBy('nombre')->get(['id_rol','nombre']),
            'regiones'=>DB::table('region')->where('estado',1)->orderBy('nombre')->get(['id_region','nombre'])
        ]);
    }

    public function scopeProvincias(int $region){$this->ensureAdmin();return response()->json(DB::table('provincia')->where('id_region',$region)->where('estado',1)->orderBy('nombre')->get(['id_provincia','nombre']));}
    public function scopeDistritos(int $provincia){$this->ensureAdmin();return response()->json(DB::table('distrito')->where('id_provincia',$provincia)->where('estado',1)->orderBy('nombre')->get(['id_distrito','nombre']));}
    public function scopeLocales(int $distrito){$this->ensureAdmin();return response()->json(DB::table('local')->where('id_distrito',$distrito)->where('estado',1)->orderBy('nombre')->get(['id_local','nombre']));}

    private function allowedScopeLevels(array $roleIds): array
    {
        $names = DB::table('rol')
            ->whereIn('id_rol', array_values(array_unique(array_map('intval', $roleIds))))
            ->where('estado', 1)
            ->pluck('nombre')
            ->all();

        $map = [
            'Administrador' => ['ADMIN'],
            'Personero Regional' => ['REGION'],
            'Personero Provincial' => ['PROVINCIA'],
            'Personero Distrital' => ['DISTRITO'],
            'Personero de Local' => ['LOCAL'],
            'Personero de Mesa' => ['LOCAL'],
            'Digitador' => ['REGION','PROVINCIA','DISTRITO'],
        ];

        $allowed = [];
        foreach ($names as $name) {
            $allowed = array_merge($allowed, $map[$name] ?? ['REGION','PROVINCIA','DISTRITO']);
        }

        $allowed = array_values(array_unique($allowed));
        return in_array('ADMIN', $allowed, true) ? ['ADMIN'] : ($allowed ?: ['REGION','PROVINCIA','DISTRITO']);
    }

    private function saveRoles(int $uid,array $roleIds):void
    {
        DB::table('usuario_rol')->where('id_usuario',$uid)->update(['estado'=>0,'updated_at'=>now()]);
        foreach(array_unique(array_map('intval',$roleIds)) as $rid){
            if(DB::table('rol')->where('id_rol',$rid)->where('estado',1)->exists()){
                DB::table('usuario_rol')->updateOrInsert(['id_usuario'=>$uid,'id_rol'=>$rid],['estado'=>1,'updated_at'=>now(),'created_at'=>now()]);
            }
        }
    }

    private function saveScope(int $uid,array $data):void
    {
        DB::table('usuario_ambito')->where('id_usuario',$uid)->update(['estado'=>0,'updated_at'=>now()]);
        $nivel=$data['nivel']??'';
        if($nivel==='ADMIN')return;
        $row=['id_usuario'=>$uid,'nivel'=>$nivel,'id_region'=>null,'id_provincia'=>null,'id_distrito'=>null,'id_local'=>null,'estado'=>1,'created_at'=>now(),'updated_at'=>now()];
        if($nivel==='REGION'){$row['id_region']=(int)$data['id_region'];}
        elseif($nivel==='PROVINCIA'){$row['id_region']=(int)$data['id_region'];$row['id_provincia']=(int)$data['id_provincia'];}
        elseif($nivel==='DISTRITO'){$row['id_region']=(int)$data['id_region'];$row['id_provincia']=(int)$data['id_provincia'];$row['id_distrito']=(int)$data['id_distrito'];}
        elseif($nivel==='LOCAL'){$row['id_region']=(int)$data['id_region'];$row['id_provincia']=(int)$data['id_provincia'];$row['id_distrito']=(int)$data['id_distrito'];$row['id_local']=(int)$data['id_local'];}
        else throw new \InvalidArgumentException('Seleccione un nivel de ámbito válido.');
        if($nivel==='REGION'&&!DB::table('region')->where('id_region',$row['id_region'])->where('estado',1)->exists())throw new \InvalidArgumentException('Región inválida.');
        if($nivel==='PROVINCIA'&&!DB::table('provincia')->where('id_provincia',$row['id_provincia'])->where('id_region',$row['id_region'])->where('estado',1)->exists())throw new \InvalidArgumentException('Provincia no pertenece a la región.');
        if($nivel==='DISTRITO'&&!DB::table('distrito')->where('id_distrito',$row['id_distrito'])->where('id_provincia',$row['id_provincia'])->where('estado',1)->exists())throw new \InvalidArgumentException('Distrito no pertenece a la provincia.');
        if($nivel==='LOCAL'&&!DB::table('local')->where('id_local',$row['id_local'])->where('id_distrito',$row['id_distrito'])->where('estado',1)->exists())throw new \InvalidArgumentException('Local no pertenece al distrito.');
        DB::table('usuario_ambito')->insert($row);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();
        $data=$request->validate(['dni'=>'required|string|max:20','roles'=>'required|array|min:1','roles.*'=>'integer','nivel'=>'required|string','id_region'=>'nullable|integer','id_provincia'=>'nullable|integer','id_distrito'=>'nullable|integer','id_local'=>'nullable|integer']);
        $allowedScopes = $this->allowedScopeLevels($data['roles']);
        if (!in_array($data['nivel'], $allowedScopes, true)) {
            return back()->withInput()->with('error','El nivel de ámbito no corresponde a los roles seleccionados.');
        }

        $p=DB::table('persona')->where('dni',trim($data['dni']))->first();
        if(!$p)return back()->withInput()->with('error','El DNI no existe en Personas. Registre primero la persona.');
        if(DB::table('usuario')->where('id_persona',$p->id_persona)->exists())return back()->withInput()->with('error','La persona ya tiene un usuario.');
        try{
            $uid=DB::transaction(function()use($p,$data){
                $uid=DB::table('usuario')->insertGetId(['id_persona'=>$p->id_persona,'usuario'=>$p->dni,'password_hash'=>Hash::make($p->dni),'debe_cambiar_password'=>1,'estado'=>1,'created_at'=>now(),'updated_at'=>now()]);
                $this->saveRoles($uid,$data['roles']); $this->saveScope($uid,$data); return $uid;
            });
        }catch(\Throwable $e){return back()->withInput()->with('error','No se pudo registrar el usuario: '.$e->getMessage());}
        $this->audit('REGISTRAR',$uid,null,['id_persona'=>$p->id_persona,'usuario'=>$p->dni]);
        return redirect()->route('usuarios.index')->with('success','Usuario registrado correctamente. La contraseña inicial es el DNI y deberá cambiarla al ingresar.');
    }

    public function update(Request $request,int $id)
    {
        $this->ensureAdmin(); $u=DB::table('usuario')->where('id_usuario',$id)->firstOrFail();
        $data=$request->validate(['roles'=>'required|array|min:1','roles.*'=>'integer','nivel'=>'required|string','id_region'=>'nullable|integer','id_provincia'=>'nullable|integer','id_distrito'=>'nullable|integer','id_local'=>'nullable|integer']);
        $allowedScopes = $this->allowedScopeLevels($data['roles']);
        if (!in_array($data['nivel'], $allowedScopes, true)) {
            return back()->withInput()->with('error','El nivel de ámbito no corresponde a los roles seleccionados.');
        }
        try{DB::transaction(function()use($id,$data){$this->saveRoles($id,$data['roles']);$this->saveScope($id,$data);});}
        catch(\Throwable $e){return back()->with('error','No se pudo actualizar el usuario: '.$e->getMessage());}
        $this->audit('EDITAR',$id,null,$data);
        return back()->with('success','Usuario actualizado correctamente.');
    }

    public function toggle(int $id)
    {
        $this->ensureAdmin(); $u=DB::table('usuario')->where('id_usuario',$id)->firstOrFail();
        if($id==session('id_usuario')&&$u->estado)return back()->with('error','No puede deshabilitar su propio usuario.');
        DB::table('usuario')->where('id_usuario',$id)->update(['estado'=>!$u->estado,'updated_at'=>now()]);
        $this->audit($u->estado?'DESHABILITAR':'HABILITAR',$id,['estado'=>(int)$u->estado],['estado'=>(int)!$u->estado]);
        return back()->with('success',$u->estado?'Usuario deshabilitado.':'Usuario habilitado.');
    }

    public function resetPassword(int $id)
    {
        $this->ensureAdmin(); $u=DB::table('usuario as u')->join('persona as p','p.id_persona','=','u.id_persona')->where('u.id_usuario',$id)->select('u.*','p.dni')->firstOrFail();
        DB::table('usuario')->where('id_usuario',$id)->update(['password_hash'=>Hash::make($u->dni),'debe_cambiar_password'=>1,'password_changed_at'=>null,'updated_at'=>now()]);
        $this->audit('RESTABLECER_PASSWORD',$id,null,['debe_cambiar_password'=>1]);
        return back()->with('success','Contraseña restablecida al DNI. El usuario deberá cambiarla al ingresar.');
    }

    public function export()
    {
        $this->ensureAdmin();
        $users=DB::table('usuario as u')->join('persona as p','p.id_persona','=','u.id_persona')->select('u.*','p.dni','p.nombres','p.apellido_paterno','p.apellido_materno')->orderBy('p.apellido_paterno')->get();
        $rows=$users->map(function($u,$i){
            $roles=DB::table('usuario_rol as ur')->join('rol as r','r.id_rol','=','ur.id_rol')->where('ur.id_usuario',$u->id_usuario)->where('ur.estado',1)->pluck('r.nombre')->implode(' | ');
            $scope=DB::table('usuario_ambito')->where('id_usuario',$u->id_usuario)->where('estado',1)->first();
            return [$i+1,$u->dni,$u->nombres,$u->apellido_paterno,$u->apellido_materno,$roles,$scope?->nivel?:'ADMIN','','','','',$u->estado?'Activo':'Inactivo',$u->debe_cambiar_password?'Sí':'No',$u->ultimo_acceso?:''];
        })->all();
        return SimpleXlsx::download(['N°','DNI','Nombres','Apellido paterno','Apellido materno','Roles','Nivel ámbito','Región','Provincia','Distrito','Local','Estado','Debe cambiar contraseña','Último acceso'],$rows,'usuarios_'.now()->format('Ymd_His').'.xlsx','Usuarios');
    }

    public function template()
    {
        return SimpleXlsx::download(['DNI','Roles','Nivel ámbito','Región','Provincia','Distrito','Local','Estado'],[],'plantilla_usuarios.xlsx','Usuarios');
    }

    public function import(Request $request)
    {
        $this->ensureAdmin();
        $request->validate(['archivo'=>'required|file|mimes:xlsx|max:5120']);
        try{$rows=SimpleXlsx::read($request->file('archivo')->getRealPath());}catch(\Throwable $e){return back()->with('error','No se pudo leer el Excel: '.$e->getMessage());}
        if(!$rows)return back()->with('error','El archivo Excel está vacío.');
        $h=array_map(fn($v)=>$this->normalizeImportHeader((string)$v),$rows[0]);
        if(array_slice($h,0,8)!==['dni','roles','nivel ambito','region','provincia','distrito','local','estado'])return back()->with('error','La plantilla no es válida. Descargue la plantilla oficial.');
        $stats=['procesados'=>0,'nuevos'=>0,'duplicados'=>0,'errores'=>0];$errors=[];$seen=[];
        foreach(array_slice($rows,1) as $i=>$row){
            $n=$i+2;$stats['procesados']++;$v=array_map(fn($x)=>trim((string)$x),array_pad($row,8,''));[$dni,$rolesText,$nivel,$rn,$pn,$dn,$ln,$estado]=$v;
            if($dni===''||$rolesText===''||$nivel===''){ $stats['errores']++;$errors[]=[$n,$dni,'DNI, Roles y Nivel ámbito son obligatorios.'];continue;}
            $key=strtolower($dni);if(isset($seen[$key])){$stats['duplicados']++;$errors[]=[$n,$dni,'DNI repetido dentro del Excel.'];continue;}$seen[$key]=1;
            $p=DB::table('persona')->where('dni',$dni)->first();if(!$p){$stats['errores']++;$errors[]=[$n,$dni,'El DNI no existe en Personas.'];continue;}
            if(DB::table('usuario')->where('id_persona',$p->id_persona)->exists()){$stats['duplicados']++;$errors[]=[$n,$dni,'La persona ya tiene usuario.'];continue;}
            $roleNames=preg_split('/\s*[|;,]\s*/',$rolesText,-1,PREG_SPLIT_NO_EMPTY);$rids=[];
            foreach($roleNames as $rnRole){$rid=DB::table('rol')->whereRaw('LOWER(nombre)=LOWER(?)',[$rnRole])->where('estado',1)->value('id_rol');if(!$rid){$stats['errores']++;$errors[]=[$n,$dni,'Rol no encontrado: '.$rnRole];continue 2;}$rids[]=$rid;}
            $scope=['nivel'=>strtoupper(trim($nivel))];
            if($scope['nivel']!=='ADMIN'){
                $rid=$rn!==''?DB::table('region')->whereRaw('LOWER(TRIM(nombre))=LOWER(?)',[$rn])->where('estado',1)->value('id_region'):null;
                if(!$rid){$stats['errores']++;$errors[]=[$n,$dni,'Región requerida o inexistente.'];continue;}
                $scope['id_region']=$rid;
                if($scope['nivel']==='PROVINCIA'||$scope['nivel']==='DISTRITO'||$scope['nivel']==='LOCAL'){$pid=$pn!==''?DB::table('provincia')->where('id_region',$rid)->whereRaw('LOWER(TRIM(nombre))=LOWER(?)',[$pn])->where('estado',1)->value('id_provincia'):null;if(!$pid){$stats['errores']++;$errors[]=[$n,$dni,'Provincia requerida o inexistente.'];continue;}$scope['id_provincia']=$pid;}
                if($scope['nivel']==='DISTRITO'||$scope['nivel']==='LOCAL'){$did=$dn!==''?DB::table('distrito')->where('id_provincia',$scope['id_provincia'])->whereRaw('LOWER(TRIM(nombre))=LOWER(?)',[$dn])->where('estado',1)->value('id_distrito'):null;if(!$did){$stats['errores']++;$errors[]=[$n,$dni,'Distrito requerido o inexistente.'];continue;}$scope['id_distrito']=$did;}
                if($scope['nivel']==='LOCAL'){$lid=$ln!==''?DB::table('local')->where('id_distrito',$scope['id_distrito'])->whereRaw('LOWER(TRIM(nombre))=LOWER(?)',[$ln])->where('estado',1)->value('id_local'):null;if(!$lid){$stats['errores']++;$errors[]=[$n,$dni,'Local requerido o inexistente.'];continue;}$scope['id_local']=$lid;}
            }
            try{$uid=DB::transaction(function()use($p,$rids,$scope,$estado){$uid=DB::table('usuario')->insertGetId(['id_persona'=>$p->id_persona,'usuario'=>$p->dni,'password_hash'=>Hash::make($p->dni),'debe_cambiar_password'=>1,'estado'=>strtolower($estado)==='inactivo'?0:1,'created_at'=>now(),'updated_at'=>now()]);$this->saveRoles($uid,$rids);$this->saveScope($uid,$scope);return $uid;});$this->audit('IMPORTAR',$uid,null,['usuario'=>$p->dni]);$stats['nuevos']++;}catch(\Throwable $e){$stats['errores']++;$errors[]=[$n,$dni,'No se pudo registrar: '.$e->getMessage()];}
        }
        $file=null;if($errors){$file='errores_importacion_usuarios_'.now()->format('Ymd_His').'_'.Str::random(5).'.xlsx';SimpleXlsx::write(storage_path('app/'.$file),['Fila','DNI','Error'],$errors);}
        return back()->with('import_summary',$stats)->with('import_error_file',$file);
    }
    public function errors(string $filename){if(!preg_match('/^errores_importacion_usuarios_[A-Za-z0-9_-]+\.xlsx$/',$filename))abort(404);$path=storage_path('app/'.$filename);abort_unless(is_file($path),404);return response()->download($path,$filename)->deleteFileAfterSend(true);}
}
