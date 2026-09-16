<?php

return static function (PDO $pdo): void {
    adicionarColunaSeAusente($pdo, 'questoes', 'habilidade', 'VARCHAR(180) NULL');
    adicionarColunaSeAusente($pdo, 'questoes', 'tipo_questao', "VARCHAR(40) NOT NULL DEFAULT 'multipla_escolha'");
    adicionarColunaSeAusente($pdo, 'questoes', 'estilo_prova', "VARCHAR(40) NOT NULL DEFAULT 'geral'");

    $pdo->exec("CREATE TABLE IF NOT EXISTS neo_learning_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        conteudo_id INT NOT NULL,
        materia_id INT NOT NULL,
        questao_id INT NULL,
        event_key VARCHAR(100) NOT NULL,
        enunciado TEXT NOT NULL,
        resposta CHAR(1) NOT NULL,
        correta CHAR(1) NOT NULL,
        acertou TINYINT(1) NOT NULL,
        dificuldade TINYINT UNSIGNED NOT NULL DEFAULT 1,
        tempo_segundos INT UNSIGNED NULL,
        tentativa SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        formato VARCHAR(40) NOT NULL DEFAULT 'questoes',
        habilidade VARCHAR(180) NULL,
        tipo_questao VARCHAR(40) NOT NULL DEFAULT 'multipla_escolha',
        tipo_erro VARCHAR(40) NOT NULL DEFAULT 'nao_classificado',
        explicacao TEXT NULL,
        revisar_em DATE NULL,
        revisado_em DATETIME NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_learning_event (user_id, event_key),
        INDEX idx_learning_content (user_id, conteudo_id, criado_em, id),
        INDEX idx_learning_errors (user_id, acertou, revisar_em),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (conteudo_id, user_id) REFERENCES conteudos(id, user_id) ON DELETE CASCADE,
        FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE,
        FOREIGN KEY (questao_id) REFERENCES questoes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS neo_learning_overrides (
        user_id INT NOT NULL,
        insight_key VARCHAR(60) NOT NULL,
        valor VARCHAR(120) NOT NULL,
        atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, insight_key),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS neo_simulados (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        tipo VARCHAR(40) NOT NULL,
        filtros_json LONGTEXT NOT NULL,
        questoes_json LONGTEXT NOT NULL,
        respostas_json LONGTEXT NULL,
        acertos INT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        concluido_em DATETIME NULL,
        INDEX idx_simulados_user (user_id, criado_em),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
