<?php

return static function (PDO $pdo): void {
    adicionarColunaSeAusente($pdo, 'conteudos', 'removido_em', 'DATETIME NULL AFTER criado_em');

    if (!indiceExiste($pdo, 'conteudos', 'idx_conteudos_ativos')) {
        $pdo->exec('ALTER TABLE conteudos ADD INDEX idx_conteudos_ativos (user_id, materia_id, removido_em, dificuldade, ordem)');
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS requisicoes_ia (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            tipo VARCHAR(40) NOT NULL,
            contexto_hash CHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'iniciada',
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finalizado_em DATETIME NULL,
            INDEX idx_requisicoes_ia_limite (user_id, tipo, criado_em),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tentativas_login (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            escopo VARCHAR(20) NOT NULL,
            identificador_hash CHAR(64) NOT NULL,
            ip_hash CHAR(64) NOT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_identificador (escopo, identificador_hash, criado_em),
            INDEX idx_login_ip (escopo, ip_hash, criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $progressos = $pdo->query('SELECT user_id, materia_id FROM progresso_materias')->fetchAll(PDO::FETCH_ASSOC);
    $stmtTotais = $pdo->prepare("
        SELECT COALESCE(SUM(h.acertos), 0), COALESCE(SUM(h.total), 0)
        FROM historico h
        JOIN conteudos c ON c.id = h.conteudo_id
        WHERE h.user_id = ? AND c.materia_id = ?
    ");
    $stmtRecentes = $pdo->prepare("
        SELECT COALESCE(SUM(r.acertos), 0), COALESCE(SUM(r.total), 0)
        FROM (
            SELECT h.acertos, h.total
            FROM historico h
            JOIN conteudos c ON c.id = h.conteudo_id
            WHERE h.user_id = ? AND c.materia_id = ?
            ORDER BY h.data DESC, h.id DESC
            LIMIT 5
        ) r
    ");
    $stmtAtualizar = $pdo->prepare("
        UPDATE progresso_materias
        SET acertos_total = ?, questoes_total = ?, desempenho_recente = ?
        WHERE user_id = ? AND materia_id = ?
    ");

    foreach ($progressos as $progresso) {
        $userId = (int)$progresso['user_id'];
        $materiaId = (int)$progresso['materia_id'];
        $stmtTotais->execute([$userId, $materiaId]);
        [$acertos, $total] = array_map('intval', $stmtTotais->fetch(PDO::FETCH_NUM));
        $stmtRecentes->execute([$userId, $materiaId]);
        [$acertosRecentes, $totalRecente] = array_map('intval', $stmtRecentes->fetch(PDO::FETCH_NUM));
        $desempenho = $totalRecente > 0 ? round($acertosRecentes * 100 / $totalRecente, 2) : 0;
        $stmtAtualizar->execute([$acertos, $total, $desempenho, $userId, $materiaId]);
    }
};
