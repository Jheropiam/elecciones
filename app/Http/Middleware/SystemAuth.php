<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response;
class SystemAuth {
 public function handle(Request $request, Closure $next): Response {
  if(!$request->session()->has('id_usuario')) return redirect()->route('login')->with('error','Debe iniciar sesión.');
  if(\Illuminate\Support\Facades\DB::table('usuario')->where('id_usuario',$request->session()->get('id_usuario'))->value('debe_cambiar_password') && !$request->routeIs('password.change*')) return redirect()->route('password.change')->with('warning','Debe cambiar su contraseña antes de continuar.');
  \App\Support\AccessControl::authorizeRequest($request);
  return $next($request);
 }
}
