<?php
declare(strict_types=1);
function badge(string $text): string {
 $tone=match($text) { 'Completada','Confirmada','Activo','Sin observaciones'=>'good', 'Cancelada','No asistió','Inactivo','Referencia indicada'=>'danger', 'Vencida','Requiere seguimiento'=>'warning', default=>'neutral' };
 return '<span class="badge '.$tone.'">'.e($text).'</span>';
}
function modalLink(string $label,string $modal,array $params=[],string $class='button secondary'): string {
 $base=['page'=>$GLOBALS['page'],'modal'=>$modal];
 if(isset($_GET['date'])) $base['date']=$_GET['date'];
 if($GLOBALS['page']==='agenda' && ($_GET['scope']??'')==='upcoming') { $base['scope']='upcoming'; if(isset($_GET['ap'])) $base['ap']=max(1,(int)$_GET['ap']); }
 return '<a class="'.e($class).'" href="?'.e(http_build_query(array_merge($base,$params))).'">'.$label.'</a>';
}
function field(string $label,string $name,string $type='text',mixed $value='',bool $required=true,string $extra=''): void {
 echo '<label class="field"><span>'.e($label).($required?' <b aria-hidden="true">*</b>':'').'</span><input type="'.e($type).'" name="'.e($name).'" value="'.old($name,$value).'" '.($required?'required':'').' '.$extra.'></label>';
}
function selectField(string $label,string $name,array $options,mixed $value=''): void {
 echo '<label class="field"><span>'.e($label).' <b aria-hidden="true">*</b></span><select name="'.e($name).'" required><option value="">Seleccionar</option>';
 foreach($options as $key=>$text) echo '<option value="'.e($key).'"'.selected($_POST[$name]??$value,$key).'>'.e($text).'</option>';
 echo '</select></label>';
}
function textField(string $label,string $name,string $value='',bool $required=false): void {
 echo '<label class="field full"><span>'.e($label).($required?' <b aria-hidden="true">*</b>':'').'</span><textarea name="'.e($name).'" rows="3" maxlength="5000" '.($required?'required':'').'>'.old($name,$value).'</textarea></label>';
}
function formStart(string $action): void { echo '<form method="post" class="data-form">'.csrf().'<input type="hidden" name="action" value="'.e($action).'">'; }
function formEnd(string $label='Guardar cambios'): void { echo '<footer class="form-footer"><button type="button" class="button secondary" data-close>Cancelar</button><button class="button primary" type="submit">'.e($label).'</button></footer></form>'; }
function hidden(string $key,mixed $value): void { echo '<input type="hidden" name="'.e($key).'" value="'.e($value).'">'; }
function credAgePanel(string $identity,string $date,bool $enforce=true): void {
 echo '<div class="cred-age-panel" data-cred-identity="'.e($identity).'" data-cred-date="'.e($date).'" data-cred-enforce="'.($enforce?'1':'0').'" data-today="'.date('Y-m-d').'" aria-live="polite"><strong data-age-title>Edad y grupo CRED</strong><p data-age-description>Selecciona el paciente o nacimiento y la fecha para consultar el rango.</p><small>NTS 238-MINSA/DGIESP-2025 · Hora de Lima</small></div>';
}
function emptyState(string $title,string $text): void { echo '<div class="empty">'.icon('heart').'<h3>'.e($title).'</h3><p>'.e($text).'</p></div>'; }
function pageHeading(string $kicker,string $title,string $description,string $action=''): void { echo '<div class="page-heading"><div><p class="eyebrow">'.e($kicker).'</p><h1>'.e($title).'</h1><p>'.e($description).'</p></div>'.$action.'</div>'; }
function patientOptions(): array { return array_column(rows('SELECT id,CONCAT(name,\' · HC \',LPAD(id,6,\'0\')) AS label FROM patients WHERE active=1 ORDER BY name'),'label','id'); }
function selectedMonth(): string { $m=(string)($_GET['month']??date('Y-m')); if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$m)) $m=date('Y-m'); return $m; }
function reportData(string $month): array {
 $start=$month.'-01'; $end=date('Y-m-d',strtotime($start.' +1 month'));
 return rows('SELECT p.id,p.name,p.document,a.scheduled_at,a.type,a.status FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.scheduled_at>=? AND a.scheduled_at<? ORDER BY a.scheduled_at',[$start,$end]);
}
function renderPage(array $u): void {
 global $page;
 $write=canWrite($u); $today=date('Y-m-d');
 if($page==='dashboard') {
   pageHeading('Seguimiento de crecimiento y desarrollo','Bienvenido, '.explode(' ',$u['name'])[0], 'Tu jornada de atención, organizada en un solo lugar.', $write?modalLink(icon('plus').' Nuevo control','control',[],'button primary'):'');
   $active=(int)scalar('SELECT COUNT(*) FROM patients WHERE active=1');
   $appointments=(int)scalar('SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_at)=? AND status<>\'Cancelada\'',[$today]);
   $done=(int)scalar('SELECT COUNT(*) FROM controls WHERE control_date=?',[$today]);
   $late=(int)scalar("SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE p.active=1 AND DATE(a.scheduled_at)<? AND a.status IN ('Programada','Confirmada')",[$today]);
   echo '<div class="metrics">';
   foreach([['patients','Pacientes activos',$active,'Fichas registradas'],['calendar','Citas de hoy',$appointments,'Agenda del '.date('d/m/Y')],['check','Controles realizados',$done,'Registrados hoy'],['alert','Citas vencidas',$late,'Pendientes de seguimiento']] as [$i,$t,$n,$s]) echo '<article class="metric"><span class="metric-icon '.$i.'">'.icon($i).'</span><div><p>'.e($t).'</p><strong>'.e($n).'</strong><small>'.e($s).'</small></div></article>';
   echo '</div><div class="dashboard-columns"><section class="panel"><div class="panel-heading"><div><h2>Agenda de hoy</h2><p>Atenciones del '.date('d/m/Y').'</p></div><a class="text-link" href="?page=agenda">Ver agenda →</a></div>';
   $list=rows('SELECT a.*,p.name FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE DATE(a.scheduled_at)=? ORDER BY a.scheduled_at',[$today]);
   if(!$list) emptyState('Hoy no hay citas','Programa la próxima atención desde la agenda.');
   foreach($list as $a) echo '<div class="appointment"><time>'.date('H:i',strtotime($a['scheduled_at'])).'</time><div><strong>'.e($a['name']).'</strong><small>'.e($a['type']).'</small></div>'.badge($a['status']).modalLink('Ver','appointment_detail',['id'=>$a['id']],'text-link').'</div>';
   echo '</section><section class="panel blue-panel"><span class="large-symbol">'.icon('heart').'</span><p class="eyebrow">ATENCIÓN CENTRADA EN LAS PERSONAS</p><h2>Cada control cuenta.</h2><p>Consulta el historial, registra la atención y deja programado el siguiente encuentro con la familia.</p><div class="quick-actions">'.modalLink('Buscar una ficha →','patient_search',[],'button white');
   if($write) echo modalLink('Registrar paciente','patient',[],'button ghost');
   echo '</div><small>Hora oficial de Perú · America/Lima</small></section></div><section class="panel"><div class="panel-heading"><div><h2>Seguimiento pendiente</h2><p>Citas vencidas y observaciones del último control</p></div></div>';
   $follow=rows("SELECT p.id,p.name,p.document,(SELECT MIN(a.scheduled_at) FROM appointments a WHERE a.patient_id=p.id AND DATE(a.scheduled_at)<CURDATE() AND a.status IN ('Programada','Confirmada')) AS overdue,(SELECT c.development FROM controls c WHERE c.patient_id=p.id ORDER BY c.control_date DESC,c.id DESC LIMIT 1) AS development FROM patients p WHERE p.active=1 HAVING overdue IS NOT NULL OR development IN ('Requiere seguimiento','Referencia indicada') ORDER BY overdue LIMIT 20");
   if(!$follow) emptyState('Sin pendientes por ahora','Aquí aparecerán las citas vencidas y las observaciones de seguimiento.');
   else { echo '<div class="table-wrap"><table><thead><tr><th>Paciente</th><th>Situación</th><th>Desde</th><th>Acción</th></tr></thead><tbody>'; foreach($follow as $p) echo '<tr><td><strong>'.e($p['name']).'</strong><small>DNI '.e($p['document']?:'sin documento').'</small></td><td>'.badge($p['overdue']?'Vencida':$p['development']).'</td><td>'.($p['overdue']?e(dateLabel($p['overdue'])):'Último control').'</td><td>'.modalLink('Consultar ficha','detail',['id'=>$p['id']],'text-link').'</td></tr>'; echo '</tbody></table></div>'; }
   echo '</section>';
 } elseif($page==='patients') {
   pageHeading('Gestión de pacientes','Niñas y niños','Una ficha por paciente, con su responsable e historial de atención.',$write?modalLink(icon('plus').' Nuevo paciente','patient',[],'button primary'):'');
   $search=trim((string)($_GET['q']??'')); $state=($_GET['state']??'active')==='inactive'?'inactive':'active'; $n=max(1,(int)($_GET['p']??1)); $offset=($n-1)*12;
   $where='WHERE active=? AND (name LIKE ? OR document LIKE ? OR guardian_name LIKE ?)'; $args=[$state==='active'?1:0,'%'.$search.'%','%'.$search.'%','%'.$search.'%'];
   $count=(int)scalar('SELECT COUNT(*) FROM patients '.$where,$args);
   $list=rows('SELECT * FROM patients '.$where.' ORDER BY name LIMIT 12 OFFSET '.$offset,$args);
   echo '<section class="panel"><form class="filters" method="get"><input type="hidden" name="page" value="patients"><label class="search-field">'.icon('search').'<input type="search" name="q" placeholder="Nombre, DNI o responsable" aria-label="Buscar paciente" value="'.e($search).'"></label><select name="state" aria-label="Estado"><option value="active"'.selected($state,'active').'>Activos</option><option value="inactive"'.selected($state,'inactive').'>Inactivos</option></select><button class="button secondary">Buscar</button></form>';
   if(!$list) emptyState('No se encontraron pacientes','Registra una ficha o cambia los criterios de búsqueda.');
   else { echo '<div class="table-wrap"><table><thead><tr><th>Paciente / Historia</th><th>Edad</th><th>Responsable</th><th>Teléfono</th><th>Acciones</th></tr></thead><tbody>'; foreach($list as $p) { echo '<tr><td><div class="person"><span class="avatar">'.e(mb_substr($p['name'],0,1)).'</span><div><strong>'.e($p['name']).'</strong><small>HC '.str_pad((string)$p['id'],6,'0',STR_PAD_LEFT).' · DNI '.e($p['document']?:'pendiente').'</small></div></div></td><td>'.e(age($p['birth_date'])).'</td><td>'.e($p['guardian_name']).'</td><td>'.e($p['phone']).'</td><td><div class="row-actions">'.modalLink('Ver ficha','detail',['id'=>$p['id']],'text-link'); if($write) echo modalLink('Editar','patient',['id'=>$p['id']],'text-link'); echo '</div></td></tr>'; } echo '</tbody></table></div>'; }
   echo '<div class="pagination"><span>'.$count.' pacientes encontrados</span><div>'; for($i=max(1,$n-2);$i<=min((int)ceil($count/12),$n+2);$i++) echo '<a class="'.($i===$n?'current':'').'" href="?'.e(http_build_query(['page'=>'patients','q'=>$search,'state'=>$state,'p'=>$i])).'">'.$i.'</a>'; echo '</div></div></section>';
 } elseif($page==='agenda') {
   $date=(string)($_GET['date']??$today); try { validDate($date); } catch(Throwable) { $date=$today; }
   $upcoming=($_GET['scope']??'')==='upcoming';
   pageHeading('Organización de atenciones','Agenda CRED','Consulta las citas por día o revisa las próximas pendientes. Hora de Lima.',$write?modalLink(icon('plus').' Nueva cita','appointment',[],'button primary'):'');
   echo '<section class="panel"><nav class="agenda-tabs" aria-label="Vista de agenda"><a class="button '.($upcoming?'secondary':'primary').'" href="?page=agenda&date='.e($date).'"'.(!$upcoming?' aria-current="page"':'').'>Por día</a><a class="button '.($upcoming?'primary':'secondary').'" href="?page=agenda&scope=upcoming"'.($upcoming?' aria-current="page"':'').'>Próximas citas</a></nav>';
   if($upcoming) {
     echo '<p class="muted">Citas programadas y confirmadas desde hoy, '.dateLabel($today).', en adelante. Incluye las pendientes de hoy, aunque su hora ya haya pasado.</p>';
     $where="a.scheduled_at>=? AND a.status IN ('Programada','Confirmada')";
     $total=(int)scalar('SELECT COUNT(*) FROM appointments a WHERE '.$where,[$today.' 00:00:00']);
     $pages=max(1,(int)ceil($total/20)); $number=min($pages,max(1,(int)($_GET['ap']??1))); $offset=($number-1)*20;
     $list=rows('SELECT a.*,p.name FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE '.$where.' ORDER BY a.scheduled_at,a.id LIMIT 20 OFFSET '.$offset,[$today.' 00:00:00']);
   } else {
     echo '<form class="filters" method="get"><input type="hidden" name="page" value="agenda"><label>Fecha <input type="date" name="date" value="'.e($date).'" required></label><button class="button secondary">Consultar</button><a class="text-link" href="?page=agenda">Hoy</a><span class="filter-spacer"></span><a class="button secondary" href="?page=agenda&date='.date('Y-m-d',strtotime($date.' -1 day')).'" aria-label="Día anterior">←</a><a class="button secondary" href="?page=agenda&date='.date('Y-m-d',strtotime($date.' +1 day')).'" aria-label="Día siguiente">→</a></form><p class="muted">Mostrando todas las citas del '.dateLabel($date).'. Para otras fechas, usa Próximas citas o cambia el día.</p>';
     $list=rows('SELECT a.*,p.name FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE DATE(a.scheduled_at)=? ORDER BY a.scheduled_at,a.id',[$date]);
   }
   if(!$list) emptyState($upcoming?'Sin próximas citas pendientes':'Sin citas para esta fecha',$upcoming?'Las nuevas citas programadas aparecerán aquí.':'Revisa Próximas citas o selecciona otro día.');
   else { echo '<div class="table-wrap"><table><thead><tr><th>'.($upcoming?'Fecha y hora':'Hora').'</th><th>Paciente</th><th>Atención</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>'; foreach($list as $a) { echo '<tr id="cita-'.(int)$a['id'].'"><td><strong>'.($upcoming?dateLabel($a['scheduled_at'],true):date('H:i',strtotime($a['scheduled_at']))).'</strong></td><td>'.e($a['name']).'</td><td>'.e($a['type']).'</td><td>'.badge($a['status']).'</td><td><div class="row-actions">'.modalLink('Detalle','appointment_detail',['id'=>$a['id']],'text-link'); if($write && in_array($a['status'],['Programada','Confirmada'],true)) { if(substr($a['scheduled_at'],0,10)<=$today) echo modalLink('Atender','control',['patient_id'=>$a['patient_id'],'appointment_id'=>$a['id']],'text-link'); echo modalLink('Gestionar','appointment_status',['id'=>$a['id']],'text-link'); } echo '</div></td></tr>'; } echo '</tbody></table></div>'; }
   if($upcoming && $total) { echo '<div class="pagination"><span>'.$total.' citas pendientes · Página '.$number.' de '.$pages.'</span><div>'; if($number>1)echo '<a href="?page=agenda&scope=upcoming&ap='.($number-1).'" aria-label="Página anterior">←</a>'; if($number<$pages)echo '<a href="?page=agenda&scope=upcoming&ap='.($number+1).'" aria-label="Página siguiente">→</a>'; echo '</div></div>'; }
   echo '</section>';
 } elseif($page==='reports') {
   $month=selectedMonth(); $data=reportData($month); $start=$month.'-01'; $end=date('Y-m-d',strtotime($start.' +1 month'));
   $scheduled=count(array_filter($data,fn($r)=>$r['status']!=='Cancelada')); $done=count(array_filter($data,fn($r)=>$r['status']==='Completada')); $rate=$scheduled?round(100*$done/$scheduled,1):0;
   $controls=(int)scalar('SELECT COUNT(*) FROM controls WHERE control_date>=? AND control_date<?',[$start,$end]);
   pageHeading('Información para la gestión','Reportes de atención','Resultados calculados a partir de las citas y los controles registrados.','<a class="button secondary" href="?page=export&month='.e($month).'">Descargar CSV</a>');
   echo '<section class="panel"><form class="filters" method="get"><input type="hidden" name="page" value="reports"><label>Periodo <input type="month" name="month" value="'.e($month).'" required></label><button class="button secondary">Aplicar</button></form><div class="metrics report-metrics">';
   foreach([['Citas programadas',$scheduled],['Citas completadas',$done],['Cumplimiento',$rate.'%'],['Controles registrados',$controls]] as [$label,$number]) echo '<article class="metric"><div><p>'.e($label).'</p><strong>'.e($number).'</strong></div></article>';
   echo '</div><p class="muted">Cumplimiento = citas completadas / citas del periodo, excluyendo canceladas. Los controles sin cita también cuentan como controles registrados.</p><div class="table-wrap"><table><thead><tr><th>Estado de cita</th><th>Cantidad</th></tr></thead><tbody>';
   foreach(['Programada','Confirmada','Completada','No asistió','Cancelada'] as $state) echo '<tr><td>'.badge($state).'</td><td>'.count(array_filter($data,fn($r)=>$r['status']===$state)).'</td></tr>';
   echo '</tbody></table></div></section>';
 } elseif($page==='users') {
   pageHeading('Administración','Usuarios y permisos','Cada persona accede con una cuenta individual y un perfil definido.',modalLink(icon('plus').' Nuevo usuario','user',[],'button primary'));
   echo '<div class="role-cards"><article><strong>Administrador</strong><p>Gestiona usuarios, registros y auditoría.</p></article><article><strong>Enfermería</strong><p>Registra pacientes, citas y controles.</p></article><article><strong>Supervisor</strong><p>Consulta fichas, agenda e indicadores sin modificarlos.</p></article></div><section class="panel"><div class="table-wrap"><table><thead><tr><th>Usuario</th><th>Perfil</th><th>Estado</th><th>Acción</th></tr></thead><tbody>';
   foreach(rows('SELECT id,name,email,role,active FROM users ORDER BY name') as $a) echo '<tr><td><strong>'.e($a['name']).'</strong><small>'.e($a['email']).'</small></td><td>'.e(roleLabel($a['role'])).'</td><td>'.badge($a['active']?'Activo':'Inactivo').'</td><td><div class="row-actions">'.modalLink('Editar','user',['id'=>$a['id']],'text-link').($a['id']===$u['id']?'Tu cuenta':modalLink($a['active']?'Desactivar':'Activar','user_toggle',['id'=>$a['id']],'text-link')).'</div></td></tr>';
   echo '</tbody></table></div></section>';
 } elseif($page==='audit') {
   pageHeading('Trazabilidad','Registro de actividad','Últimas 100 operaciones del sistema, con fecha y hora de Lima.');
   echo '<section class="panel"><div class="table-wrap"><table><thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Operación</th><th>Referencia</th></tr></thead><tbody>';
   foreach(rows('SELECT a.*,u.name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 100') as $a) echo '<tr><td>'.e(dateLabel($a['created_at'],true)).'</td><td>'.e($a['name']??'Sistema').'</td><td>'.e($a['action']).'</td><td>'.e($a['entity']).' #'.e($a['entity_id']).'</td></tr>';
   echo '</tbody></table></div></section>';
 } elseif($page==='account') {
   pageHeading('Preferencias de acceso','Mi cuenta','Gestiona la seguridad de tu acceso.');
   echo '<section class="panel account-card"><span class="avatar large">'.e(mb_substr($u['name'],0,1)).'</span><h2>'.e($u['name']).'</h2><p>'.e($u['email']).'</p>'.badge(roleLabel($u['role'])).'<p>Zona horaria: America/Lima · UTC−5</p>'.modalLink('Cambiar contraseña','password',[],'button primary').'</section>';
 }
}
function roleLabel(string $role): string { return ['admin'=>'Administrador','nurse'=>'Enfermería','supervisor'=>'Supervisor'][$role]??$role; }
