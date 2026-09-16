<?php

return static function (PDO $pdo): void {
    adicionarColunaSeAusente(
        $pdo,
        'users',
        'dias_estudo_semana',
        'TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER nivel'
    );

    $pdo->exec("UPDATE users SET dias_estudo_semana = 3 WHERE dias_estudo_semana < 1 OR dias_estudo_semana > 7");
};

