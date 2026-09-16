<?php

return static function (PDO $pdo): void {
    adicionarColunaSeAusente($pdo, 'users', 'personalizacao_versao', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
    adicionarColunaSeAusente($pdo, 'users', 'personalizacao_json', 'LONGTEXT NULL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS onboarding_progress (
        user_id INT NOT NULL PRIMARY KEY,
        answers_json LONGTEXT NOT NULL,
        diagnostic_json LONGTEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
