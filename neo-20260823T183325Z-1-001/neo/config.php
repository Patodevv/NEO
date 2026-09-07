<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirLogin();

$usuario = usuarioAtual($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $gostos = mb_substr(trim($_POST['gostos'] ?? ''), 0, 2000);
    $preferenciasJson = json_encode(['texto' => $gostos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = $pdo->prepare("UPDATE users SET gostos = ?, preferencias_json = ? WHERE id = ?");
    $stmt->execute([$gostos, $preferenciasJson, $usuario['id']]);
    $usuario['gostos'] = $gostos;
    $usuario['preferencias_json'] = $preferenciasJson;
}

$tituloPagina = 'Configurações';
$paginaAtual = 'config';
$usaSidebar = true;
$cssPaginas = ['config'];
$nomeCompleto = trim((string)$usuario['nome'] . ' ' . (string)($usuario['sobrenome'] ?? ''));
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>

    <div class="neo-page-shell settings-page">
        <section class="neo-page-heading neo-panel">
            <div class="neo-page-heading-copy">
                <span class="neo-page-kicker">Configurações</span>
                <h1>Sua experiência no NEO</h1>
            </div>
            <span class="neo-summary-pill"><b><?= htmlspecialchars($usuario['nome']) ?></b></span>
        </section>

        <section class="settings-account neo-panel">
            <span class="settings-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"></circle><path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path></svg>
            </span>
            <div class="settings-account-copy">
                <span class="neo-page-kicker">Conta</span>
                <b><?= htmlspecialchars($nomeCompleto) ?></b>
                <small><?= htmlspecialchars($usuario['email']) ?></small>
            </div>
            <a href="perfil.php" class="ghost">Abrir perfil</a>
        </section>

        <form method="post" class="settings-form">
            <?= campoCsrf() ?>
            <section class="settings-panel neo-panel">
                <div class="settings-panel-head">
                    <span class="settings-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path><path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path></svg>
                    </span>
                    <div>
                        <span class="neo-page-kicker">Preferências</span>
                        <h2>Seu jeito de aprender</h2>
                    </div>
                </div>
                <label for="gostos">Interesses e estilo de explicação</label>
                <textarea id="gostos" name="gostos" rows="5" maxlength="2000" placeholder="Ex: gosto de jogos, futebol, música e explicações passo a passo."><?= htmlspecialchars($usuario['gostos'] ?? '') ?></textarea>
            </section>

            <div class="settings-save-row">
                <button type="submit" class="save-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h12l2 2v14H5V4Z"></path><path d="M8 4v6h8V4"></path><path d="M8 20v-6h8v6"></path></svg>
                    Salvar alterações
                </button>
            </div>
        </form>

        <section class="settings-account-action neo-panel">
            <div>
                <span class="neo-page-kicker">Sessão</span>
                <h2>Sair da conta</h2>
            </div>
            <a href="logout.php" class="neo-danger-button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 5H5v14h5"></path><path d="M14 8l4 4-4 4"></path><path d="M8 12h10"></path></svg>
                Sair
            </a>
        </section>
    </div>
</main>
</body>
</html>
