<?php
/* Admin navigation rail: same component and active-state rules as the user rail. */
$tab        = (string) service('request')->getGet('tab');
$onOverview = trim(uri_string(), '/') === 'admin/dashboard';
$openReports = (int) db_connect()->table('user_reports')->whereIn('status', ['open', 'reviewing'])->countAllResults();
$current    = ' is-active" aria-current="page';
?>
<div class="sidebar-overlay"></div>

<aside class="dashboard-sidebar app-rail" id="dashboardSidebar" aria-label="Admin menu">
    <div class="sidebar-mobile-top">
        <span class="sidebar-mobile-title">Admin menu</span>
        <button class="sidebar-close icon-btn" type="button" aria-label="Close menu"><?= icon('x') ?></button>
    </div>

    <nav class="side-nav" aria-label="Admin pages">
        <p class="side-heading">Administrator</p>
        <a href="<?= base_url('admin/dashboard') ?>" class="side-link<?= $onOverview && $tab !== 'delivered' ? $current : '' ?>"><?= icon('dashboard') ?><span class="side-label">Overview</span></a>
        <a href="<?= base_url('admin/dashboard?tab=delivered') ?>" class="side-link<?= $onOverview && $tab === 'delivered' ? $current : '' ?>"><?= icon('check-circle') ?><span class="side-label">Delivered requests</span></a>
        <a href="<?= base_url('admin/reports') ?>" class="side-link<?= nav_attrs('admin/reports') ?>"><?= icon('shield') ?><span class="side-label">Reports</span><?= count_badge($openReports, $openReports === 1 ? 'report to review' : 'reports to review') ?></a>
    </nav>

    <div class="side-foot">
        <a href="<?= base_url('about') ?>" class="side-link<?= nav_attrs('about') ?>"><?= icon('help') ?><span class="side-label">About Pasa-Buy</span></a>
        <form action="<?= base_url('logout') ?>" method="post" class="side-logout">
            <?= csrf_field() ?>
            <button type="submit" class="side-link side-link-danger" data-open-dialog="logoutDialog"><?= icon('logout') ?><span class="side-label">Log out</span></button>
        </form>
    </div>
</aside>
<?= $this->include('includes/logout_dialog') ?>
