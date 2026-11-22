<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$team = [
    ['img/team/reniedo.png', 'Bea Nicole Reniedo', 'Project Lead', 'Oversees the system design and keeps the platform efficient and organized.'],
    ['img/team/luce.jpg', 'Alexa May "Cy" Luce', 'Market & Operations Strategist', 'Handles market planning and helps ensure the platform meets community needs.'],
    ['img/team/sales.jpg', 'Marjhelly P. Sales', 'User Experience & Design Specialist', 'Designs a simple and user-friendly interface for a smooth experience.'],
];
?>

<main class="<?= session('isLoggedIn') ? 'dashboard-page' : 'site-main' ?>" id="main-content">
    <div class="content-wrap">
        <section class="hero-card" aria-labelledby="aboutTitle">
            <p class="eyebrow" style="background: rgba(255,255,255,.12); color: var(--mint); position:relative; z-index:1;">About us</p>
            <h1 id="aboutTitle" style="font-size: clamp(1.8rem, 1.3rem + 2vw, 2.6rem); position:relative; z-index:1;">About Neighborly</h1>
            <p style="margin-top:12px; max-width:60ch; font-size:1.05rem; position:relative; z-index:1;">
                Neighborly is a local technology startup founded in 2026 in Lucban, Quezon.
            </p>
            <p style="margin-top:8px; max-width:60ch; position:relative; z-index:1;">
                It develops community-based digital platforms that connect residents, support local businesses, and make everyday transactions faster, easier, and more accessible.
            </p>
        </section>

        <section aria-labelledby="teamTitle">
            <h2 id="teamTitle" class="section-title"><?= icon('users') ?> Meet the team</h2>
            <div class="team-grid">
                <?php foreach ($team as [$photo, $name, $role, $bio]): ?>
                    <article class="card team-card">
                        <div class="team-photo"><img src="<?= base_url($photo) ?>" alt="<?= esc($name, 'attr') ?>" loading="lazy"></div>
                        <h3><?= esc($name) ?></h3>
                        <span class="team-role"><?= esc($role) ?></span>
                        <p><?= esc($bio) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
