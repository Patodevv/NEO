<?php

require_once __DIR__ . '/economy.php';

const NEO_DIAS_PARA_OFENSIVA_SEMANAL = 3;
const NEO_LIMITE_RECOMPENSAS_CONTEUDO_DIA = 3;

function xpParaProximoNivel(int $nivel): int
{
    return max(100, $nivel * 100);
}

function estadoNivelPorXpTotal(int $xpTotal): array
{
    $nivel = 1;
    $restante = max(0, $xpTotal);

    while ($restante >= xpParaProximoNivel($nivel) && $nivel < 100) {
        $restante -= xpParaProximoNivel($nivel);
        $nivel++;
    }

    return [
        'nivel' => $nivel,
        'xp_no_nivel' => $restante,
        'xp_proximo' => xpParaProximoNivel($nivel),
    ];
}

function progressoMateria(PDO $pdo, int $userId, int $materiaId): array
{
    $stmt = $pdo->prepare("SELECT * FROM progresso_materias WHERE user_id = ? AND materia_id = ?");
    $stmt->execute([$userId, $materiaId]);
    $progresso = $stmt->fetch(PDO::FETCH_ASSOC);

    return $progresso ?: [
        'user_id' => $userId,
        'materia_id' => $materiaId,
        'xp_total' => 0,
        'nivel' => 1,
        'acertos_total' => 0,
        'questoes_total' => 0,
        'desempenho_recente' => 0,
    ];
}

