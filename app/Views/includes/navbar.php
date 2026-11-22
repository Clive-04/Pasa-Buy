<?php 
$current = uri_string();

$isLoggedIn = session()->has('user_id');
$role = session('role'); // make sure this exists

if ($isLoggedIn) {
    $homeLink = ($role === 'admin')
        ? base_url('admin/dashboard')
        : base_url('dashboard');
} else {
    $homeLink = base_url('login');
}
?>
<nav class="navbar">
    <div class="nav-left">
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navMenu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <a href="<?= $homeLink ?>" class="logo">
            <img src="<?= base_url('img/logo.PNG') ?>" alt="Pasa-Buy Logo" class="logo-img">
            <span class="logo-text">Pasa-Buy</span>
        </a>
    </div>

    <div class="nav-center nav-menu" id="navMenu">
        <a href="<?= $homeLink ?>"
            class="nav-link <?= (
                $current == '' || 
                $current == 'dashboard' || 
                $current == 'login' ||
                $current == 'admin/dashboard'
            ) ? 'active' : '' ?>">
            Home
        </a>

        <a href="<?= base_url('about') ?>"
            class="nav-link <?= ($current == 'about') ? 'active' : '' ?>">
            About
        </a>

        <a href="<?= base_url('contact') ?>"
            class="nav-link <?= ($current == 'contact') ? 'active' : '' ?>">
            Contact
        </a>
    </div>

    <div class="nav-right nav-actions">
        <?php if (session()->has('user_id')): ?>

        <?php if (session('role') !== 'admin'): ?>
            <!-- ✅ SEARCH BAR (USER ONLY) -->
            <form action="<?= base_url('users/search') ?>" method="get" class="nav-search-form">
                <input type="text" name="q" class="nav-search-input" placeholder="Search users..." value="<?= esc($_GET['q'] ?? '') ?>">
                <button type="submit" class="nav-search-btn">Search</button>
            </form>
        <?php endif; ?>

        <!-- ✅ ALWAYS SHOW WELCOME -->
        <span class="nav-welcome">
            Welcome, <?= session('user_name') ? explode(' ', session('user_name'))[0] : 'User' ?>!
        </span>

    <?php else: ?>
            <a href="<?= base_url('login') ?>" class="nav-login">Login</a>
            <a href="<?= base_url('register') ?>" class="nav-signup">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>