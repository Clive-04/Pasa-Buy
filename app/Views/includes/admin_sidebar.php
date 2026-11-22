<?php
$adminUser = db_connect()->table('users')
    ->select('first_name, profile_photo')
    ->where('id', (int) session('user_id'))
    ->get()->getRowArray();
?>

<button class="sidebar-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="dashboardSidebar">
    ☰
</button>

<div class="sidebar-overlay"></div>

<aside class="dashboard-sidebar" id="dashboardSidebar" aria-label="Admin menu">

    <div class="sidebar-mobile-top">
        <span class="sidebar-mobile-title">Admin Menu</span>
        <button class="sidebar-close" type="button" aria-label="Close menu">✕</button>
    </div>

    <div class="dashboard-profile-card">
        <div class="profile-avatar">
            <?php if (avatar_url($adminUser['profile_photo'] ?? null)): ?>
                <img src="<?= esc(avatar_url($adminUser['profile_photo']), 'attr') ?>" alt="">
            <?php else: ?>
                <?= esc(initials($adminUser['first_name'] ?? 'A')) ?>
            <?php endif; ?>
        </div>

        <h3><?= esc($adminUser['first_name'] ?? 'Admin') ?></h3>
        <p>Administrator</p>
    </div>

    <nav class="dashboard-menu" aria-label="Admin">
        <a href="<?= base_url('admin/dashboard') ?>" class="dashboard-link">Dashboard</a>

        <form action="<?= base_url('logout') ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="dashboard-link logout-link">Logout</button>
        </form>
    </nav>
</aside>
