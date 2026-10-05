<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS status_provedores_ia (
            provedor VARCHAR(40) NOT NULL PRIMARY KEY,
            modelo VARCHAR(120) NULL,
            limite_tokens INT NULL,
            tokens_restantes INT NULL,
            limite_requisicoes INT NULL,
            requisicoes_restantes INT NULL,
            reset_tokens_segundos INT NULL,
            reset_requisicoes_segundos INT NULL,
            retry_after_segundos INT NULL,
            status_http INT NULL,
            ultima_falha VARCHAR(500) NULL,
            reset_tokens_em DATETIME NULL,
            reset_requisicoes_em DATETIME NULL,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
};
