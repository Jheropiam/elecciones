<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Support\AuditLogger;
use App\Support\SystemConfig;

class AuthController extends Controller
{
    public function show() { return view('auth.login'); }

    public function login(Request $request) {
        $data=$request->validate(['dni'=>['required','string','max:20'],'password'=>['required','string']]);
        $user=DB::table('usuario')->where('usuario',$data['dni'])->where('estado',1)->first();
        $maxIntentos = max(1, (int) SystemConfig::get('max_intentos_login', 5));
        $minutosBloqueo = max(1, (int) SystemConfig::get('minutos_bloqueo_login', 15));
        $bloqueado = $user && $user->bloqueado_hasta && now()->lt($user->bloqueado_hasta);
        $passwordCorrecta = $user && Hash::check($data['password'], $user->password_hash);
        if(!$user || $bloqueado || !$passwordCorrecta) {
            if($user) {
                $intentos = min(($user->intentos_fallidos ?? 0) + 1, 65535);
                $update = ['intentos_fallidos'=>$intentos];
                if ($intentos >= $maxIntentos) {
                    $update['bloqueado_hasta'] = now()->addMinutes($minutosBloqueo);
                    $intentosMsg = "Cuenta bloqueada temporalmente por {$minutosBloqueo} minutos.";
                } else {
                    $restantes = max(0, $maxIntentos - $intentos);
                    $intentosMsg = "Credenciales incorrectas. Intentos restantes: {$restantes}.";
                }
                DB::table('usuario')->where('id_usuario',$user->id_usuario)->update($update);
                AuditLogger::log('Autenticación','usuario',$user->id_usuario,'LOGIN_FALLIDO',null,['usuario'=>$data['dni'],'intentos'=>$intentos],(int)$user->id_usuario);
            } else {
                $intentosMsg = 'DNI o contraseña incorrectos.';
                AuditLogger::log('Autenticación','usuario',null,'LOGIN_FALLIDO',null,['usuario'=>$data['dni']]);
            }
            return back()->withInput($request->only('dni'))->with('error',$intentosMsg);
        }
        $request->session()->regenerate();
        $request->session()->put('id_usuario',$user->id_usuario);
        DB::table('usuario')->where('id_usuario',$user->id_usuario)->update(['ultimo_acceso'=>now(),'intentos_fallidos'=>0,'bloqueado_hasta'=>null]);
        AuditLogger::log('Autenticación','usuario',$user->id_usuario,'LOGIN',null,['usuario'=>$user->usuario]);
        $expiraDias = max(0, (int) SystemConfig::get('dias_expiracion_password', 0));
        $passwordExpirada = $expiraDias > 0 && $user->password_changed_at && now()->greaterThan(\Carbon\Carbon::parse($user->password_changed_at)->addDays($expiraDias));
        return (($user->debe_cambiar_password || $passwordExpirada) ? redirect()->route('password.change') : redirect()->route('dashboard'));
    }
    public function changeForm(){ return view('auth.change-password'); }
    public function changePassword(Request $request){
        $data=$request->validate(['current_password'=>['required','string'],'password'=>['required','string','min:8','confirmed']]);
        $id=session('id_usuario'); $user=DB::table('usuario')->where('id_usuario',$id)->first();
        if(!$user || !Hash::check($data['current_password'],$user->password_hash)) return back()->with('error','La contraseña actual no es correcta.');
        DB::table('usuario')->where('id_usuario',$id)->update(['password_hash'=>Hash::make($data['password']),'debe_cambiar_password'=>0,'password_changed_at'=>now(),'updated_at'=>now()]);
        AuditLogger::log('Autenticación','usuario',$id,'CAMBIAR_PASSWORD',null,['debe_cambiar_password'=>0]);
        return redirect()->route('dashboard')->with('success','Contraseña actualizada correctamente.');
    }
    public function logout(Request $request){ $id=(int)$request->session()->get('id_usuario'); AuditLogger::log('Autenticación','usuario',$id,'LOGOUT'); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login')->with('success','Sesión cerrada.'); }
}
