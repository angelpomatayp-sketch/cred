<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/Lima');
$config=require dirname(__DIR__).'/storage/config.local.php';
$db=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("SET time_zone='-05:00'");
$base='http://localhost/percy/index.php'; $tag='qa-'.bin2hex(random_bytes(5)); $password='QA-'.bin2hex(random_bytes(12)); $email=$tag.'@example.invalid';
$ids=[]; $pids=[]; $cookies=[]; $checks=0; $fail=null;
function sql(string $s,array $v=[]): PDOStatement { global $db; $q=$db->prepare($s); $q->execute($v); return $q; }
function check(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException($message); $checks++; echo "PASS $message\n"; }
function request(string $jar,string $path='',?array $post=null): array {
 global $base;
 $c=curl_init($base.$path); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15]);
 if($post!==null) { curl_setopt($c,CURLOPT_POST,true); curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($post)); }
 $body=curl_exec($c); if($body===false) throw new RuntimeException(curl_error($c)); $status=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c); return [$body,$status];
}
function token(string $html): string { if(!preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m)) throw new RuntimeException('CSRF missing'); return $m[1]; }
function login(string $jar,string $email,string $password): array { [$body]=request($jar); return request($jar,'',['action'=>'login','csrf'=>token($body),'email'=>$email,'password'=>$password]); }
function send(string $jar,string $action,array $data=[],string $path=''): array { [$body]=request($jar); return request($jar,$path,['action'=>$action,'csrf'=>token($body),...$data]); }
try {
 sql('INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,?)',['QA Admin '.$tag,$email,password_hash($password,PASSWORD_DEFAULT),'admin']); $ids[]=(int)$db->lastInsertId();
 foreach(['admin','nurse','supervisor','anon'] as $role) $cookies[$role]=tempnam(sys_get_temp_dir(),'credqa_');
 [$html,$code]=request($cookies['anon'],'?page=patients'); check($code===200 && str_contains($html,'Bienvenido al sistema') && !str_contains($html,'<table>'),'anonymous request does not disclose records');
 [$html,$code]=login($cookies['admin'],$email,$password); check($code===200 && str_contains($html,'Bienvenido, QA'),'administrator login');
 foreach(['dashboard','patients','agenda','reports','users','audit','account'] as $page) { [$html,$code]=request($cookies['admin'],'?page='.$page); check($code===200 && !str_contains($html,'No se pudo cargar'),'page '.$page); }
 foreach(['patient','control','appointment','user','password'] as $modal) { [$html,$code]=request($cookies['admin'],'?page=patients&modal='.$modal); check($code===200 && str_contains($html,'id="activeModal"') && !str_contains($html,'No se pudo cargar'),'modal '.$modal); }
 foreach(['nurse','supervisor'] as $role) {
   [$html,$code]=send($cookies['admin'],'user_save',['name'=>'QA '.$role.' '.$tag,'email'=>$tag.'-'.$role.'@example.invalid','password'=>$password,'role'=>$role],'?page=users&modal=user');
   $id=sql('SELECT id FROM users WHERE email=?',[$tag.'-'.$role.'@example.invalid'])->fetchColumn(); if($id) $ids[]=(int)$id;
   check((bool)$id && $code===200,'create '.$role.' account through form');
   [$html,$code]=login($cookies[$role],$tag.'-'.$role.'@example.invalid',$password); check(str_contains($html,'Bienvenido, QA'),'login '.$role);
 }
 $nid=(int)sql('SELECT id FROM users WHERE email=?',[$tag.'-nurse@example.invalid'])->fetchColumn();
 [$html,$code]=send($cookies['admin'],'user_save',['id'=>$nid,'name'=>'QA Enfermería '.$tag,'email'=>$tag.'-nurse@example.invalid','password'=>'','role'=>'supervisor'],'?page=users&modal=user&id='.$nid);
 check(sql('SELECT role FROM users WHERE id=?',[$nid])->fetchColumn()==='supervisor','administrator edits role in modal');
 [$html,$code]=send($cookies['nurse'],'patient_toggle',['id'=>0]); check($code===403,'changed role takes effect for existing session');
 send($cookies['admin'],'user_save',['id'=>$nid,'name'=>'QA Enfermería '.$tag,'email'=>$tag.'-nurse@example.invalid','password'=>'','role'=>'nurse']);
 [$html,$code]=send($cookies['admin'],'user_save',['id'=>$ids[0],'name'=>'QA Admin '.$tag,'email'=>$email,'password'=>'','role'=>'supervisor']);
 check(sql('SELECT role FROM users WHERE id=?',[$ids[0]])->fetchColumn()==='admin','administrator cannot remove own administration role');
 [$html,$code]=send($cookies['admin'],'patient_save',['name'=>'Bad','birth_date'=>'2099-01-01','sex'=>'F','guardian_name'=>'QA','guardian_relationship'=>'Madre','phone'=>'000000000']); check(str_contains($html,'Revisa la fecha de nacimiento'),'reject future birth date');
 $patient=['name'=>'QA Niño '.$tag,'document'=>'','birth_date'=>date('Y-m-d',strtotime('-1 year')),'sex'=>'M','guardian_name'=>'QA Responsable','guardian_relationship'=>'Madre','phone'=>'000000000','address'=>'Prueba temporal'];
 [$html,$code]=send($cookies['nurse'],'patient_save',[...$patient,'birth_date'=>date('Y-m-d',strtotime('-12 years'))]);
 check(str_contains($html,'Edad fuera del rango CRED') && !(bool)sql('SELECT id FROM patients WHERE name=?',[$patient['name']])->fetchColumn(),'reject new twelve-year-old patient');
 [$html,$code]=send($cookies['nurse'],'patient_save',$patient,'?page=patients&modal=patient');
 $pid=(int)sql('SELECT id FROM patients WHERE name=?',[$patient['name']])->fetchColumn(); if($pid)$pids[]=$pid;
 check($pid>0 && str_contains($html,'Ficha del paciente guardada'),'nurse creates persistent patient');
 [$html,$code]=send($cookies['nurse'],'appointment_save',['patient_id'=>$pid,'date'=>date('Y-m-d',strtotime('+11 years')),'time'=>'10:00','type'=>'Control periódico','note'=>'']);
 check(str_contains($html,'Edad fuera del rango CRED') && (int)sql('SELECT COUNT(*) FROM appointments WHERE patient_id=?',[$pid])->fetchColumn()===0,'reject appointment on twelfth birthday');
 [$html,$code]=request($cookies['nurse'],'?cred_context=1&patient_id='.$pid.'&on='.date('Y-m-d'));
 $context=json_decode($html,true); check($code===200 && $context['eligible'] && $context['label']==='Un año','authenticated age context uses selected patient and date');
 [$html,$code]=send($cookies['nurse'],'patient_save',[...$patient,'id'=>$pid,'address'=>'Dirección actualizada'],'?page=patients&modal=patient&id='.$pid);
 check(sql('SELECT address FROM patients WHERE id=?',[$pid])->fetchColumn()==='Dirección actualizada','edit patient through modal');
 [$html,$code]=send($cookies['supervisor'],'patient_save',[...$patient,'id'=>$pid,'name'=>'Unauthorized']); check($code===403 && sql('SELECT name FROM patients WHERE id=?',[$pid])->fetchColumn()===$patient['name'],'supervisor write blocked on server');
 [$html,$code]=request($cookies['nurse'],'?page=users'); check($code===403,'nurse user administration blocked');
 [$html,$code]=request($cookies['admin'],'',['action'=>'patient_toggle','id'=>$pid,'csrf'=>'invalid']); check($code===403 && (int)sql('SELECT active FROM patients WHERE id=?',[$pid])->fetchColumn()===1,'CSRF blocks mutation');
 [$html,$code]=send($cookies['nurse'],'appointment_save',['patient_id'=>$pid,'date'=>date('Y-m-d',strtotime('+1 day')),'time'=>'10:00','type'=>'Control periódico','note'=>'QA cita'],'?page=agenda&modal=appointment');
 $aid=(int)sql('SELECT id FROM appointments WHERE patient_id=?',[$pid])->fetchColumn(); check($aid>0 && str_contains($html,'Cita guardada'),'create appointment');
 [$html,$code]=request($cookies['nurse'],'?page=patients&modal=detail&id='.$pid);
 check(str_contains($html,'Nueva cita') && str_contains($html,'date='.date('Y-m-d',strtotime('+1 day')).'#cita-'.$aid) && str_contains($html,'Ver en agenda'),'patient history links to exact appointment day and row');
 [$html,$code]=request($cookies['nurse'],'?page=agenda&date='.date('Y-m-d'));
 check(!str_contains($html,'id="cita-'.$aid.'"'),'daily agenda excludes future appointment');
 [$html,$code]=request($cookies['nurse'],'?page=agenda&date='.date('Y-m-d',strtotime('+1 day')));
 check(str_contains($html,'id="cita-'.$aid.'"'),'daily agenda displays appointment on correct date');
 [$html,$code]=request($cookies['nurse'],'?page=agenda&scope=upcoming');
 check(str_contains($html,'id="cita-'.$aid.'"') && str_contains($html,'Fecha y hora'),'upcoming agenda includes future pending appointment with date');
 preg_match('/<tr id="cita-'.$aid.'">(.*?)<\/tr>/s',$html,$appointmentRow);
 check(isset($appointmentRow[1]) && !str_contains($appointmentRow[1],'>Atender<'),'future appointment has no premature attendance action');
 [$html,$code]=request($cookies['nurse'],'?page=agenda&scope=upcoming&modal=appointment_detail&id='.$aid);
 check(str_contains($html,'data-return="?page=agenda&amp;scope=upcoming"'),'modal close preserves upcoming agenda');
 [$html,$code]=send($cookies['nurse'],'appointment_save',['id'=>$aid,'patient_id'=>$pid,'date'=>date('Y-m-d',strtotime('+2 day')),'time'=>'11:00','type'=>'Seguimiento','note'=>'QA reprogramada']);
 check(substr(sql('SELECT scheduled_at FROM appointments WHERE id=?',[$aid])->fetchColumn(),0,16)===date('Y-m-d',strtotime('+2 day')).' 11:00','reschedule appointment');
 [$html,$code]=send($cookies['nurse'],'appointment_status',['id'=>$aid,'status'=>'No asistió']); check(str_contains($html,'todavía no ha ocurrido'),'future no-show rejected');
 [$html,$code]=send($cookies['nurse'],'appointment_status',['id'=>$aid,'status'=>'Confirmada']); check(sql('SELECT status FROM appointments WHERE id=?',[$aid])->fetchColumn()==='Confirmada','confirm appointment');
 // Move only the test appointment into today to exercise attendance independently of the clock.
 sql('UPDATE appointments SET scheduled_at=? WHERE id=?',[date('Y-m-d').' 08:00:00',$aid]);
 $control=['patient_id'=>$pid,'appointment_id'=>$aid,'control_date'=>date('Y-m-d'),'weight'=>'9.8','height'=>'74.5','head_circumference'=>'46.2','development'=>'Requiere seguimiento','findings'=>'<script>alert(1)</script>','instructions'=>'QA indicaciones','next_date'=>date('Y-m-d',strtotime('+1 month')),'next_time'=>'09:00'];
 [$html,$code]=send($cookies['nurse'],'control_save',[...$control,'next_date'=>date('Y-m-d',strtotime('+11 years'))]);
 check(str_contains($html,'Edad fuera del rango CRED') && (int)sql('SELECT COUNT(*) FROM controls WHERE patient_id=?',[$pid])->fetchColumn()===0 && sql('SELECT status FROM appointments WHERE id=?',[$aid])->fetchColumn()==='Confirmada','invalid follow-up leaves control and appointment unchanged');
 [$html,$code]=send($cookies['nurse'],'control_save',[...$control,'weight'=>'-1']); check(str_contains($html,'rango de las medidas'),'reject invalid measurement');
 [$html,$code]=send($cookies['nurse'],'control_save',$control,'?page=patients&modal=control&patient_id='.$pid.'&appointment_id='.$aid);
 $cid=(int)sql('SELECT id FROM controls WHERE patient_id=?',[$pid])->fetchColumn(); check($cid>0 && str_contains($html,'Control guardado'),'save clinical control');
 check(sql('SELECT status FROM appointments WHERE id=?',[$aid])->fetchColumn()==='Completada','control closes appointment');
 [$html,$code]=request($cookies['nurse'],'?page=agenda&scope=upcoming');
 check(!str_contains($html,'id="cita-'.$aid.'"'),'completed appointment excluded from upcoming pending list');
 check((int)sql('SELECT COUNT(*) FROM appointments WHERE patient_id=?',[$pid])->fetchColumn()===2,'control schedules follow-up');
 [$html,$code]=send($cookies['nurse'],'control_save',$control); check((int)sql('SELECT COUNT(*) FROM controls WHERE patient_id=?',[$pid])->fetchColumn()===1,'duplicate attended appointment blocked');
 [$html,$code]=send($cookies['nurse'],'control_save',[...$control,'appointment_id'=>'']); check((int)sql('SELECT COUNT(*) FROM controls WHERE patient_id=?',[$pid])->fetchColumn()===1,'duplicate follow-up rolls back entire new control');
 [$html,$code]=request($cookies['supervisor'],'?page=patients&modal=control_detail&id='.$cid); check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html,'<script>alert(1)</script>'),'history escapes stored HTML');
 [$html,$code]=request($cookies['nurse'],'?page=patients&modal=detail&id='.$pid); check(!str_contains($html,'Registrar vacuna') && !str_contains($html,'Vacunaciones registradas'),'vaccination UI removed');
 [$html,$code]=send($cookies['nurse'],'vaccination_save',['patient_id'=>$pid]); check(str_contains($html,'Acción no reconocida') && (int)sql('SELECT COUNT(*) FROM vaccinations WHERE patient_id=?',[$pid])->fetchColumn()===0,'removed vaccination action cannot write');
 foreach(['detail'=>$pid,'control_detail'=>$cid,'appointment_detail'=>$aid,'patient_toggle'=>$pid] as $modal=>$id) { [$html,$code]=request($cookies['admin'],'?page=patients&modal='.$modal.'&id='.$id); check(!str_contains($html,'No se pudo cargar') && str_contains($html,'id="activeModal"'),'populated modal '.$modal); }
 [$html,$code]=request($cookies['supervisor'],'?page=export&month='.date('Y-m')); check($code===200 && str_contains($html,$patient['name']) && str_contains($html,'Completada'),'supervisor CSV exports actual records');
 [$html,$code]=send($cookies['admin'],'patient_toggle',['id'=>$pid]); check((int)sql('SELECT active FROM patients WHERE id=?',[$pid])->fetchColumn()===0,'deactivate patient');
 [$html,$code]=send($cookies['nurse'],'control_save',[...$control,'appointment_id'=>'','next_date'=>'']); check(str_contains($html,'paciente está inactivo'),'inactive patient cannot receive new control');
 [$html,$code]=send($cookies['admin'],'patient_toggle',['id'=>$pid]); check((int)sql('SELECT active FROM patients WHERE id=?',[$pid])->fetchColumn()===1,'reactivate patient');
 $sid=(int)sql('SELECT id FROM users WHERE email=?',[$tag.'-supervisor@example.invalid'])->fetchColumn();
 [$html,$code]=send($cookies['admin'],'user_toggle',['id'=>$sid]); [$html,$code]=request($cookies['supervisor'],'?page=patients'); check(str_contains($html,'Bienvenido al sistema'),'deactivated account loses access on existing session');
 check((int)sql('SELECT COUNT(*) FROM audit_log WHERE user_id=?',[$ids[0]])->fetchColumn()>4,'audit records mutations');
 check(abs(strtotime(sql('SELECT NOW()')->fetchColumn())-time())<5,'database and PHP use Lima time');
 $newPassword='Changed-'.bin2hex(random_bytes(10));
 [$html,$code]=send($cookies['nurse'],'password',['current_password'=>$password,'new_password'=>$newPassword,'confirm_password'=>$newPassword],'?page=account&modal=password');
 check(str_contains($html,'Contraseña actualizada') && password_verify($newPassword,sql('SELECT password_hash FROM users WHERE id=?',[$nid])->fetchColumn()),'change own password with verification');
 [$html,$code]=send($cookies['nurse'],'logout'); check(str_contains($html,'Bienvenido al sistema'),'logout clears access');
 [$html,$code]=login($cookies['nurse'],$tag.'-nurse@example.invalid',$newPassword); check(str_contains($html,'Bienvenido, QA'),'changed password logs in');
 // Simulate ageing only on this disposable patient's row; no production records change.
 sql('UPDATE patients SET birth_date=? WHERE id=?',[date('Y-m-d',strtotime('-12 years')),$pid]);
 [$html,$code]=send($cookies['nurse'],'control_save',[...$control,'appointment_id'=>'','next_date'=>'']);
 check(str_contains($html,'Edad fuera del rango CRED') && (int)sql('SELECT COUNT(*) FROM controls WHERE patient_id=?',[$pid])->fetchColumn()===1,'existing patient outside range cannot receive current CRED');
 [$html,$code]=request($cookies['nurse'],'?page=patients&modal=detail&id='.$pid);
 check(str_contains($html,'Fuera del rango CRED') && str_contains($html,'Historial de controles'),'aged-out history remains readable');
 [$html,$code]=send($cookies['nurse'],'control_save',[...$control,'appointment_id'=>'','next_date'=>'','control_date'=>date('Y-m-d',strtotime('-1 day'))]);
 check((int)sql('SELECT COUNT(*) FROM controls WHERE patient_id=?',[$pid])->fetchColumn()===2,'historical control validates age at attention rather than today');
 echo "Completed: $checks checks.\n";
} catch(Throwable $ex) { $fail=$ex; fwrite(STDERR,"FAIL: ".$ex->getMessage()."\n"); }
finally {
 // Remove only identities and clinical records created by this particular test run.
 $db->beginTransaction();
 foreach($pids as $pid) { sql('DELETE FROM vaccinations WHERE patient_id=?',[$pid]); sql('DELETE FROM controls WHERE patient_id=?',[$pid]); sql('DELETE FROM appointments WHERE patient_id=?',[$pid]); sql('DELETE FROM patients WHERE id=? AND name=?',[$pid,'QA Niño '.$tag]); }
 foreach($ids as $id) { sql('DELETE FROM audit_log WHERE user_id=?',[$id]); sql('DELETE FROM users WHERE id=? AND email LIKE ?',[$id,$tag.'%']); }
 $db->commit(); foreach($cookies as $file) if(is_file($file))unlink($file);
 echo "Removed only temporary QA records and cookies.\n";
}
exit($fail?1:0);
