<?php
declare(strict_types=1);
date_default_timezone_set('America/Lima');
require_once __DIR__.'/cred.php';
require_once __DIR__.'/database.php';
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
session_name('CRED_SESSION');
session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax', 'secure'=>getenv('CRED_HTTPS') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'path'=>'/']);
session_start();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = databaseConfig();
        $pdo = new PDO($c['dsn'], $c['user'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        $pdo->exec("SET time_zone = '-05:00'");
    }
    return $pdo;
}
function query(string $sql, array $args=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($args); return $s; }
function rows(string $sql, array $args=[]): array { return query($sql,$args)->fetchAll(); }
function one(string $sql, array $args=[]): ?array { return query($sql,$args)->fetch() ?: null; }
function scalar(string $sql, array $args=[]): mixed { return query($sql,$args)->fetchColumn(); }
function redirect(string $path='index.php'): never { header('Location: '.$path, true, 303); exit; }
function user(): ?array {
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 1800) { unset($_SESSION['uid']); }
    $_SESSION['last_seen'] = time();
    return isset($_SESSION['uid']) ? one('SELECT id,name,email,role FROM users WHERE id=? AND active=1', [$_SESSION['uid']]) : null;
}
function canWrite(array $u): bool { return in_array($u['role'], ['admin','nurse'], true); }
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function audit(string $action, string $entity, ?int $id=null): void { query('INSERT INTO audit_log (user_id,action,entity,entity_id) VALUES (?,?,?,?)', [$_SESSION['uid']??null,$action,$entity,$id]); }
function input(string $name, int $max=255, bool $required=true): string {
    $v = $_POST[$name] ?? '';
    if (!is_string($v)) throw new InvalidArgumentException('Formato de campo no válido.');
    $v=trim($v);
    if (($required && $v==='') || mb_strlen($v)>$max) throw new InvalidArgumentException('Revisa los campos obligatorios y su longitud.');
    return $v;
}
function choice(string $name, array $values): string { $v=input($name); if (!in_array($v,$values,true)) throw new InvalidArgumentException('Selecciona una opción válida.'); return $v; }
function validDate(string $v): string { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v); if (!$d || $d->format('Y-m-d')!==$v) throw new InvalidArgumentException('La fecha no es válida.'); return $v; }
function postedDate(string $key): string { return validDate(input($key,10)); }
function measurement(string $key, float $min, float $max, bool $required=true): ?float {
    $v=input($key,10,$required); if ($v==='' && !$required) return null;
    if (!is_numeric($v) || (float)$v<$min || (float)$v>$max) throw new InvalidArgumentException('Revisa las unidades y el rango de las medidas ingresadas.'); return (float)$v;
}
function patient(int $id): array { $p=one('SELECT * FROM patients WHERE id=?',[$id]); if (!$p) throw new InvalidArgumentException('No se encontró el paciente.'); return $p; }
function activePatient(int $id): array { $p=patient($id); if (!$p['active']) throw new InvalidArgumentException('El paciente está inactivo. Reactívalo antes de registrar una atención.'); return $p; }
function age(string $date): string { $diff=(new DateTimeImmutable($date))->diff(new DateTimeImmutable('today')); return $diff->y ? $diff->y.' años · '.$diff->m.' meses' : ($diff->m ? $diff->m.' meses' : $diff->d.' días'); }
function dateLabel(?string $date, bool $time=false): string { return $date ? date($time?'d/m/Y H:i':'d/m/Y', strtotime($date)) : 'Sin registro'; }
function flash(string $message): void { $_SESSION['flash']=$message; }
function old(string $key, mixed $fallback=''): string { return e($_POST[$key] ?? $fallback); }
function selected(mixed $a,mixed $b): string { return (string)$a===(string)$b ? ' selected' : ''; }
function icon(string $name): string {
 $paths=['home'=>'<path d="m3 10 9-7 9 7v10H3Z"/><path d="M9 20v-7h6v7"/>','patients'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6m2 4a5 5 0 0 1 2 4v2"/>','calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-13 4h2m4 0h2"/>','reports'=>'<path d="M4 3v18h17M8 17v-5m5 5V7m5 10V4"/>','users'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>','audit'=>'<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6m-6 4h6m-6 4h4"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','search'=>'<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>','logout'=>'<path d="M9 4H4v16h5m-1-8h13m-4-4 4 4-4 4"/>','heart'=>'<path d="M20 5c-3-3-7-1-8 1C9 1 2 3 2 8c0 5 10 12 10 12s10-7 10-12c0-1-1-2-2-3Z"/><path d="M4 12h4l2-4 3 8 2-4h5"/>','check'=>'<path d="m5 12 4 4L19 6"/>','alert'=>'<path d="m12 3 10 18H2Z M12 9v5m0 3h.01"/>'];
 return '<svg viewBox="0 0 24 24" aria-hidden="true">'.($paths[$name]??$paths['home']).'</svg>';
}
