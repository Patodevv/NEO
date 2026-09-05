<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/db.php';

$versoes = $pdo->query('SELECT versao, aplicado_em FROM schema_migrations ORDER BY versao')
    ->fetchAll(PDO::FETCH_ASSOC);

echo "Migrations aplicadas:\n";
foreach ($versoes as $versao) {
    echo '- ' . $versao['versao'] . ' (' . $versao['aplicado_em'] . ")\n";
}

echo "Banco atualizado.\n";
