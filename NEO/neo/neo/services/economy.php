<?php

function buscarTransacaoCossasPorChave(PDO $pdo, int $userId, string $chave): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM transacoes_cossas WHERE user_id = ? AND idempotency_key = ?");
    $stmt->execute([$userId, $chave]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function alterarSaldoCossas(
    PDO $pdo,
    int $userId,
    int $valor,
    string $tipo,
    string $referenciaTipo,
    string $referenciaId,
    string $idempotencyKey,
    array $metadados = []
): array {
    if ($valor === 0) {
        throw new InvalidArgumentException('Uma transacao de Cocas nao pode ter valor zero.');
    }

    $iniciouTransacao = !$pdo->inTransaction();
    if ($iniciouTransacao) {
        $pdo->beginTransaction();
    }

    try {
        $existente = buscarTransacaoCossasPorChave($pdo, $userId, $idempotencyKey);
        if ($existente) {
            if ($iniciouTransacao) {
                $pdo->commit();
            }
            return $existente + ['idempotente' => true];
        }

        $stmt = $pdo->prepare("SELECT cossas FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $saldoAtual = $stmt->fetchColumn();
        if ($saldoAtual === false) {
            throw new DomainException('Usuario nao encontrado.');
        }

        $existente = buscarTransacaoCossasPorChave($pdo, $userId, $idempotencyKey);
        if ($existente) {
            if ($iniciouTransacao) {
                $pdo->commit();
            }
            return $existente + ['idempotente' => true];
        }

        $novoSaldo = (int)$saldoAtual + $valor;
        if ($novoSaldo < 0) {
            throw new DomainException('Saldo de coças insuficiente.');
        }

        $pdo->prepare("UPDATE users SET cossas = ? WHERE id = ?")->execute([$novoSaldo, $userId]);
        $stmt = $pdo->prepare("
            INSERT INTO transacoes_cossas
                (user_id, valor, saldo_apos, tipo, referencia_tipo, referencia_id, idempotency_key, metadados_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $valor, $novoSaldo, $tipo, $referenciaTipo, $referenciaId, $idempotencyKey,
            $metadados ? json_encode($metadados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);

        $transacao = [
            'id' => (int)$pdo->lastInsertId(), 'user_id' => $userId, 'valor' => $valor,
            'saldo_apos' => $novoSaldo, 'tipo' => $tipo, 'idempotency_key' => $idempotencyKey,
            'idempotente' => false,
        ];

        if ($iniciouTransacao) {
            $pdo->commit();
        }
        return $transacao;
    } catch (Throwable $e) {
        if ($iniciouTransacao && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
