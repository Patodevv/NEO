<?php

require_once __DIR__ . '/../config/env.php';

function limiteHorarioIA(string $tipo): int
{
    return match ($tipo) {
        'conteudos' => 12,
        'livro' => 10,
        'questoes' => 12,
        'facilitador' => 30,
        'feedback' => 30,
        default => 10,
    };
}

function segundosAteDisponibilidadeIA(PDO $pdo, int $userId, string $tipo): int
{
    $limite = limiteHorarioIA($tipo);
    $stmt = $pdo->prepare("
        SELECT criado_em
        FROM requisicoes_ia
        WHERE user_id = ? AND tipo = ? AND criado_em >= NOW() - INTERVAL 1 HOUR
        ORDER BY criado_em ASC
        LIMIT 1 OFFSET " . max(0, $limite - 1)
    );
    $stmt->execute([$userId, mb_substr(trim($tipo), 0, 40)]);
    $criadoEm = $stmt->fetchColumn();
    if (!$criadoEm) {
        return 0;
    }

    $stmtTempo = $pdo->prepare('SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(?, INTERVAL 1 HOUR)))');
    $stmtTempo->execute([$criadoEm]);
    return max(0, (int)$stmtTempo->fetchColumn());
}

function provedoresConfiguradosIA(): array
{
    $groqApiKey = (string)ambienteNeo('GROQ_API_KEY', '');
    $openaiApiKey = (string)ambienteNeo('OPENAI_API_KEY', '');

    return [
        'Groq' => trim($groqApiKey) !== '',
        'OpenAI' => trim($openaiApiKey) !== '',
    ];
}

function iaTemProvedorConfigurado(): bool
{
    return in_array(true, provedoresConfiguradosIA(), true);
}

function inteiroCabecalhoRateLimitIA(mixed $valor): ?int
{
    if ($valor === null) {
        return null;
    }

    $valor = trim((string)$valor);
    if ($valor === '') {
        return null;
    }

    if (preg_match('/-?\d+/', $valor, $partes) !== 1) {
        return null;
    }

    return max(0, (int)$partes[0]);
}

function segundosCabecalhoRateLimitIA(mixed $valor): ?int
{
    if ($valor === null) {
        return null;
    }

    $valor = trim((string)$valor);
    if ($valor === '') {
        return null;
    }

    if (is_numeric($valor)) {
        return max(0, (int)ceil((float)$valor));
    }

    $total = 0.0;
    if (preg_match_all('/([0-9]+(?:\.[0-9]+)?)\s*(ms|s|m|h|d)\b/i', $valor, $partes, PREG_SET_ORDER) === 0) {
        return null;
    }

    foreach ($partes as $parte) {
        $quantidade = (float)$parte[1];
        $unidade = strtolower($parte[2]);
        $total += match ($unidade) {
            'ms' => $quantidade / 1000,
            's' => $quantidade,
            'm' => $quantidade * 60,
            'h' => $quantidade * 3600,
            'd' => $quantidade * 86400,
            default => 0,
        };
    }

    return max(0, (int)ceil($total));
}

