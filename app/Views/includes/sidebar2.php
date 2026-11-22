<?php $me = current_user(); ?>
<div class="sidebar-overlay"></div>

<aside class="dashboard-sidebar app-sidebar" id="dashboardSidebar" aria-label="Main menu">
    <div class="sidebar-mobile-top">
        <span class="sidebar-mobile-title">Menu</span>
        <button class="sidebar-close icon-btn" type="button" aria-label="Close menu"><?= icon('x') ?></button>
    </div>

    <a href="<?= base_url('profile') ?>" class="side-profile<?= nav_attrs('profile') ?>">
        <?= avatar($me, 'md') ?>
        <span>
            <strong><?= esc(trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''))) ?></strong>
            <small>My profile <?= icon('chevron') ?></small>
        </span>
    </a>

    <a href="<?= base_url('wallet') ?>" class="side-balance<?= nav_attrs('wallet') ?>">
        <span><?= icon('wallet') ?> <?= is_demo_mode() ? 'Wallet (test)' : 'Wallet' ?></span>
        <strong><?= money($me['wallet_balance'] ?? '0') ?></strong>
    </a>

    <nav class="side-nav" aria-label="Pages">
        <a href="<?= base_url('dashboard') ?>" class="side-link<?= nav_attrs('dashboard') ?>"><?= icon('dashboard') ?><span>Dashboard</span></a>

        <p class="side-heading">I need something</p>
        <a href="<?= base_url('post-request') ?>" class="side-link<?= nav_attrs('post-request') ?>"><?= icon('plus') ?><span>Post a request</span></a>
        <a href="<?= base_url('my-requests') ?>" class="side-link<?= nav_attrs('my-requests') ?>"><?= icon('list') ?><span>My requests</span></a>

        <p class="side-heading">I want to earn</p>
        <a href="<?= base_url('browse-requests') ?>" class="side-link<?= nav_attrs('browse-requests') ?>"><?= icon('search') ?><span>Find requests</span></a>
        <a href="<?= base_url('accepted-requests') ?>" class="side-link<?= nav_attrs('accepted-requests') ?>"><?= icon('truck') ?><span>My deliveries</span></a>

        <p class="side-heading">Talk</p>
        <a href="<?= base_url('messages') ?>" class="side-link<?= nav_attrs('messages') ?>"><?= icon('message') ?><span>Messages</span><?= unread_badge('messages') ?></a>

        <form action="<?= base_url('logout') ?>" method="post" class="side-logout">
            <?= csrf_field() ?>
            <button type="submit" class="side-link side-link-danger" data-open-dialog="logoutDialog"><?= icon('logout') ?><span>Log out</span></button>
        </form>
    </nav>
</aside>
<?= $this->include('includes/logout_dialog') ?>
