<?php
declare(strict_types=1);
function handlePost(?array $u): never {
 if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf']??''))) { http_response_code(403); throw new InvalidArgumentException('La sesión del formulario venció. Recarga la página.'); }
 $action=input('action',40);
 if ($action==='login') {
    $email=strtolower(input('email',160)); $password=input('password',255);
    $key=hash('sha256',$email.'|'.($_SERVER['REMOTE_ADDR']??''));
    if ((int)scalar('SELECT COUNT(*) FROM login_attempts WHERE identity_hash=? AND attempted_at>DATE_SUB(NOW(), INTERVAL 15 MINUTE)',[$key])>=5) throw new InvalidArgumentException('Demasiados intentos. Vuelve a intentar en 15 minutos.');
    $account=one('SELECT * FROM users WHERE email=? AND active=1',[$email]);
    if (!$account || !password_verify($password,$account['password_hash'])) { query('INSERT INTO login_attempts(identity_hash) VALUES(?)',[$key]); throw new InvalidArgumentException('Correo o contraseña incorrectos.'); }
    session_regenerate_id(true); $_SESSION['uid']=(int)$account['id']; $_SESSION['csrf']=bin2hex(random_bytes(32));
    query('DELETE FROM login_attempts WHERE identity_hash=? OR attempted_at<DATE_SUB(NOW(), INTERVAL 1 DAY)',[$key]); audit('Inicio de sesión','users',(int)$account['id']); redirect();
 }
 if (!$u) { http_response_code(401); throw new InvalidArgumentException('Inicia sesión para continuar.'); }
 if ($action==='logout') { audit('Cierre de sesión','users',(int)$u['id']); $_SESSION=[]; session_destroy(); redirect(); }
 if ($action==='password') {
    $account=one('SELECT password_hash FROM users WHERE id=?',[$u['id']]);
    if (!password_verify(input('current_password'),$account['password_hash'])) throw new InvalidArgumentException('La contraseña actual no es correcta.');
    $password=input('new_password'); if (strlen($password)<12 || $password!==input('confirm_password')) throw new InvalidArgumentException('Las contraseñas deben coincidir y tener al menos 12 caracteres.');
    query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$u['id']]); audit('Cambió su contraseña','users',(int)$u['id']); session_regenerate_id(true); flash('Contraseña actualizada.'); redirect('index.php?page=account');
 }
 if (in_array($action,['user_save','user_toggle'],true)) {
    if ($u['role']!=='admin') { http_response_code(403); throw new InvalidArgumentException('Solo administración puede gestionar usuarios.'); }
    if ($action==='user_save') {
      $email=strtolower(input('email',160)); if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Ingresa un correo válido.');
      $id=(int)($_POST['id']??0);
      $password=input('password',255,$id===0); if ($password!=='' && strlen($password)<12) throw new InvalidArgumentException('La contraseña debe tener al menos 12 caracteres.');
      $role=choice('role',['admin','nurse','supervisor']);
      $name=input('name',120);
      db()->beginTransaction();
      if($id) {
        $target=one('SELECT id FROM users WHERE id=? FOR UPDATE',[$id]); if(!$target) throw new InvalidArgumentException('Usuario no encontrado.');
        if($id===(int)$u['id'] && $role!=='admin') throw new InvalidArgumentException('No puedes retirar el permiso de administrador de tu propia cuenta.');
        query('UPDATE users SET name=?,email=?,role=? WHERE id=?',[$name,$email,$role,$id]);
        if($password!=='') query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);
        audit('Editó usuario','users',$id);
      } else { query('INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]); audit('Creó usuario','users',(int)db()->lastInsertId()); }
      db()->commit();
    } else {
      $id=(int)input('id'); if ($id===(int)$u['id']) throw new InvalidArgumentException('No puedes desactivar tu propia cuenta.');
      db()->beginTransaction(); $target=one('SELECT * FROM users WHERE id=? FOR UPDATE',[$id]); if (!$target) throw new InvalidArgumentException('Usuario no encontrado.');
      query('UPDATE users SET active=1-active WHERE id=?',[$id]); audit('Cambió estado de usuario','users',$id); db()->commit();
    }
    flash('Usuario actualizado.'); redirect('index.php?page=users');
 }
 if (!canWrite($u)) { http_response_code(403); throw new InvalidArgumentException('Tu perfil es de consulta. No puedes modificar registros.'); }
 if ($action==='patient_save') {
    $id=(int)($_POST['id']??0); if ($id) patient($id);
    $birth=postedDate('birth_date'); if ($birth>date('Y-m-d') || $birth<'1900-01-01') throw new InvalidArgumentException('Revisa la fecha de nacimiento.');
    if(!$id) requireCredAge($birth,date('Y-m-d'),'el ingreso del paciente');
    if($id && $birth!==patient($id)['birth_date']) {
      // Preserve old records while rejecting birth-date corrections that invalidate existing care.
      foreach(rows('SELECT control_date AS care_date FROM controls WHERE patient_id=? UNION ALL SELECT DATE(scheduled_at) AS care_date FROM appointments WHERE patient_id=? AND status<>\'Cancelada\'',[$id,$id]) as $record) {
        requireCredAge($birth,$record['care_date'],'los registros vinculados a la ficha');
      }
    }
    $doc=input('document',20,false); if ($doc!=='' && !preg_match('/^\d{8}$/',$doc)) throw new InvalidArgumentException('El DNI debe tener 8 dígitos; déjalo vacío si el niño aún no tiene documento.');
    $phone=input('phone',20); if (!preg_match('/^[+\d ()-]{7,20}$/',$phone)) throw new InvalidArgumentException('Ingresa un teléfono de contacto válido.');
    $data=[input('name',150),$doc?:null,$birth,choice('sex',['F','M']),input('guardian_name',150),input('guardian_relationship',50),$phone,input('address',250,false)];
    db()->beginTransaction();
    if ($id) { query('UPDATE patients SET name=?,document=?,birth_date=?,sex=?,guardian_name=?,guardian_relationship=?,phone=?,address=? WHERE id=?',[...$data,$id]); }
    else { query('INSERT INTO patients(name,document,birth_date,sex,guardian_name,guardian_relationship,phone,address,created_by) VALUES (?,?,?,?,?,?,?,?,?)',[...$data,$u['id']]); $id=(int)db()->lastInsertId(); }
    audit('Guardó paciente','patients',$id); db()->commit(); flash('Ficha del paciente guardada.'); redirect('index.php?page=patient&id='.$id);
 }
 if ($action==='patient_toggle') {
    $id=(int)input('id'); patient($id); db()->beginTransaction();
    query('UPDATE patients SET active=1-active WHERE id=?',[$id]); audit('Cambió estado del paciente','patients',$id); db()->commit(); flash('Estado actualizado. El historial se conserva.'); redirect('index.php?page=patient&id='.$id);
 }
 if ($action==='appointment_save') {
    $pid=(int)input('patient_id'); $p=activePatient($pid);
    $date=postedDate('date'); $time=input('time',5); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$time)) throw new InvalidArgumentException('Hora inválida.');
    requireCredAge($p['birth_date'],$date,'la fecha de la cita');
    if ($date.' '.$time < date('Y-m-d H:i')) throw new InvalidArgumentException('Programa una cita en una fecha y hora futura.');
    $type=choice('type',['Primera atención','Control periódico','Seguimiento']); $note=input('note',500,false); $id=(int)($_POST['id']??0);
    db()->beginTransaction();
    if ($id) { $a=one('SELECT * FROM appointments WHERE id=? FOR UPDATE',[$id]); if (!$a || $a['status']==='Completada' || (int)$a['patient_id']!==$pid) throw new InvalidArgumentException('Esta cita no se puede reprogramar.'); query("UPDATE appointments SET scheduled_at=?,type=?,note=?,status='Programada' WHERE id=?",[$date.' '.$time,$type,$note,$id]); }
    else { query('INSERT INTO appointments(patient_id,scheduled_at,type,note,created_by) VALUES (?,?,?,?,?)',[$pid,$date.' '.$time,$type,$note,$u['id']]); $id=(int)db()->lastInsertId(); }
    audit('Programó cita','appointments',$id); db()->commit(); flash('Cita guardada en la agenda.'); redirect('index.php?page=agenda&date='.$date);
 }
 if ($action==='appointment_status') {
    $id=(int)input('id'); $status=choice('status',['Confirmada','No asistió','Cancelada']);
    db()->beginTransaction(); $a=one('SELECT * FROM appointments WHERE id=? FOR UPDATE',[$id]);
    if (!$a || !in_array($a['status'],['Programada','Confirmada'],true)) throw new InvalidArgumentException('La cita ya fue cerrada.');
    if($status==='Confirmada') { $p=activePatient((int)$a['patient_id']); requireCredAge($p['birth_date'],substr($a['scheduled_at'],0,10),'la confirmación de la cita'); }
    if ($status==='No asistió' && $a['scheduled_at']>date('Y-m-d H:i:s')) throw new InvalidArgumentException('La cita todavía no ha ocurrido.');
    query('UPDATE appointments SET status=? WHERE id=?',[$status,$id]); audit('Cita: '.$status,'appointments',$id); db()->commit(); flash('Estado de la cita actualizado.'); redirect('index.php?page=agenda&date='.substr($a['scheduled_at'],0,10));
 }
 if ($action==='control_save') {
    $pid=(int)input('patient_id'); $p=activePatient($pid); $date=postedDate('control_date');
    if ($date>date('Y-m-d') || $date<$p['birth_date']) throw new InvalidArgumentException('La fecha del control debe estar entre el nacimiento y hoy.');
    requireCredAge($p['birth_date'],$date,'la fecha del control');
    $weight=measurement('weight',0.1,150); $height=measurement('height',20,200); $head=measurement('head_circumference',15,80,false);
    $development=choice('development',['Sin observaciones','Requiere seguimiento','Referencia indicada']);
    $findings=input('findings',5000,false); $instructions=input('instructions',5000);
    $next=input('next_date',10,false); $nextTime=input('next_time',5,false);
    if ($next!=='') { validDate($next); if ($next<=$date || $next<date('Y-m-d') || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$nextTime) || $next.' '.$nextTime<=date('Y-m-d H:i')) throw new InvalidArgumentException('El próximo control necesita una fecha y hora futura, posterior a esta atención.'); }
    if($next!=='') requireCredAge($p['birth_date'],$next,'el próximo control');
    $aid=(int)($_POST['appointment_id']??0);
    db()->beginTransaction();
    if ($aid) { $a=one('SELECT * FROM appointments WHERE id=? FOR UPDATE',[$aid]); if (!$a || (int)$a['patient_id']!==$pid || !in_array($a['status'],['Programada','Confirmada'],true) || substr($a['scheduled_at'],0,10)!==$date) throw new InvalidArgumentException('La cita no corresponde al paciente y fecha, o ya fue cerrada.'); }
    query('INSERT INTO controls(patient_id,appointment_id,control_date,weight,height,head_circumference,development,findings,instructions,next_date,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$pid,$aid?:null,$date,$weight,$height,$head,$development,$findings,$instructions,$next?:null,$u['id']]);
    $id=(int)db()->lastInsertId();
    if ($aid) query("UPDATE appointments SET status='Completada' WHERE id=?",[$aid]);
    if ($next!=='') query("INSERT INTO appointments(patient_id,scheduled_at,type,note,created_by) VALUES (?,?,'Seguimiento','Programada al registrar el control',?)",[$pid,$next.' '.$nextTime,$u['id']]);
    audit('Registró control CRED','controls',$id); db()->commit(); flash('Control guardado'.($next!==''?' y próxima cita programada.':'.')); redirect('index.php?page=patient&id='.$pid);
 }
 throw new InvalidArgumentException('Acción no reconocida.');
}
