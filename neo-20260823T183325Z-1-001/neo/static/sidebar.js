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

    if (localStorage.getItem(STORAGE_KEY) === '1') {
        sidebar.style.transition = 'none';
        openSidebar(false);
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                sidebar.style.transition = '';
            });
        });
    }

    toggle.addEventListener('click', function () { openSidebar(); });
    closeBtn.addEventListener('click', function () { closeSidebar(); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
});
