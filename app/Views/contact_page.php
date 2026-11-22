<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$members = [
    ['Alexa May A. Luce', '0997 941 8978', 'amaluce@slsu.edu.ph'],
    ['Bea Nicole Reniedo', '0951 914 9351', 'bnreniedo@slsu.edu.ph'],
    ['Marjhelly P. Sales', '0905 348 5137', 'mjhellysales@slsu.edu.ph'],
];
?>

<main class="<?= session('isLoggedIn') ? 'dashboard-page' : 'site-main' ?>" id="main-content">
    <div class="content-wrap">
        <section class="hero-card" aria-labelledby="contactTitle">
            <p class="eyebrow" style="background: rgba(255,255,255,.12); color: var(--mint); position:relative; z-index:1;">We're here to help</p>
            <h1 id="contactTitle" style="font-size: clamp(1.8rem, 1.3rem + 2vw, 2.6rem); position:relative; z-index:1;">Contact us</h1>
            <p style="margin-top:12px; max-width:60ch; position:relative; z-index:1;">Have questions or need assistance? Reach out and we'll get back to you as soon as possible.</p>
            <ul class="contact-list">
                <li><?= icon('mail') ?> <a href="mailto:support@pasabuy.com">support@pasabuy.com</a></li>
                <li><?= icon('phone') ?> 0951 914 9351</li>
                <li><?= icon('map-pin') ?> Lucban, Quezon, Philippines</li>
            </ul>
        </section>

        <section aria-labelledby="teamContactTitle">
            <h2 id="teamContactTitle" class="section-title"><?= icon('users') ?> Contact the team</h2>
            <div class="team-grid">
                <?php foreach ($members as [$name, $phone, $email]): ?>
                    <article class="card contact-card">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <?= avatar(['first_name' => explode(' ', $name)[0], 'last_name' => explode(' ', $name)[count(explode(' ', $name)) - 1]], 'md') ?>
                            <div>
                                <h3 style="font-size:1rem;"><?= esc($name) ?></h3>
                                <span class="text-muted" style="font-size:.82rem;">Team member</span>
                            </div>
                        </div>
                        <dl>
                            <div><dt><?= icon('phone') ?><span class="visually-hidden">Phone</span></dt><dd><?= esc($phone) ?></dd></div>
                            <div><dt><?= icon('mail') ?><span class="visually-hidden">Email</span></dt><dd><a class="link" href="mailto:<?= esc($email, 'attr') ?>"><?= esc($email) ?></a></dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