function registrarStatusProvedorIA(
    ?PDO $pdo,
    string $provedor,
    string $modelo,
    int $httpCode,
    array $headers,
    ?string $erro = null
): void {
    if (!$pdo instanceof PDO) {
        return;
    }

    $normalizados = [];
    foreach ($headers as $nome => $valor) {
        $normalizados[strtolower(trim((string)$nome))] = trim((string)$valor);
    }

    $limiteTokens = inteiroCabecalhoRateLimitIA($normalizados['x-ratelimit-limit-tokens'] ?? null);
    $tokensRestantes = inteiroCabecalhoRateLimitIA($normalizados['x-ratelimit-remaining-tokens'] ?? null);
    $limiteRequisicoes = inteiroCabecalhoRateLimitIA($normalizados['x-ratelimit-limit-requests'] ?? null);
    $requisicoesRestantes = inteiroCabecalhoRateLimitIA($normalizados['x-ratelimit-remaining-requests'] ?? null);
    $resetTokens = segundosCabecalhoRateLimitIA($normalizados['x-ratelimit-reset-tokens'] ?? null);
    $resetRequisicoes = segundosCabecalhoRateLimitIA($normalizados['x-ratelimit-reset-requests'] ?? null);
    $retryAfter = segundosCabecalhoRateLimitIA($normalizados['retry-after'] ?? null);
    $agora = time();
    $resetTokensEm = $resetTokens !== null ? date('Y-m-d H:i:s', $agora + $resetTokens) : null;
    $resetRequisicoesEm = $resetRequisicoes !== null ? date('Y-m-d H:i:s', $agora + $resetRequisicoes) : null;
    $erro = $erro !== null ? mb_substr(trim($erro), 0, 500) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO status_provedores_ia (
                provedor,
                modelo,
                limite_tokens,
                tokens_restantes,
                limite_requisicoes,
                requisicoes_restantes,
                reset_tokens_segundos,
                reset_requisicoes_segundos,
                retry_after_segundos,
                status_http,
                ultima_falha,
                reset_tokens_em,
                reset_requisicoes_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                modelo = VALUES(modelo),
                limite_tokens = VALUES(limite_tokens),
                tokens_restantes = VALUES(tokens_restantes),
                limite_requisicoes = VALUES(limite_requisicoes),
                requisicoes_restantes = VALUES(requisicoes_restantes),
                reset_tokens_segundos = VALUES(reset_tokens_segundos),
                reset_requisicoes_segundos = VALUES(reset_requisicoes_segundos),
                retry_after_segundos = VALUES(retry_after_segundos),
                status_http = VALUES(status_http),
                ultima_falha = VALUES(ultima_falha),
                reset_tokens_em = VALUES(reset_tokens_em),
                reset_requisicoes_em = VALUES(reset_requisicoes_em),
                atualizado_em = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            mb_substr(trim($provedor), 0, 40),
            mb_substr(trim($modelo), 0, 120),
            $limiteTokens,
            $tokensRestantes,
            $limiteRequisicoes,
            $requisicoesRestantes,
            $resetTokens,
            $resetRequisicoes,
            $retryAfter,
            $httpCode,
            $erro,
            $resetTokensEm,
            $resetRequisicoesEm,
        ]);
    } catch (Throwable $e) {
        error_log('[NEO][IA][status] ' . $e->getMessage());
    }
}

function statusProvedorIA(PDO $pdo, string $provedor): ?array
{
    try {
        $stmt = $pdo->prepare("
            SELECT
                provedor,
                modelo,
                limite_tokens,
                tokens_restantes,
                limite_requisicoes,
                requisicoes_restantes,
                reset_tokens_segundos,
                reset_requisicoes_segundos,
                retry_after_segundos,
                status_http,
                ultima_falha,
                GREATEST(0, TIMESTAMPDIFF(SECOND, atualizado_em, NOW())) AS idade_segundos,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), reset_tokens_em)) AS reset_tokens_restante,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), reset_requisicoes_em)) AS reset_requisicoes_restante
            FROM status_provedores_ia
            WHERE provedor = ?
            LIMIT 1
        ");
        $stmt->execute([mb_substr(trim($provedor), 0, 40)]);
        $status = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($status) ? $status : null;
    } catch (Throwable) {
        return null;
    }
}

function melhorStatusProvedorIA(PDO $pdo): ?array
{
    $configurados = provedoresConfiguradosIA();
    foreach (['Groq', 'OpenAI'] as $provedor) {
        if (!($configurados[$provedor] ?? false)) {
            continue;
        }
        $status = statusProvedorIA($pdo, $provedor);
        if (is_array($status)) {
            return $status;
        }
    }

    return null;
}

function segundosAteRecuperacaoFalhaIA(PDO $pdo): int
{
    $status = melhorStatusProvedorIA($pdo);
    if (!is_array($status)) {
        return 0;
    }

    $httpCode = (int)($status['status_http'] ?? 0);
    $idade = (int)($status['idade_segundos'] ?? 0);
    $retryAfter = (int)($status['retry_after_segundos'] ?? 0);

    if ($retryAfter > $idade) {
        return $retryAfter - $idade;
    }

    if ($httpCode === 0 || $httpCode === 429 || $httpCode >= 500) {
        return max(0, 180 - $idade);
    }

    return 0;
}

