<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirAdmin();

$mensagem = '';
$usuarioEditar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gostos = trim($_POST['gostos'] ?? '');
    $cossas = max(0, (int)($_POST['cossas'] ?? 0));
    $xp = max(0, (int)($_POST['xp'] ?? 0));
    $nivel = max(1, (int)($_POST['nivel'] ?? 1));
    $decoracao = trim($_POST['decoracao_perfil'] ?? '');

    if ($id > 0 && $nome !== '' && $email !== '') {
        $stmt = $pdo->prepare("
            UPDATE users
            SET nome = ?, email = ?, gostos = ?, cossas = ?, xp = ?, nivel = ?, decoracao_perfil = ?
            WHERE id = ?
        ");
        $stmt->execute([$nome, $email, $gostos, $cossas, $xp, $nivel, $decoracao !== '' ? $decoracao : null, $id]);
        $mensagem = 'Conta atualizada.';
    }
}

$editarId = (int)($_GET['editar'] ?? 0);
if ($editarId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editarId]);
    $usuarioEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$usuarios = $pdo->query("
    SELECT u.*,
        (SELECT COUNT(*) FROM conteudos c WHERE c.user_id = u.id) AS total_conteudos,
        (SELECT COUNT(*) FROM historico h WHERE h.user_id = u.id) AS total_tentativas
    FROM users u
    ORDER BY u.criado_em DESC
")->fetchAll(PDO::FETCH_ASSOC);

$tituloPagina = 'Painel admin';
$cssPaginas = ['admin'];
require __DIR__ . '/includes/head.php';
?>
<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <span class="eyebrow">NEOMIND</span>
            <h1>Painel admin</h1>
        </div>
        <a href="adm_logout.php" class="ghost">Sair</a>
    </header>

    <?php if ($mensagem): ?>
        <div class="msg-ok"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <?php if ($usuarioEditar): ?>
        <section class="admin-panel">
            <h2>Editar <?= htmlspecialchars($usuarioEditar['nome']) ?></h2>
            <form method="post" class="admin-form">
                <input type="hidden" name="id" value="<?= (int)$usuarioEditar['id'] ?>">
                <label>Nome<input name="nome" value="<?= htmlspecialchars($usuarioEditar['nome']) ?>" required></label>
                <label>Email<input type="email" name="email" value="<?= htmlspecialchars($usuarioEditar['email']) ?>" required></label>
                <label>Gostos<textarea name="gostos" rows="4"><?= htmlspecialchars($usuarioEditar['gostos'] ?? '') ?></textarea></label>
                <label>Coças<input type="number" name="cossas" min="0" value="<?= (int)($usuarioEditar['cossas'] ?? 0) ?>"></label>
                <label>XP<input type="number" name="xp" min="0" value="<?= (int)($usuarioEditar['xp'] ?? 0) ?>"></label>
                <label>Nível<input type="number" name="nivel" min="1" value="<?= (int)($usuarioEditar['nivel'] ?? 1) ?>"></label>
                <label>Decoração
                    <select name="decoracao_perfil">
                        <option value="">Nenhuma</option>
                        <option value="anel_ouro" <?= ($usuarioEditar['decoracao_perfil'] ?? '') === 'anel_ouro' ? 'selected' : '' ?>>Anel dourado</option>
                        <option value="anel_neon" <?= ($usuarioEditar['decoracao_perfil'] ?? '') === 'anel_neon' ? 'selected' : '' ?>>Anel neon azul</option>
                        <option value="anel_foco" <?= ($usuarioEditar['decoracao_perfil'] ?? '') === 'anel_foco' ? 'selected' : '' ?>>Anel foco total</option>
                    </select>
                </label>
                <button type="submit" class="primary">Salvar alterações</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-panel">
        <h2>Usuários cadastrados</h2>
        <div class="admin-table">
            <?php foreach ($usuarios as $u): ?>
                <div class="admin-row">
                    <div class="admin-user">
                        <span class="profile">
                            <?php if (!empty($u['foto'])): ?>
                                <img src="<?= htmlspecialchars($u['foto']) ?>" alt="">
                            <?php else: ?>
                                <?= htmlspecialchars(strtoupper(substr($u['nome'], 0, 1))) ?>
                            <?php endif; ?>
                        </span>
                        <div>
                            <b><?= htmlspecialchars($u['nome']) ?></b>
                            <small><?= htmlspecialchars($u['email']) ?></small>
                        </div>
                    </div>
                    <span><?= saldoCossasVisual($u) ?> coças</span>
                    <span>Level <?= (int)($u['nivel'] ?? 1) ?></span>
                    <span><?= (int)$u['total_conteudos'] ?> conteúdos</span>
                    <span><?= (int)$u['total_tentativas'] ?> tentativas</span>
                    <a class="ghost" href="adm.php?editar=<?= (int)$u['id'] ?>">Editar</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>
</body>
</html>
