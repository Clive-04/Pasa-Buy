<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f2f7f5" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#061a14" media="(prefers-color-scheme: dark)">
    <meta name="csrf-token-name" content="<?= esc(csrf_token(), 'attr') ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <link rel="icon" type="image/png" href="<?= base_url('img/favicon.PNG') ?>">

    <title><?= esc($title ?? 'Pasa-Buy') ?></title>

    <?php /* Apply the saved light/dark choice before first paint (no flash). */ ?>
    <script>
        try {
            var t = localStorage.getItem('pasabuy-theme');
            if (t === 'light' || t === 'dark') { document.documentElement.setAttribute('data-theme', t); }
        } catch (e) {}
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/pages.css') ?>">

    <script src="<?= asset('js/responsive-layout.js') ?>" defer></script>
    <script src="<?= asset('js/app.js') ?>" defer></script>
</head>
<body<?php if (session('isLoggedIn')): ?> data-poll-url="<?= esc(base_url('notifications/unread-count'), 'attr') ?>" data-poll-seconds="<?= (int) config('PasaBuy')->notificationPollSeconds ?>"<?php endif; ?>>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?php if (is_demo_mode()): ?>
        <div class="demo-banner" role="note">
            Demo version: wallet balances are simulated credits, not real money. Please don't enter real personal details.
        </div>
    <?php endif; ?>
