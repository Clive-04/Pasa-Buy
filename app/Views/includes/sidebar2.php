<?php
$sidebarUserId = (int) session('user_id');
$sidebarUser   = $sidebarUserId > 0
    ? db_connect()->table('users')->select('first_name, last_name, profile_photo')->where('id', $sidebarUserId)->get()->getRowArray()
    : null;

$notifCount        = 0;
$messageNotifCount = 0;

if ($sidebarUserId > 0) {
    $notifCount = db_connect()->table('request_notifications')
        ->where('user_id', $sidebarUserId)->where('is_read', 0)->countAllResults();

    $messageNotifCount = db_connect()->table('notifications')
        ->where('user_id', $sidebarUserId)->where('type', 'message')->where('is_read', 0)->countAllResults();
}

$badge = static fn (int $n): string => $n > 9 ? '9+' : (string) $n;
?>

<button class="sidebar-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="dashboardSidebar">
    ☰
</button>

<div class="sidebar-overlay"></div>

<aside class="dashboard-sidebar" id="dashboardSidebar" aria-label="Account menu">
    <div class="sidebar-mobile-top">
        <span class="sidebar-mobile-title">Menu</span>
        <button class="sidebar-close" type="button" aria-label="Close menu">✕</button>
    </div>

    <div class="dashboard-profile-card">
        <div class="profile-avatar">
            <?php if (avatar_url($sidebarUser['profile_photo'] ?? null)): ?>
                <img src="<?= esc(avatar_url($sidebarUser['profile_photo']), 'attr') ?>" alt="">
            <?php else: ?>
                <?= esc(initials($sidebarUser['first_name'] ?? 'U')) ?>
            <?php endif; ?>
        </div>

        <h3><?= esc($sidebarUser['first_name'] ?? 'User') ?></h3>
        <p>Community User</p>
    </div>

    <nav class="dashboard-menu" aria-label="Dashboard">
        <a href="<?= base_url('dashboard') ?>" class="dashboard-link">Dashboard</a>
        <a href="<?= base_url('post-request') ?>" class="dashboard-link">Post a Request</a>
        <a href="<?= base_url('browse-requests') ?>" class="dashboard-link">Browse Requests</a>
        <a href="<?= base_url('my-requests') ?>" class="dashboard-link">My Requests</a>
        <a href="<?= base_url('accepted-requests') ?>" class="dashboard-link">Accepted Requests</a>
        <a href="<?= base_url('wallet') ?>" class="dashboard-link">Wallet</a>

        <a href="<?= base_url('notifications') ?>" class="dashboard-link dashboard-link-with-badge">
            <span>Notifications</span>
            <span class="notif-badge" data-unread="notifications" <?= $notifCount > 0 ? '' : 'hidden' ?>>
                <?= $badge($notifCount) ?><span class="visually-hidden"> unread</span>
            </span>
        </a>

        <a href="<?= base_url('messages') ?>" class="dashboard-link dashboard-link-with-badge">
            <span>Messages</span>
            <span class="notif-badge" data-unread="messages" <?= $messageNotifCount > 0 ? '' : 'hidden' ?>>
                <?= $badge($messageNotifCount) ?><span class="visually-hidden"> unread</span>
            </span>
        </a>

        <a href="<?= base_url('profile') ?>" class="dashboard-link">Profile</a>

        <form action="<?= base_url('logout') ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="dashboard-link logout-link">Logout</button>
        </form>
    </nav>
</aside>
