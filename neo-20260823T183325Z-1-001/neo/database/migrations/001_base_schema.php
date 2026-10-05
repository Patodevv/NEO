<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            senha VARCHAR(255) NOT NULL,
            gostos TEXT,
            cor VARCHAR(20) DEFAULT '#0878ff',
            foto VARCHAR(255),
            cossas INT NOT NULL DEFAULT 0,
            xp INT NOT NULL DEFAULT 0,
            nivel INT NOT NULL DEFAULT 1,
            decoracao_perfil VARCHAR(100),
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS materias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS conteudos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            materia_id INT NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            status VARCHAR(50) DEFAULT 'Não iniciado',
            corpo TEXT,
            dificuldade INT NOT NULL DEFAULT 1,
            ordem INT NOT NULL DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conteudos_user_materia (user_id, materia_id),
            UNIQUE KEY user_conteudo_unique (id, user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS questoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            conteudo_id INT NOT NULL,
            enunciado TEXT NOT NULL,
            opcao_a VARCHAR(500) NOT NULL,
            opcao_b VARCHAR(500) NOT NULL,
            opcao_c VARCHAR(500) NOT NULL,
            opcao_d VARCHAR(500) NOT NULL,
            correta CHAR(1) NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_questoes_user_conteudo (user_id, conteudo_id),
            FOREIGN KEY (conteudo_id, user_id) REFERENCES conteudos(id, user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS historico (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            conteudo_id INT NOT NULL,
            acertos INT NOT NULL,
            total INT NOT NULL,
            data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_historico_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (conteudo_id) REFERENCES conteudos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS compras_loja (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_id VARCHAR(100) NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_item_unique (user_id, item_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("INSERT IGNORE INTO materias (nome) VALUES (?)");
    foreach (['Matemática', 'Português', 'Física', 'Química', 'Biologia', 'História', 'Geografia', 'Redação'] as $materia) {
        $stmt->execute([$materia]);
    }
};

