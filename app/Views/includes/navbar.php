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
        <?= brand_mark($homeLink) ?>
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
            <a href="<?= base_url('users/search') ?>" class="icon-btn topbar-search-link" aria-label="Search people" title="Search people"><?= icon('search') ?></a>
            <a href="<?= base_url('messages') ?>" class="icon-btn<?= nav_attrs('messages') ?>" title="Messages">
                <?= icon('message') ?><span class="visually-hidden">Messages</span>
                <?= unread_badge('messages', 'badge badge-dot') ?>
            </a>
            <div class="notif-menu" data-notif-menu data-feed-url="<?= esc(base_url('notifications/feed'), 'attr') ?>">
                <button type="button" class="icon-btn<?= nav_attrs('notifications') ?>" aria-haspopup="true" aria-expanded="false" aria-controls="notifPanel" title="Notifications" data-notif-toggle>
                    <?= icon('bell') ?><span class="visually-hidden">Notifications</span>
                    <?= unread_badge('notifications', 'badge badge-dot') ?>
                </button>
                <div class="notif-panel" id="notifPanel" role="region" aria-label="Notifications" hidden>
                    <div class="notif-panel-head">
                        <strong>Notifications</strong>
                        <form action="<?= base_url('notifications/mark-all-read') ?>" method="post" class="inline-form" data-notif-markall>
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-ghost btn-sm">Mark all read</button>
                        </form>
                    </div>
                    <div class="notif-list" data-notif-list aria-live="polite">
                        <p class="notif-empty">Loading…</p>
                    </div>
                    <a href="<?= base_url('notifications') ?>" class="notif-all">See all notifications <?= icon('chevron') ?></a>
                </div>
            </div>
        <?php endif; ?>
        <?= theme_toggle() ?>

        <a href="<?= base_url($isAdmin ? 'admin/dashboard' : 'profile') ?>" class="topbar-user<?= nav_attrs('profile') ?>" title="<?= $isAdmin ? 'Admin overview' : 'Your profile' ?>">
            <?= avatar($me, 'sm') ?>
            <span class="topbar-user-name">
                <?= esc($me['first_name']) ?>
                <small><?= $isAdmin ? 'Administrator' : 'View profile' ?></small>
            </span>
        </a>
    </nav>
</header>
<?= $this->include('includes/talkjs_session') ?>
<?php else: ?>
<header class="site-header">
    <?= brand_mark(base_url('login')) ?>

    <nav class="site-nav" aria-label="Main">
        <?php foreach (['about' => 'About', 'contact' => 'Contact'] as $path => $label): ?>
            <a href="<?= base_url($path) ?>"<?= $current === $path ? ' aria-current="page"' : '' ?>><?= $label ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="site-actions">
        <?= theme_toggle() ?>
        <?php if ($current !== 'login' && $current !== ''): ?>
            <a href="<?= base_url('login') ?>" class="btn btn-ghost btn-sm">Log in</a>
        <?php endif; ?>
        <?php if ($current !== 'register'): ?>
            <a href="<?= base_url('register') ?>" class="btn btn-primary btn-sm">Sign up</a>
        <?php endif; ?>
    </div>
</header>
<?php endif; ?>
