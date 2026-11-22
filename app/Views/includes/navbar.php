<?php
$current    = uri_string();
$isLoggedIn = (bool) session('isLoggedIn');
$isAdmin    = session('role') === 'admin';
$me         = $isLoggedIn ? current_user() : null;
$homeLink   = $isLoggedIn ? base_url($isAdmin ? 'admin/dashboard' : 'dashboard') : base_url('login');
?>
<?php if ($isLoggedIn && $me !== null): ?>
<header class="app-topbar">
    <div class="topbar-left">
        <a href="<?= $homeLink ?>" class="icon-btn topbar-menu" data-open-sidebar aria-label="Open menu" aria-controls="dashboardSidebar" aria-expanded="false">
            <?= icon('menu') ?>
        </a>
        <a href="<?= $homeLink ?>" class="topbar-brand" aria-label="Pasa-Buy home">
            <img src="<?= base_url('img/logo.PNG') ?>" alt="" class="logo-img">
            <span>Pasa-Buy</span>
        </a>
    </div>

    <?php if (! $isAdmin): ?>
        <form action="<?= base_url('users/search') ?>" method="get" class="topbar-search" role="search">
            <label for="topbarSearch" class="visually-hidden">Search people by name or username</label>
            <?= icon('search') ?>
            <input type="search" id="topbarSearch" name="q" placeholder="Search people…" maxlength="100"
                   value="<?= esc((string) service('request')->getGet('q')) ?>">
        </form>
    <?php endif; ?>

    <nav class="topbar-actions" aria-label="Shortcuts">
        <?php if (! $isAdmin): ?>
            <a href="<?= base_url('users/search') ?>" class="icon-btn topbar-search-link" aria-label="Search people" title="Search people">
                <?= icon('search') ?>
            </a>
            <a href="<?= base_url('messages') ?>" class="icon-btn<?= nav_attrs('messages') ?>" title="Messages">
                <?= icon('message') ?><span class="visually-hidden">Messages</span>
                <?= unread_badge('messages', 'badge badge-dot') ?>
            </a>
            <a href="<?= base_url('notifications') ?>" class="icon-btn<?= nav_attrs('notifications') ?>" title="Notifications">
                <?= icon('bell') ?><span class="visually-hidden">Notifications</span>
                <?= unread_badge('notifications', 'badge badge-dot') ?>
            </a>
        <?php endif; ?>

        <a href="<?= base_url($isAdmin ? 'admin/dashboard' : 'profile') ?>" class="topbar-user<?= nav_attrs('profile') ?>" title="<?= $isAdmin ? 'Admin dashboard' : 'Your profile' ?>">
            <?= avatar($me, 'sm') ?>
            <span class="topbar-user-name">
                <?= esc($me['first_name']) ?>
                <small><?= $isAdmin ? 'Administrator' : 'View profile' ?></small>
            </span>
        </a>
    </nav>
</header>
<?php else: ?>
<header>
<nav class="navbar" aria-label="Main">
    <div class="nav-left">
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navMenu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <a href="<?= $homeLink ?>" class="logo">
            <img src="<?= base_url('img/logo.PNG') ?>" alt="" class="logo-img">
            <span class="logo-text">Pasa-Buy</span>
        </a>
    </div>

    <div class="nav-center nav-menu" id="navMenu">
        <?php foreach (['login' => 'Home', 'about' => 'About', 'contact' => 'Contact'] as $path => $label): ?>
            <?php $active = $current === $path || ($path === 'login' && $current === ''); ?>
            <a href="<?= base_url($path) ?>" class="nav-link <?= $active ? 'active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <div class="nav-right nav-actions">
        <a href="<?= base_url('login') ?>" class="nav-login">Log in</a>
        <a href="<?= base_url('register') ?>" class="nav-signup">Sign up</a>
    </div>
</nav>
</header>
<?php endif; ?>
