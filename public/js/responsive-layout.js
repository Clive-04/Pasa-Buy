document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    // SIDEBAR
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.dashboard-sidebar');
    const sidebarClose = document.querySelector('.sidebar-close');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (sidebarOverlay) sidebarOverlay.classList.add('show');
        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'true');
        body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
        body.classList.remove('sidebar-open');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', openSidebar);
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    // NAVBAR
    const navToggle = document.querySelector('.nav-toggle');
    const navCenter = document.querySelector('.nav-center');
    const navRight = document.querySelector('.nav-right');

    function closeNavMenu() {
        if (navCenter) navCenter.classList.remove('open');
        if (navRight) navRight.classList.remove('open');
        if (navToggle) {
            navToggle.classList.remove('active');
            navToggle.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleNavMenu() {
        if (!navCenter || !navRight || !navToggle) return;

        const isOpen = navCenter.classList.contains('open');

        navCenter.classList.toggle('open');
        navRight.classList.toggle('open');
        navToggle.classList.toggle('active');

        navToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    }

    if (navToggle) {
        navToggle.addEventListener('click', toggleNavMenu);
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1100) {
            closeSidebar();
        }

        if (window.innerWidth > 900) {
            closeNavMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
            closeNavMenu();
        }
    });
});