<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/lib/database.php';
try {
    if (!getenv('MYSQLHOST')) throw new RuntimeException('Se requieren las variables MySQL de Railway.');
    $c = databaseConfig();
    $db = new PDO($c['dsn'], $c['user'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if ((int)$db->query("SELECT GET_LOCK('cred_setup', 30)")->fetchColumn() !== 1) throw new RuntimeException('Instalación ocupada.');
    try {
        $db->exec(file_get_contents(dirname(__DIR__).'/schema.sql'));
        echo "Estructura lista. No se crean cuentas ni se importan datos automáticamente.\n";
    } finally { $db->query("SELECT RELEASE_LOCK('cred_setup')"); }
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo preparar la base. Verifica las variables MySQL, conectividad y permisos. No se muestran credenciales.\n");
    exit(1);
}
