<?php
/*
 * Main navigation rail (signed-in users). Full height under the top bar,
 * icon-only at intermediate widths (labels stay in the DOM as accessible
 * names and appear as tooltips), a drawer on small screens.
 * The profile entry lives in the top bar only.
 */
$me      = current_user();
$actions = action_items();
$nReq    = count($actions['requests']);
$nDel    = count($actions['deliveries']);
?>
<div class="sidebar-overlay"></div>

<aside class="dashboard-sidebar app-rail" id="dashboardSidebar" aria-label="Main menu">
    <div class="sidebar-mobile-top">
        <span class="sidebar-mobile-title">Menu</span>
        <button class="sidebar-close icon-btn" type="button" aria-label="Close menu"><?= icon('x') ?></button>
    </div>

    <nav class="side-nav" aria-label="Pages">
        <a href="<?= base_url('dashboard') ?>" class="side-link<?= nav_attrs('dashboard') ?>"><?= icon('dashboard') ?><span class="side-label">Dashboard</span></a>

        <p class="side-heading">I need something</p>
        <a href="<?= base_url('post-request') ?>" class="side-link<?= nav_attrs('post-request') ?>"><?= icon('plus') ?><span class="side-label">Post a request</span></a>
        <a href="<?= base_url('my-requests') ?>" class="side-link<?= nav_attrs('my-requests') ?>"><?= icon('list') ?><span class="side-label">My requests</span><?= count_badge($nReq, $nReq === 1 ? 'request needs you (pay or rate)' : 'requests need you (pay or rate)') ?></a>

        <p class="side-heading">I want to earn</p>
        <a href="<?= base_url('browse-requests') ?>" class="side-link<?= nav_attrs('browse-requests') ?>"><?= icon('search') ?><span class="side-label">Find requests</span></a>
        <a href="<?= base_url('accepted-requests') ?>" class="side-link<?= nav_attrs('accepted-requests') ?>"><?= icon('truck') ?><span class="side-label">My deliveries</span><?= count_badge($nDel, $nDel === 1 ? 'delivery needs an update or rating' : 'deliveries need an update or rating') ?></a>

        <p class="side-heading">Account</p>
        <a href="<?= base_url('messages') ?>" class="side-link<?= nav_attrs('messages') ?>"><?= icon('message') ?><span class="side-label">Messages</span><?= unread_badge('messages') ?></a>
        <a href="<?= base_url('notifications') ?>" class="side-link<?= nav_attrs('notifications') ?>"><?= icon('bell') ?><span class="side-label">Notifications</span><?= unread_badge('notifications') ?></a>
        <a href="<?= base_url('wallet') ?>" class="side-link<?= nav_attrs('wallet') ?>"><?= icon('wallet') ?><span class="side-label"><?= is_demo_mode() ? 'Wallet (test)' : 'Wallet' ?> <small class="side-meta"><?= money($me['wallet_balance'] ?? '0') ?></small></span></a>
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
