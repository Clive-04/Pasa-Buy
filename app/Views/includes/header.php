<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="5">
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
    <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <script src="<?= base_url('js/sidebar-active.js') ?>" defer></script>
    <script src="<?= base_url('js/responsive-layout.js') ?>" defer></script>