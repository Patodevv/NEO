<?php

return static function (PDO $pdo): void {
    adicionarColunaSeAusente($pdo, 'users', 'ultimo_login_em', 'DATETIME NULL AFTER criado_em');

    adicionarColunaSeAusente($pdo, 'questoes', 'dificuldade', 'TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER correta');
    adicionarColunaSeAusente($pdo, 'questoes', 'explicacao_correta', 'TEXT NULL AFTER dificuldade');
    foreach (['a', 'b', 'c', 'd'] as $letra) {
        adicionarColunaSeAusente($pdo, 'questoes', 'feedback_' . $letra, 'TEXT NULL');
    }
    foreach ([1, 2, 3] as $nivelDica) {
        adicionarColunaSeAusente($pdo, 'questoes', 'dica_' . $nivelDica, 'TEXT NULL');
    }

    adicionarColunaSeAusente($pdo, 'historico', 'dificuldade', 'TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER total');
    adicionarColunaSeAusente($pdo, 'historico', 'exp_ganho', 'INT NOT NULL DEFAULT 0 AFTER dificuldade');
    adicionarColunaSeAusente($pdo, 'historico', 'cossas_ganhas', 'INT NOT NULL DEFAULT 0 AFTER exp_ganho');
    adicionarColunaSeAusente($pdo, 'historico', 'recompensado', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER cossas_ganhas');
    adicionarColunaSeAusente($pdo, 'historico', 'conjunto_hash', 'CHAR(64) NULL AFTER recompensado');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS produtos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(100) NOT NULL UNIQUE,
            nome VARCHAR(150) NOT NULL,
            descricao TEXT NULL,
            preco_cossas INT NOT NULL,
            imagem VARCHAR(255) NULL,
            categoria VARCHAR(80) NOT NULL DEFAULT 'decoracao_perfil',
            classe_visual VARCHAR(50) NULL,
            estoque INT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            temporario TINYINT(1) NOT NULL DEFAULT 0,
            permanente TINYINT(1) NOT NULL DEFAULT 1,
            disponivel_de DATETIME NULL,
            disponivel_ate DATETIME NULL,
            limite_por_usuario INT NULL DEFAULT 1,
            removido_em DATETIME NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_produtos_disponiveis (ativo, removido_em, disponivel_de, disponivel_ate),
            CONSTRAINT chk_produto_preco CHECK (preco_cossas >= 0),
            CONSTRAINT chk_produto_estoque CHECK (estoque IS NULL OR estoque >= 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmtProduto = $pdo->prepare("
        INSERT IGNORE INTO produtos
            (codigo, nome, descricao, preco_cossas, categoria, classe_visual, estoque, ativo, temporario, permanente, limite_por_usuario)
        VALUES (?, ?, ?, ?, 'decoracao_perfil', ?, NULL, 1, 0, 1, 1)
    ");
    foreach ([
        ['anel_ouro', 'Anel dourado', 'Borda dourada permanente para a foto do perfil.', 180, 'gold'],
        ['anel_neon', 'Anel neon azul', 'Borda neon azul permanente para a foto do perfil.', 260, 'neon'],
        ['anel_foco', 'Anel foco total', 'Borda de foco permanente para a foto do perfil.', 220, 'focus'],
    ] as $produto) {
        $stmtProduto->execute($produto);
    }

    adicionarColunaSeAusente($pdo, 'compras_loja', 'produto_id', 'INT NULL AFTER item_id');
    adicionarColunaSeAusente($pdo, 'compras_loja', 'quantidade', 'INT NOT NULL DEFAULT 1 AFTER produto_id');
    adicionarColunaSeAusente($pdo, 'compras_loja', 'preco_pago', 'INT NOT NULL DEFAULT 0 AFTER quantidade');
    adicionarColunaSeAusente($pdo, 'compras_loja', 'status', "VARCHAR(30) NOT NULL DEFAULT 'concluida' AFTER preco_pago");
    adicionarColunaSeAusente($pdo, 'compras_loja', 'idempotency_key', 'VARCHAR(120) NULL AFTER status');
    adicionarColunaSeAusente($pdo, 'compras_loja', 'transacao_cossas_id', 'BIGINT NULL AFTER idempotency_key');

    $pdo->exec("UPDATE compras_loja cl JOIN produtos p ON p.codigo = cl.item_id SET cl.produto_id = p.id WHERE cl.produto_id IS NULL");
    $pdo->exec("UPDATE compras_loja cl JOIN produtos p ON p.id = cl.produto_id SET cl.preco_pago = p.preco_cossas WHERE cl.preco_pago = 0");

    if (!indiceExiste($pdo, 'compras_loja', 'idx_compras_user_produto')) {
        $pdo->exec("ALTER TABLE compras_loja ADD INDEX idx_compras_user_produto (user_id, produto_id, status)");
    }
    if (indiceExiste($pdo, 'compras_loja', 'user_item_unique')) {
        $pdo->exec("ALTER TABLE compras_loja DROP INDEX user_item_unique");
    }
    if (!indiceExiste($pdo, 'compras_loja', 'uq_compras_idempotencia')) {
        $pdo->exec("ALTER TABLE compras_loja ADD UNIQUE KEY uq_compras_idempotencia (user_id, idempotency_key)");
    }
    if (!constraintExiste($pdo, 'compras_loja', 'fk_compras_produto')) {
        $pdo->exec("ALTER TABLE compras_loja ADD CONSTRAINT fk_compras_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transacoes_cossas (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            valor INT NOT NULL,
            saldo_apos INT NOT NULL,
            tipo VARCHAR(60) NOT NULL,
            referencia_tipo VARCHAR(60) NULL,
            referencia_id VARCHAR(120) NULL,
            idempotency_key VARCHAR(160) NOT NULL,
            metadados_json LONGTEXT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cossas_idempotencia (user_id, idempotency_key),
            INDEX idx_cossas_user_data (user_id, criado_em),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        INSERT IGNORE INTO transacoes_cossas
            (user_id, valor, saldo_apos, tipo, referencia_tipo, referencia_id, idempotency_key)
        SELECT id, cossas, cossas, 'saldo_inicial', 'migracao', 'neo_v2', CONCAT('saldo-inicial:', id)
        FROM users
    ");

    if (!constraintExiste($pdo, 'compras_loja', 'fk_compras_transacao_cossas')) {
        $pdo->exec("ALTER TABLE compras_loja ADD CONSTRAINT fk_compras_transacao_cossas FOREIGN KEY (transacao_cossas_id) REFERENCES transacoes_cossas(id) ON DELETE SET NULL");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS progresso_materias (
            user_id INT NOT NULL,
            materia_id INT NOT NULL,
            xp_total INT NOT NULL DEFAULT 0,
            nivel INT NOT NULL DEFAULT 1,
            acertos_total INT NOT NULL DEFAULT 0,
            questoes_total INT NOT NULL DEFAULT 0,
            desempenho_recente DECIMAL(5,2) NOT NULL DEFAULT 0,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, materia_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        INSERT IGNORE INTO progresso_materias
            (user_id, materia_id, acertos_total, questoes_total, desempenho_recente)
        SELECT h.user_id, c.materia_id, SUM(h.acertos), SUM(h.total),
               CASE WHEN SUM(h.total) > 0 THEN ROUND(SUM(h.acertos) * 100 / SUM(h.total), 2) ELSE 0 END
        FROM historico h
        JOIN conteudos c ON c.id = h.conteudo_id
        GROUP BY h.user_id, c.materia_id
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transacoes_exp (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            materia_id INT NOT NULL,
            valor INT NOT NULL,
            xp_total_apos INT NOT NULL,
            nivel_apos INT NOT NULL,
            tipo VARCHAR(60) NOT NULL,
            referencia_tipo VARCHAR(60) NULL,
            referencia_id VARCHAR(120) NULL,
            idempotency_key VARCHAR(160) NOT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_exp_idempotencia (user_id, materia_id, idempotency_key),
            INDEX idx_exp_user_materia_data (user_id, materia_id, criado_em),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS recompensas_atividades (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            conteudo_id INT NOT NULL,
            conjunto_hash CHAR(64) NOT NULL,
            historico_id INT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_recompensa_conjunto (user_id, conteudo_id, conjunto_hash),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (conteudo_id) REFERENCES conteudos(id) ON DELETE CASCADE,
            FOREIGN KEY (historico_id) REFERENCES historico(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS respostas_historico (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            historico_id INT NOT NULL,
            questao_id INT NULL,
            enunciado_snapshot TEXT NOT NULL,
            resposta_usuario CHAR(1) NOT NULL,
            resposta_correta CHAR(1) NOT NULL,
            acertou TINYINT(1) NOT NULL,
            feedback TEXT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_respostas_historico (historico_id),
            FOREIGN KEY (historico_id) REFERENCES historico(id) ON DELETE CASCADE,
            FOREIGN KEY (questao_id) REFERENCES questoes(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ajudas_questoes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            questao_id INT NOT NULL,
            nivel TINYINT UNSIGNED NOT NULL,
            custo_cossas INT NOT NULL DEFAULT 0,
            dica TEXT NOT NULL,
            transacao_cossas_id BIGINT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ajuda_nivel (user_id, questao_id, nivel),
            INDEX idx_ajudas_user_questao (user_id, questao_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (questao_id) REFERENCES questoes(id) ON DELETE CASCADE,
            FOREIGN KEY (transacao_cossas_id) REFERENCES transacoes_cossas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS atividades_estudo_diarias (
            user_id INT NOT NULL,
            data_atividade DATE NOT NULL,
            materia_id INT NOT NULL,
            atividade_tipo VARCHAR(50) NOT NULL,
            referencia_id VARCHAR(120) NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, data_atividade),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS progresso_ofensivas (
            user_id INT PRIMARY KEY,
            semanas_atuais INT NOT NULL DEFAULT 0,
            melhor_sequencia INT NOT NULL DEFAULT 0,
            ultima_semana_concluida DATE NULL,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ofensivas_semanais (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            semana_inicio DATE NOT NULL,
            dias_ativos TINYINT UNSIGNED NOT NULL DEFAULT 0,
            concluida_em DATETIME NULL,
            numero_sequencia INT NULL,
            recompensa_cossas INT NOT NULL DEFAULT 0,
            transacao_cossas_id BIGINT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ofensiva_semana (user_id, semana_inicio),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (transacao_cossas_id) REFERENCES transacoes_cossas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS auditoria_ia (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            tipo VARCHAR(50) NOT NULL,
            contexto_hash CHAR(64) NOT NULL,
            status VARCHAR(30) NOT NULL,
            tentativas TINYINT UNSIGNED NOT NULL DEFAULT 1,
            problemas_json LONGTEXT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_auditoria_ia_tipo_data (tipo, criado_em),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
};
