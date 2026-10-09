<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
require __DIR__.'/lib/actions.php';
require __DIR__.'/lib/views.php';
require __DIR__.'/lib/modals.php';
$error=''; $u=null; $unavailable=false;
try { $u=user(); if($_SERVER['REQUEST_METHOD']==='POST') handlePost($u); }
catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); }
catch(PDOException $ex) { $error=$ex->getCode()==='23000'?'Ya existe un registro con ese DNI, correo o cita. Revisa la información.':'No se pudo guardar la información. Vuelve a intentar.'; error_log('CRED database error code '.$ex->getCode()); }
catch(Throwable $ex) { http_response_code(503); $unavailable=true; $error='El sistema no está disponible. Comunícate con el administrador.'; error_log('CRED: '.$ex->getMessage()); }
if(!$unavailable) { try { if(db()->inTransaction()) db()->rollBack(); } catch(Throwable) { $unavailable=true; $error='El sistema requiere configuración por el administrador.'; } }
$page=(string)($_GET['page']??'dashboard');
if($page==='patient') { $page='patients'; $_GET['modal']='detail'; }
if(!in_array($page,['dashboard','patients','agenda','reports','export','users','audit','account'],true)) $page='dashboard';
if($u && in_array($page,['users','audit'],true) && $u['role']!=='admin') { http_response_code(403); $page='dashboard'; $error='No tienes permiso para acceder a ese módulo.'; }
if(isset($_GET['cred_context'])) {
 header('Content-Type: application/json; charset=utf-8');
 if(!$u){http_response_code(401);echo json_encode(['error'=>'Inicia sesión.']);exit;}
 try {
   $birth=isset($_GET['patient_id'])?patient((int)$_GET['patient_id'])['birth_date']:validDate((string)($_GET['birth_date']??''));
   $on=validDate((string)($_GET['on']??date('Y-m-d')));
   echo json_encode(credContext($birth,$on),JSON_UNESCAPED_UNICODE);
 }catch(InvalidArgumentException $ex){http_response_code(422);echo json_encode(['error'=>$ex->getMessage()],JSON_UNESCAPED_UNICODE);}
 exit;
}
if($u && $page==='export') {
 header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="citas-'.selectedMonth().'.csv"');
 $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['Historia','Paciente','DNI','Fecha y hora Lima','Tipo','Estado']);
 foreach(reportData(selectedMonth()) as $row) { $safe=array_map(fn($v)=>preg_match('/^[=+@\-\t\r\n]/',(string)$v)?"'".$v:(string)$v,array_values($row)); fputcsv($out,$safe); } fclose($out); audit('Exportó reporte de citas','appointments'); exit;
}
$modal=(string)($_GET['modal']??''); $flash=$_SESSION['flash']??''; unset($_SESSION['flash']);
$titles=['patient'=>'Ficha del paciente','detail'=>'Consulta de historia','control'=>'Registrar control CRED','control_detail'=>'Detalle del control','appointment'=>'Programar atención','appointment_detail'=>'Detalle de la cita','appointment_status'=>'Gestionar cita','patient_toggle'=>'Estado del paciente','user_toggle'=>'Estado del usuario','user'=>'Crear usuario','password'=>'Cambiar contraseña','patient_search'=>'Buscar paciente'];
if($modal==='user' && !empty($_GET['id'])) $titles['user']='Editar usuario';
if($modal==='appointment') $titles['appointment']=empty($_GET['id'])?'Nueva cita':'Reprogramar cita';
?>
<!doctype html><html lang="es-PE"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#20478e"><title>CRED · Seguimiento infantil</title><link rel="stylesheet" href="styles.css"><link rel="icon" href="assets/cred.svg" type="image/svg+xml"><link rel="manifest" href="manifest.webmanifest"><script src="cred.js" defer></script></head>
<body class="<?= $u?'':'login-body' ?>"><a class="skip-link" href="#main">Saltar al contenido</a>
<?php if(!$u): ?>
<main class="login-layout" id="main"><section class="login-story"><div class="institution"><span class="peru-mark">PERÚ</span><span>Salud<br><strong>Piura</strong></span></div><div class="story-content"><span class="login-emblem"><?= icon('heart') ?></span><p class="eyebrow">CRECIMIENTO Y DESARROLLO</p><h1>Acompañamos<br>cada etapa de vida.</h1><p>Información organizada para una atención cercana a niñas, niños y sus familias.</p><div class="story-footer"><span>Seguimiento infantil</span><span>Atención de enfermería</span></div></div><p class="institution-caption">Referencia institucional<br>Sub Región de Salud Morropón Huancabamba</p></section><section class="login-side"><div class="login-card"><div class="login-brand"><img src="assets/cred.svg" alt="" width="56" height="56"><div><strong>CRED<span> SALUD</span></strong><small>Sistema de seguimiento infantil</small></div></div><h2>Bienvenido al sistema</h2><p>Ingrese su correo y contraseña para acceder.</p>
<?php if($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif ?>
<?php if(!$unavailable): ?><form method="post" class="login-form"><?= csrf() ?><input type="hidden" name="action" value="login"><label class="field"><span>Correo de usuario</span><input type="email" name="email" value="<?= old('email') ?>" autocomplete="username" placeholder="usuario@establecimiento.pe" required maxlength="160" autofocus></label><label class="field"><span>Contraseña</span><span class="password-field"><input id="loginPassword" type="password" name="password" autocomplete="current-password" required maxlength="255"><button type="button" data-password="loginPassword" aria-label="Mostrar contraseña">Mostrar</button></span></label><button class="button primary login-submit">Ingresar al sistema <?= icon('logout') ?></button></form><?php endif ?>
<p class="login-help">¿Necesitas acceso? Solicita una cuenta al administrador de tu establecimiento.</p><div class="login-note">Acceso por perfiles · Sesión personal</div></div><footer class="login-bottom">Proyecto CRED · Piura, Perú<br><small>Diseño inspirado en SIHCE. Sistema independiente, sin conexión con MINSA.</small></footer></section></main>
<?php else: ?>
<div class="app-layout"><aside class="sidebar" id="sidebar"><a class="brand" href="index.php"><img src="assets/cred.svg" alt="" width="42" height="42"><span><strong>CRED <em>SALUD</em></strong><small>Seguimiento infantil</small></span></a><p class="nav-caption">GESTIÓN ASISTENCIAL</p><nav aria-label="Navegación principal">
<?php foreach(['dashboard'=>['home','Inicio'],'patients'=>['patients','Pacientes'],'agenda'=>['calendar','Agenda'],'reports'=>['reports','Reportes']] as $key=>[$ic,$label]): ?><a class="nav-item <?= $page===$key?'active':'' ?>" href="?page=<?= $key ?>" <?= $page===$key?'aria-current="page"':'' ?>><?= icon($ic) ?><span><?= e($label) ?></span></a><?php endforeach ?>
<?php if($u['role']==='admin'): ?><p class="nav-caption">ADMINISTRACIÓN</p><?php foreach(['users'=>['users','Usuarios'],'audit'=>['audit','Actividad']] as $key=>[$ic,$label]): ?><a class="nav-item <?= $page===$key?'active':'' ?>" href="?page=<?= $key ?>"><?= icon($ic) ?><span><?= $label ?></span></a><?php endforeach ?><?php endif ?></nav><div class="sidebar-bottom"><form method="post"><?= csrf() ?><input type="hidden" name="action" value="logout"><button class="nav-item logout"><?= icon('logout') ?> Cerrar sesión</button></form></div></aside><div class="sidebar-shade" data-menu-close></div><div class="workspace"><header class="topbar"><button class="menu-button" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="sidebar" data-menu>☰</button><div class="topbar-title"><strong>Sistema de seguimiento CRED</strong><small>Atención de enfermería · Piura</small></div><div class="topbar-right"><time class="clock" data-clock datetime="<?= date('c') ?>"><?= date('d/m/Y · H:i') ?><small>Lima, Perú</small></time><a class="profile" href="?page=account"><span class="avatar"><?= e(mb_substr($u['name'],0,1)) ?></span><span><strong><?= e($u['name']) ?></strong><small><?= e(roleLabel($u['role'])) ?></small></span></a></div></header><main id="main" class="main-content">
<?php if($flash && !$modal): ?><div class="alert success" role="status"><?= icon('check') ?><?= e($flash) ?></div><?php endif ?>
<?php if($error && !$modal): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif ?>
<?php try { renderPage($u); } catch(Throwable $ex) { echo '<div class="alert error">No se pudo cargar esta sección.</div>'; error_log($ex->getMessage()); } ?>
<footer class="page-footer"><span>CRED · Sistema de seguimiento infantil</span><span>Hora de Perú · UTC−5</span></footer></main></div></div>
<?php if($modal): $closeParams=['page'=>$page]; if(isset($_GET['date']))$closeParams['date']=$_GET['date']; if($page==='agenda' && ($_GET['scope']??'')==='upcoming'){ $closeParams['scope']='upcoming'; if(isset($_GET['ap']))$closeParams['ap']=max(1,(int)$_GET['ap']); } $closeUrl='?'.http_build_query($closeParams); ?>
<dialog class="modal <?= in_array($modal,['detail','control','control_detail'],true)?'wide':'' ?>" id="activeModal" aria-labelledby="modalTitle" data-return="<?= e($closeUrl) ?>" open><header class="modal-header"><div><p class="eyebrow">CRED · ATENCIÓN INTEGRAL</p><h2 id="modalTitle"><?= e($titles[$modal]??'Consulta') ?></h2></div><a class="modal-close" href="<?= e($closeUrl) ?>" aria-label="Cerrar ventana">×</a></header><div class="modal-body">
<?php if($flash): ?><div class="alert success" role="status"><?= icon('check') ?><?= e($flash) ?></div><?php endif ?>
<?php if($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif ?>
<?php try { renderModal($modal,$u); } catch(Throwable $ex) { echo '<div class="alert error">'.e($ex instanceof InvalidArgumentException?$ex->getMessage():'No se pudo cargar el registro.').'</div>'; } ?>
</div></dialog><?php endif ?>
<?php endif ?></body></html>
