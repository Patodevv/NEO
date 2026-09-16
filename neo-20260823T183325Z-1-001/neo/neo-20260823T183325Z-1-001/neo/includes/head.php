<?php
$assetVersion = function (string $arquivo): string {
    $caminho = __DIR__ . '/../' . $arquivo;
    return is_file($caminho) ? (string)filemtime($caminho) : (string)time();
};
require_once __DIR__ . '/materia_icon.php';
$mostrarRostoNeo = !empty($mostrarDespertarDashboard);
$introDisponivel = !empty($usaSidebar) || !empty($forcarIntroNeo);
$mostrarIntroNeo = $introDisponivel && ($mostrarRostoNeo || !empty($_SESSION['neo_intro_login']) || !empty($forcarIntroNeo));
$sidebarStorageKey = 'neo_sidebar_open_user_' . (int)($usuario['id'] ?? 0);
if (!empty($_SESSION['neo_intro_login']) && ($mostrarIntroNeo || $mostrarRostoNeo)) {
    unset($_SESSION['neo_intro_login']);
}
$classesBody = is_array($bodyClasses ?? null) ? $bodyClasses : [];
$introCadastroNeo = in_array('neo-onboarding-page', $classesBody, true);
$perfilVisualNeo = json_decode((string)($usuario['personalizacao_json'] ?? ''), true);
if (is_array($perfilVisualNeo) && in_array('aprendizado', $perfilVisualNeo['gamificacao'] ?? [], true)) {
    $classesBody[] = 'neo-learning-focus';
}
if (!empty($usaSidebar)) {
    $classesBody[] = 'has-sidebar';
}
if ($mostrarRostoNeo) {
    $classesBody[] = 'neo-dashboard-awakening';
    $classesBody[] = 'neo-interface-locked';
    $classesBody[] = 'neo-face-docking';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#071126">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($tituloPagina ?? 'NeoMind') ?> · NeoMind</title>

    <link rel="stylesheet" href="static/style.css?v=<?= $assetVersion('static/style.css') ?>">
    <?php if (!empty($usaSidebar)): ?>
        <link rel="stylesheet" href="static/sidebar.css?v=<?= $assetVersion('static/sidebar.css') ?>">
        <link rel="stylesheet" href="static/topbar.css?v=<?= $assetVersion('static/topbar.css') ?>">
        <link rel="stylesheet" href="static/manel-tour.css?v=<?= $assetVersion('static/manel-tour.css') ?>">
        <link rel="stylesheet" href="static/mobile-nav.css?v=<?= $assetVersion('static/mobile-nav.css') ?>">
    <?php endif; ?>
    <?php foreach (($cssPaginas ?? []) as $cssPagina): ?>
        <link rel="stylesheet" href="static/pages/<?= htmlspecialchars($cssPagina) ?>.css?v=<?= $assetVersion('static/pages/' . $cssPagina . '.css') ?>">
    <?php endforeach; ?>

    <?php if (!empty($usaSidebar)): ?>
    <script>
        (function () {
            try {
                var key = <?= json_encode($sidebarStorageKey, JSON_UNESCAPED_SLASHES) ?>;
                var forceClosed = <?= $mostrarRostoNeo ? 'true' : 'false' ?>;
                if (forceClosed) {
                    localStorage.setItem(key, '0');
                    return;
                }
                if (!window.matchMedia('(max-width: 760px)').matches && localStorage.getItem(key) === '1') {
                    document.documentElement.classList.add('sidebar-preopen', 'sidebar-open');
                }
            } catch (e) {}
        })();
    </script>
    <?php endif; ?>
</head>
<body<?= $classesBody ? ' class="' . htmlspecialchars(implode(' ', array_unique($classesBody))) . '"' : '' ?>>
<?php if ($introDisponivel): ?>
<div class="neo-intro-overlay" data-login-intro="<?= $mostrarIntroNeo ? '1' : '0' ?>" data-parent-curtain="<?= $introCadastroNeo ? '1' : '0' ?>" aria-hidden="true">
    <?php if ($introCadastroNeo): ?><span class="neo-intro-curtain neo-intro-curtain-top"></span><span class="neo-intro-curtain neo-intro-curtain-bottom"></span><?php endif; ?>
    <iframe src="ani.html<?= $introCadastroNeo ? '?parent-curtain=1' : '' ?>" title="NEO" tabindex="-1" allowtransparency="true"></iframe>
</div>
<script>
    (function () {
        var overlay = document.querySelector('.neo-intro-overlay');
        if (!overlay) return;

        var navigation = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
        var isReload = navigation ? navigation.type === 'reload' : performance.navigation && performance.navigation.type === 1;
        var shouldShow = overlay.dataset.loginIntro === '1' || isReload;

        if (!shouldShow) {
            overlay.remove();
            return;
        }

        document.body.classList.add('neo-interface-locked');
        overlay.classList.add('show');
        window.addEventListener('message', function (event) {
            if (event.data && event.data.type === 'neo-intro-open') {
                overlay.classList.add('transparent');
            }
            if (event.data && event.data.type === 'neo-intro-parent-curtain') {
                overlay.classList.add('transparent', 'parent-curtain-open');
                window.setTimeout(function () {
                    window.dispatchEvent(new CustomEvent('neo:intro-opened'));
                }, 580);
            }
        });
        window.setTimeout(function () {
            overlay.classList.add('hide');
        }, 2300);
        window.setTimeout(function () {
            overlay.remove();
            if (!document.querySelector('[data-face-overlay]')) {
                document.body.classList.remove('neo-interface-locked');
            }
        }, 2780);
    })();
</script>
<?php endif; ?>
<?php if ($mostrarRostoNeo): ?>
<div class="neo-face-overlay<?= $mostrarIntroNeo ? ' is-waiting' : '' ?>" data-face-overlay data-wait-for-intro="<?= $mostrarIntroNeo ? '1' : '0' ?>" role="status" aria-label="Preparando sua Dashboard">
    <div class="neo-face-stage">
        <iframe data-src="rosto_azul.html" title="NEO despertando" tabindex="-1" data-face-frame></iframe>
    </div>
</div>
<?php endif; ?>

<div class="neo-ai-loader" id="neoAiLoader" role="status" aria-live="polite" aria-hidden="true" hidden>
    <div class="neo-ai-loader-panel">
        <span class="neo-ai-loader-comet" aria-hidden="true">
            <i></i><i></i><i></i>
            <svg viewBox="0 0 120 120" focusable="false">
                <path class="neo-ai-star-shadow" d="M60 6 C66 34 86 54 114 60 C86 66 66 86 60 114 C54 86 34 66 6 60 C34 54 54 34 60 6 Z"></path>
                <path class="neo-ai-star-core" d="M60 18 C65 40 80 55 102 60 C80 65 65 80 60 102 C55 80 40 65 18 60 C40 55 55 40 60 18 Z"></path>
                <path class="neo-ai-star-center" d="M60 34 C64 48 72 56 86 60 C72 64 64 72 60 86 C56 72 48 64 34 60 C48 56 56 48 60 34 Z"></path>
            </svg>
        </span>
        <strong data-ai-loader-message>A IA está preparando tudo</strong>
        <span>Isso pode levar alguns instantes.</span>
    </div>
</div>
<script src="static/neo-ui.js?v=<?= $assetVersion('static/neo-ui.js') ?>" defer></script>
<?php if (!empty($usaSidebar)): ?>
<?php if (empty($rostoNoPainelEstudo)) require __DIR__ . '/companion_face.php'; ?>
<aside class="manel-panel" data-manel-panel data-user-id="<?= (int)($usuario['id'] ?? 0) ?>" aria-hidden="true" hidden>
    <header class="manel-header">
        <div class="manel-title">
            <span class="manel-mini-face" aria-hidden="true">
                <svg viewBox="0 0 300 220" focusable="false">
                    <rect class="manel-mini-eye" x="95" y="70" width="30" height="60" rx="15" ry="15"></rect>
                    <rect class="manel-mini-eye" x="175" y="70" width="30" height="60" rx="15" ry="15"></rect>
                    <path class="manel-mini-mouth" d="M 128 150 L 172 150"></path>
                </svg>
            </span>
            <strong>Manel</strong>
        </div>
        <div class="manel-actions">
            <button type="button" class="manel-icon-btn neo-star-hover" data-manel-tour-start title="Ver tutorial" aria-label="Ver tutorial">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M9.5 9a2.5 2.5 0 0 1 5 .5c0 1.5-2.5 2-2.5 3.5M12 16h.01"></path>
                </svg>
            </button>
            <button type="button" class="manel-icon-btn neo-star-hover" data-manel-new title="Nova conversa" aria-label="Nova conversa">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
            </button>
            <button type="button" class="manel-icon-btn neo-star-hover" data-manel-close title="Fechar" aria-label="Fechar Manel">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18"></path>
                </svg>
            </button>
        </div>
    </header>
    <div class="manel-messages" data-manel-messages></div>
    <div class="manel-suggestions" data-manel-suggestions></div>
    <form class="manel-form" data-manel-form>
        <textarea data-manel-input rows="1" maxlength="2000" placeholder="Fale com o Manel"></textarea>
        <button type="submit" class="manel-send neo-star-hover" data-manel-send aria-label="Enviar">
            <?= estrelaHoverNeo() ?>
            <svg class="manel-send-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 12h13"></path>
                <path d="M13 6l6 6-6 6"></path>
            </svg>
            <svg class="manel-stop-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="7" y="7" width="10" height="10" rx="2"></rect>
            </svg>
        </button>
    </form>
</aside>
<?php require __DIR__ . '/manel_tour.php'; ?>
<script src="static/neo-welcome.js?v=<?= $assetVersion('static/neo-welcome.js') ?>" data-neo-welcome data-user-id="<?= (int)($usuario['id'] ?? 0) ?>" data-first-access="<?= $mostrarRostoNeo ? '1' : '0' ?>" defer></script>
<?php endif; ?>
