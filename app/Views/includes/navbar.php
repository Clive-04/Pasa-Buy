<?php
$current    = uri_string();
$isLoggedIn = (bool) session('isLoggedIn');
$isAdmin    = session('role') === 'admin';
$homeLink   = $isLoggedIn ? base_url($isAdmin ? 'admin/dashboard' : 'dashboard') : base_url('login');
$isHome     = in_array($current, ['', 'dashboard', 'login', 'admin/dashboard'], true);
?>
<header>
<nav class="navbar" aria-label="Main">
    <div class="nav-left">
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navMenu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <a href="<?= $homeLink ?>" class="logo">
            <img src="<?= base_url('img/logo.PNG') ?>" alt="" class="logo-img">
            <span class="logo-text">Pasa-Buy</span>
        </a>
    </div>

    <div class="nav-center nav-menu" id="navMenu">
        <a href="<?= $homeLink ?>" class="nav-link <?= $isHome ? 'active' : '' ?>"<?= $isHome ? ' aria-current="page"' : '' ?>>Home</a>
        <a href="<?= base_url('about') ?>" class="nav-link <?= $current === 'about' ? 'active' : '' ?>"<?= $current === 'about' ? ' aria-current="page"' : '' ?>>About</a>
        <a href="<?= base_url('contact') ?>" class="nav-link <?= $current === 'contact' ? 'active' : '' ?>"<?= $current === 'contact' ? ' aria-current="page"' : '' ?>>Contact</a>
    </div>

    <div class="nav-right nav-actions">
        <?php if ($isLoggedIn): ?>
            <?php if (! $isAdmin): ?>
                <form action="<?= base_url('users/search') ?>" method="get" class="nav-search-form" role="search">
                    <label for="navSearch" class="visually-hidden">Search users</label>
                    <input type="search" id="navSearch" name="q" class="nav-search-input" placeholder="Search users..." value="<?= esc((string) service('request')->getGet('q')) ?>" maxlength="100">
                    <button type="submit" class="nav-search-btn">Search</button>
                </form>
            <?php endif; ?>

            <span class="nav-welcome">
                Welcome, <?= esc(explode(' ', (string) session('user_name'))[0] ?: 'User') ?>!
            </span>
        <?php else: ?>
            <a href="<?= base_url('login') ?>" class="nav-login">Login</a>
            <a href="<?= base_url('register') ?>" class="nav-signup">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>
</header>
