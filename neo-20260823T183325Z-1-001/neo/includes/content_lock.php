<?php

function hashQuestoesAtuais(array $questoes): ?string
{
    if (!$questoes) {
        return null;
    }

    $ids = [];
    foreach ($questoes as $questao) {
        $id = (int)($questao['id'] ?? 0);
        $correta = strtoupper(trim((string)($questao['correta'] ?? '')));
        if ($id <= 0 || $correta === '') {
            return null;
        }
        $ids[] = $id . ':' . $correta;
    }

    sort($ids, SORT_STRING);
    return hash('sha256', implode('|', $ids));
}

function atividadeAtualConcluida(PDO $pdo, int $userId, int $conteudoId, array $questoes): bool
{
    $hash = hashQuestoesAtuais($questoes);
    if ($hash === null) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM historico
        WHERE user_id = ?
          AND conteudo_id = ?
          AND conjunto_hash = ?
    ");
    $stmt->execute([$userId, $conteudoId, $hash]);

    return (int)$stmt->fetchColumn() > 0;
}
