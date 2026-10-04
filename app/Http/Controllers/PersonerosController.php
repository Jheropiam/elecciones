<?php

namespace App\Http\Controllers;

use App\Support\SimpleXlsx;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class PersonerosController extends Controller
{
    private const TYPES = [
        'regional' => [
            'label' => 'Personero Regional',
            'table' => 'personero_regional',
            'pk' => 'id_personero_regional',
            'scope' => 'REGION',
            'role' => 'Personero Regional',
            'field' => 'id_region',
        ],
        'provincia' => [
            'label' => 'Personero de Provincia',
            'table' => 'personero_provincia',
            'pk' => 'id_personero_provincia',
            'scope' => 'PROVINCIA',
            'role' => 'Personero Provincial',
            'field' => 'id_provincia',
        ],
        'distrito' => [
            'label' => 'Personero de Distrito',
            'table' => 'personero_distrito',
            'pk' => 'id_personero_distrito',
            'scope' => 'DISTRITO',
            'role' => 'Personero Distrital',
            'field' => 'id_distrito',
        ],
        'local' => [
            'label' => 'Personero de Local',
            'table' => 'personero_local',
            'pk' => 'id_personero_local',
            'scope' => 'LOCAL',
            'role' => 'Personero de Local',
            'field' => 'id_local',
        ],
        'mesa' => [
            'label' => 'Personero de Mesa',
            'table' => 'personero_mesa',
            'pk' => 'id_personero_mesa',
            'scope' => null,
            'role' => null,
            'field' => 'id_mesa',
        ],
    ];

    private function cfg(string $tipo): array
    {
        abort_unless(isset(self::TYPES[$tipo]), 404);
        return self::TYPES[$tipo];
    }

    public function index(Request $request)
    {
        $tipo = $request->get('tipo', 'regional');
        $esAvance = ($tipo === 'avance');
        if ($esAvance) $tipo = 'regional';
        if (!isset(self::TYPES[$tipo])) $tipo = 'regional';

        $q = trim((string)$request->get('q', ''));
        $estado = $request->has('estado') ? (string)$request->get('estado') : '';
        $region = $request->get('region');
        $provincia = $request->get('provincia');
        $distrito = $request->get('distrito');
        $local = $request->get('local');
        $mesa = $request->get('mesa');
        $bloquearFiltroRegion = false;
        $bloquearFiltroProvincia = false;

        // Restricciones de ámbito exclusivas del submódulo Personero de Provincia.
        // Administrador: puede recorrer todas las regiones y provincias.
        // Personero/Candidato Regional: región fijada a la ficha de persona;
        // puede seleccionar cualquier provincia dentro de esa región.
        // Los demás perfiles: región y provincia fijadas a su ficha de persona.
        $esAdmin = \App\Support\AccessControl::isAdmin();
        if ($tipo === 'provincia' && !$esAdmin) {
            $bloquearFiltroRegion = true;
            $idUsuarioActual = (int) session('id_usuario');
            $personaActual = DB::table('usuario as u')
                ->join('persona as pe', 'pe.id_persona', '=', 'u.id_persona')
                ->where('u.id_usuario', $idUsuarioActual)
                ->select('pe.id_region', 'pe.id_provincia')
                ->first();

            $rolesActuales = DB::table('usuario_rol as ur')
                ->join('rol as ro', 'ro.id_rol', '=', 'ur.id_rol')
                ->where('ur.id_usuario', $idUsuarioActual)
                ->where('ur.estado', 1)
                ->where('ro.estado', 1)
                ->pluck('ro.nombre')
                ->map(fn ($nombre) => mb_strtolower(trim((string) $nombre)))
                ->all();
            $esAmbitoRegional = collect($rolesActuales)->contains(fn ($rol) =>
                in_array($rol, ['personero regional', 'candidato regional'], true)
            );

            if ($personaActual) {
                $idRegionPersona = $personaActual->id_region ? (int) $personaActual->id_region : null;
                $idProvinciaPersona = $personaActual->id_provincia ? (int) $personaActual->id_provincia : null;
                if (!$idRegionPersona && $idProvinciaPersona) {
                    $idRegionPersona = DB::table('provincia')->where('id_provincia', $idProvinciaPersona)->value('id_region');
                    $idRegionPersona = $idRegionPersona ? (int) $idRegionPersona : null;
                }
                $region = $idRegionPersona;
                if (!$esAmbitoRegional) {
                    $provincia = $idProvinciaPersona;
                    $bloquearFiltroProvincia = true;
                }
            } else {
                $region = null;
                $provincia = null;
            }
        }

        // En Personero de Distrito, los filtros y las consultas respetan el ámbito
        // territorial registrado en persona. El administrador conserva acceso total.
        if ($tipo === 'distrito' && !$esAdmin) {
            $idUsuarioActual = (int) session('id_usuario');
            $personaActual = DB::table('usuario as u')
                ->join('persona as pe', 'pe.id_persona', '=', 'u.id_persona')
                ->where('u.id_usuario', $idUsuarioActual)
                ->select('pe.id_region', 'pe.id_provincia', 'pe.id_distrito')
                ->first();

            $rolesActuales = DB::table('usuario_rol as ur')
                ->join('rol as ro', 'ro.id_rol', '=', 'ur.id_rol')
                ->where('ur.id_usuario', $idUsuarioActual)
                ->where('ur.estado', 1)
                ->where('ro.estado', 1)
                ->pluck('ro.nombre')
                ->map(fn ($nombre) => mb_strtolower(trim((string) $nombre)))
                ->all();

            $esAmbitoRegional = collect($rolesActuales)->contains(fn ($rol) =>
                in_array($rol, ['personero regional', 'candidato regional'], true)
            );
            $esAmbitoProvincial = collect($rolesActuales)->contains(fn ($rol) =>
                in_array($rol, ['personero provincial', 'candidato provincia', 'candidato provincial'], true)
            );

            if ($personaActual) {
                $idRegionPersona = $personaActual->id_region ? (int) $personaActual->id_region : null;
                $idProvinciaPersona = $personaActual->id_provincia ? (int) $personaActual->id_provincia : null;
                $idDistritoPersona = $personaActual->id_distrito ? (int) $personaActual->id_distrito : null;

                if (!$idProvinciaPersona && $idDistritoPersona) {
                    $idProvinciaPersona = DB::table('distrito')->where('id_distrito', $idDistritoPersona)->value('id_provincia');
                    $idProvinciaPersona = $idProvinciaPersona ? (int) $idProvinciaPersona : null;
                }
                if (!$idRegionPersona && $idProvinciaPersona) {
                    $idRegionPersona = DB::table('provincia')->where('id_provincia', $idProvinciaPersona)->value('id_region');
                    $idRegionPersona = $idRegionPersona ? (int) $idRegionPersona : null;
                }

                $region = $idRegionPersona;
                $bloquearFiltroRegion = true;

                if (!$esAmbitoRegional) {
                    $provincia = $idProvinciaPersona;
                    $bloquearFiltroProvincia = true;
                }
                if (!$esAmbitoRegional && !$esAmbitoProvincial) {
                    $distrito = $idDistritoPersona;
                }
            } else {
                $region = $provincia = $distrito = null;
                $bloquearFiltroRegion = true;
                $bloquearFiltroProvincia = true;
            }
        }

        $regionesQuery = DB::table('region')->where('estado', 1);
        if ($tipo === 'distrito' && !$esAdmin && $region !== null && $region !== '') {
            $regionesQuery->where('id_region', (int) $region);
        }
        $regiones = $regionesQuery->orderBy('nombre')->get();
        $provinciasQuery = DB::table('provincia')->where('estado', 1);
        if (in_array($tipo, ['provincia', 'distrito', 'local', 'mesa'], true) && $region !== null && $region !== '') {
            $provinciasQuery->where('id_region', (int) $region);
        }
        if ($tipo === 'distrito' && !$esAdmin && $bloquearFiltroProvincia && $provincia !== null && $provincia !== '') {
            $provinciasQuery->where('id_provincia', (int) $provincia);
        }
        $provincias = $provinciasQuery->orderBy('nombre')->get();
        $distritosQuery = DB::table('distrito')->where('estado', 1);
        if (in_array($tipo, ['distrito', 'local', 'mesa'], true) && $provincia !== null && $provincia !== '') {
            $distritosQuery->where('id_provincia', (int) $provincia);
        }
        if ($tipo === 'distrito' && !$esAdmin && !$esAmbitoRegional && !$esAmbitoProvincial && $distrito !== null && $distrito !== '') {
            $distritosQuery->where('id_distrito', (int) $distrito);
        }
        $distritos = $distritosQuery->orderBy('nombre')->get();
        $localesQuery = DB::table('local')->where('estado', 1);
        if (in_array($tipo, ['local', 'mesa'], true) && $distrito !== null && $distrito !== '') $localesQuery->where('id_distrito', (int) $distrito);
        $locales = $localesQuery->orderBy('nombre')->get();
        $mesasQuery = DB::table('mesa')->where('estado', 1);
        if ($tipo === 'mesa' && $local !== null && $local !== '') $mesasQuery->where('id_local', (int) $local);
        $mesas = $mesasQuery->orderBy('numero_mesa')->get();

        // En Personero Regional, los usuarios que no son Administradores
        // deben trabajar automáticamente con la región registrada en su
        // propia ficha de persona. Se fuerza también en el servidor para
        // evitar que puedan consultar otra región manipulando la URL.
        if ($tipo === 'regional' && !$esAdmin) {
            $idUsuario = (int) session('id_usuario');
            $idRegionUsuario = DB::table('usuario as u')
                ->join('persona as pe', 'pe.id_persona', '=', 'u.id_persona')
                ->where('u.id_usuario', $idUsuario)
                ->value('pe.id_region');
            $region = $idRegionUsuario ? (int) $idRegionUsuario : null;
        }

        $c = self::TYPES[$tipo];
        $personeros = collect();

        if (!$esAvance) {
            $query = DB::table($c['table'].' as x')
                ->join('persona as pe', 'pe.id_persona', '=', 'x.id_persona')
                ->select('x.*', 'pe.dni', 'pe.nombres', 'pe.apellido_paterno', 'pe.apellido_materno', 'pe.celular');

            if ($tipo === 'regional') {
                $query->join('region as r', 'r.id_region', '=', 'x.id_region')->addSelect('r.nombre as region_nombre');
                if ($region) $query->where('x.id_region', $region);
            } elseif ($tipo === 'provincia') {
                $query->join('provincia as p', 'p.id_provincia', '=', 'x.id_provincia')->join('region as r', 'r.id_region', '=', 'p.id_region')->addSelect('r.nombre as region_nombre','p.nombre as provincia_nombre');
                if ($region) $query->where('p.id_region', $region);
                if ($provincia) $query->where('x.id_provincia', $provincia);
            } elseif ($tipo === 'distrito') {
                $query->join('distrito as d', 'd.id_distrito', '=', 'x.id_distrito')->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')->join('region as r', 'r.id_region', '=', 'p.id_region')->addSelect('r.nombre as region_nombre','p.nombre as provincia_nombre','d.nombre as distrito_nombre');
                if ($region) $query->where('p.id_region', $region);
                if ($provincia) $query->where('d.id_provincia', $provincia);
                if ($distrito) $query->where('x.id_distrito', $distrito);
            } elseif ($tipo === 'local') {
                $query->join('local as l', 'l.id_local', '=', 'x.id_local')->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')->join('region as r', 'r.id_region', '=', 'p.id_region')->addSelect('r.nombre as region_nombre','p.nombre as provincia_nombre','d.nombre as distrito_nombre','l.nombre as local_nombre');
                if ($region) $query->where('p.id_region', $region);
                if ($provincia) $query->where('d.id_provincia', $provincia);
                if ($distrito) $query->where('l.id_distrito', $distrito);
                if ($local) $query->where('x.id_local', $local);
            } else {
                $query->join('mesa as m', 'm.id_mesa', '=', 'x.id_mesa')->join('local as l', 'l.id_local', '=', 'm.id_local')->join('distrito as d', 'd.id_distrito', '=', 'l.id_distrito')->join('provincia as p', 'p.id_provincia', '=', 'd.id_provincia')->join('region as r', 'r.id_region', '=', 'p.id_region')->addSelect('r.nombre as region_nombre','p.nombre as provincia_nombre','d.nombre as distrito_nombre','l.nombre as local_nombre','m.numero_mesa');
                if ($region) $query->where('p.id_region', $region);
                if ($provincia) $query->where('d.id_provincia', $provincia);
                if ($distrito) $query->where('l.id_distrito', $distrito);
                if ($local) $query->where('m.id_local', $local);
                if ($mesa) $query->where('x.id_mesa', $mesa);
            }
            if ($q !== '') {
                $like='%'.$q.'%';
                $query->where(function($w) use ($like) {
                    $w->where('pe.dni','like',$like)->orWhere('pe.nombres','like',$like)->orWhere('pe.apellido_paterno','like',$like)->orWhere('pe.apellido_materno','like',$like);
                });
            }
            if ($estado !== '') $query->where('x.estado', (int)$estado);
            $personeros=$query->orderBy('pe.apellido_paterno')->orderBy('pe.nombres')->paginate(\App\Support\Pagination::perPage($request, 15))->withQueryString();
        }

        // Avance: una mesa se considera cubierta cuando tiene personero de mesa TITULAR activo.
        $totalMesas = DB::table('mesa')->where('estado',1)->count();
        $mesasConTitular = DB::table('personero_mesa as pm')
            ->join('mesa as m','m.id_mesa','=','pm.id_mesa')
            ->where('pm.estado',1)->where('pm.condicion','TITULAR')->where('m.estado',1)->count();
        $avance = [
            'total_mesas' => $totalMesas,
            'cubiertas' => $mesasConTitular,
            'pendientes' => max(0,$totalMesas-$mesasConTitular),
            'porcentaje' => $totalMesas ? round(($mesasConTitular/$totalMesas)*100,2) : 0,
        ];

        return view('personeros.index', compact(
            'tipo','esAvance','regiones','provincias','distritos','locales','mesas',
            'personeros','q','estado','region','provincia','distrito','local','mesa','avance',
            'bloquearFiltroRegion','bloquearFiltroProvincia'
        ))->with('sidebarModules', DashboardController::sidebarModules())
          ->with('title', 'Personeros');
    }

    public function buscarPersona(string $dni)
    {
        $p = DB::table('persona')
            ->where('dni', trim($dni))
            ->where('estado',1)->first();

        if (!$p) return response()->json(['ok'=>false,'message'=>'La persona no está registrada en Personas.']);
        return response()->json([
            'ok'=>true,
            'id_persona'=>$p->id_persona,
            'nombre'=>trim($p->nombres.' '.$p->apellido_paterno.' '.($p->apellido_materno ?? '')),
            'dni'=>$p->dni,
        ]);
    }

    public function provincias($region)
    {
        return response()->json(DB::table('provincia')->where('id_region',$region)->where('estado',1)->orderBy('nombre')->get(['id_provincia','nombre']));
    }

    public function distritos($provincia)
    {
        return response()->json(DB::table('distrito')->where('id_provincia',$provincia)->where('estado',1)->orderBy('nombre')->get(['id_distrito','nombre']));
    }

    public function locales($distrito)
    {
        return response()->json(DB::table('local')->where('id_distrito',$distrito)->where('estado',1)->orderBy('nombre')->get(['id_local','nombre']));
    }

    public function mesas($local)
    {
        return response()->json(DB::table('mesa')->where('id_local',$local)->where('estado',1)->orderBy('numero_mesa')->get(['id_mesa','numero_mesa','total_electores']));
    }

    private function roleAndUser(int $idPersona, array $c, ?int $scopeId, Request $request): ?int
    {
        if (!$c['role']) return null;

        $persona=DB::table('persona')->where('id_persona',$idPersona)->first();
        if (!$persona) throw new \RuntimeException('La persona no existe.');

        $usuario=DB::table('usuario')->where('id_persona',$idPersona)->first();
        if (!$usuario) {
            $usuarioId=DB::table('usuario')->insertGetId([
                'id_persona'=>$idPersona,
                'usuario'=>$persona->dni,
                'password_hash'=>Hash::make($persona->dni),
                'debe_cambiar_password'=>1,
                'estado'=>1,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
        } else {
            $usuarioId=$usuario->id_usuario;
        }

        $rol=DB::table('rol')->where('nombre',$c['role'])->first();
        if (!$rol) {
            $rolId=DB::table('rol')->insertGetId(['nombre'=>$c['role'],'estado'=>1,'created_at'=>now(),'updated_at'=>now()]);
        } else {
            $rolId=$rol->id_rol;
        }
        DB::table('usuario_rol')->updateOrInsert(
            ['id_usuario'=>$usuarioId,'id_rol'=>$rolId],
            ['estado'=>1,'updated_at'=>now()]
        );

        // Para evitar duplicar ámbitos del mismo nivel para el mismo usuario,
        // reutilizamos uno idéntico y dejamos históricos inactivos.
        if ($scopeId) {
            $where=['id_usuario'=>$usuarioId,'nivel'=>$c['scope']];
            if ($c['scope']==='REGION') $where['id_region']=$scopeId;
            if ($c['scope']==='PROVINCIA') $where['id_provincia']=$scopeId;
            if ($c['scope']==='DISTRITO') $where['id_distrito']=$scopeId;
            if ($c['scope']==='LOCAL') $where['id_local']=$scopeId;
            $exists=DB::table('usuario_ambito')->where($where)->first();
            if (!$exists) {
                DB::table('usuario_ambito')->insert(array_merge($where,[
                    'estado'=>1,'created_at'=>now(),'updated_at'=>now()
                ]));
            } else {
                DB::table('usuario_ambito')->where('id_usuario_ambito',$exists->id_usuario_ambito)->update(['estado'=>1,'updated_at'=>now()]);
            }
        }
        return $usuarioId;
    }

    private function validateScope(Request $request, string $tipo): array
    {
        $c=$this->cfg($tipo);
        $idPersona=(int)$request->input('id_persona');
        if (!$idPersona || !DB::table('persona')->where('id_persona',$idPersona)->where('estado',1)->exists()) {
            throw new \RuntimeException('Debe buscar y seleccionar una persona registrada en Personas.');
        }
        $cond=$request->input('condicion');
        if (!in_array($cond,['TITULAR','PRIMER_SUPLENTE','SEGUNDO_SUPLENTE','TERCER_SUPLENTE'],true)) {
            throw new \RuntimeException('La condición seleccionada no es válida.');
        }

        $field=$c['field'];
        $scopeId=(int)$request->input($field);
        if (!$scopeId) throw new \RuntimeException('Debe seleccionar el ámbito correspondiente.');

        $table=$c['table'];
        $dup=DB::table($table)->where($field,$scopeId)->where('condicion',$cond)->where('estado',1);
        if ($request->route('id')) $dup->where($c['pk'],'<>',(int)$request->route('id'));
        if ($dup->exists()) throw new \RuntimeException('Ya existe un personero activo con esa condición en el ámbito seleccionado.');

        if ($tipo==='mesa') {
            if (!DB::table('mesa')->where('id_mesa',$scopeId)->where('estado',1)->exists()) throw new \RuntimeException('La mesa seleccionada no está activa.');
        }
        return [$idPersona,$scopeId,$cond,$c];
    }

    public function store(Request $request, string $tipo)
    {
        try {
            [$idPersona,$scopeId,$cond,$c]=$this->validateScope($request,$tipo);

            $usuarioId=$this->roleAndUser($idPersona,$c,$scopeId,$request);
            $id = DB::table($c['table'])->insertGetId([
                'id_persona'=>$idPersona,
                'id_usuario'=>$usuarioId,
                $c['field']=>$scopeId,
                'condicion'=>$cond,
                'estado'=>1,
                'created_at'=>now(),'updated_at'=>now()
            ]);
            AuditLogger::log('Personeros',$c['table'],$id,'REGISTRAR',null,['id_persona'=>$idPersona,'id_usuario'=>$usuarioId,'scope'=>$scopeId,'condicion'=>$cond,'tipo'=>$tipo,'estado'=>1]);
            return back()->with('success', $c['label'].' registrado correctamente.')->with('tipo',$tipo);
        } catch(Throwable $e) {
            return back()->withInput()->withErrors($e->getMessage())->with('tipo',$tipo);
        }
    }

    public function update(Request $request, string $tipo, int $id)
    {
        try {
            [$idPersona,$scopeId,$cond,$c]=$this->validateScope($request,$tipo);
            $row=DB::table($c['table'])->where($c['pk'],$id)->first();
            if (!$row) abort(404);
            $old=(array)$row;

            $usuarioId=$row->id_usuario ?: $this->roleAndUser($idPersona,$c,$scopeId,$request);
            DB::table($c['table'])->where($c['pk'],$id)->update([
                'id_persona'=>$idPersona,
                'id_usuario'=>$usuarioId,
                $c['field']=>$scopeId,
                'condicion'=>$cond,
                'updated_at'=>now()
            ]);
            if ($usuarioId) $this->roleAndUser($idPersona,$c,$scopeId,$request);
            AuditLogger::log('Personeros',$c['table'],$id,'EDITAR',$old,['id_persona'=>$idPersona,'id_usuario'=>$usuarioId,'scope'=>$scopeId,'condicion'=>$cond,'tipo'=>$tipo]);
            return back()->with('success',$c['label'].' actualizado correctamente.')->with('tipo',$tipo);
        } catch(Throwable $e) {
            return back()->withInput()->withErrors($e->getMessage())->with('tipo',$tipo);
        }
    }

    public function toggle(string $tipo,int $id)
    {
        $c=$this->cfg($tipo);
        $row=DB::table($c['table'])->where($c['pk'],$id)->first();
        abort_unless($row,404);
        $nuevo=$row->estado?0:1;
        DB::table($c['table'])->where($c['pk'],$id)->update(['estado'=>$nuevo,'updated_at'=>now()]);
        AuditLogger::log('Personeros',$c['table'],$id,$nuevo?'HABILITAR':'DESHABILITAR',['estado'=>(int)$row->estado],['estado'=>$nuevo]);
        return back()->with('success','Estado actualizado correctamente.')->with('tipo',$tipo);
    }

    public function export(string $tipo)
    {
        $c=$this->cfg($tipo);
        $query=DB::table($c['table'].' as x')->join('persona as pe','pe.id_persona','=','x.id_persona');
        if ($tipo==='regional') $query->join('region as r','r.id_region','=','x.id_region')->addSelect('r.nombre as region');
        elseif($tipo==='provincia') $query->join('provincia as p','p.id_provincia','=','x.id_provincia')->join('region as r','r.id_region','=','p.id_region')->addSelect('r.nombre as region','p.nombre as provincia');
        elseif($tipo==='distrito') $query->join('distrito as d','d.id_distrito','=','x.id_distrito')->join('provincia as p','p.id_provincia','=','d.id_provincia')->join('region as r','r.id_region','=','p.id_region')->addSelect('r.nombre as region','p.nombre as provincia','d.nombre as distrito');
        elseif($tipo==='local') $query->join('local as l','l.id_local','=','x.id_local')->join('distrito as d','d.id_distrito','=','l.id_distrito')->join('provincia as p','p.id_provincia','=','d.id_provincia')->join('region as r','r.id_region','=','p.id_region')->addSelect('r.nombre as region','p.nombre as provincia','d.nombre as distrito','l.nombre as local');
        else $query->join('mesa as m','m.id_mesa','=','x.id_mesa')->join('local as l','l.id_local','=','m.id_local')->join('distrito as d','d.id_distrito','=','l.id_distrito')->join('provincia as p','p.id_provincia','=','d.id_provincia')->join('region as r','r.id_region','=','p.id_region')->addSelect('r.nombre as region','p.nombre as provincia','d.nombre as distrito','l.nombre as local','m.numero_mesa as mesa');

        $rows=$query->addSelect('pe.dni','pe.nombres','pe.apellido_paterno','pe.apellido_materno','x.condicion','x.estado')->orderBy('pe.apellido_paterno')->get();
        $headers=['DNI','Nombres','Apellido paterno','Apellido materno'];
        if($tipo==='regional') $headers[]='Región';
        if($tipo==='provincia') $headers=array_merge($headers,['Región','Provincia']);
        if($tipo==='distrito') $headers=array_merge($headers,['Región','Provincia','Distrito']);
        if($tipo==='local') $headers=array_merge($headers,['Región','Provincia','Distrito','Local']);
        if($tipo==='mesa') $headers=array_merge($headers,['Región','Provincia','Distrito','Local','Mesa']);
        $headers=array_merge($headers,['Condición','Estado']);
        $data=[];
        foreach($rows as $r){
            $row=[$r->dni,$r->nombres,$r->apellido_paterno,$r->apellido_materno];
            foreach(['region','provincia','distrito','local','mesa'] as $f) if(isset($r->$f)) $row[]=$r->$f;
            $row[]=$r->condicion; $row[]=$r->estado?'Activo':'Inactivo'; $data[]=$row;
        }
        return SimpleXlsx::download($headers,$data,'personeros_'.$tipo.'_'.date('Ymd_His').'.xlsx','Personeros');
    }

    public function template(string $tipo)
    {
        $c=$this->cfg($tipo);
        $headers=['DNI'];
        if($tipo==='regional') $headers[]='Región';
        if($tipo==='provincia') $headers=array_merge($headers,['Región','Provincia']);
        if($tipo==='distrito') $headers=array_merge($headers,['Región','Provincia','Distrito']);
        if($tipo==='local') $headers=array_merge($headers,['Región','Provincia','Distrito','Local']);
        if($tipo==='mesa') $headers=array_merge($headers,['Región','Provincia','Distrito','Local','Mesa']);
        $headers[]='Condición';
        $headers[]='Estado';
        return SimpleXlsx::download($headers,[], 'plantilla_personeros_'.$tipo.'.xlsx','Personeros');
    }

    private function normalizeImportHeader(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $value = strtr($value, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        ]);
        return mb_strtolower($value, 'UTF-8');
    }

    public function import(Request $request,string $tipo)
    {
        $c=$this->cfg($tipo);
        if(!$request->hasFile('archivo') || !$request->file('archivo')->isValid())
            return back()->withErrors('Debe seleccionar un archivo Excel válido.')->with('tipo',$tipo);
        try {
            $rows=SimpleXlsx::read($request->file('archivo')->getRealPath());
            if(count($rows)<2) throw new \RuntimeException('El Excel no contiene registros.');
            $headers=array_map(fn($v)=>$this->normalizeImportHeader((string)$v),array_shift($rows));
            $required=['dni'];
            if($tipo==='regional') $required[]='region';
            if($tipo==='provincia') $required=array_merge($required,['region','provincia']);
            if($tipo==='distrito') $required=array_merge($required,['region','provincia','distrito']);
            if($tipo==='local') $required=array_merge($required,['region','provincia','distrito','local']);
            if($tipo==='mesa') $required=array_merge($required,['region','provincia','distrito','local','mesa']);
            $required[]='condicion';
            foreach($required as $h) if(!in_array($h,$headers,true)) throw new \RuntimeException("Falta la columna obligatoria: $h");

            $idx=[]; foreach($headers as $i=>$h)$idx[$h]=$i;
            $processed=$new=$dup=$errors=0; $errRows=[];
            foreach($rows as $rowNo=>$row){
                $processed++;
                $dni=trim((string)($row[$idx['dni']]??''));
                $cond=strtoupper(trim((string)($row[$idx['condicion']]??'')));
                $reason='';
                if($dni==='') $reason='DNI vacío.';
                elseif(!$p=DB::table('persona')->where('dni',$dni)->where('estado',1)->first()) $reason='El DNI no está registrado en Personas.';
                elseif(!in_array($cond,['TITULAR','PRIMER_SUPLENTE','SEGUNDO_SUPLENTE','TERCER_SUPLENTE'],true)) $reason='Condición inválida.';
                $scopeId=null;
                if(!$reason){
                    $scopeId=$this->resolveImportScope($tipo,$row,$idx);
                    if(!$scopeId) $reason='El ámbito indicado no existe o está inactivo.';
                }
                if(!$reason && DB::table($c['table'])->where($c['field'],$scopeId)->where('condicion',$cond)->where('estado',1)->exists()) $reason='Ya existe un personero activo con esa condición en el ámbito.';
                if(!$reason){
                    try{
                        $usuarioId=$this->roleAndUser($p->id_persona,$c,$scopeId,$request);
                        DB::table($c['table'])->insert(['id_persona'=>$p->id_persona,'id_usuario'=>$usuarioId,$c['field']=>$scopeId,'condicion'=>$cond,'estado'=>1,'created_at'=>now(),'updated_at'=>now()]);
                        $new++;
                    }catch(Throwable $e){$reason=$e->getMessage();}
                }
                if($reason){$errors++; $errRows[]=array_merge($row,[$reason]);}
            }
            $file=null;
            if($errRows){
                $errPath=rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'errores_personeros_'.uniqid().'.xlsx';
                SimpleXlsx::write($errPath,array_merge($headers,['Error']),$errRows,'Errores');
                $file=basename($errPath);
                // Move into a predictable temp location for the download route.
                $target=storage_path('app/personeros_errors_'.$file);
                @copy($errPath,$target);
                @unlink($errPath);
            }
            return back()->with('tipo',$tipo)->with('import_summary',[
                'procesados'=>$processed,'nuevos'=>$new,'duplicados'=>$dup,'errores'=>$errors
            ])->with('import_error_file',$file);
        }catch(Throwable $e){
            return back()->with('tipo',$tipo)->withErrors($e->getMessage());
        }
    }

    private function resolveImportScope(string $tipo,array $row,array $idx): ?int
    {
        $norm=fn($v)=>mb_strtolower(trim((string)$v));
        if($tipo==='regional'){
            $name=$norm($row[$idx['region']]??'');
            return DB::table('region')->whereRaw('LOWER(TRIM(nombre)) = ?',[$name])->where('estado',1)->value('id_region');
        }
        if($tipo==='provincia'){
            $rn=$norm($row[$idx['region']]??''); $pn=$norm($row[$idx['provincia']]??'');
            $r=DB::table('region')->whereRaw('LOWER(TRIM(nombre)) = ?',[$rn])->where('estado',1)->first();
            return $r?DB::table('provincia')->where('id_region',$r->id_region)->whereRaw('LOWER(TRIM(nombre)) = ?',[$pn])->where('estado',1)->value('id_provincia'):null;
        }
        if($tipo==='distrito'){
            $rn=$norm($row[$idx['region']]??''); $pn=$norm($row[$idx['provincia']]??''); $dn=$norm($row[$idx['distrito']]??'');
            $r=DB::table('region')->whereRaw('LOWER(TRIM(nombre)) = ?',[$rn])->where('estado',1)->first();
            $p=$r?DB::table('provincia')->where('id_region',$r->id_region)->whereRaw('LOWER(TRIM(nombre)) = ?',[$pn])->where('estado',1)->first():null;
            return $p?DB::table('distrito')->where('id_provincia',$p->id_provincia)->whereRaw('LOWER(TRIM(nombre)) = ?',[$dn])->where('estado',1)->value('id_distrito'):null;
        }
        if($tipo==='local'){
            $rn=$norm($row[$idx['region']]??''); $pn=$norm($row[$idx['provincia']]??''); $dn=$norm($row[$idx['distrito']]??''); $ln=$norm($row[$idx['local']]??'');
            $r=DB::table('region')->whereRaw('LOWER(TRIM(nombre)) = ?',[$rn])->where('estado',1)->first();
            $p=$r?DB::table('provincia')->where('id_region',$r->id_region)->whereRaw('LOWER(TRIM(nombre)) = ?',[$pn])->where('estado',1)->first():null;
            $d=$p?DB::table('distrito')->where('id_provincia',$p->id_provincia)->whereRaw('LOWER(TRIM(nombre)) = ?',[$dn])->where('estado',1)->first():null;
            return $d?DB::table('local')->where('id_distrito',$d->id_distrito)->whereRaw('LOWER(TRIM(nombre)) = ?',[$ln])->where('estado',1)->value('id_local'):null;
        }
        $rn=$norm($row[$idx['region']]??''); $pn=$norm($row[$idx['provincia']]??''); $dn=$norm($row[$idx['distrito']]??''); $ln=$norm($row[$idx['local']]??''); $mn=$norm($row[$idx['mesa']]??'');
        $r=DB::table('region')->whereRaw('LOWER(TRIM(nombre)) = ?',[$rn])->where('estado',1)->first();
        $p=$r?DB::table('provincia')->where('id_region',$r->id_region)->whereRaw('LOWER(TRIM(nombre)) = ?',[$pn])->where('estado',1)->first():null;
        $d=$p?DB::table('distrito')->where('id_provincia',$p->id_provincia)->whereRaw('LOWER(TRIM(nombre)) = ?',[$dn])->where('estado',1)->first():null;
        $l=$d?DB::table('local')->where('id_distrito',$d->id_distrito)->whereRaw('LOWER(TRIM(nombre)) = ?',[$ln])->where('estado',1)->first():null;
        return $l?DB::table('mesa')->where('id_local',$l->id_local)->whereRaw('LOWER(TRIM(numero_mesa)) = ?',[$mn])->where('estado',1)->value('id_mesa'):null;
    }

    public function errors(string $filename)
    {
        $path=storage_path('app/personeros_errors_'.$filename);
        abort_unless(is_file($path),404);
        return response()->download($path,$filename)->deleteFileAfterSend(true);
    }
}
