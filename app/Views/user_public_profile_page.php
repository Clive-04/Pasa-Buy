<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$fullName      = trim(($profileUser['first_name'] ?? '') . ' ' . ($profileUser['last_name'] ?? ''));
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs' => [['Search people', 'users/search'], [$fullName, null]],
                'title'  => 'Public profile',
                'lead'   => 'Email, mobile number and address are private and never shown here.',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <section class="hero-card profile-hero" aria-label="Profile summary">
                <?= avatar($profileUser, 'lg') ?>
                <div class="profile-hero-text">
                    <h2 class="profile-name"><?= esc($fullName) ?></h2>
                    <p class="handle"><?= ! empty($profileUser['username']) ? '@' . esc($profileUser['username']) : 'Pasa-Buy member' ?></p>
                    <div class="chips">
                        <span class="chip chip-on-hero"><?= icon('calendar') ?> Member since <?= esc(date('F Y', strtotime($profileUser['created_at']))) ?></span>
                        <span class="chip chip-on-hero"><?= icon('star') ?> <?= $averageRating !== null ? esc($averageRating) . ' from ' . (int) $ratingCount . ' rating' . ($ratingCount === 1 ? '' : 's') : 'No ratings yet' ?></span>
                        <span class="chip chip-on-hero"><?= icon('package') ?> <?= (int) $totalRequests ?> request<?= (int) $totalRequests === 1 ? '' : 's' ?> posted</span>
                    </div>
                </div>
                <?php if (! $isOwnProfile): ?>
                    <a href="<?= base_url('messages?user_id=' . (int) $profileUser['id']) ?>" class="btn btn-accent"><?= icon('message') ?> Message</a>
                <?php endif; ?>
            </section>

            <div class="profile-grid">
                <section class="card" aria-labelledby="aboutHeading">
                    <div class="card-head"><h2 id="aboutHeading"><?= icon('info') ?> About</h2></div>
                    <p style="color: var(--ink-2);"><?= ! empty($profileUser['about_me']) ? nl2br(esc($profileUser['about_me'])) : esc($profileUser['first_name']) . ' has not written anything yet.' ?></p>
                </section>

                <section class="card" aria-labelledby="rateHeading">
                    <div class="card-head"><h2 id="rateHeading"><?= icon('star') ?> Ratings</h2></div>
                    <?php if ($isOwnProfile): ?>
                        <p class="text-muted">This is how others see your profile. <a class="link" href="<?= base_url('profile') ?>">Edit my profile <?= icon('chevron') ?></a></p>
                    <?php else: ?>
                        <p class="text-muted" style="margin:0;">Ratings come from completed errands. After a delivery with <?= esc($profileUser['first_name']) ?>, open that request to rate it. Each delivery counts once.</p>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
