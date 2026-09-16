<?php

return static function (PDO $pdo): void {
    $pdo->exec("UPDATE conteudos
        SET corpo = NULL,
            ai_provider = NULL,
            ai_model = NULL,
            status = 'Não iniciado'
        WHERE corpo LIKE '%Use os interesses informados pelo estudante%'
           OR corpo LIKE '%Este material apresenta uma explicacao organizada sobre%'
           OR corpo LIKE '%Este material apresenta uma explicação organizada sobre%'");
};
