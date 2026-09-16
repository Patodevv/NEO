<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ultimos_acessos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            conteudo_id INT NOT NULL,
            materia_id INT NOT NULL,
            acessado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_conteudo_acesso_unique (user_id, conteudo_id),
            INDEX idx_ultimos_acessos_user_data (user_id, acessado_em),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (conteudo_id) REFERENCES conteudos(id) ON DELETE CASCADE,
            FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $indices = [
        ['conteudos', 'idx_conteudos_user_materia_ordem', 'ALTER TABLE conteudos ADD INDEX idx_conteudos_user_materia_ordem (user_id, materia_id, dificuldade, ordem, id)'],
        ['historico', 'idx_historico_user_data', 'ALTER TABLE historico ADD INDEX idx_historico_user_data (user_id, data)'],
        ['compras_loja', 'idx_compras_user_data', 'ALTER TABLE compras_loja ADD INDEX idx_compras_user_data (user_id, criado_em)'],
        ['ultimos_acessos', 'idx_ultimos_acessos_user_data_id', 'ALTER TABLE ultimos_acessos ADD INDEX idx_ultimos_acessos_user_data_id (user_id, acessado_em, id)'],
    ];

    foreach ($indices as [$tabela, $indice, $sql]) {
        if (!indiceExiste($pdo, $tabela, $indice)) {
            $pdo->exec($sql);
        }
    }
};
