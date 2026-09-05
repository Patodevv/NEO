<?php

return static function (PDO $pdo): void {
    adicionarColunaSeAusente($pdo, 'questoes', 'ai_provider', 'VARCHAR(30) NULL AFTER correta');
    adicionarColunaSeAusente($pdo, 'questoes', 'ai_model', 'VARCHAR(120) NULL AFTER ai_provider');
};
