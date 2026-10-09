<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/lib/database.php';
try {
    $mode = $argv[1] ?? '';
    if (!in_array($mode, ['export','import'], true)) throw new RuntimeException('Usa export o import.');
    $c = $mode === 'export' ? require dirname(__DIR__).'/storage/config.local.php' : databaseConfig();
    $db = new PDO($c['dsn'], $c['user'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec("SET time_zone='-05:00'");
    if ($mode === 'export') {
        // Salida sensible: canalizar directamente a import, nunca publicar ni guardar en Git.
        echo json_encode($db->query('SELECT id,name,email,password_hash,role,active,created_at FROM users ORDER BY id')->fetchAll(), JSON_THROW_ON_ERROR);
        exit;
    }
    if (!getenv('MYSQLHOST')) throw new RuntimeException('Importación permitida únicamente con variables MySQL.');
    $users = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($users) || !$users || !array_is_list($users)) throw new RuntimeException('Sin usuarios válidos.');
    $hasAdmin = false;
    foreach ($users as $u) {
        if (!is_array($u) || count($u) !== 7 || !isset($u['id'],$u['name'],$u['email'],$u['password_hash'],$u['role'],$u['active'],$u['created_at'])) throw new RuntimeException('Formato inválido.');
        if (!filter_var($u['email'], FILTER_VALIDATE_EMAIL) || !in_array($u['role'], ['admin','nurse','supervisor'], true) || !password_get_info($u['password_hash'])['algo']) throw new RuntimeException('Cuenta inválida.');
        $hasAdmin = $hasAdmin || ($u['role'] === 'admin' && (int)$u['active'] === 1);
    }
    if (!$hasAdmin) throw new RuntimeException('Falta administrador activo.');
    if ((int)$db->query("SELECT GET_LOCK('cred_users_import', 30)")->fetchColumn() !== 1) throw new RuntimeException('Importación ocupada.');
    try {
        $db->beginTransaction();
        foreach (['users','patients','appointments','controls','vaccinations','audit_log','login_attempts'] as $table) {
            if ((int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() !== 0) throw new RuntimeException('La base destino no está vacía. No se sobrescribe.');
        }
        $insert = $db->prepare('INSERT INTO users (id,name,email,password_hash,role,active,created_at) VALUES (?,?,?,?,?,?,?)');
        foreach ($users as $u) $insert->execute([$u['id'],$u['name'],$u['email'],$u['password_hash'],$u['role'],$u['active'],$u['created_at']]);
        $saved = $db->query('SELECT id,name,email,password_hash,role,active,created_at FROM users ORDER BY id')->fetchAll();
        if ($saved != $users) throw new RuntimeException('No coincide la verificación de las cuentas.');
        $db->commit();
        echo 'Usuarios importados: '.count($users).". Roles y hashes conservados. Sin datos clínicos.\n";
    } finally {
        if ($db->inTransaction()) $db->rollBack();
        $db->query("SELECT RELEASE_LOCK('cred_users_import')");
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Transferencia detenida. Revisa conectividad, formato y que el destino esté vacío. No se muestran datos sensibles.\n");
    exit(1);
}