function statusRecargaIA(PDO $pdo, int $userId): array
{
    $conteudos = segundosAteDisponibilidadeIA($pdo, $userId, 'conteudos');
    $livro = max(segundosAteDisponibilidadeIA($pdo, $userId, 'livro'), $conteudos);
    $questoes = segundosAteDisponibilidadeIA($pdo, $userId, 'questoes');
    $falha = segundosAteRecuperacaoFalhaIA($pdo);
    $statusProvedor = melhorStatusProvedorIA($pdo);
    $configurados = provedoresConfiguradosIA();
    $temProvedor = in_array(true, $configurados, true);
    $segundosProvedor = 0;
    $porcentagem = null;
    $tokensRestantes = null;
    $limiteTokens = null;
    $requisicoesRestantes = null;
    $limiteRequisicoes = null;
    $provedor = null;
    $leituraRecente = false;
    $porcentagemBase = null;
    $segundosRecargaTotal = 0;
    $segundosRecargaProvedor = 0;

    if (is_array($statusProvedor)) {
        $provedor = (string)($statusProvedor['provedor'] ?? '');
        $tokensRestantes = $statusProvedor['tokens_restantes'] !== null ? (int)$statusProvedor['tokens_restantes'] : null;
        $limiteTokens = $statusProvedor['limite_tokens'] !== null ? (int)$statusProvedor['limite_tokens'] : null;
        $requisicoesRestantes = $statusProvedor['requisicoes_restantes'] !== null ? (int)$statusProvedor['requisicoes_restantes'] : null;
        $limiteRequisicoes = $statusProvedor['limite_requisicoes'] !== null ? (int)$statusProvedor['limite_requisicoes'] : null;
        $idade = (int)($statusProvedor['idade_segundos'] ?? 999999);
        $leituraRecente = $idade <= 600;
        $resetTokensRestante = max(0, (int)($statusProvedor['reset_tokens_restante'] ?? 0));
        $resetRequisicoesRestante = max(0, (int)($statusProvedor['reset_requisicoes_restante'] ?? 0));

        if ($tokensRestantes !== null && $limiteTokens !== null && $limiteTokens > 0) {
            $tokensLidos = max(0, min($limiteTokens, $tokensRestantes));
            $porcentagemBase = max(0, min(100, (int)round($tokensLidos * 100 / $limiteTokens)));
            $tokensEstimados = $tokensLidos;
            $resetTokensTotal = max(0, (int)($statusProvedor['reset_tokens_segundos'] ?? 0));

            if ($resetTokensTotal > 0 && $tokensLidos < $limiteTokens) {
                $segundosRecargaTotal = max($segundosRecargaTotal, $resetTokensTotal);
                $progresso = max(0, min(1, $idade / $resetTokensTotal));
                $tokensEstimados = (int)round($tokensLidos + (($limiteTokens - $tokensLidos) * $progresso));
            }

            if ($resetTokensTotal > 0 && $resetTokensRestante <= 0 && $tokensLidos < $limiteTokens) {
                $tokensEstimados = $limiteTokens;
            }

            $tokensRestantes = max(0, min($limiteTokens, $tokensEstimados));
            $porcentagem = max(0, min(100, (int)round($tokensRestantes * 100 / $limiteTokens)));
        }

        $minimoTokensGeracao = 900;
        if ($statusProvedor['tokens_restantes'] !== null && (int)$statusProvedor['tokens_restantes'] < $minimoTokensGeracao && $resetTokensRestante > 0) {
            $segundosProvedor = max($segundosProvedor, $resetTokensRestante);
            $segundosRecargaProvedor = max($segundosRecargaProvedor, $resetTokensRestante);
        }

        if ($requisicoesRestantes !== null && $requisicoesRestantes <= 0 && $resetRequisicoesRestante > 0) {
            $segundosProvedor = max($segundosProvedor, $resetRequisicoesRestante);
            $segundosRecargaProvedor = max($segundosRecargaProvedor, $resetRequisicoesRestante);
            $segundosRecargaTotal = max($segundosRecargaTotal, (int)($statusProvedor['reset_requisicoes_segundos'] ?? 0));
        }
    }

    $segundos = max($livro, $questoes, $falha, $segundosProvedor);
    $estado = 'pronto';

    if (!$temProvedor) {
        $estado = 'sem_chave';
    } elseif ($falha > 0) {
        $estado = 'falha';
    } elseif ($segundos > 0) {
        $estado = 'recarga';
    } elseif (!$leituraRecente) {
        $estado = 'sem_leitura';
    }

    return [
        'segundos' => $segundos,
        'livro' => $livro,
        'conteudos' => $conteudos,
        'questoes' => $questoes,
        'falha' => $falha,
        'provedor' => $provedor,
        'provedor_configurado' => $temProvedor,
        'provedores_configurados' => $configurados,
        'leitura_recente' => $leituraRecente,
        'tokens_restantes' => $tokensRestantes,
        'limite_tokens' => $limiteTokens,
        'requisicoes_restantes' => $requisicoesRestantes,
        'limite_requisicoes' => $limiteRequisicoes,
        'porcentagem' => $porcentagem,
        'porcentagem_base' => $porcentagemBase,
        'segundos_recarga_provedor' => $segundosRecargaProvedor,
        'segundos_recarga_total' => $segundosRecargaTotal,
        'estado' => $estado,
        'pronto' => $estado === 'pronto',
    ];
}

