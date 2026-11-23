document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;

    // ------------------------------------------------------------ SIDEBAR
    // Opened by the top-bar menu button ([data-open-sidebar]) on small
    // screens. Pages without a sidebar keep the button's link behaviour.
    var sidebar = document.querySelector('.dashboard-sidebar');
    var openers = document.querySelectorAll('[data-open-sidebar], .sidebar-toggle');
    var sidebarClose = document.querySelector('.sidebar-close');
    var sidebarOverlay = document.querySelector('.sidebar-overlay');
    var lastOpener = null;
    var DRAWER_MAX = 900; // keep in sync with theme.css (max-width: 900px)

    function setExpanded(value) {
        openers.forEach(function (el) { el.setAttribute('aria-expanded', value ? 'true' : 'false'); });
    }

    function openSidebar(event) {
        if (!sidebar || window.innerWidth > DRAWER_MAX) {
            return; // wider screens: the rail is always visible; let links behave normally
        }
        if (event) {
            event.preventDefault();
            lastOpener = event.currentTarget;
        }
        sidebar.classList.add('open');
        if (sidebarOverlay) sidebarOverlay.classList.add('show');
        setExpanded(true);
        body.classList.add('sidebar-open');
        var first = sidebar.querySelector('.sidebar-close, a, button');
        if (first) first.focus({ preventScroll: true });
    }

    function closeSidebar() {
        if (!sidebar || !sidebar.classList.contains('open')) return;
        sidebar.classList.remove('open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
        setExpanded(false);
        body.classList.remove('sidebar-open');
        if (lastOpener) lastOpener.focus({ preventScroll: true });
    }

    // Keep keyboard focus inside the open drawer (it is modal on small screens).
    if (sidebar) {
        sidebar.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab' || !sidebar.classList.contains('open')) return;
            var items = Array.prototype.filter.call(
                sidebar.querySelectorAll('a[href], button:not([disabled])'),
                function (el) { return el.offsetParent !== null; }
            );
            if (!items.length) return;
            var first = items[0];
            var last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    openers.forEach(function (el) { el.addEventListener('click', openSidebar); });
    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // ------------------------------------------------------------- NAVBAR
    // Guest pages (login/register/about/contact) use the classic navbar.
    var navToggle = document.querySelector('.nav-toggle');
    var navCenter = document.querySelector('.nav-center');
    var navRight = document.querySelector('.nav-right');

    function closeNavMenu() {
        if (navCenter) navCenter.classList.remove('open');
        if (navRight) navRight.classList.remove('open');
        if (navToggle) {
            navToggle.classList.remove('active');
            navToggle.setAttribute('aria-expanded', 'false');
        }
    }

    if (navToggle) {
        navToggle.addEventListener('click', function () {
            if (!navCenter || !navRight) return;
            var isOpen = navCenter.classList.contains('open');
            navCenter.classList.toggle('open');
            navRight.classList.toggle('open');
            navToggle.classList.toggle('active');
            navToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        });
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > DRAWER_MAX) closeSidebar();
        if (window.innerWidth > 900) closeNavMenu();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
            closeNavMenu();
        }
    });
});
