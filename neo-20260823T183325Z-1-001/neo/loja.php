<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirLogin();
$usuario = usuarioAtual($pdo);

$itensLoja = [
    'anel_ouro' => ['nome' => 'Anel dourado', 'tipo' => 'Borda de foto', 'preco' => 180, 'classe' => 'gold'],
    'anel_neon' => ['nome' => 'Anel neon azul', 'tipo' => 'Borda de foto', 'preco' => 260, 'classe' => 'neon'],
    'anel_foco' => ['nome' => 'Anel foco total', 'tipo' => 'Borda de foto', 'preco' => 220, 'classe' => 'focus'],
];

$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = $_POST['item_id'] ?? '';
    if (isset($itensLoja[$itemId])) {
        $stmt = $pdo->prepare("SELECT id FROM compras_loja WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$usuario['id'], $itemId]);
        $jaComprou = (bool)$stmt->fetch();

        if (!$jaComprou && (int)$usuario['cossas'] < (int)$itensLoja[$itemId]['preco']) {
            $mensagem = 'Você ainda não tem coças suficientes para comprar esse item.';
        } else {
            if (!$jaComprou) {
                $stmt = $pdo->prepare("UPDATE users SET cossas = cossas - ? WHERE id = ?");
                $stmt->execute([(int)$itensLoja[$itemId]['preco'], $usuario['id']]);

                $stmt = $pdo->prepare("INSERT INTO compras_loja (user_id, item_id) VALUES (?, ?)");
                $stmt->execute([$usuario['id'], $itemId]);
            }

            $stmt = $pdo->prepare("UPDATE users SET decoracao_perfil = ? WHERE id = ?");
            $stmt->execute([$itemId, $usuario['id']]);
            $usuario = usuarioAtual($pdo);
            $mensagem = $jaComprou ? 'Decoração aplicada ao perfil.' : 'Item comprado e aplicado ao perfil.';
        }
    }
}

$stmt = $pdo->prepare("SELECT item_id FROM compras_loja WHERE user_id = ?");
$stmt->execute([$usuario['id']]);
$comprados = array_fill_keys(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'item_id'), true);

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
        <?php foreach ($itensLoja as $id => $item): ?>
            <article class="store-item <?= htmlspecialchars($item['classe']) ?>">
                <div class="item-preview"></div>
                <span><?= htmlspecialchars($item['tipo']) ?></span>
                <h2><?= htmlspecialchars($item['nome']) ?></h2>
                <div class="price-row">
                    <span class="coin"></span>
                    <b><?= (int)$item['preco'] ?></b>
                </div>
                <form method="post">
                    <input type="hidden" name="item_id" value="<?= htmlspecialchars($id) ?>">
                    <button type="submit" class="primary">
                        <?= !empty($comprados[$id]) ? 'Aplicar' : 'Comprar' ?>
                    </button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
