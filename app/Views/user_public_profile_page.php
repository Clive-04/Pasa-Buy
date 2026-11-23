<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$fullName  = trim($profileUser['first_name'] . ' ' . $profileUser['last_name']);
$firstName = (string) $profileUser['first_name'];
$max       = max(1, max($summary['distribution']));
$reviewUrl = static fn (int $p): string => base_url('users/' . (int) $profileUser['id'] . ($p > 1 ? '?reviews=' . $p : '')) . '#reviews';
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs' => [['Search people', 'users/search'], [$fullName, null]],
                'title'  => $fullName,
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <section class="hero-card profile-hero" aria-label="Profile summary">
                <?= avatar($profileUser, 'lg') ?>
                <div class="profile-hero-text">
                    <p class="handle"><?= ! empty($profileUser['username']) ? '@' . esc($profileUser['username']) : 'Pasa-Buy member' ?> · member since <?= esc(date('F Y', strtotime($profileUser['created_at']))) ?></p>
                    <dl class="profile-stats">
                        <div><dt>Rating</dt><dd><?= $summary['average'] !== null ? '★ ' . esc(number_format($summary['average'], 1)) . ' <small>(' . $summary['count'] . ')</small>' : 'No ratings yet' ?></dd></div>
                        <div><dt>Deliveries completed</dt><dd><?= (int) $summary['deliveries'] ?></dd></div>
                        <div><dt>Requests posted</dt><dd><?= (int) $summary['requests'] ?></dd></div>
                    </dl>
                </div>
                <div class="profile-hero-actions">
                    <?php if ($isOwnProfile): ?>
                        <a href="<?= base_url('profile') ?>" class="btn btn-accent"><?= icon('edit') ?> Edit my profile</a>
                    <?php elseif ($canMessage): ?>
                        <a href="<?= base_url('messages?user_id=' . (int) $profileUser['id']) ?>" class="btn btn-accent"><?= icon('message') ?> Message</a>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (! $isOwnProfile): ?>
                <div class="profile-safety">
                    <?php if (! $canMessage): ?>
                        <p class="text-muted"><?= icon('lock') ?> <?= $iBlocked ? 'You blocked ' . esc($firstName) . '. Neither of you can send new messages.' : 'You can message ' . esc($firstName) . ' once one of you accepts the other’s request.' ?></p>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <?= view('includes/user_safety_actions', ['other' => $profileUser, 'iBlocked' => $iBlocked, 'returnTo' => 'users/' . (int) $profileUser['id'], 'requestId' => null]) ?>
                </div>
            <?php endif; ?>

            <div class="profile-grid">
                <section class="card" aria-labelledby="aboutHeading">
                    <div class="card-head"><h2 id="aboutHeading"><?= icon('info') ?> About</h2></div>
                    <p class="about-text"><?= ! empty($profileUser['about_me']) ? nl2br(esc($profileUser['about_me'])) : esc($firstName) . ' hasn’t written anything yet.' ?></p>
                </section>

                <section class="card" id="reviews" aria-labelledby="reviewsHeading">
                    <div class="card-head"><h2 id="reviewsHeading"><?= icon('star') ?> Reviews</h2></div>
                    <?php if ($summary['count'] === 0): ?>
                        <p class="text-muted">No reviews yet. Reviews come from completed errands, one per delivery.</p>
                    <?php else: ?>
                        <ul class="rating-bars" aria-label="Rating breakdown">
                            <?php foreach ($summary['distribution'] as $stars => $n): ?>
                                <li>
                                    <span><?= $stars ?> ★</span>
                                    <span class="rating-bar" aria-hidden="true"><span style="width: <?= round($n / $max * 100) ?>%"></span></span>
                                    <span class="rating-count"><?= (int) $n ?><span class="visually-hidden"> rating<?= $n === 1 ? '' : 's' ?> of <?= $stars ?> star<?= $stars === 1 ? '' : 's' ?></span></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <ol class="review-list">
                            <?php foreach ($reviews['items'] as $review): ?>
                                <li class="review">
                                    <p class="review-head">
                                        <span class="review-stars" aria-label="<?= $review['rating'] ?> out of 5 stars"><?= str_repeat('★', $review['rating']) ?><span class="review-stars-off"><?= str_repeat('★', 5 - $review['rating']) ?></span></span>
                                        <span class="text-muted"><?= esc($review['reviewer']) ?><?= $review['reviewer_role'] !== null ? ' · their ' . esc($review['reviewer_role']) : '' ?> · <time datetime="<?= esc($review['created_at'], 'attr') ?>"><?= esc(time_ago($review['created_at'])) ?></time></span>
                                    </p>
                                    <?php if ($review['feedback'] !== ''): ?>
                                        <p class="review-text"><?= esc($review['feedback']) ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>

                        <?php if ($reviews['pages'] > 1): ?>
                            <nav class="simple-pager" aria-label="Review pages">
                                <?php if ($reviews['page'] > 1): ?><a class="btn btn-secondary btn-sm" href="<?= $reviewUrl($reviews['page'] - 1) ?>" rel="prev"><?= icon('arrow-left') ?> Newer</a><?php endif; ?>
                                <span>Page <?= (int) $reviews['page'] ?> of <?= (int) $reviews['pages'] ?></span>
                                <?php if ($reviews['page'] < $reviews['pages']): ?><a class="btn btn-secondary btn-sm" href="<?= $reviewUrl($reviews['page'] + 1) ?>" rel="next">Older <?= icon('arrow-right') ?></a><?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>

            <?php if ($together !== []): ?>
                <section class="card" aria-labelledby="togetherHeading">
                    <div class="card-head">
                        <h2 id="togetherHeading"><?= icon('package') ?> Your errands with <?= esc($firstName) ?></h2>
                    </div>
                    <p class="text-muted"><?= icon('lock') ?> Only you and <?= esc($firstName) ?> can see this list.</p>
                    <ul class="together-list">
                        <?php foreach ($together as $e): ?>
                            <li>
                                <a href="<?= base_url('request-details/' . $e['id']) ?>">
                                    <span><strong><?= esc($e['item_name']) ?></strong><small>You were the <?= esc($e['viewer_role']) ?> · <?= esc(date('M j, Y', strtotime($e['created_at']))) ?></small></span>
                                    <span class="status-pill <?= esc($e['status'], 'attr') ?>"><?= esc(status_label($e['status'])) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
