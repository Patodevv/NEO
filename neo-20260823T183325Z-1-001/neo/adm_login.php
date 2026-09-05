<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

if (adminLogado()) {
    header('Location: adm.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $senha = $_POST['senha'] ?? '';
    $hashAdmin = trim((string)ambienteNeo('NEO_ADMIN_PASSWORD_HASH', ''));

    if (loginTemporariamenteBloqueado('admin')) {
        $erro = 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.';
    } elseif ($hashAdmin === '') {
        $erro = 'A senha administrativa ainda não foi configurada no ambiente.';
    } elseif (password_verify($senha, $hashAdmin)) {
        renovarSessaoAutenticada();
        limparFalhasLogin('admin');
        $_SESSION['admin_logado'] = true;
        $_SESSION['admin_autenticado_em'] = time();
        header('Location: adm.php');
        exit;
    } else {
        registrarFalhaLogin('admin');
        $erro = 'Senha administrativa incorreta.';
    }
}

$tituloPagina = 'Admin';
$cssPaginas = ['auth'];
require __DIR__ . '/includes/head.php';
?>
<div class="auth-wrap">
    <div class="auth-box">
        <div class="logo">NEOMIND</div>
        <h1>Admin</h1>
        <p class="sub">Acesse o painel administrativo.</p>
        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form method="post">
            <?= campoCsrf() ?>
            <div class="field">
                <label>Senha</label>
                <input type="password" name="senha" required>
            </div>
            <button type="submit" class="primary">Entrar</button>
        </form>
        <div class="switch-auth">
            <a href="login.php">Voltar para login</a>
        </div>
    </div>
</div>
</body>
</html>
