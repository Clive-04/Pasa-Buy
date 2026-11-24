<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#eef2f9" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#060e1c" media="(prefers-color-scheme: dark)">
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

    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>" data-turbo-track="reload">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>" data-turbo-track="reload">
    <link rel="stylesheet" href="<?= asset('css/pages.css') ?>" data-turbo-track="reload">
    <link rel="stylesheet" href="<?= asset('css/colour.css') ?>" data-turbo-track="reload">
    <link rel="stylesheet" href="<?= asset('css/brand-pages.css') ?>" data-turbo-track="reload">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>" data-turbo-track="reload">
    <link rel="stylesheet" href="<?= asset('css/motion.css') ?>" data-turbo-track="reload">

    <?php
    /*
     * Turbo Drive: links and forms load the next page in the background and
     * swap the body, so the page doesn't flash or reload its CSS/JS. The PHP
     * still returns normal pages, and everything works without JavaScript.
     *  - view-transition: same-page swaps use the View Transitions in motion.css
     *  - prefetch off: some GET pages have side effects (opening a chat or a
     *    notification marks it read), so nothing loads on mere hover
     *  - the chat pages always load in full (their live connection and polling
     *    are page-bound), see turbo-visit-control below
     */
    ?>
    <meta name="view-transition" content="same-origin">
    <meta name="turbo-prefetch" content="false">
    <?php if (str_starts_with(trim(uri_string(), '/'), 'messages')): ?>
        <meta name="turbo-visit-control" content="reload">
    <?php endif; ?>
    <script type="module" src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.12/dist/turbo.es2017-esm.js" data-turbo-track="reload"></script>

    <script src="<?= asset('js/responsive-layout.js') ?>" defer data-turbo-track="reload"></script>
    <script src="<?= asset('js/app.js') ?>" defer data-turbo-track="reload"></script>
    <script src="<?= asset('js/motion.js') ?>" defer data-turbo-track="reload"></script>
</head>
<body<?php if (session('isLoggedIn')): ?> data-poll-url="<?= esc(base_url('notifications/unread-count'), 'attr') ?>" data-poll-seconds="<?= (int) config('PasaBuy')->notificationPollSeconds ?>"<?php endif; ?>>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?php /* The demo notice now appears where it matters: in the wallet top-up dialog. */ ?>
