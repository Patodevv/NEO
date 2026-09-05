<?php

function tabelaExiste(PDO $pdo, string $tabela): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$tabela]);
    return (int)$stmt->fetchColumn() > 0;
}

function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$tabela, $coluna]);
    return (int)$stmt->fetchColumn() > 0;
}

function indiceExiste(PDO $pdo, string $tabela, string $indice): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$tabela, $indice]);
    return (int)$stmt->fetchColumn() > 0;
}

function constraintExiste(PDO $pdo, string $tabela, string $constraint): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?");
    $stmt->execute([$tabela, $constraint]);
    return (int)$stmt->fetchColumn() > 0;
}

function adicionarColunaSeAusente(PDO $pdo, string $tabela, string $coluna, string $definicao): void
{
    if (!colunaExiste($pdo, $tabela, $coluna)) {
        $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
    }
}

function executarMigracoes(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            versao VARCHAR(180) PRIMARY KEY,
            aplicado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $lock = (int)$pdo->query("SELECT GET_LOCK('neo_schema_migrations', 15)")->fetchColumn();

    if ($lock !== 1) {
        throw new RuntimeException('Nao foi possivel obter o bloqueio das migrations.');
    }

    try {
        $aplicadas = array_fill_keys($pdo->query("SELECT versao FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN), true);
        $arquivos = glob(__DIR__ . '/migrations/*.php') ?: [];
        sort($arquivos, SORT_STRING);
        $registrar = $pdo->prepare("INSERT INTO schema_migrations (versao) VALUES (?)");

        foreach ($arquivos as $arquivo) {
            $versao = basename($arquivo, '.php');

            if (isset($aplicadas[$versao])) {
                continue;
            }

            $migracao = require $arquivo;

            if (!is_callable($migracao)) {
                throw new RuntimeException("Migration invalida: {$versao}");
            }

            $migracao($pdo);
            $registrar->execute([$versao]);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('neo_schema_migrations')");
    }
}

