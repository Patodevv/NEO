<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/materia_icon.php';
function cometaCadastroNeo(string $classe = ''): string
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

function fogueteCadastroNeo(): string
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

$tituloPagina = 'Seu NEO, do seu jeito';
$cssPaginas = ['auth', 'estudos', 'onboarding'];
$bodyClasses = ['study-screen', 'auth-onboarding-page', 'neo-onboarding-page'];
$forcarIntroNeo = true;
$draftOwner = logado() ? 'user_' . (int)$_SESSION['user_id'] : 'guest_' . substr(hash('sha256', csrfToken()), 0, 16);
require __DIR__ . '/includes/head.php';
?>
<main class="study-page neo-setup" data-neo-onboarding data-draft-key="<?= htmlspecialchars($draftOwner, ENT_QUOTES, 'UTF-8') ?>" data-edit="<?= isset($_GET['editar']) || isset($_GET['edit']) ? '1' : '0' ?>">
    <div class="onboarding-space-scene" aria-hidden="true">
        <span class="onboarding-comet-flight onboarding-comet-flight-one"><?= cometaCadastroNeo('onboarding-background-comet') ?></span>
        <span class="onboarding-rocket-flight onboarding-rocket-flight-one"><?= fogueteCadastroNeo() ?></span>
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
    <section class="study-shell" aria-label="Personalização com o Manel">
        <header class="study-heading">
            <h1 class="sr-only">Personalização do NEO com o Manel</h1>
        </header>
        <section class="study-workspace" aria-label="Conversa com o Manel">
            <div class="study-thread" data-onboarding-thread>
                <div class="study-face-slot">
                    <?php require __DIR__ . '/includes/companion_face.php'; ?>
                </div>
                <div id="neo-setup-stage" class="study-messages neo-setup-stage" aria-busy="true" hidden></div>
                <p id="neo-setup-feedback" class="study-error neo-setup-feedback" role="alert" tabindex="-1" hidden></p>
            </div>
        </section>
        <button id="neo-setup-back" class="neo-setup-corner-back neo-star-hover" type="button" aria-label="Voltar" hidden><?= estrelaHoverNeo() ?><span>← Voltar</span></button>
        <button id="neo-setup-next" class="neo-setup-corner-next neo-star-hover" type="button" aria-label="Seguinte" hidden><?= estrelaHoverNeo() ?><span>Seguinte →</span></button>
        <?php if (!logado()): ?>
            <p class="neo-setup-login-footer">Já tem conta? <a href="login.php">Entrar</a></p>
        <?php endif; ?>
    </section>
    <noscript><p class="neo-setup-noscript">Ative o JavaScript no navegador para conversar com o Manel e concluir sua personalização. <a href="login.php">Voltar para entrar</a></p></noscript>
</main>
<template data-onboarding-button-star><?= estrelaHoverNeo() ?></template>
<script src="static/neo-onboarding.js?v=<?= $assetVersion('static/neo-onboarding.js') ?>" defer></script>
</body>
</html>