function dificuldadeAdaptativa(PDO $pdo, int $userId, int $materiaId): int
{
    $progresso = progressoMateria($pdo, $userId, $materiaId);
    $nivel = max(1, (int)$progresso['nivel']);

    $stmt = $pdo->prepare("
        SELECT acertos, total
        FROM historico h
        JOIN conteudos c ON c.id = h.conteudo_id
        WHERE h.user_id = ? AND c.materia_id = ?
        ORDER BY h.data DESC, h.id DESC
        LIMIT 5
    ");
    $stmt->execute([$userId, $materiaId]);
    $recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $acertos = array_sum(array_column($recentes, 'acertos'));
    $total = array_sum(array_column($recentes, 'total'));
    $taxa = $total > 0 ? $acertos / $total : 0.65;

    $ajuste = $taxa >= 0.85 ? 2 : ($taxa >= 0.72 ? 1 : ($taxa < 0.45 ? -1 : 0));
    return max(1, min(12, $nivel + $ajuste));
}

function adicionarExpMateria(
    PDO $pdo,
    int $userId,
    int $materiaId,
    int $valor,
    string $tipo,
    string $referenciaTipo,
    string $referenciaId,
    string $idempotencyKey
): array {
    if ($valor <= 0 || !$pdo->inTransaction()) {
        throw new InvalidArgumentException('EXP deve ser positivo e registrado dentro de uma transacao.');
    }

    $stmt = $pdo->prepare("SELECT * FROM transacoes_exp WHERE user_id = ? AND materia_id = ? AND idempotency_key = ?");
    $stmt->execute([$userId, $materiaId, $idempotencyKey]);
    if ($existente = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return $existente + ['idempotente' => true, 'subiu_nivel' => false];
    }

    $pdo->prepare("INSERT IGNORE INTO progresso_materias (user_id, materia_id) VALUES (?, ?)")->execute([$userId, $materiaId]);
    $stmt = $pdo->prepare("SELECT xp_total, nivel FROM progresso_materias WHERE user_id = ? AND materia_id = ? FOR UPDATE");
    $stmt->execute([$userId, $materiaId]);
    $atual = $stmt->fetch(PDO::FETCH_ASSOC);
    $xpTotal = (int)$atual['xp_total'] + $valor;
    $estado = estadoNivelPorXpTotal($xpTotal);
    $subiuMateria = $estado['nivel'] > (int)$atual['nivel'];

    $pdo->prepare("UPDATE progresso_materias SET xp_total = ?, nivel = ? WHERE user_id = ? AND materia_id = ?")
        ->execute([$xpTotal, $estado['nivel'], $userId, $materiaId]);

    $stmt = $pdo->prepare("
        INSERT INTO transacoes_exp
            (user_id, materia_id, valor, xp_total_apos, nivel_apos, tipo, referencia_tipo, referencia_id, idempotency_key)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $materiaId, $valor, $xpTotal, $estado['nivel'], $tipo, $referenciaTipo, $referenciaId, $idempotencyKey]);

    $stmt = $pdo->prepare("SELECT xp, nivel FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $global = $stmt->fetch(PDO::FETCH_ASSOC);
    $xpGlobal = max(0, (int)$global['xp']) + $valor;
    $nivelGlobal = max(1, (int)$global['nivel']);
    $nivelAnterior = $nivelGlobal;

    while ($xpGlobal >= xpParaProximoNivel($nivelGlobal) && $nivelGlobal < 100) {
        $xpGlobal -= xpParaProximoNivel($nivelGlobal);
        $nivelGlobal++;
    }

    $pdo->prepare("UPDATE users SET xp = ?, nivel = ? WHERE id = ?")->execute([$xpGlobal, $nivelGlobal, $userId]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'valor' => $valor,
        'xp_total_apos' => $xpTotal,
        'nivel_apos' => $estado['nivel'],
        'subiu_nivel_materia' => $subiuMateria,
        'nivel_global' => $nivelGlobal,
        'subiu_nivel' => $nivelGlobal > $nivelAnterior,
        'niveis_globais_ganhos' => $nivelGlobal - $nivelAnterior,
        'idempotente' => false,
    ];
}

function inicioSemanaNeo(?DateTimeImmutable $data = null): DateTimeImmutable
{
    $data = $data ?? new DateTimeImmutable('today');
    return $data->modify('monday this week')->setTime(0, 0);
}

function registrarAtividadeSemanal(PDO $pdo, int $userId, int $materiaId, string $referenciaId): array
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('A ofensiva deve ser atualizada dentro da transacao da atividade.');
    }

    $hoje = new DateTimeImmutable('today');
    $semana = inicioSemanaNeo($hoje);
    $semanaTexto = $semana->format('Y-m-d');

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO atividades_estudo_diarias
            (user_id, data_atividade, materia_id, atividade_tipo, referencia_id)
        VALUES (?, ?, ?, 'questoes', ?)
    ");
    $stmt->execute([$userId, $hoje->format('Y-m-d'), $materiaId, $referenciaId]);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM atividades_estudo_diarias
        WHERE user_id = ? AND data_atividade BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $semanaTexto, $semana->modify('+6 days')->format('Y-m-d')]);
    $diasAtivos = (int)$stmt->fetchColumn();

    $pdo->prepare("INSERT IGNORE INTO ofensivas_semanais (user_id, semana_inicio) VALUES (?, ?)")
        ->execute([$userId, $semanaTexto]);
    $pdo->prepare("UPDATE ofensivas_semanais SET dias_ativos = ? WHERE user_id = ? AND semana_inicio = ?")
        ->execute([$diasAtivos, $userId, $semanaTexto]);

    $stmt = $pdo->prepare("SELECT * FROM ofensivas_semanais WHERE user_id = ? AND semana_inicio = ? FOR UPDATE");
    $stmt->execute([$userId, $semanaTexto]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    $resultado = ['dias_ativos' => $diasAtivos, 'concluida' => !empty($registro['concluida_em']), 'recompensa' => 0, 'sequencia' => (int)($registro['numero_sequencia'] ?? 0)];
    if ($diasAtivos < NEO_DIAS_PARA_OFENSIVA_SEMANAL || !empty($registro['concluida_em'])) {
        return $resultado;
    }

    $pdo->prepare("INSERT IGNORE INTO progresso_ofensivas (user_id) VALUES (?)")->execute([$userId]);
    $stmt = $pdo->prepare("SELECT * FROM progresso_ofensivas WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $progresso = $stmt->fetch(PDO::FETCH_ASSOC);
    $semanaAnterior = $semana->modify('-7 days')->format('Y-m-d');
    $sequencia = ($progresso['ultima_semana_concluida'] ?? null) === $semanaAnterior
        ? (int)$progresso['semanas_atuais'] + 1
        : 1;
    $melhor = max((int)$progresso['melhor_sequencia'], $sequencia);
    $recompensa = min(200, 50 * $sequencia);

    $transacao = alterarSaldoCossas(
        $pdo, $userId, $recompensa, 'ofensiva_semanal', 'semana', $semanaTexto,
        "ofensiva:{$semanaTexto}", ['sequencia' => $sequencia, 'dias_ativos' => $diasAtivos]
    );

    $pdo->prepare("
        UPDATE progresso_ofensivas
        SET semanas_atuais = ?, melhor_sequencia = ?, ultima_semana_concluida = ?
        WHERE user_id = ?
    ")->execute([$sequencia, $melhor, $semanaTexto, $userId]);
    $pdo->prepare("
        UPDATE ofensivas_semanais
        SET concluida_em = NOW(), numero_sequencia = ?, recompensa_cossas = ?, transacao_cossas_id = ?
        WHERE user_id = ? AND semana_inicio = ?
    ")->execute([$sequencia, $recompensa, $transacao['id'], $userId, $semanaTexto]);

    return ['dias_ativos' => $diasAtivos, 'concluida' => true, 'recompensa' => $recompensa, 'sequencia' => $sequencia];
}

function registrarResultadoAtividade(
    PDO $pdo,
    int $userId,
    array $conteudo,
    array $questoes,
    array $respostas,
    array $feedbacks
): array {
    if (!$questoes) {
        throw new InvalidArgumentException('A atividade nao possui questoes.');
    }

    $ids = [];
    $acertos = 0;
    foreach ($questoes as $questao) {
        $id = (int)$questao['id'];
        $resposta = strtoupper(trim((string)($respostas[$id] ?? '')));
        if (!in_array($resposta, ['A', 'B', 'C', 'D'], true)) {
            throw new DomainException('Responda todas as questoes antes de enviar.');
        }
        $ids[] = $id . ':' . strtoupper((string)$questao['correta']);
        if ($resposta === strtoupper((string)$questao['correta'])) {
            $acertos++;
        }
    }

    sort($ids, SORT_STRING);
    $conjuntoHash = hash('sha256', implode('|', $ids));
    $total = count($questoes);
    $dificuldade = max(1, min(12, (int)($conteudo['dificuldade_adaptativa'] ?? $conteudo['dificuldade'] ?? 1)));
    $materiaId = (int)$conteudo['materia_id'];
    $conteudoId = (int)$conteudo['id'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() === false) {
            throw new DomainException('Usuario nao encontrado.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO historico (user_id, conteudo_id, acertos, total, dificuldade, conjunto_hash)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $conteudoId, $acertos, $total, $dificuldade, $conjuntoHash]);
        $historicoId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO respostas_historico
                (historico_id, questao_id, enunciado_snapshot, resposta_usuario, resposta_correta, acertou, feedback)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($questoes as $questao) {
            $id = (int)$questao['id'];
            $resposta = strtoupper((string)$respostas[$id]);
            $correta = strtoupper((string)$questao['correta']);
            $stmt->execute([$historicoId, $id, $questao['enunciado'], $resposta, $correta, $resposta === $correta ? 1 : 0, $feedbacks[$id] ?? null]);
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM recompensas_atividades
            WHERE user_id = ? AND conteudo_id = ? AND criado_em >= CURDATE() AND criado_em < CURDATE() + INTERVAL 1 DAY
        ");
        $stmt->execute([$userId, $conteudoId]);
        $limiteDiarioAtingido = (int)$stmt->fetchColumn() >= NEO_LIMITE_RECOMPENSAS_CONTEUDO_DIA;
        $recompensado = false;

        if (!$limiteDiarioAtingido) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO recompensas_atividades (user_id, conteudo_id, conjunto_hash, historico_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $conteudoId, $conjuntoHash, $historicoId]);
            $recompensado = $stmt->rowCount() === 1;
        }
        $xpGanho = 0;
        $cossasGanhas = 0;
        $exp = ['nivel_apos' => (int)(progressoMateria($pdo, $userId, $materiaId)['nivel'] ?? 1), 'nivel_global' => 1, 'subiu_nivel' => false, 'subiu_nivel_materia' => false];

        if ($recompensado) {
            $xpGanho = 10 + ($acertos * (10 + 2 * $dificuldade)) + ($acertos === $total ? 20 : 0);
            $cossasGanhas = 5 + ($acertos * (3 + min(6, $dificuldade))) + ($acertos === $total ? 15 : 0);
            $exp = adicionarExpMateria($pdo, $userId, $materiaId, $xpGanho, 'atividade_questoes', 'historico', (string)$historicoId, "atividade:{$conteudoId}:{$conjuntoHash}");
            $cossasGanhas += 50 * (int)($exp['niveis_globais_ganhos'] ?? 0);
            alterarSaldoCossas($pdo, $userId, $cossasGanhas, 'atividade_questoes', 'historico', (string)$historicoId, "atividade:{$conteudoId}:{$conjuntoHash}", ['acertos' => $acertos, 'total' => $total, 'dificuldade' => $dificuldade]);
        }

        $pdo->prepare("INSERT IGNORE INTO progresso_materias (user_id, materia_id) VALUES (?, ?)")->execute([$userId, $materiaId]);
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(h.acertos), 0) acertos, COALESCE(SUM(h.total), 0) total
            FROM (SELECT h2.acertos, h2.total FROM historico h2 JOIN conteudos c2 ON c2.id = h2.conteudo_id WHERE h2.user_id = ? AND c2.materia_id = ? ORDER BY h2.data DESC, h2.id DESC LIMIT 5) h
        ");
        $stmt->execute([$userId, $materiaId]);
        $recente = $stmt->fetch(PDO::FETCH_ASSOC);
        $desempenho = (int)$recente['total'] > 0 ? ((int)$recente['acertos'] / (int)$recente['total']) * 100 : 0;
        $pdo->prepare("
            UPDATE progresso_materias
            SET acertos_total = acertos_total + ?, questoes_total = questoes_total + ?, desempenho_recente = ?
            WHERE user_id = ? AND materia_id = ?
        ")->execute([$acertos, $total, round($desempenho, 2), $userId, $materiaId]);

        $ofensiva = registrarAtividadeSemanal($pdo, $userId, $materiaId, (string)$historicoId);
        $percentual = $total > 0 ? ($acertos / $total) * 100 : 0;
        $novoStatus = $percentual >= 60 ? 'Concluído' : 'Em andamento';
        $pdo->prepare("
            UPDATE conteudos
            SET status = CASE WHEN status = 'Concluído' THEN status ELSE ? END
            WHERE id = ? AND user_id = ?
        ")->execute([$novoStatus, $conteudoId, $userId]);
        $pdo->prepare("UPDATE historico SET exp_ganho = ?, cossas_ganhas = ?, recompensado = ? WHERE id = ?")
            ->execute([$xpGanho, $cossasGanhas, $recompensado ? 1 : 0, $historicoId]);
        $pdo->commit();

        return [
            'historico_id' => $historicoId, 'acertos' => $acertos, 'total' => $total,
            'recompensado' => $recompensado, 'xp' => $xpGanho, 'cossas' => $cossasGanhas,
            'motivo_sem_recompensa' => $recompensado ? null : ($limiteDiarioAtingido ? 'limite_diario' : 'lista_repetida'),
            'nivel' => (int)($exp['nivel_global'] ?? 1), 'nivel_materia' => (int)($exp['nivel_apos'] ?? 1),
            'subiu_nivel' => !empty($exp['subiu_nivel']), 'subiu_nivel_materia' => !empty($exp['subiu_nivel_materia']),
            'ofensiva' => $ofensiva,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function registrarAjudaQuestao(PDO $pdo, int $userId, int $questaoId, int $nivel, string $dica): array
{
    $nivel = max(1, min(3, $nivel));
    $custos = [1 => 0, 2 => 25, 3 => 40];
    $custo = $custos[$nivel];
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT * FROM ajudas_questoes WHERE user_id = ? AND questao_id = ? AND nivel = ?");
        $stmt->execute([$userId, $questaoId, $nivel]);
        if ($existente = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            return $existente + ['ja_existia' => true];
        }

        $transacaoId = null;
        if ($custo > 0) {
            $transacao = alterarSaldoCossas($pdo, $userId, -$custo, 'facilitador', 'questao', (string)$questaoId, "facilitador:{$questaoId}:{$nivel}");
            $transacaoId = (int)$transacao['id'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO ajudas_questoes (user_id, questao_id, nivel, custo_cossas, dica, transacao_cossas_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $questaoId, $nivel, $custo, $dica, $transacaoId]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        return ['id' => $id, 'nivel' => $nivel, 'custo_cossas' => $custo, 'dica' => $dica, 'ja_existia' => false];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

