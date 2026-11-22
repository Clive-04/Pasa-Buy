<?php
use App\Models\UserModel;

if (session()->has('user_id')) {
    $userModel = new UserModel();
    $user = $userModel->find(session('user_id'));

    $user_name = session('user_name');
    $first_name = explode(' ', $user_name)[0] ?? 'Admin';
    $avatar = strtoupper(substr($first_name, 0, 1));
} else {
    $user = null;
    $first_name = 'Admin';
    $avatar = 'A';
}
?>

<button class="sidebar-toggle" type="button" aria-label="Open sidebar">
    ☰
</button>

<div class="sidebar-overlay"></div>

<aside class="dashboard-sidebar" id="dashboardSidebar">

    <!-- MOBILE HEADER -->
    <div class="sidebar-mobile-top">
        <span class="sidebar-mobile-title">Admin Menu</span>
        <button class="sidebar-close" type="button">✕</button>
    </div>

    <!-- PROFILE -->
    <div class="dashboard-profile-card">
        <div class="profile-avatar">
            <?php if (!empty($user['profile_photo'])): ?>
                <img src="<?= base_url('uploads/profile_photos/' . $user['profile_photo']) ?>" alt="Profile Photo">
            <?php else: ?>
                <?= $avatar ?>
            <?php endif; ?>
        </div>

        <h3><?= esc($first_name) ?></h3>
        <p>Administrator</p>
    </div>

    <!-- MENU -->
    <nav class="dashboard-menu">

        <a href="<?= base_url('admin/dashboard') ?>" class="dashboard-link">
            Dashboard
        </a>

        <a href="<?= base_url('logout') ?>" class="dashboard-link logout-link">
            Logout
        </a>

    </nav>
</aside>