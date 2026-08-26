<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
if (logado()) {
    header('Location: index.php');
    exit;
}
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: index.php');
            exit;
        } else {
            $erro = 'E-mail ou senha inválidos.';
        }
    }
}
$tituloPagina = 'Login';
$cssPaginas = ['auth'];
require __DIR__ . '/includes/head.php';
?>
<div class="auth-wrap">
    <div class="auth-box">
        <div class="logo">NEOMIND</div>
        <h1>Entrar</h1>
        <p class="sub">Acesse sua conta para continuar estudando.</p>
        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form method="post">
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
