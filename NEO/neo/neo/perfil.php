<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/store.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'foto') {
    validarCsrf();
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

            $nomeArquivo = 'user_' . (int)$usuario['id'] . '_' . bin2hex(random_bytes(12)) . '.' . $permitidos[$mime];
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
    validarCsrf();
    try {
        aplicarDecoracaoPerfil($pdo, (int)$usuario['id'], (int)($_POST['produto_id'] ?? 0));
        $usuario = usuarioAtual($pdo);
        $mensagem = 'Item aplicado ao perfil.';
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    }
}

$inventario = inventarioUsuario($pdo, (int)$usuario['id']);
$stmt = $pdo->prepare("
    SELECT m.nome, pm.nivel, pm.xp_total, pm.desempenho_recente
    FROM progresso_materias pm JOIN materias m ON m.id = pm.materia_id
    WHERE pm.user_id = ? ORDER BY m.nome
");
$stmt->execute([$usuario['id']]);
$progressosMaterias = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <?= campoCsrf() ?>
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
                <?php foreach ($inventario as $item): ?>
                    <article class="inventory-item <?= htmlspecialchars($item['classe_visual'] ?? '') ?>">
                        <div class="inventory-preview"><?php if (!empty($item['imagem'])): ?><img src="<?= htmlspecialchars($item['imagem']) ?>" alt=""><?php endif; ?></div>
                        <span><?= htmlspecialchars($item['categoria']) ?></span>
                        <h2><?= htmlspecialchars($item['nome']) ?></h2>
                        <?php if ($item['categoria'] === 'decoracao_perfil'): ?>
                            <form method="post">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="acao" value="aplicar_item">
                                <input type="hidden" name="produto_id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="<?= ($usuario['decoracao_perfil'] ?? '') === $item['codigo'] ? 'ghost' : 'primary' ?>">
                                    <?= ($usuario['decoracao_perfil'] ?? '') === $item['codigo'] ? 'Em uso' : 'Aplicar' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="inventory-section">
        <div class="section-title">
            <span>Domínio por matéria</span>
            <small>EXP e desempenho independentes por conteúdo</small>
        </div>
        <?php if (!$progressosMaterias): ?>
            <div class="inventory-empty"><p>Responda atividades para iniciar sua progressão por matéria.</p></div>
        <?php else: ?>
            <div class="inventory-grid">
                <?php foreach ($progressosMaterias as $progressoMateria): ?>
                    <article class="inventory-item">
                        <span><?= htmlspecialchars($progressoMateria['nome']) ?></span>
                        <h2>Nível <?= (int)$progressoMateria['nivel'] ?></h2>
                        <p><?= (int)$progressoMateria['xp_total'] ?> EXP • <?= number_format((float)$progressoMateria['desempenho_recente'], 0, ',', '.') ?>% recente</p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
