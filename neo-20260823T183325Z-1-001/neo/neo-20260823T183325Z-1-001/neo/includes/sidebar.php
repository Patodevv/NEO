<?php
$paginaAtual = $paginaAtual ?? '';
?>

<button class="sidebar-toggle neo-star-hover" id="sidebarToggle" aria-label="Abrir menu" data-manel-tip="Abre o menu principal do NEO.">
    <?= estrelaHoverNeo() ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
        <line x1="4" y1="7" x2="20" y2="7"></line>
        <line x1="4" y1="12" x2="20" y2="12"></line>
        <line x1="4" y1="17" x2="20" y2="17"></line>
    </svg>
</button>

<aside class="sidebar" id="sidebar" data-state-key="<?= htmlspecialchars($sidebarStorageKey ?? ('neo_sidebar_open_user_' . (int)($usuario['id'] ?? 0))) ?>" data-force-closed="<?= !empty($mostrarRostoNeo) ? '1' : '0' ?>">
    <a href="index.php" class="logo" aria-label="NEO" data-manel-tip="Volta para a página inicial.">
        <img src="assets/logo.png" alt="NEO" decoding="async">
    </a>

    <a href="index.php" class="nav-btn neo-star-hover <?= $paginaAtual === 'inicio' ? 'active' : '' ?>" aria-label="Início" data-manel-tip="Abre a página inicial e sua próxima atividade.">
        <?= estrelaHoverNeo() ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 11 12 4l8 7"></path>
            <path d="M6 10v8a1 1 0 0 0 1 1h3v-5h4v5h3a1 1 0 0 0 1-1v-8"></path>
        </svg>
    </a>

    <a href="materias.php" class="nav-btn neo-star-hover <?= $paginaAtual === 'materias' ? 'active' : '' ?>" aria-label="Matérias" data-manel-tip="Abre suas matérias e os livros de cada uma.">
        <?= estrelaHoverNeo() ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="4" width="7" height="7" rx="1.5"></rect>
            <rect x="13" y="4" width="7" height="7" rx="1.5"></rect>
            <rect x="4" y="13" width="7" height="7" rx="1.5"></rect>
            <rect x="13" y="13" width="7" height="7" rx="1.5"></rect>
        </svg>
    </a>

    <a href="historico.php" class="nav-btn neo-star-hover <?= $paginaAtual === 'historico' ? 'active' : '' ?>" aria-label="Histórico" data-manel-tip="Mostra as atividades e resultados que você já concluiu.">
        <?= estrelaHoverNeo() ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="8.5"></circle>
            <path d="M12 7.5V12l3.2 2"></path>
        </svg>
    </a>

    <a href="loja.php" class="nav-btn neo-star-hover <?= $paginaAtual === 'loja' ? 'active' : '' ?>" aria-label="Loja" data-manel-tip="Abre a loja de itens visuais do seu perfil.">
        <?= estrelaHoverNeo() ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 9h14l-1 10H6L5 9Z"></path>
            <path d="M8 9a4 4 0 0 1 8 0"></path>
            <path d="M9 14h6"></path>
        </svg>
    </a>

    <a href="aprendizado.php" class="nav-btn neo-learning-nav neo-star-hover <?= $paginaAtual === 'aprendizado' ? 'active' : '' ?>" aria-label="Meu aprendizado" data-manel-tip="Abre sua rotina, seu progresso, revisões e simulados.">
        <?= estrelaHoverNeo() ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 19V5M4 19h16M8 15l4-5 4 2 4-7"></path><circle cx="12" cy="10" r="1.5"></circle>
        </svg>
    </a>

    <div class="nav-bottom">
        <a href="perfil.php" class="nav-btn neo-star-hover <?= $paginaAtual === 'perfil' ? 'active' : '' ?>" aria-label="Perfil" data-manel-tip="Abre seu perfil, nível e conquistas.">
            <?= estrelaHoverNeo() ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="3.5"></circle>
                <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
            </svg>
        </a>

        <a href="config.php" class="nav-btn neo-star-hover <?= $paginaAtual === 'config' ? 'active' : '' ?>" aria-label="Configurações" data-manel-tip="Abre as configurações da sua conta.">
            <?= estrelaHoverNeo() ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 13a7.6 7.6 0 0 0 0-2l2-1.5-2-3.4-2.3 1a7.4 7.4 0 0 0-1.7-1L15 3.6h-4l-.4 2.5a7.4 7.4 0 0 0-1.7 1l-2.3-1-2 3.4L6.6 11a7.6 7.6 0 0 0 0 2l-2 1.5 2 3.4 2.3-1c.5.4 1.1.75 1.7 1l.4 2.6h4l.4-2.5c.6-.25 1.2-.6 1.7-1l2.3 1 2-3.4-2-1.6Z"></path>
            </svg>
        </a>

        <button class="nav-btn nav-close" id="sidebarClose" data-manel-tip="Fecha o menu lateral." aria-label="Fechar menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <line x1="6" y1="6" x2="18" y2="18"></line>
                <line x1="18" y1="6" x2="6" y2="18"></line>
            </svg>
        </button>
    </div>
</aside>

<script src="static/sidebar.js?v=<?= filemtime(__DIR__ . '/../static/sidebar.js') ?>"></script>

