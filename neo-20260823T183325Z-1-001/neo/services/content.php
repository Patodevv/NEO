<?php

require_once __DIR__ . '/ai_usage.php';

function arquivarConteudo(PDO $pdo, int $userId, int $materiaId, int $conteudoId): bool
{
    $lockName = nomeBloqueioOperacaoIA($userId, 'conteudo:' . $conteudoId);
    $stmtLock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $stmtLock->execute([$lockName]);
    if ((int)$stmtLock->fetchColumn() !== 1) {
        throw new DomainException('Este livro está sendo atualizado. Aguarde a conclusão antes de apagá-lo.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT id FROM conteudos
            WHERE id = ? AND materia_id = ? AND user_id = ? AND removido_em IS NULL
            FOR UPDATE
        ");
        $stmt->execute([$conteudoId, $materiaId, $userId]);

        if ($stmt->fetchColumn() === false) {
            $pdo->commit();
            return false;
        }

        $pdo->prepare('DELETE FROM ultimos_acessos WHERE conteudo_id = ? AND user_id = ?')
            ->execute([$conteudoId, $userId]);
        $pdo->prepare('DELETE FROM questoes WHERE conteudo_id = ? AND user_id = ?')
            ->execute([$conteudoId, $userId]);
        $pdo->prepare('UPDATE conteudos SET removido_em = NOW() WHERE id = ? AND user_id = ?')
            ->execute([$conteudoId, $userId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        $stmtRelease = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmtRelease->execute([$lockName]);
    }
}
