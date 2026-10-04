<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder { public function run(): void {
 $personaId=DB::table('persona')->where('dni','70750380')->value('id_persona');
 if(!$personaId) $personaId=DB::table('persona')->insertGetId(['dni'=>'70750380','nombres'=>'JHEFERSON ROMULO','apellido_paterno'=>'PINEDO','apellido_materno'=>'AMIAS','celular'=>'944844574','correo'=>'jpineod@gamil.com','estado'=>1,'created_at'=>now(),'updated_at'=>now()]);
 $role=DB::table('rol')->where('nombre','Administrador')->first();
 if(!$role){ $roleId=DB::table('rol')->insertGetId(['nombre'=>'Administrador','descripcion'=>'Acceso administrativo general','estado'=>1,'created_at'=>now(),'updated_at'=>now()]); } else $roleId=$role->id_rol;
 $user=DB::table('usuario')->where('id_persona',$personaId)->first();
 if(!$user) $userId=DB::table('usuario')->insertGetId(['id_persona'=>$personaId,'usuario'=>'70750380','password_hash'=>Hash::make('70750380'),'debe_cambiar_password'=>1,'estado'=>1,'intentos_fallidos'=>0,'created_at'=>now(),'updated_at'=>now()]); else $userId=$user->id_usuario;
 if(!DB::table('usuario_rol')->where(['id_usuario'=>$userId,'id_rol'=>$roleId])->exists()) DB::table('usuario_rol')->insert(['id_usuario'=>$userId,'id_rol'=>$roleId,'estado'=>1,'created_at'=>now(),'updated_at'=>now()]);
 }}
