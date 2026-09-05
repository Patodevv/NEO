<?php

require_once __DIR__ . '/env.php';

date_default_timezone_set((string)ambienteNeo('APP_TIMEZONE', 'America/Sao_Paulo'));

$host = (string)ambienteNeo('DB_HOST', 'localhost');
$porta = (int)ambienteNeo('DB_PORT', 3306);
$dbname = (string)ambienteNeo('DB_NAME', 'neo');
$user = (string)ambienteNeo('DB_USER', 'root');
$senha = (string)ambienteNeo('DB_PASSWORD', '');

if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbname)) {
    throw new RuntimeException('Nome de banco invalido.');
}

try {
    if (ambienteBooleanoNeo('DB_AUTO_CREATE', true)) {
        $pdoServidor = new PDO(
            "mysql:host={$host};port={$porta};charset=utf8mb4",
            $user,
            $senha,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdoServidor->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    $pdo = new PDO(
        "mysql:host={$host};port={$porta};dbname={$dbname};charset=utf8mb4",
        $user,
        $senha,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    require_once dirname(__DIR__) . '/database/migrator.php';
    executarMigracoes($pdo);
} catch (Throwable $e) {
    error_log('[NEO][database] ' . $e->getMessage());

    if (PHP_SAPI === 'cli') {
        throw $e;
    }

    http_response_code(503);
    exit('O servico esta temporariamente indisponivel. Tente novamente em instantes.');
}
