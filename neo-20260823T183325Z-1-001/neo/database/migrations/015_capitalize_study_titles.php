<?php

return static function (PDO $pdo): void {
    $pdo->exec("UPDATE materias
        SET nome = CONCAT(UPPER(LEFT(TRIM(nome), 1)), SUBSTRING(TRIM(nome), 2))
        WHERE BINARY nome <> BINARY CONCAT(UPPER(LEFT(TRIM(nome), 1)), SUBSTRING(TRIM(nome), 2))");

    $pdo->exec("UPDATE conteudos
        SET titulo = CONCAT(UPPER(LEFT(TRIM(titulo), 1)), SUBSTRING(TRIM(titulo), 2))
        WHERE BINARY titulo <> BINARY CONCAT(UPPER(LEFT(TRIM(titulo), 1)), SUBSTRING(TRIM(titulo), 2))");
};
