<?php

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
