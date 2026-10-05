<?php

return static function (PDO $pdo): void {
    adicionarColunaSeAusente($pdo, 'users', 'sobrenome', 'VARCHAR(100) NULL AFTER nome');
    adicionarColunaSeAusente($pdo, 'users', 'idade', 'TINYINT UNSIGNED NULL AFTER sobrenome');
    adicionarColunaSeAusente($pdo, 'users', 'genero', 'VARCHAR(40) NULL AFTER idade');
    adicionarColunaSeAusente($pdo, 'users', 'preferencias_json', 'LONGTEXT NULL AFTER gostos');
    adicionarColunaSeAusente($pdo, 'users', 'onboarding_concluido_em', 'DATETIME NULL AFTER ultimo_login_em');

    $pdo->exec("
        UPDATE users
        SET onboarding_concluido_em = COALESCE(ultimo_login_em, criado_em)
        WHERE onboarding_concluido_em IS NULL
    ");
};

