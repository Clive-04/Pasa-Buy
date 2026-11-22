<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php /* The former <meta http-equiv="refresh" content="5"> reloaded every page
             every 5 seconds and wiped form input; badges now refresh in place. */ ?>
    <meta name="csrf-token-name" content="<?= esc(csrf_token(), 'attr') ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <link rel="icon" type="image/png" href="<?= base_url('img/favicon.PNG') ?>">

    <title><?= esc($title ?? 'Pasa-Buy') ?></title>

    <link rel="stylesheet" href="<?= base_url('css/landing.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/auth.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/post-request.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/request.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/messages.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/about.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/contact.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/profile.css') ?>">
    <?php if (str_starts_with(uri_string(), 'admin')): ?>
        <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="<?= base_url('js/responsive-layout.js') ?>" defer></script>
    <script src="<?= base_url('js/app.js') ?>" defer></script>
</head>
<body<?php if (session('isLoggedIn')): ?> data-poll-url="<?= esc(base_url('notifications/unread-count'), 'attr') ?>" data-poll-seconds="<?= (int) config('PasaBuy')->notificationPollSeconds ?>"<?php endif; ?>>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?php if (is_demo_mode()): ?>
        <div class="demo-banner" role="note">
            Demo version: wallet balances are simulated credits, not real money. Please don't enter real personal details.
        </div>
    <?php endif; ?>
