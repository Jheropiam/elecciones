<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
 public function up(): void {
  $sql=file_get_contents(database_path('schema/electoral_schema.sql'));
  $sql=preg_replace('/CREATE DATABASE IF NOT EXISTS.*?;\s*/is','',$sql);
  $sql=preg_replace('/\bUSE\s+`?sistema_electoral`?\s*;\s*/i','',$sql);
  $sql=preg_replace('/SET FOREIGN_KEY_CHECKS\s*=\s*[01]\s*;\s*/i','',$sql);
  $sql=preg_replace('/DROP TABLE IF EXISTS.*?;\s*/is','',$sql);
  $sql=preg_replace('/^\s*--.*$/m','',$sql);
  $parts=preg_split('/;\s*(?=CREATE TABLE|INSERT INTO|ALTER TABLE|CREATE INDEX|UPDATE|$)/i',$sql);
  foreach($parts as $statement){ $statement=trim($statement); if($statement!=='') DB::unprepared($statement.';'); }
 }
 public function down(): void {
  $tables=['notificacion','configuracion_sistema','auditoria','digitacion_acta','personero_mesa','personero_local','personero_distrito','personero_provincia','personero_regional','usuario_ambito','rol_permiso','usuario_rol','permiso','opcion_modulo','modulo','usuario','persona','partido','mesa','local','distrito','provincia','region'];
  DB::statement('SET FOREIGN_KEY_CHECKS=0'); foreach($tables as $t) DB::statement('DROP TABLE IF EXISTS `'.$t.'`'); DB::statement('SET FOREIGN_KEY_CHECKS=1');
 }
};
