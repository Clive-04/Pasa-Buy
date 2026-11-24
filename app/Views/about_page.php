<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$team = [
    ['img/team/reniedo.png', 'Bea Nicole Reniedo', 'Project Lead', 'Oversees the system design and keeps the platform efficient and organized.'],
    ['img/team/luce.jpg', 'Alexa May "Cy" Luce', 'Market & Operations Strategist', 'Handles market planning and helps ensure the platform meets community needs.'],
    ['img/team/sales.jpg', 'Marjhelly P. Sales', 'User Experience & Design Specialist', 'Designs a simple and user-friendly interface for a smooth experience.'],
];
// Steps and principles describe how Pasa-Buy actually works today.
$steps = [
    ['Post what you need', 'Name the item, the store and where to bring it. Set a service fee for the runner.'],
    ['A neighbour runs it', 'A runner nearby accepts, buys the items and marks the errand on the way.'],
    ['Delivered, paid, rated', 'Pay by wallet or cash on delivery, then rate each other. Every delivery counts.'],
];
$values = [
    ['users', 'Local first', 'Errands are run by people in your own community, for stores you already know.'],
    ['cash', 'Fair to runners', 'Runners keep the service fee minus a 10% platform fee. No hidden cuts.'],
    ['shield', 'Safe by design', 'Chats open only between a requester and their runner. Anyone can be reported or blocked.'],
];
$loggedIn = (bool) session('isLoggedIn');
?>

<main class="site-main about" id="main-content">
    <div class="content-wrap">
        <section class="story-hero" aria-labelledby="aboutTitle">
            <div class="story-hero-text">
                <p class="eyebrow">About Neighborly</p>
                <h1 id="aboutTitle" data-reveal-words>Errands, run by <span class="ink-mark">neighbours</span>.</h1>
                <p class="story-lede">Neighborly is a local technology startup from Lucban, Quezon. We build community platforms that connect residents, support local businesses, and make everyday errands faster and easier. Pasa-Buy is the first.</p>
            </div>
            <dl class="fact-sheet">
                <div><dt>Founded</dt><dd>2026</dd></div>
                <div><dt>Based in</dt><dd>Lucban, Quezon</dd></div>
                <div><dt>First product</dt><dd>Pasa-Buy</dd></div>
                <div><dt>Team</dt><dd><?= count($team) ?> people</dd></div>
            </dl>
        </section>

        <section class="how" aria-labelledby="howTitle">
            <div class="section-intro">
                <p class="eyebrow">How it works</p>
                <h2 id="howTitle">Three steps from “I need this” to “it’s here”.</h2>
            </div>
            <ol class="how-steps" data-draw-line>
                <?php foreach ($steps as $i => [$title, $text]): ?>
                    <li class="how-step">
                        <span class="how-num" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
                        <h3><?= esc($title) ?></h3>
                        <p><?= esc($text) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>

        <section class="values" aria-labelledby="valuesTitle">
            <div class="section-intro">
                <p class="eyebrow">What we stand for</p>
                <h2 id="valuesTitle">Built for the people who use it.</h2>
            </div>
            <ul class="value-list">
                <?php foreach ($values as [$iconName, $title, $text]): ?>
                    <li>
                        <span class="value-icon"><?= icon($iconName) ?></span>
                        <div>
                            <h3><?= esc($title) ?></h3>
                            <p><?= esc($text) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="team" aria-labelledby="teamTitle">
            <div class="section-intro">
                <p class="eyebrow">The team</p>
                <h2 id="teamTitle">The people behind Pasa-Buy.</h2>
            </div>
            <div class="team-grid">
                <?php foreach ($team as [$photo, $name, $role, $bio]): ?>
                    <article class="team-card">
                        <div class="team-photo"><img src="<?= base_url($photo) ?>" alt="<?= esc($name, 'attr') ?>" loading="lazy"></div>
                        <p class="team-role"><?= esc($role) ?></p>
                        <h3><?= esc($name) ?></h3>
                        <p class="team-bio"><?= esc($bio) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="cta-band" aria-labelledby="ctaTitle">
            <div>
                <h2 id="ctaTitle">Need something from the store?</h2>
                <p>Post it in a minute, or earn by running an errand for a neighbour.</p>
            </div>
            <div class="cta-actions">
                <?php if ($loggedIn): ?>
                    <a href="<?= base_url('post-request') ?>" class="btn btn-accent btn-lg"><?= icon('plus') ?> Post a request</a>
                    <a href="<?= base_url('browse-requests') ?>" class="btn btn-light-on-hero btn-lg">Find requests <?= icon('arrow-right') ?></a>
                <?php else: ?>
                    <a href="<?= base_url('register') ?>" class="btn btn-accent btn-lg">Create an account <?= icon('arrow-right') ?></a>
                    <a href="<?= base_url('login') ?>" class="btn btn-light-on-hero btn-lg">Log in</a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
