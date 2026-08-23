<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$salvo = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cor = trim($_POST['cor'] ?? '#0878ff');
    $gostos = trim($_POST['gostos'] ?? '');
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
        $stmt = $pdo->prepare("UPDATE users SET cor = ?, gostos = ? WHERE id = ?");
        $stmt->execute([$cor, $gostos, $usuario['id']]);
        $usuario['cor'] = $cor;
        $usuario['gostos'] = $gostos;
        $salvo = true;
    }
}
$tituloPagina = 'Configurações';
$paginaAtual  = 'config';
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div>
            <span class="eyebrow">NEOMIND • PLATAFORMA DE ESTUDOS</span>
            <h1>Configurações</h1>
        </div>
        <a href="config.php" class="profile"><?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?></a>
    </header>
    <div class="section-title">
        <span>Configurações</span>
        <small>Personalize sua experiência</small>
    </div>
    <?php if ($salvo): ?>
        <div class="msg-ok">✓ Perfil salvo com sucesso.</div>
    <?php endif; ?>
    <div class="settings-card">
        <div class="setting">
            <div>
                <b>Conta</b>
                <small><?= htmlspecialchars($usuario['nome']) ?> • <?= htmlspecialchars($usuario['email']) ?></small>
            </div>
            <a href="logout.php" class="ghost">Sair</a>
        </div>

        <div class="setting setting-stack">
            <div>
                <b>Preferencias de estudo</b>
                <small>Essas informacoes ajudam a IA a criar exemplos e questoes mais proximos de voce</small>
            </div>

            <form method="post" class="settings-form">
                <textarea name="gostos" rows="4" placeholder="Ex: gosto de jogos, futebol, musica, explicacoes passo a passo."><?= htmlspecialchars($usuario['gostos'] ?? '') ?></textarea>

                <div class="color-picker">
                    <label>Cor do site</label>
                    <input type="color" name="cor" value="<?= htmlspecialchars($usuario['cor']) ?>">
                </div>

                <button type="submit" class="save-btn">Salvar</button>
            </form>
        </div>
        <div class="setting">
            <div>
                <b>IA conectada</b>
                <small>Conteudos e questoes sao gerados automaticamente quando ainda nao existem.</small>
            </div>
            <a href="materias.php" class="ghost">Estudar</a>
        </div>
    </div>
</main>
</body>
</html>
