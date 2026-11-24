<?php
/* Admin navigation rail: same component and active-state rules as the user rail. */
$db          = db_connect();
$openReports = (int) $db->table('user_reports')->whereIn('status', ['open', 'reviewing'])->countAllResults();
$pendingTop  = (int) $db->table('wallet_topups')->where('status', 'pending')->countAllResults();
?>
<div class="sidebar-overlay"></div>

<aside class="dashboard-sidebar app-rail" id="dashboardSidebar" aria-label="Admin menu">
    <div class="sidebar-mobile-top">
        <span class="sidebar-mobile-title">Admin menu</span>
        <button class="sidebar-close icon-btn" type="button" aria-label="Close menu"><?= icon('x') ?></button>
    </div>

    <nav class="side-nav" aria-label="Admin pages">
        <p class="side-heading">Administrator</p>
        <a href="<?= base_url('admin/dashboard') ?>" class="side-link<?= nav_attrs('admin/dashboard') ?>"><?= icon('dashboard') ?><span class="side-label">Overview</span></a>
        <a href="<?= base_url('admin/requests') ?>" class="side-link<?= nav_attrs('admin/requests') ?>"><?= icon('package') ?><span class="side-label">Requests</span></a>
        <a href="<?= base_url('admin/users') ?>" class="side-link<?= nav_attrs('admin/users') ?>"><?= icon('users') ?><span class="side-label">Users</span></a>
        <a href="<?= base_url('admin/topups') ?>" class="side-link<?= nav_attrs('admin/topups') ?>"><?= icon('wallet') ?><span class="side-label">Top-ups</span><?= count_badge($pendingTop, $pendingTop === 1 ? 'top-up still pending' : 'top-ups still pending') ?></a>
        <a href="<?= base_url('admin/reports') ?>" class="side-link<?= nav_attrs('admin/reports') ?>"><?= icon('shield') ?><span class="side-label">Reports</span><?= count_badge($openReports, $openReports === 1 ? 'report to review' : 'reports to review') ?></a>
    </nav>

    <div class="side-foot">
        <a href="<?= base_url('about') ?>" class="side-link<?= nav_attrs('about') ?>"><?= icon('help') ?><span class="side-label">About Pasa-Buy</span></a>
        <form action="<?= base_url('logout') ?>" method="post" class="side-logout" data-turbo="false">
            <?= csrf_field() ?>
            <button type="submit" class="side-link side-link-danger" data-open-dialog="logoutDialog"><?= icon('logout') ?><span class="side-label">Log out</span></button>
        </form>
    </div>
</aside>
<?= $this->include('includes/logout_dialog') ?>
