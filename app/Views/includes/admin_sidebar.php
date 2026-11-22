<?php $me = current_user(); ?>
<div class="sidebar-overlay"></div>

<aside class="dashboard-sidebar app-sidebar" id="dashboardSidebar" aria-label="Admin menu">
    <div class="sidebar-mobile-top">
        <span class="sidebar-mobile-title">Admin menu</span>
        <button class="sidebar-close icon-btn" type="button" aria-label="Close menu"><?= icon('x') ?></button>
    </div>

    <div class="side-profile">
        <?= avatar($me, 'md') ?>
        <span>
            <strong><?= esc(trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''))) ?></strong>
            <small><?= icon('shield') ?> Administrator</small>
        </span>
    </div>

    <nav class="side-nav" aria-label="Admin pages">
        <a href="<?= base_url('admin/dashboard') ?>" class="side-link<?= nav_attrs('admin/dashboard') ?>"><?= icon('dashboard') ?><span>Overview<small>Requests and platform fees</small></span></a>
        <a href="<?= base_url('admin/dashboard?tab=delivered') ?>" class="side-link"><?= icon('check-circle') ?><span>Delivered requests</span></a>

        <p class="side-heading">Help</p>
        <a href="<?= base_url('about') ?>" class="side-link"><?= icon('info') ?><span>About Pasa-Buy</span></a>

        <form action="<?= base_url('logout') ?>" method="post" class="side-logout">
            <?= csrf_field() ?>
            <button type="submit" class="side-link side-link-danger" data-open-dialog="logoutDialog"><?= icon('logout') ?><span>Log out</span></button>
        </form>
    </nav>
</aside>
<?= $this->include('includes/logout_dialog') ?>
