<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
if (logado()) {
    header('Location: index.php');
    exit;
}
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $gostos = trim($_POST['gostos'] ?? '');
    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (strlen($senha) < 4) {
        $erro = 'A senha deve ter pelo menos 4 caracteres.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erro = 'Já existe uma conta com esse e-mail.';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (nome, email, senha, gostos) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $email, $hash, $gostos]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            header('Location: index.php');
            exit;
        }
    }
}
$tituloPagina = 'Criar conta';
$cssPaginas = ['auth'];
require __DIR__ . '/includes/head.php';
?>
<div class="auth-wrap">
    <div class="auth-box">
        <div class="logo">NEOMIND</div>
        <h1>Criar conta</h1>
        <p class="sub">Comece sua preparação de estudos agora</p>
        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="field">
                <label>Nome</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label>E-mail</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label>Senha</label>
                <input type="password" name="senha" required>
            </div>
            <div class="field">
                <label>Gostos e estilo de estudo</label>
                <textarea name="gostos" rows="4" placeholder="Ex: gosto de futebol, tecnologia, musica, exemplos simples e questoes diretas."><?= htmlspecialchars($_POST['gostos'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="primary">Criar conta</button>
        </form>

        <div class="switch-auth">
            Já tem conta? <a href="login.php">Entrar</a>
        </div>
    </div>
</div>
</body>
</html>
