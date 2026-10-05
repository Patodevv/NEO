<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/onboarding.php';
function cometaLoginNeo(string $classe = ''): string
{
    $classeExtra = $classe !== '' ? ' ' . htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') : '';

    return '<span class="neo-ai-loader-comet' . $classeExtra . '" aria-hidden="true">'
        . '<i></i><i></i><i></i>'
        . '<svg viewBox="0 0 120 120" focusable="false">'
        . '<path class="neo-ai-star-shadow" d="M60 6 C66 34 86 54 114 60 C86 66 66 86 60 114 C54 86 34 66 6 60 C34 54 54 34 60 6 Z"></path>'
        . '<path class="neo-ai-star-core" d="M60 18 C65 40 80 55 102 60 C80 65 65 80 60 102 C55 80 40 65 18 60 C40 55 55 40 60 18 Z"></path>'
        . '<path class="neo-ai-star-center" d="M60 34 C64 48 72 56 86 60 C72 64 64 72 60 86 C56 72 48 64 34 60 C48 56 56 48 60 34 Z"></path>'
        . '</svg></span>';
}

function fogueteLoginNeo(): string
{
    return '<svg class="onboarding-rocket" viewBox="0 0 96 104" focusable="false" aria-hidden="true">'
        . '<path class="onboarding-rocket-flame onboarding-rocket-flame-side" d="M18 84L24 102L30 84Z"></path>'
        . '<path class="onboarding-rocket-flame-core" d="M22 85L24 96L27 85Z"></path>'
        . '<path class="onboarding-rocket-flame onboarding-rocket-flame-main" d="M39 82L48 104L57 82Z"></path>'
        . '<path class="onboarding-rocket-flame-core" d="M44 83L48 98L52 83Z"></path>'
        . '<path class="onboarding-rocket-flame onboarding-rocket-flame-side" d="M66 84L72 102L78 84Z"></path>'
        . '<path class="onboarding-rocket-flame-core" d="M70 85L72 96L75 85Z"></path>'
        . '<path class="onboarding-shuttle-wing" d="M40 50L16 72L12 86L42 75Z"></path>'
        . '<path class="onboarding-shuttle-wing" d="M56 50L80 72L84 86L54 75Z"></path>'
        . '<path class="onboarding-shuttle-booster" d="M17 35L24 25L31 35V84H17Z"></path>'
        . '<path class="onboarding-shuttle-booster-cap" d="M17 35L24 25L31 35Z"></path>'
        . '<path class="onboarding-shuttle-booster" d="M65 35L72 25L79 35V84H65Z"></path>'
        . '<path class="onboarding-shuttle-booster-cap" d="M65 35L72 25L79 35Z"></path>'
        . '<path class="onboarding-shuttle-body" d="M48 4C56 16 59 31 58 55L57 83H39L38 55C37 31 40 16 48 4Z"></path>'
        . '<path class="onboarding-shuttle-stripe" d="M44 30H52V79H44Z"></path>'
        . '<path class="onboarding-shuttle-window" d="M42 23L48 16L54 23L53 34H43Z"></path>'
        . '<path class="onboarding-shuttle-detail" d="M24 43V75M72 43V75M48 40V70"></path>'
        . '<path class="onboarding-shuttle-shine" d="M43 12C41 20 40 29 40 41"></path>'
        . '</svg>';
}

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
$bodyClasses = ['auth-onboarding-page'];
require __DIR__ . '/includes/head.php';
?>
<main class="auth-wrap auth-login-wrap onboarding-wrap">
    <div class="onboarding-space-scene" aria-hidden="true">
        <span class="onboarding-comet-flight onboarding-comet-flight-one"><?= cometaLoginNeo('onboarding-background-comet') ?></span>
        <span class="onboarding-rocket-flight onboarding-rocket-flight-one"><?= fogueteLoginNeo() ?></span>
        <?php for ($estrela = 1; $estrela <= 4; $estrela++): ?>
            <span class="onboarding-space-star onboarding-space-star-<?= $estrela ?>"><i></i></span>
        <?php endfor; ?>
        <svg class="onboarding-constellation onboarding-constellation-one" viewBox="0 0 150 100" focusable="false">
            <path class="onboarding-constellation-line" d="M10 70L34 42L61 55L84 21L112 36L138 13M61 55L76 87M112 36L132 73"></path>
            <g class="onboarding-constellation-node">
                <rect x="6" y="66" width="8" height="8" transform="rotate(45 10 70)"></rect><rect x="30" y="38" width="8" height="8" transform="rotate(45 34 42)"></rect><rect x="57" y="51" width="8" height="8" transform="rotate(45 61 55)"></rect><rect x="80" y="17" width="8" height="8" transform="rotate(45 84 21)"></rect><rect x="108" y="32" width="8" height="8" transform="rotate(45 112 36)"></rect><rect x="134" y="9" width="8" height="8" transform="rotate(45 138 13)"></rect><rect x="72" y="83" width="8" height="8" transform="rotate(45 76 87)"></rect><rect x="128" y="69" width="8" height="8" transform="rotate(45 132 73)"></rect>
            </g>
        </svg>
    </div>
    <div class="auth-box auth-login-box">
        <?= cometaLoginNeo('onboarding-panel-comet') ?>
        <h1>Entrar</h1>
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
        <div class="switch-auth auth-login-links">
            <span>Ainda não tem conta? <a href="register.php">Criar conta</a></span>
        </div>
    </div>
</main>
<script>
(() => {
    document.addEventListener('keydown', (event) => {
        if (event.ctrlKey && event.altKey && !event.shiftKey && event.key.toLowerCase() === 'd') {
            event.preventDefault();
            window.location.href = 'adm_login.php';
        }
    });
})();
</script>
</body>
</html>
