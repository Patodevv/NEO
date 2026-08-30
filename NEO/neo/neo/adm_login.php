<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

if (adminLogado()) {
    header('Location: adm.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha = $_POST['senha'] ?? '';
    if (hash_equals('adm5889', $senha)) {
        $_SESSION['admin_logado'] = true;
        header('Location: adm.php');
        exit;
    }

    $erro = 'Senha administrativa incorreta.';
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
            <div class="field">
                <label>Senha</label>
                <input type="password" name="senha" required>
            </div>
            <button type="submit" class="primary">Entrar</button>
        </form>
    </div>
</div>
</body>
</html>