function nomeBloqueioOperacaoIA(int $userId, string $escopo): string
{
    return 'neo_ai_' . substr(hash('sha256', $userId . ':' . $escopo), 0, 56);
}

function iniciarOperacaoIA(PDO $pdo, int $userId, string $tipo, string $contexto, ?string $escopoBloqueio = null): array
{
    $tipo = mb_substr(trim($tipo), 0, 40);
    $escopoBloqueio = $escopoBloqueio ?? $tipo . ':' . $contexto;
    $lockName = nomeBloqueioOperacaoIA($userId, $escopoBloqueio);
    $quotaLockName = nomeBloqueioOperacaoIA($userId, 'quota:' . $tipo);
    $stmtLock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $stmtLock->execute([$lockName]);

    if ((int)$stmtLock->fetchColumn() !== 1) {
        throw new DomainException('Já existe uma tarefa de IA em andamento para este conteúdo. Aguarde a conclusão.');
    }

    try {
        $stmtQuotaLock = $pdo->prepare('SELECT GET_LOCK(?, 3)');
        $stmtQuotaLock->execute([$quotaLockName]);
        if ((int)$stmtQuotaLock->fetchColumn() !== 1) {
            throw new DomainException('Não foi possível reservar esta geração agora. Tente novamente em instantes.');
        }

        $requisicaoId = 0;
        try {
            $pdo->prepare('DELETE FROM requisicoes_ia WHERE criado_em < NOW() - INTERVAL 7 DAY')->execute();
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM requisicoes_ia
                WHERE user_id = ? AND tipo = ? AND criado_em >= NOW() - INTERVAL 1 HOUR
            ");
            $stmt->execute([$userId, $tipo]);

            if ((int)$stmt->fetchColumn() >= limiteHorarioIA($tipo)) {
                throw new DomainException('O limite temporário de gerações foi atingido. Aguarde alguns minutos antes de tentar novamente.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO requisicoes_ia (user_id, tipo, contexto_hash)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $tipo, hash('sha256', $contexto)]);
            $requisicaoId = (int)$pdo->lastInsertId();
        } finally {
            $stmtQuotaRelease = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmtQuotaRelease->execute([$quotaLockName]);
        }

        return ['id' => $requisicaoId, 'lock' => $lockName];
    } catch (Throwable $e) {
        $stmtRelease = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmtRelease->execute([$lockName]);
        throw $e;
    }
}

function finalizarOperacaoIA(PDO $pdo, array $operacao, string $status): void
{
    try {
        $stmt = $pdo->prepare('UPDATE requisicoes_ia SET status = ?, finalizado_em = NOW() WHERE id = ?');
        $stmt->execute([$status, (int)$operacao['id']]);
    } finally {
        $stmtRelease = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmtRelease->execute([(string)$operacao['lock']]);
    }
}

function executarOperacaoControladaIA(
    PDO $pdo,
    int $userId,
    string $tipo,
    string $contexto,
    callable $operacao,
    ?string $escopoBloqueio = null
): mixed {
    $registro = iniciarOperacaoIA($pdo, $userId, $tipo, $contexto, $escopoBloqueio);

    try {
        $resultado = $operacao();
    } catch (Throwable $e) {
        finalizarOperacaoIA($pdo, $registro, 'falhou');
        throw $e;
    }

    finalizarOperacaoIA($pdo, $registro, 'concluida');
    return $resultado;
}

function executarComBloqueioRecurso(PDO $pdo, int $userId, string $escopo, callable $operacao): mixed
{
    $lockName = nomeBloqueioOperacaoIA($userId, $escopo);
    $stmtLock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $stmtLock->execute([$lockName]);
    if ((int)$stmtLock->fetchColumn() !== 1) {
        throw new DomainException('Este conteúdo está sendo atualizado em outra aba. Aguarde a conclusão.');
    }

    try {
        return $operacao();
    } finally {
        $stmtRelease = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmtRelease->execute([$lockName]);
    }
}
