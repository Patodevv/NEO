<?php

return static function (PDO $pdo): void {
    adicionarColunaSeAusente($pdo, 'compras_loja', 'expira_em', 'DATETIME NULL AFTER transacao_cossas_id');

    if (!indiceExiste($pdo, 'conteudos', 'uq_conteudo_titulo')) {
        $stmt = $pdo->query("
            SELECT user_id, materia_id, titulo, COUNT(*) quantidade
            FROM conteudos
            GROUP BY user_id, materia_id, titulo
            HAVING COUNT(*) > 1
            LIMIT 1
        ");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE conteudos ADD UNIQUE KEY uq_conteudo_titulo (user_id, materia_id, titulo)");
        }
    }

    if (!indiceExiste($pdo, 'historico', 'idx_historico_user_conteudo_data')) {
        $pdo->exec("ALTER TABLE historico ADD INDEX idx_historico_user_conteudo_data (user_id, conteudo_id, data)");
    }
    if (!indiceExiste($pdo, 'ajudas_questoes', 'idx_ajudas_criado')) {
        $pdo->exec("ALTER TABLE ajudas_questoes ADD INDEX idx_ajudas_criado (user_id, criado_em)");
    }
};
