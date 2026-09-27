document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const closeBtn = document.getElementById('sidebarClose');
    const topbar = document.querySelector('.topbar');

    if (!toggle || !sidebar || !closeBtn) return;

    const storageKey = sidebar.dataset.stateKey || 'neo_sidebar_open';
    const forceClosed = sidebar.dataset.forceClosed === '1';
    const wasPreopened = document.documentElement.classList.contains('sidebar-preopen');
    let flashTimer = null;
    const mobile = window.matchMedia('(max-width: 760px)');
    sidebar.querySelectorAll('a.nav-btn').forEach(function (link) {
        const label = document.createElement('span');
        label.className = 'mobile-nav-label';
        const labels = {
            'index.php': 'Início',
            'materias.php': 'Matérias',
            'historico.php': 'Histórico',
            'loja.php': 'Loja',
            'aprendizado.php': 'Aprendizado',
            'perfil.php': 'Perfil',
            'config.php': 'Config.'
        };
        label.textContent = labels[link.getAttribute('href')] || link.getAttribute('aria-label') || '';
        link.appendChild(label);
        if (link.classList.contains('active')) link.setAttribute('aria-current', 'page');
    });

    function readStoredState() {
        try {
            return localStorage.getItem(storageKey);
        } catch (e) {
            return null;
        }
    }

    function storeState(open) {
        try {
            localStorage.setItem(storageKey, open ? '1' : '0');
        } catch (e) {}
    }

    function flashTopbar() {
        if (!topbar) return;
        topbar.classList.remove('topbar-flash');
        if (flashTimer) window.clearTimeout(flashTimer);
        window.requestAnimationFrame(function () {
            topbar.classList.add('topbar-flash');
        });
        flashTimer = window.setTimeout(function () {
            topbar.classList.remove('topbar-flash');
            flashTimer = null;
        }, 650);
    }

    function openSidebar(flash = false, persist = true) {
        if (mobile.matches) return;
        if (sidebar.classList.contains('open')) return;
        sidebar.classList.add('open');
        toggle.classList.add('hidden');
        document.documentElement.classList.add('sidebar-open');
        if (flash) flashTopbar();
        if (persist) storeState(true);
    }

    function closeSidebar(persist = true) {
        sidebar.classList.remove('open');
        toggle.classList.remove('hidden');
        document.documentElement.classList.remove('sidebar-open');
        if (persist) storeState(false);
    }

    if (!mobile.matches && !forceClosed && (wasPreopened || readStoredState() === '1')) {
        openSidebar(false, false);
    } else {
        closeSidebar(false);
    }

    window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
            document.documentElement.classList.remove('sidebar-preopen');
        });
    });

    toggle.addEventListener('click', function () { openSidebar(true); });
    closeBtn.addEventListener('click', function () { closeSidebar(); });
    mobile.addEventListener('change', function () {
        document.documentElement.classList.remove('sidebar-preopen');
        if (mobile.matches) closeSidebar(false);
        else if (readStoredState() === '1') openSidebar(false, false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
});
