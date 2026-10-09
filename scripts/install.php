<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/Lima');
$path = dirname(__DIR__) . '/storage/config.local.php';
if (is_file($path)) { fwrite(STDERR, "El sistema ya está configurado. No se modificó la base.\n"); exit(1); }
$db = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', getenv('CRED_INSTALL_USER') ?: 'root', getenv('CRED_INSTALL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("SET time_zone='-05:00'");
$exists = $db->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'cred_salud'")->fetchColumn();
if ($exists) { fwrite(STDERR, "La base cred_salud ya existe. Instalación detenida para conservarla.\n"); exit(1); }
$db->exec('CREATE DATABASE cred_salud CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec('USE cred_salud');
$db->exec(file_get_contents(dirname(__DIR__) . '/schema.sql'));
$password = bin2hex(random_bytes(24));
$db->exec("CREATE USER 'cred_app'@'127.0.0.1' IDENTIFIED BY " . $db->quote($password));
$db->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON cred_salud.* TO 'cred_app'@'127.0.0.1'");
$adminPassword = 'Cred-' . bin2hex(random_bytes(6)) . '!';
$stmt = $db->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)');
$stmt->execute(['Administrador CRED', 'admin@cred.local', password_hash($adminPassword, PASSWORD_DEFAULT), 'admin']);
$config = ['dsn'=>'mysql:host=127.0.0.1;dbname=cred_salud;charset=utf8mb4', 'user'=>'cred_app', 'password'=>$password];
if (file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) { throw new RuntimeException('No se pudo guardar la configuración.'); }
echo "Instalación completada. Base: cred_salud\nUsuario: admin@cred.local\nContraseña inicial: $adminPassword\n";
