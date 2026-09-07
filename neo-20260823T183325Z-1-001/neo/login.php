<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
if (logado()) {
    header('Location: index.php');
    exit;
}
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if (loginTemporariamenteBloqueado($pdo, 'usuario', $email)) {
        $erro = 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.';
    } elseif ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($senha, $user['senha'])) {
            renovarSessaoAutenticada();
            limparFalhasLogin($pdo, 'usuario', $email);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['neo_intro_login'] = true;
            $pdo->prepare("UPDATE users SET ultimo_login_em = NOW() WHERE id = ?")->execute([(int)$user['id']]);
            header('Location: index.php');
            exit;
        } else {
            registrarFalhaLogin($pdo, 'usuario', $email);
            $erro = 'E-mail ou senha inválidos.';
        }
    }
}
$tituloPagina = 'Login';
$cssPaginas = ['auth'];
require __DIR__ . '/includes/head.php';
?>
<div class="auth-wrap">
    <a href="adm_login.php" class="admin-corner" aria-label="Admin" title="Admin">ADM</a>
    <div class="auth-box">
        <div class="logo">NEOMIND</div>
        <h1>Entrar</h1>
        <p class="sub">Acesse sua conta para continuar estudando.</p>
        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form method="post">
            <?= campoCsrf() ?>
            <div class="field">
                <label>E-mail</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label>Senha</label>
                <input type="password" name="senha" required>
            </div>
            <button type="submit" class="primary">Entrar</button>
        </form>
        <div class="switch-auth">
            Ainda não tem conta? <a href="register.php">Criar conta</a>
        </div>
    </div>
</div>
</body>
</html>
