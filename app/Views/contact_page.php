<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
// name, role, phone, email, photo
$members = [
    ['Bea Nicole Reniedo', 'Project Lead', '0951 914 9351', 'bnreniedo@slsu.edu.ph', 'img/team/reniedo.png'],
    ['Alexa May A. Luce', 'Market & Operations Strategist', '0997 941 8978', 'amaluce@slsu.edu.ph', 'img/team/luce.jpg'],
    ['Marjhelly P. Sales', 'User Experience & Design Specialist', '0905 348 5137', 'mjhellysales@slsu.edu.ph', 'img/team/sales.jpg'],
];
$tel = static fn (string $phone): string => 'tel:+63' . ltrim(preg_replace('/\D/', '', $phone), '0');

// Answers describe how Pasa-Buy works today.
$faq = [
    ['Is the wallet real money?', 'No. This is a demo: wallet balances are simulated credits, and top-ups run in the payment provider’s test mode. Please don’t enter real personal or payment details.'],
    ['Who can I message?', 'Chats open between a requester and the runner of an accepted errand. You can’t message strangers, which keeps spam and scams out.'],
    ['How much does a runner earn?', 'The runner gets the item cost back plus the service fee, minus a 10% platform fee on the service fee.'],
    ['Someone is bothering me. What can I do?', 'Use Report on their profile or in the chat, and an admin will review it. You can also Block them: neither of you can send new messages, and your earlier messages are kept.'],
];
?>

<main class="site-main contact" id="main-content">
    <div class="content-wrap">
        <section class="contact-hero" aria-labelledby="contactTitle">
            <div class="contact-hero-text">
                <p class="eyebrow">Contact</p>
                <h1 id="contactTitle" data-reveal-words>Let’s <span class="ink-mark">talk</span>.</h1>
                <p class="story-lede">Questions about an errand, your wallet or your account? Reach the Pasa-Buy team directly.</p>
            </div>
            <div class="contact-tiles">
                <a class="contact-tile" href="mailto:support@pasabuy.com">
                    <span class="contact-tile-icon"><?= icon('mail') ?></span>
                    <span><small>Email support</small><strong>support@pasabuy.com</strong></span>
                    <?= icon('arrow-right', 'contact-tile-go') ?>
                </a>
                <a class="contact-tile" href="<?= $tel('0951 914 9351') ?>">
                    <span class="contact-tile-icon"><?= icon('phone') ?></span>
                    <span><small>Call us</small><strong>0951 914 9351</strong></span>
                    <?= icon('arrow-right', 'contact-tile-go') ?>
                </a>
                <div class="contact-tile is-static">
                    <span class="contact-tile-icon"><?= icon('map-pin') ?></span>
                    <span><small>Based in</small><strong>Lucban, Quezon, Philippines</strong></span>
                </div>
            </div>
        </section>

        <section aria-labelledby="teamContactTitle">
            <div class="section-intro">
                <p class="eyebrow">Team directory</p>
                <h2 id="teamContactTitle">Reach a team member.</h2>
            </div>
            <ul class="directory">
                <?php foreach ($members as [$name, $role, $phone, $email, $photo]): ?>
                    <li class="directory-row">
                        <img class="directory-photo" src="<?= base_url($photo) ?>" alt="" loading="lazy">
                        <div class="directory-who">
                            <strong><?= esc($name) ?></strong>
                            <span><?= esc($role) ?></span>
                        </div>
                        <div class="directory-ways">
                            <span class="copy-field">
                                <a href="<?= $tel($phone) ?>"><?= icon('phone') ?> <?= esc($phone) ?></a>
                                <button type="button" class="copy-btn" data-copy="<?= esc($phone, 'attr') ?>" aria-label="Copy <?= esc($name, 'attr') ?>’s phone number"><?= icon('check', 'copy-done') ?><span class="copy-label">Copy</span></button>
                            </span>
                            <span class="copy-field">
                                <a href="mailto:<?= esc($email, 'attr') ?>"><?= icon('mail') ?> <?= esc($email) ?></a>
                                <button type="button" class="copy-btn" data-copy="<?= esc($email, 'attr') ?>" aria-label="Copy <?= esc($name, 'attr') ?>’s email"><?= icon('check', 'copy-done') ?><span class="copy-label">Copy</span></button>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="copy-status visually-hidden" role="status" aria-live="polite" data-copy-status></p>
        </section>

        <section aria-labelledby="faqTitle">
            <div class="section-intro">
                <p class="eyebrow">Before you write</p>
                <h2 id="faqTitle">Quick answers.</h2>
            </div>
            <div class="faq">
                <?php foreach ($faq as $i => [$q, $a]): ?>
                    <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
                        <summary><?= esc($q) ?><span class="faq-sign" aria-hidden="true"></span></summary>
                        <div class="faq-answer"><p><?= esc($a) ?></p></div>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
