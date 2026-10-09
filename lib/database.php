<?php
declare(strict_types=1);
function databaseConfig(): array {
    if (getenv('MYSQLHOST') !== false) {
        foreach (['MYSQLHOST','MYSQLPORT','MYSQLDATABASE','MYSQLUSER','MYSQLPASSWORD'] as $key) {
            if (getenv($key) === false || getenv($key) === '') throw new RuntimeException('Falta la variable '.$key);
        }
        $host = getenv('MYSQLHOST'); $port = getenv('MYSQLPORT'); $name = getenv('MYSQLDATABASE');
        if (preg_match('/[;\s]/', $host.$name) || !ctype_digit($port)) throw new RuntimeException('Configuración MySQL inválida.');
        return ['dsn'=>"mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", 'user'=>getenv('MYSQLUSER'), 'password'=>getenv('MYSQLPASSWORD')];
    }
    $file = dirname(__DIR__).'/storage/config.local.php';
    if (!is_file($file)) throw new RuntimeException('Configura las variables MySQL o instala el sistema local.');
    return require $file;
}
