document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const closeBtn = document.getElementById('sidebarClose');

    if (!toggle || !sidebar || !closeBtn) return;

    const STORAGE_KEY = 'neo_sidebar_open';

    function openSidebar(persist = true) {
        sidebar.classList.add('open');
        toggle.classList.add('hidden');
        if (persist) localStorage.setItem(STORAGE_KEY, '1');
    }

    function closeSidebar(persist = true) {
        sidebar.classList.remove('open');
        toggle.classList.remove('hidden');
        if (persist) localStorage.setItem(STORAGE_KEY, '0');
    }

    // Restaura o estado da sidebar (aberta/fechada) entre navegações de página
    if (localStorage.getItem(STORAGE_KEY) === '1') {
        // Abre sem animação de entrada ao carregar a página
        sidebar.style.transition = 'none';
        openSidebar(false);
        // Reativa as transições no próximo frame
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                sidebar.style.transition = '';
            });
        });
    }

    toggle.addEventListener('click', function () { openSidebar(); });
    closeBtn.addEventListener('click', function () { closeSidebar(); });

    // Fecha ao pressionar ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
});
