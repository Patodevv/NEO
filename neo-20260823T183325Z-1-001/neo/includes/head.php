<?php
$assetVersion = function (string $arquivo): string {
    $caminho = __DIR__ . '/../' . $arquivo;
    return is_file($caminho) ? (string)filemtime($caminho) : (string)time();
};
$mostrarRostoNeo = !empty($mostrarDespertarDashboard);
$introDisponivel = !empty($usaSidebar) || !empty($forcarIntroNeo);
$mostrarIntroNeo = $introDisponivel && ($mostrarRostoNeo || !empty($_SESSION['neo_intro_login']) || !empty($forcarIntroNeo));
$sidebarStorageKey = 'neo_sidebar_open_user_' . (int)($usuario['id'] ?? 0);
if (!empty($_SESSION['neo_intro_login']) && ($mostrarIntroNeo || $mostrarRostoNeo)) {
    unset($_SESSION['neo_intro_login']);
}
$classesBody = is_array($bodyClasses ?? null) ? $bodyClasses : [];
if ($mostrarRostoNeo) {
    $classesBody[] = 'neo-dashboard-awakening';
    $classesBody[] = 'neo-interface-locked';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? 'NeoMind') ?> · NeoMind</title>

    <link rel="stylesheet" href="static/style.css?v=<?= $assetVersion('static/style.css') ?>">
    <?php if (!empty($usaSidebar)): ?>
        <link rel="stylesheet" href="static/sidebar.css?v=<?= $assetVersion('static/sidebar.css') ?>">
        <link rel="stylesheet" href="static/topbar.css?v=<?= $assetVersion('static/topbar.css') ?>">
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
                if (localStorage.getItem(key) === '1') {
                    document.documentElement.classList.add('sidebar-preopen', 'sidebar-open');
                }
            } catch (e) {}
        })();
    </script>
    <?php endif; ?>
</head>
<body<?= $classesBody ? ' class="' . htmlspecialchars(implode(' ', array_unique($classesBody))) . '"' : '' ?>>
<?php if ($introDisponivel): ?>
<div class="neo-intro-overlay" data-login-intro="<?= $mostrarIntroNeo ? '1' : '0' ?>" aria-hidden="true">
    <iframe src="ani.html" title="NEO" tabindex="-1"></iframe>
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
<script>
    (function () {
        var overlay = document.querySelector('[data-face-overlay]');
        var frame = document.querySelector('[data-face-frame]');
        if (!overlay || !frame) return;
        var revealed = false;
        var finished = false;
        var started = false;

        function startFace() {
            if (started) return;
            started = true;

            function showFace() {
                window.requestAnimationFrame(function () {
                    overlay.classList.remove('is-waiting');
                    overlay.classList.add('is-entering');
                });
            }

            frame.addEventListener('load', showFace, { once: true });
            frame.src = frame.dataset.src;
            window.setTimeout(showFace, 180);
            window.setTimeout(revealDashboard, 2200);
            window.setTimeout(finishAwakening, 3200);
        }

        function revealDashboard() {
            if (revealed) return;
            revealed = true;
            document.body.classList.remove('neo-dashboard-awakening');
            document.body.classList.add('neo-dashboard-revealing');
            overlay.classList.add('is-revealing');
        }

        function finishAwakening() {
            if (finished) return;
            finished = true;
            revealDashboard();
            overlay.classList.add('is-finished');
            window.setTimeout(function () {
                overlay.remove();
                document.body.classList.remove('neo-interface-locked', 'neo-dashboard-revealing');
            }, 420);
        }

        window.addEventListener('message', function (event) {
            if (!event.data) return;
            if (event.data.type === 'neo-intro-star-done') {
                startFace();
                return;
            }
            if (event.source !== frame.contentWindow) return;
            if (event.data.type === 'neo-face-reveal') revealDashboard();
            if (event.data.type === 'neo-face-done') finishAwakening();
        });

        if (overlay.dataset.waitForIntro === '1') {
            window.setTimeout(startFace, 1700);
        } else {
            startFace();
        }
    })();
</script>
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
