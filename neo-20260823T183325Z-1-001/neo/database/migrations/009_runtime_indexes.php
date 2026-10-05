<?php

return static function (PDO $pdo): void {
    if (!indiceExiste($pdo, 'requisicoes_ia', 'idx_requisicoes_ia_criado')) {
        $pdo->exec('ALTER TABLE requisicoes_ia ADD INDEX idx_requisicoes_ia_criado (criado_em)');
    }
    if (!indiceExiste($pdo, 'tentativas_login', 'idx_tentativas_login_criado')) {
        $pdo->exec('ALTER TABLE tentativas_login ADD INDEX idx_tentativas_login_criado (criado_em)');
    }
};
