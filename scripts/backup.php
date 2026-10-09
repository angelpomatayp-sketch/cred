<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('America/Lima');
$c=require dirname(__DIR__).'/storage/config.local.php';
$db=new PDO($c['dsn'],$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("SET time_zone='-05:00'");
$file=dirname(__DIR__).'/storage/backup-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';
$handle=fopen($file,'x'); if(!$handle)throw new RuntimeException('No se pudo crear el respaldo.');
try {
 $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
 fwrite($handle,"-- CRED backup generated in America/Lima. Import into an EMPTY database.\nSET NAMES utf8mb4;\nSET time_zone='-05:00';\nSET FOREIGN_KEY_CHECKS=0;\n");
 foreach(['users','patients','appointments','controls','vaccinations','audit_log','login_attempts'] as $table){
   $schema=$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1]; fwrite($handle,$schema.";\n");
   $q=$db->query('SELECT * FROM `'.$table.'`');
   while($row=$q->fetch(PDO::FETCH_ASSOC)){
     $columns=implode(',',array_map(fn($key)=>'`'.$key.'`',array_keys($row)));
     $values=implode(',',array_map(fn($value)=>$value===null?'NULL':$db->quote((string)$value),array_values($row)));
     fwrite($handle,'INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.$values.");\n");
   }
 }
 fwrite($handle,"SET FOREIGN_KEY_CHECKS=1;\n");$db->commit();fclose($handle);echo "Respaldo creado: $file\n";
}catch(Throwable $ex){if($db->inTransaction())$db->rollBack();fclose($handle);unlink($file);throw $ex;}
