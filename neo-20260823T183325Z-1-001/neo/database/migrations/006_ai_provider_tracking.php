<?php

return static function (PDO $pdo): void {
    adicionarColunaSeAusente($pdo, 'conteudos', 'ai_provider', 'VARCHAR(30) NULL AFTER corpo');
    adicionarColunaSeAusente($pdo, 'conteudos', 'ai_model', 'VARCHAR(120) NULL AFTER ai_provider');
};
