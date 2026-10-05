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
            aplicarItemCosmetico($pdo, (int)$usuario['id'], $produtoId);
        } elseif ($acao === 'remover') {
            removerItemCosmetico($pdo, (int)$usuario['id'], (string)($_POST['categoria'] ?? ''));
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
$ofertasLoja = array_slice($produtos, 0, min(6, count($produtos)));

function renderCardProdutoLoja(array $produto, array $usuario, array $comprados, string $classeExtra = ''): void
{
    $produtoId = (int)$produto['id'];
    $quantidadeComprada = (int)($comprados[$produtoId] ?? 0);
    $jaComprou = $quantidadeComprada > 0;
    $metadadosProdutoLoja = metadadosProduto($produto);
    $categoriaProduto = (string)$produto['categoria'];
    $equipavel = produtoEquipavel($categoriaProduto);
    $equipado = $jaComprou && produtoEquipadoPorUsuario($usuario, $produto);
    $limite = $produto['limite_por_usuario'] === null ? null : (int)$produto['limite_por_usuario'];
    $limiteAtingido = $limite !== null && $quantidadeComprada >= $limite;
    $acaoProduto = $equipado ? 'remover' : ($jaComprou && $equipavel ? 'aplicar' : 'comprar');
    $produtoDesabilitado = !$equipavel && $limiteAtingido;
    $rotuloAcao = $equipado ? 'Remover' : ($jaComprou && $equipavel ? 'Equipar' : ($produtoDesabilitado ? 'Adquirido' : 'Comprar'));
    $accentPreview = $produto['categoria'] === 'decoracao_perfil' ? '' : valorCssSeguro($metadadosProdutoLoja['accent'] ?? ($metadadosProdutoLoja['manel_color'] ?? ($metadadosProdutoLoja['name_color'] ?? '')));
    $accent2Preview = $produto['categoria'] === 'decoracao_perfil' ? '' : valorCssSeguro($metadadosProdutoLoja['accent2'] ?? '');
    $classeVarianteManel = $categoriaProduto === 'skin_manel' ? varianteManelClasse($metadadosProdutoLoja['manel_variant'] ?? '') : '';
    $stylePreview = trim(($accentPreview !== '' ? '--store-accent: ' . $accentPreview . '; --profile-accent: ' . $accentPreview . '; ' : '') . ($accent2Preview !== '' ? '--profile-accent-2: ' . $accent2Preview . '; ' : ''));
    $imagemProduto = (string)($produto['imagem'] ?? '');
    $imagemPreview = $imagemProduto;
    if (str_starts_with($imagemProduto, 'static/images/profile-frames/')) {
        $arquivoImagem = __DIR__ . '/' . $imagemProduto;
        if (is_file($arquivoImagem)) {
            $imagemPreview .= '?v=' . filemtime($arquivoImagem);
        }
    }
    ?>
    <article class="store-item neo-star-hover <?= htmlspecialchars(trim((string)($produto['classe_visual'] ?? '') . ' ' . $classeExtra)) ?>" data-store-category="<?= htmlspecialchars($produto['categoria']) ?>"<?= $stylePreview !== '' ? ' style="' . htmlspecialchars($stylePreview, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        <?= estrelaHoverNeo() ?>
        <div class="store-preview<?= $categoriaProduto === 'decoracao_perfil' ? ' store-frame-stage' : '' ?> <?= htmlspecialchars($produto['codigo'] ?? '') ?>">
            <?php if ($categoriaProduto === 'decoracao_perfil' && $imagemPreview !== ''): ?>
                <img class="store-frame-preview" src="<?= htmlspecialchars($imagemPreview) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" loading="lazy" decoding="async">
            <?php elseif (!empty($produto['imagem'])): ?>
                <img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" loading="lazy" decoding="async">
            <?php elseif ($categoriaProduto === 'tema_site'): ?>
                <div class="store-theme-demo">
                    <span></span><i></i><b></b>
                </div>
            <?php elseif ($categoriaProduto === 'skin_manel'): ?>
                <div class="store-manel-demo <?= htmlspecialchars($classeVarianteManel) ?>" aria-hidden="true">
                    <svg viewBox="0 0 300 220" focusable="false">
                        <?= decoracoesRostoManelSvg() ?>
                        <rect x="95" y="70" width="30" height="60" rx="15" ry="15"></rect>
                        <rect x="175" y="70" width="30" height="60" rx="15" ry="15"></rect>
                        <path d="M 128 147 Q 150 166 172 147"></path>
                    </svg>
                </div>
            <?php elseif ($categoriaProduto === 'cor_nome'): ?>
                <div class="store-name-demo">NEO</div>
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
                <?php if ($acaoProduto === 'remover'): ?>
                    <input type="hidden" name="categoria" value="<?= htmlspecialchars($categoriaProduto) ?>">
                <?php endif; ?>
                <?php if ($acaoProduto === 'comprar' && !$produtoDesabilitado): ?>
                    <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($_SESSION['compras_pendentes'][$produtoId]) ?>">
                <?php endif; ?>
                <button type="submit" class="<?= $produtoDesabilitado ? 'ghost' : 'primary' ?> neo-star-hover" <?= $produtoDesabilitado ? 'disabled' : '' ?>>
                    <?= estrelaHoverNeo() ?>
                    <?php if ($acaoProduto === 'remover'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6 6 18"></path></svg>
                    <?php elseif ($produtoDesabilitado || $acaoProduto === 'aplicar'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 9h14l-1 10H6L5 9Z"></path><path d="M8 9a4 4 0 0 1 8 0"></path></svg>
                    <?php endif; ?>
                    <?= $rotuloAcao ?>
                </button>
            </form>
        </div>
    </article>
    <?php
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
                <h1>Temas, skins e perfil</h1>
            </div>
        </section>

        <?php if ($ofertasLoja): ?>
            <section class="store-offers" aria-label="Ofertas">
                <div class="neo-section-heading store-offers-heading neo-panel">
                    <div>
                        <span class="neo-page-kicker">Ofertas</span>
                        <h2>Destaques da loja</h2>
                    </div>
                </div>
                <div class="store-offer-carousel" data-store-offers>
                    <div class="store-offer-track" data-store-offer-track>
                        <?php foreach ($ofertasLoja as $produtoOferta): ?>
                            <div class="store-offer-slide">
                                <?php renderCardProdutoLoja($produtoOferta, $usuario, $comprados, 'store-offer-card'); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if (count($categoriasLoja) > 1): ?>
            <nav class="store-filters" aria-label="Filtrar produtos">
                <button type="button" class="is-active neo-star-hover" data-store-filter="all" aria-pressed="true"><?= estrelaHoverNeo() ?>Todos</button>
                <?php foreach ($categoriasLoja as $categoriaCodigo => $categoriaRotulo): ?>
                    <button type="button" class="neo-star-hover" data-store-filter="<?= htmlspecialchars($categoriaCodigo) ?>" aria-pressed="false"><?= estrelaHoverNeo() ?><?= htmlspecialchars($categoriaRotulo) ?></button>
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
                <?php renderCardProdutoLoja($produto, $usuario, $comprados); ?>
            <?php endforeach; ?>
        </section>
    </div>
</main>
<script>
(function () {
    var offer = document.querySelector('[data-store-offers]');
    if (offer) {
        var track = offer.querySelector('[data-store-offer-track]');
        var slides = track ? Array.prototype.slice.call(track.children) : [];
        if (track && slides.length > 1) {
            track.appendChild(slides[0].cloneNode(true));
            var index = 0;
            var total = slides.length;
            window.setInterval(function () {
                index += 1;
                track.style.transition = 'transform .62s cubic-bezier(.22, 1, .36, 1)';
                track.style.transform = 'translateX(-' + (index * 100) + '%)';
                if (index === total) {
                    window.setTimeout(function () {
                        track.style.transition = 'none';
                        track.style.transform = 'translateX(0)';
                        index = 0;
                    }, 660);
                }
            }, 4200);
        }
    }

    var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-store-filter]'));
    if (buttons.length) {
        var items = Array.prototype.slice.call(document.querySelectorAll('[data-store-grid] [data-store-category]'));
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
    }
})();
</script>
</body>
</html>
