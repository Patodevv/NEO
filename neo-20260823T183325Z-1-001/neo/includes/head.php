<?php
$assetVersion = function (string $arquivo): string {
    $caminho = __DIR__ . '/../' . $arquivo;
    return is_file($caminho) ? (string)filemtime($caminho) : (string)time();
};
require_once __DIR__ . '/materia_icon.php';
require_once __DIR__ . '/../services/store.php';
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
$cosmeticosUsuario = ['vars' => [], 'items' => []];
if (isset($pdo, $usuario) && is_array($usuario) && !empty($usuario['id']) && function_exists('estiloCosmeticosUsuario')) {
    try {
        $cosmeticosUsuario = estiloCosmeticosUsuario($pdo, $usuario);
    } catch (Throwable) {
        $cosmeticosUsuario = ['vars' => [], 'items' => []];
    }
}
$estiloBodyCosmetico = estiloInlineVars($cosmeticosUsuario['vars'] ?? []);
$skinManelAtiva = $cosmeticosUsuario['items']['skin_manel'] ?? null;
$nomeManelAtivo = 'Manel';
$varianteManelAtiva = '';
$personalidadeManelAtiva = '';
if (is_array($skinManelAtiva)) {
    $dadosSkinManel = $skinManelAtiva['metadados'] ?? [];
    if (!empty($dadosSkinManel['manel_name'])) {
        $nomeManelAtivo = mb_substr(trim((string)$dadosSkinManel['manel_name']), 0, 24);
    }
    $varianteManelAtiva = varianteManelClasse($dadosSkinManel['manel_variant'] ?? '');
    $personalidadeManelAtiva = normalizarCodigoProduto((string)($dadosSkinManel['manel_personality'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="pt-BR"<?= $estiloBodyCosmetico !== '' ? ' style="' . htmlspecialchars($estiloBodyCosmetico, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
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
    <link rel="stylesheet" href="static/profile-frames.css?v=<?= $assetVersion('static/profile-frames.css') ?>">

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
<body<?= $classesBody ? ' class="' . htmlspecialchars(implode(' ', array_unique($classesBody))) . '"' : '' ?><?= $estiloBodyCosmetico !== '' ? ' style="' . htmlspecialchars($estiloBodyCosmetico, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
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
<?php require __DIR__ . '/manel_tour.php'; ?>
<script src="static/neo-welcome.js?v=<?= $assetVersion('static/neo-welcome.js') ?>" data-neo-welcome data-user-id="<?= (int)($usuario['id'] ?? 0) ?>" data-first-access="<?= $mostrarRostoNeo ? '1' : '0' ?>" defer></script>
<?php endif; ?>


