<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/materia_icon.php';
require_once __DIR__ . '/services/store.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $produtoId = (int)($_POST['produto_id'] ?? 0);
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'aplicar') {
            aplicarDecoracaoPerfil($pdo, (int)$usuario['id'], $produtoId);
        } elseif ($acao === 'comprar') {
            $token = (string)($_POST['purchase_token'] ?? '');
            $esperado = (string)($_SESSION['compras_pendentes'][$produtoId] ?? '');
            if ($esperado === '' || !hash_equals($esperado, $token)) {
                throw new DomainException('Esta solicitação de compra expirou. Atualize a página e tente novamente.');
            }
            $compra = comprarProduto($pdo, (int)$usuario['id'], $produtoId, $token);
            unset($_SESSION['compras_pendentes'][$produtoId]);
        }
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    }
    $usuario = usuarioAtual($pdo);
}

$produtos = listarProdutosDisponiveis($pdo);
$comprados = comprasUsuarioPorProduto($pdo, (int)$usuario['id']);
$categoriasLoja = [];
foreach ($produtos as $produto) {
    $produtoId = (int)$produto['id'];
    $categoriasLoja[$produto['categoria']] = rotuloCategoriaProduto($produto['categoria']);
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
    <?php require __DIR__ . '/includes/topbar.php'; ?>

    <div class="neo-page-shell store-page">
        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <section class="neo-page-heading neo-panel store-heading">
            <div class="neo-page-heading-copy">
                <span class="neo-page-kicker">Loja NEO</span>
                <h1>Cosméticos do perfil</h1>
            </div>
        </section>

        <?php if (count($categoriasLoja) > 1): ?>
            <nav class="store-filters" aria-label="Filtrar produtos">
                <button type="button" class="is-active" data-store-filter="all" aria-pressed="true">Todos</button>
                <?php foreach ($categoriasLoja as $categoriaCodigo => $categoriaRotulo): ?>
                    <button type="button" data-store-filter="<?= htmlspecialchars($categoriaCodigo) ?>" aria-pressed="false"><?= htmlspecialchars($categoriaRotulo) ?></button>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <section class="store-grid" data-store-grid aria-label="Produtos disponíveis">
            <?php if (!$produtos): ?>
                <div class="neo-empty-state neo-panel">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 9h14l-1 10H6L5 9Z"></path><path d="M8 9a4 4 0 0 1 8 0"></path></svg>
                    <b>Nenhum item disponível</b>
                </div>
            <?php endif; ?>

            <?php foreach ($produtos as $produto): ?>
                <?php
                $produtoId = (int)$produto['id'];
                $quantidadeComprada = (int)($comprados[$produtoId] ?? 0);
                $jaComprou = $quantidadeComprada > 0;
                $ehDecoracao = $produto['categoria'] === 'decoracao_perfil';
                $equipado = $ehDecoracao && $jaComprou && ($usuario['decoracao_perfil'] ?? '') === $produto['codigo'];
                $limite = $produto['limite_por_usuario'] === null ? null : (int)$produto['limite_por_usuario'];
                $limiteAtingido = $limite !== null && $quantidadeComprada >= $limite;
                $acaoProduto = $jaComprou && $ehDecoracao ? 'aplicar' : 'comprar';
                $produtoDesabilitado = $equipado || (!$ehDecoracao && $limiteAtingido);
                $rotuloAcao = $equipado ? 'Equipado' : ($jaComprou && $ehDecoracao ? 'Equipar' : ($produtoDesabilitado ? 'Adquirido' : 'Comprar'));
                ?>
                <article class="store-item neo-star-hover <?= htmlspecialchars($produto['classe_visual'] ?? '') ?>" data-store-category="<?= htmlspecialchars($produto['categoria']) ?>">
                    <?= estrelaHoverNeo() ?>
                    <div class="store-preview <?= htmlspecialchars($produto['codigo'] ?? '') ?>">
                        <?php if (!empty($produto['imagem'])): ?>
                            <img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <div class="store-avatar-demo">
                                <?php if (!empty($usuario['foto'])): ?>
                                    <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span><?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="store-item-copy">
                        <span><?= htmlspecialchars(rotuloCategoriaProduto($produto['categoria'])) ?></span>
                        <h2><?= htmlspecialchars($produto['nome']) ?></h2>
                    </div>

                    <div class="store-item-footer">
                        <span class="store-price"><span class="coin"></span><b><?= (int)$produto['preco_cossas'] ?></b></span>
                        <form method="post">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="produto_id" value="<?= $produtoId ?>">
                            <input type="hidden" name="acao" value="<?= $acaoProduto ?>">
                            <?php if ($acaoProduto === 'comprar' && !$produtoDesabilitado): ?>
                                <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($_SESSION['compras_pendentes'][$produtoId]) ?>">
                            <?php endif; ?>
                            <button type="submit" class="<?= $produtoDesabilitado ? 'ghost' : 'primary' ?>" <?= $produtoDesabilitado ? 'disabled' : '' ?>>
                                <?php if ($produtoDesabilitado || $acaoProduto === 'aplicar'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 9h14l-1 10H6L5 9Z"></path><path d="M8 9a4 4 0 0 1 8 0"></path></svg>
                                <?php endif; ?>
                                <?= $rotuloAcao ?>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</main>
<?php if (count($categoriasLoja) > 1): ?>
<script>
(function () {
    var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-store-filter]'));
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-store-category]'));
    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var filter = button.dataset.storeFilter;
            buttons.forEach(function (item) {
                var active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            items.forEach(function (item) {
                item.hidden = filter !== 'all' && item.dataset.storeCategory !== filter;
            });
        });
    });
})();
</script>
<?php endif; ?>
</body>
</html>
