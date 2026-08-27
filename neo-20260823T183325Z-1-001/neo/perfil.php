<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$mensagem = '';
$erro = '';
$itensLoja = [
    'anel_ouro' => ['nome' => 'Anel dourado', 'tipo' => 'Borda de foto', 'classe' => 'gold'],
    'anel_neon' => ['nome' => 'Anel neon azul', 'tipo' => 'Borda de foto', 'classe' => 'neon'],
    'anel_foco' => ['nome' => 'Anel foco total', 'tipo' => 'Borda de foto', 'classe' => 'focus'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'foto') {
    $arquivo = $_FILES['foto'] ?? null;
    if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
        $erro = 'Escolha uma imagem para usar no perfil.';
    } else {
        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $mime = mime_content_type($arquivo['tmp_name']);

        if (!isset($permitidos[$mime])) {
            $erro = 'Use uma imagem JPG, PNG, WEBP ou GIF.';
        } elseif ($arquivo['size'] > 2 * 1024 * 1024) {
            $erro = 'A imagem precisa ter no máximo 2 MB.';
        } else {
            $pasta = __DIR__ . '/static/uploads/perfis';
            if (!is_dir($pasta)) {
                mkdir($pasta, 0775, true);
            }

            $nomeArquivo = 'user_' . (int)$usuario['id'] . '_' . time() . '.' . $permitidos[$mime];
            $destino = $pasta . '/' . $nomeArquivo;
            if (move_uploaded_file($arquivo['tmp_name'], $destino)) {
                $caminhoPublico = 'static/uploads/perfis/' . $nomeArquivo;
                $stmt = $pdo->prepare("UPDATE users SET foto = ? WHERE id = ?");
                $stmt->execute([$caminhoPublico, $usuario['id']]);
                $usuario = usuarioAtual($pdo);
                $mensagem = 'Foto de perfil atualizada.';
            } else {
                $erro = 'Não foi possível salvar a foto agora.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'aplicar_item') {
    $itemId = $_POST['item_id'] ?? '';
    if (isset($itensLoja[$itemId])) {
        $stmt = $pdo->prepare("SELECT id FROM compras_loja WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$usuario['id'], $itemId]);

        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE users SET decoracao_perfil = ? WHERE id = ?");
            $stmt->execute([$itemId, $usuario['id']]);
            $usuario = usuarioAtual($pdo);
            $mensagem = 'Item aplicado ao perfil.';
        } else {
            $erro = 'Esse item ainda não está no seu inventário.';
        }
    }
}

$stmt = $pdo->prepare("SELECT item_id FROM compras_loja WHERE user_id = ? ORDER BY criado_em DESC");
$stmt->execute([$usuario['id']]);
$inventario = array_values(array_filter(
    array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'item_id'),
    fn($itemId) => isset($itensLoja[$itemId])
));

$xpAtual = (int)($usuario['xp'] ?? 0);
$nivel = max(1, (int)($usuario['nivel'] ?? 1));
$xpProximo = xpParaProximoNivel($nivel);
$progresso = $xpProximo > 0 ? min(100, round(($xpAtual / $xpProximo) * 100)) : 0;

$tituloPagina = 'Perfil';
$paginaAtual = 'perfil';
$usaSidebar = true;
$cssPaginas = ['perfil'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="user-heading">
            <span class="eyebrow">NEOMIND</span>
            <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
            <span class="page-title">Perfil</span>
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

    <section class="profile-layout">
        <aside class="profile-side">
            <div class="wallet-card">
                <span class="coin"></span>
                <div>
                    <b><?= saldoCossasVisual($usuario) ?></b>
                    <small>coças</small>
                </div>
            </div>
        </aside>

        <section class="profile-card <?= htmlspecialchars($usuario['decoracao_perfil'] ?? '') ?>">
            <div class="profile-avatar">
                <?php if (!empty($usuario['foto'])): ?>
                    <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data" class="photo-form">
                <input type="hidden" name="acao" value="foto">
                <label for="foto">Trocar foto de perfil</label>
                <input type="file" id="foto" name="foto" accept="image/png,image/jpeg,image/webp,image/gif" required>
                <button type="submit" class="primary">Salvar foto</button>
            </form>
            <h1><?= htmlspecialchars($usuario['nome']) ?></h1>
            <p><?= htmlspecialchars($usuario['email']) ?></p>
            <div class="level-box">
                <div>
                    <b>Level <?= $nivel ?></b>
                    <span><?= $xpAtual ?> / <?= $xpProximo ?> XP</span>
                </div>
                <div class="progress"><i style="width: <?= $progresso ?>%;"></i></div>
            </div>
            <a href="loja.php" class="ghost">Ver decorações</a>
        </section>
    </section>

    <section class="inventory-section">
        <div class="section-title">
            <span>Inventário</span>
            <small>Itens comprados com coças</small>
        </div>

        <?php if (!$inventario): ?>
            <div class="inventory-empty">
                <p>Você ainda não comprou nenhum item.</p>
                <a href="loja.php" class="ghost">Abrir loja</a>
            </div>
        <?php else: ?>
            <div class="inventory-grid">
                <?php foreach ($inventario as $itemId): ?>
                    <?php $item = $itensLoja[$itemId]; ?>
                    <article class="inventory-item <?= htmlspecialchars($item['classe']) ?>">
                        <div class="inventory-preview"></div>
                        <span><?= htmlspecialchars($item['tipo']) ?></span>
                        <h2><?= htmlspecialchars($item['nome']) ?></h2>
                        <form method="post">
                            <input type="hidden" name="acao" value="aplicar_item">
                            <input type="hidden" name="item_id" value="<?= htmlspecialchars($itemId) ?>">
                            <button type="submit" class="<?= ($usuario['decoracao_perfil'] ?? '') === $itemId ? 'ghost' : 'primary' ?>">
                                <?= ($usuario['decoracao_perfil'] ?? '') === $itemId ? 'Em uso' : 'Aplicar' ?>
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
