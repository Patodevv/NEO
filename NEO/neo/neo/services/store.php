<?php

require_once __DIR__ . '/economy.php';

function produtoDisponivel(array $produto, ?DateTimeImmutable $agora = null): bool
{
    $agora = $agora ?? new DateTimeImmutable();
    if (empty($produto['ativo']) || !empty($produto['removido_em'])) {
        return false;
    }
    if (!empty($produto['disponivel_de']) && $agora < new DateTimeImmutable($produto['disponivel_de'])) {
        return false;
    }
    if (!empty($produto['disponivel_ate']) && $agora > new DateTimeImmutable($produto['disponivel_ate'])) {
        return false;
    }
    return $produto['estoque'] === null || (int)$produto['estoque'] > 0;
}

function listarProdutosDisponiveis(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT * FROM produtos
        WHERE ativo = 1
          AND removido_em IS NULL
          AND (disponivel_de IS NULL OR disponivel_de <= NOW())
          AND (disponivel_ate IS NULL OR disponivel_ate >= NOW())
          AND (estoque IS NULL OR estoque > 0)
        ORDER BY categoria, nome, id
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function comprasUsuarioPorProduto(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT produto_id, COUNT(*) quantidade
        FROM compras_loja
        WHERE user_id = ? AND status = 'concluida' AND produto_id IS NOT NULL
          AND (expira_em IS NULL OR expira_em >= NOW())
        GROUP BY produto_id
    ");
    $stmt->execute([$userId]);
    $resultado = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
        $resultado[(int)$linha['produto_id']] = (int)$linha['quantidade'];
    }
    return $resultado;
}

function comprarProduto(PDO $pdo, int $userId, int $produtoId, string $idempotencyKey): array
{
    if (!preg_match('/^[a-f0-9]{32,64}$/', $idempotencyKey)) {
        throw new DomainException('Identificador de compra invalido. Atualize a pagina e tente novamente.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM compras_loja WHERE user_id = ? AND idempotency_key = ?");
        $stmt->execute([$userId, $idempotencyKey]);
        if ($existente = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            return ['compra_id' => (int)$existente['id'], 'ja_processada' => true, 'produto_id' => (int)$existente['produto_id']];
        }

        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? FOR UPDATE");
        $stmt->execute([$produtoId]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$produto || !produtoDisponivel($produto)) {
            throw new DomainException('Este produto nao esta disponivel no momento.');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM compras_loja WHERE user_id = ? AND produto_id = ? AND status = 'concluida'");
        $stmt->execute([$userId, $produtoId]);
        $comprasAnteriores = (int)$stmt->fetchColumn();
        $limite = $produto['limite_por_usuario'] === null ? null : (int)$produto['limite_por_usuario'];
        if ($limite !== null && $comprasAnteriores >= $limite) {
            throw new DomainException('Este item ja atingiu o limite de compras da sua conta.');
        }

        $preco = (int)$produto['preco_cossas'];
        $transacao = $preco > 0
            ? alterarSaldoCossas($pdo, $userId, -$preco, 'compra_loja', 'produto', (string)$produtoId, 'compra:' . $idempotencyKey, ['codigo' => $produto['codigo']])
            : null;

        if ($produto['estoque'] !== null) {
            $stmt = $pdo->prepare("UPDATE produtos SET estoque = estoque - 1 WHERE id = ? AND estoque > 0");
            $stmt->execute([$produtoId]);
            if ($stmt->rowCount() !== 1) {
                throw new DomainException('O estoque deste produto terminou.');
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO compras_loja
                (user_id, item_id, produto_id, quantidade, preco_pago, status, idempotency_key, transacao_cossas_id, expira_em)
            VALUES (?, ?, ?, 1, ?, 'concluida', ?, ?, ?)
        ");
        $expiraEm = empty($produto['permanente']) ? ($produto['disponivel_ate'] ?: null) : null;
        $stmt->execute([$userId, $produto['codigo'], $produtoId, $preco, $idempotencyKey, $transacao['id'] ?? null, $expiraEm]);
        $compraId = (int)$pdo->lastInsertId();

        if ($produto['categoria'] === 'decoracao_perfil') {
            $pdo->prepare("UPDATE users SET decoracao_perfil = ? WHERE id = ?")->execute([$produto['codigo'], $userId]);
        }

        $pdo->commit();
        return ['compra_id' => $compraId, 'ja_processada' => false, 'produto' => $produto, 'saldo_apos' => $transacao['saldo_apos'] ?? null];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function inventarioUsuario(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT p.*, COUNT(cl.id) quantidade_comprada, MAX(cl.criado_em) comprado_em
        FROM compras_loja cl
        JOIN produtos p ON p.id = cl.produto_id
        WHERE cl.user_id = ? AND cl.status = 'concluida' AND (cl.expira_em IS NULL OR cl.expira_em >= NOW())
        GROUP BY p.id
        ORDER BY comprado_em DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aplicarDecoracaoPerfil(PDO $pdo, int $userId, int $produtoId): void
{
    $stmt = $pdo->prepare("
        SELECT p.codigo
        FROM produtos p
        JOIN compras_loja cl ON cl.produto_id = p.id
        WHERE p.id = ? AND p.categoria = 'decoracao_perfil' AND cl.user_id = ? AND cl.status = 'concluida'
          AND (cl.expira_em IS NULL OR cl.expira_em >= NOW())
        LIMIT 1
    ");
    $stmt->execute([$produtoId, $userId]);
    $codigo = $stmt->fetchColumn();
    if ($codigo === false) {
        throw new DomainException('Esse item ainda nao esta no seu inventario.');
    }
    $pdo->prepare("UPDATE users SET decoracao_perfil = ? WHERE id = ?")->execute([$codigo, $userId]);
}

function salvarImagemProduto(array $arquivo): ?string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new DomainException('Nao foi possivel receber a imagem do produto.');
    }

    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($arquivo['tmp_name']);
    if (!isset($permitidos[$mime]) || (int)$arquivo['size'] > 2 * 1024 * 1024) {
        throw new DomainException('Use uma imagem JPG, PNG, WEBP ou GIF de ate 2 MB.');
    }

    $pasta = dirname(__DIR__) . '/static/uploads/produtos';
    if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
        throw new RuntimeException('Nao foi possivel criar a pasta de produtos.');
    }
    $nome = bin2hex(random_bytes(16)) . '.' . $permitidos[$mime];
    if (!move_uploaded_file($arquivo['tmp_name'], $pasta . '/' . $nome)) {
        throw new RuntimeException('Nao foi possivel salvar a imagem do produto.');
    }
    return 'static/uploads/produtos/' . $nome;
}

function normalizarCodigoProduto(string $codigo): string
{
    $codigo = mb_strtolower(trim($codigo));
    $codigo = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $codigo) ?: $codigo;
    $codigo = preg_replace('/[^a-z0-9]+/', '_', $codigo) ?? '';
    return trim($codigo, '_');
}
