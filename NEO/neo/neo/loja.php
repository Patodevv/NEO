<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/store.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$mensagem = '';
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $produtoId = (int)($_POST['produto_id'] ?? 0);
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'aplicar') {
            aplicarDecoracaoPerfil($pdo, (int)$usuario['id'], $produtoId);
            $mensagem = 'Decoração aplicada ao perfil.';
        } elseif ($acao === 'comprar') {
            $token = (string)($_POST['purchase_token'] ?? '');
            $esperado = (string)($_SESSION['compras_pendentes'][$produtoId] ?? '');
            if ($esperado === '' || !hash_equals($esperado, $token)) {
                throw new DomainException('Esta solicitação de compra expirou. Atualize a página e tente novamente.');
            }
            $compra = comprarProduto($pdo, (int)$usuario['id'], $produtoId, $token);
            unset($_SESSION['compras_pendentes'][$produtoId]);
            $mensagem = !empty($compra['ja_processada']) ? 'Essa compra já havia sido processada.' : 'Item comprado e adicionado à sua conta.';
        }
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    }
    $usuario = usuarioAtual($pdo);
}

$produtos = listarProdutosDisponiveis($pdo);
$comprados = comprasUsuarioPorProduto($pdo, (int)$usuario['id']);
foreach ($produtos as $produto) {
    $produtoId = (int)$produto['id'];
    if (empty($_SESSION['compras_pendentes'][$produtoId])) {
        $_SESSION['compras_pendentes'][$produtoId] = bin2hex(random_bytes(24));
    }
}

$tituloPagina = 'Loja';
$paginaAtual = 'loja';
$usaSidebar = true;
$cssPaginas = ['loja'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="user-heading">
            <span class="eyebrow">NEOMIND</span>
            <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
            <span class="page-title">Loja</span>
        </div>
        <a href="perfil.php" class="profile">
            <?php if (!empty($usuario['foto'])): ?>
                <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?>
            <?php endif; ?>
        </a>
    </header>

    <?php if ($mensagem): ?>
        <div class="msg-ok"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <section class="store-hero">
        <div>
            <span class="shop-kicker">LOJA NEO</span>
            <h1>Itens da semana</h1>
            <p>Compre anéis de foto usando coças conquistadas ao resolver questões e subir de level.</p>
        </div>
        <div class="wallet-pill">
            <span class="coin"></span>
            <b><?= saldoCossasVisual($usuario) ?></b>
            <span>coças</span>
        </div>
    </section>

    <section class="store-grid">
        <?php foreach ($produtos as $produto): ?>
            <?php $produtoId = (int)$produto['id']; $jaComprou = !empty($comprados[$produtoId]); ?>
            <article class="store-item <?= htmlspecialchars($produto['classe_visual'] ?? '') ?>">
                <div class="item-preview"><?php if (!empty($produto['imagem'])): ?><img src="<?= htmlspecialchars($produto['imagem']) ?>" alt=""><?php endif; ?></div>
                <span><?= htmlspecialchars($produto['categoria']) ?></span>
                <h2><?= htmlspecialchars($produto['nome']) ?></h2>
                <?php if (!empty($produto['descricao'])): ?><p><?= htmlspecialchars($produto['descricao']) ?></p><?php endif; ?>
                <div class="price-row">
                    <span class="coin"></span>
                    <b><?= (int)$produto['preco_cossas'] ?></b>
                </div>
                <form method="post">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="produto_id" value="<?= $produtoId ?>">
                    <input type="hidden" name="acao" value="<?= $jaComprou && $produto['categoria'] === 'decoracao_perfil' ? 'aplicar' : 'comprar' ?>">
                    <?php if (!$jaComprou || $produto['categoria'] !== 'decoracao_perfil'): ?>
                        <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($_SESSION['compras_pendentes'][$produtoId]) ?>">
                    <?php endif; ?>
                    <button type="submit" class="primary">
                        <?= $jaComprou && $produto['categoria'] === 'decoracao_perfil' ? 'Aplicar' : 'Comprar' ?>
                    </button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
