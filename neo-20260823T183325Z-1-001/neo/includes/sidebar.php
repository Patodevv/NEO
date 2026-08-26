<?php
$paginaAtual = $paginaAtual ?? '';
?>

<button class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menu" title="Abrir menu">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
        <line x1="4" y1="7" x2="20" y2="7"></line>
        <line x1="4" y1="12" x2="20" y2="12"></line>
        <line x1="4" y1="17" x2="20" y2="17"></line>
    </svg>
</button>

<aside class="sidebar" id="sidebar">

    <a href="index.php" class="logo" aria-label="NEO">
        <img src="assets/logo.png" alt="NEO">
    </a>

    <a href="index.php" class="nav-btn <?= $paginaAtual === 'dashboard' ? 'active' : '' ?>" title="Dashboard" aria-label="Dashboard">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 11 12 4l8 7"></path>
            <path d="M6 10v8a1 1 0 0 0 1 1h3v-5h4v5h3a1 1 0 0 0 1-1v-8"></path>
        </svg>
    </a>

    <a href="materias.php" class="nav-btn <?= $paginaAtual === 'materias' ? 'active' : '' ?>" title="Matérias" aria-label="Matérias">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="4" width="7" height="7" rx="1.5"></rect>
            <rect x="13" y="4" width="7" height="7" rx="1.5"></rect>
            <rect x="4" y="13" width="7" height="7" rx="1.5"></rect>
            <rect x="13" y="13" width="7" height="7" rx="1.5"></rect>
        </svg>
    </a>

    <a href="historico.php" class="nav-btn <?= $paginaAtual === 'historico' ? 'active' : '' ?>" title="Histórico" aria-label="Histórico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="8.5"></circle>
            <path d="M12 7.5V12l3.2 2"></path>
        </svg>
    </a>

    <div class="nav-bottom">
        <a href="config.php" class="nav-btn <?= $paginaAtual === 'config' ? 'active' : '' ?>" title="Configurações" aria-label="Configurações">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 13a7.6 7.6 0 0 0 0-2l2-1.5-2-3.4-2.3 1a7.4 7.4 0 0 0-1.7-1L15 3.6h-4l-.4 2.5a7.4 7.4 0 0 0-1.7 1l-2.3-1-2 3.4L6.6 11a7.6 7.6 0 0 0 0 2l-2 1.5 2 3.4 2.3-1c.5.4 1.1.75 1.7 1l.4 2.6h4l.4-2.5c.6-.25 1.2-.6 1.7-1l2.3 1 2-3.4-2-1.6Z"></path>
            </svg>
        </a>

        <button class="nav-btn nav-close" id="sidebarClose" title="Fechar menu" aria-label="Fechar menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <line x1="6" y1="6" x2="18" y2="18"></line>
                <line x1="18" y1="6" x2="6" y2="18"></line>
            </svg>
        </button>
    </div>

</aside>

<script src="static/sidebar.js"></script>
