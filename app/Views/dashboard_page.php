<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$hour     = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= $this->include('includes/flash') ?>

            <?php
            $todo    = action_items();
            $nTodo   = count($todo['requests']) + count($todo['deliveries']);
            $todoUrl = count($todo['requests']) > 0 ? 'my-requests' : 'accepted-requests';
            ?>
            <div class="bento">
            <section class="hero-card welcome bento-hero" aria-label="Overview">
                <div class="welcome-text">
                    <h1><?= $greeting ?>, <?= esc($me['first_name'] ?? 'there') ?></h1>
                    <p><?= esc(date('l, F j')) ?></p>
                    <dl class="hero-stats">
                        <div><dt>Active requests</dt><dd><a href="<?= base_url('my-requests') ?>"><?= (int) $activeRequests ?></a></dd></div>
                        <div><dt>Deliveries in progress</dt><dd><a href="<?= base_url('accepted-requests') ?>"><?= (int) $activeDeliveries ?></a></dd></div>
                        <div><dt>Delivered</dt><dd><a href="<?= base_url('accepted-requests?status=delivered') ?>"><?= (int) $completedDeliveries ?></a></dd></div>
                    </dl>
                </div>
                <div class="welcome-actions">
                    <a href="<?= base_url('post-request') ?>" class="btn btn-accent btn-lg"><?= icon('plus') ?> Post a request</a>
                    <a href="<?= base_url('browse-requests') ?>" class="btn btn-light-on-hero btn-lg"><?= icon('search') ?> Find requests <span class="count-pill"><?= (int) $openRequests ?></span></a>
                </div>
            </section>

            <a class="bento-tile tile-wallet" href="<?= base_url('wallet') ?>">
                <span class="tile-label"><?= icon('wallet') ?> <?= is_demo_mode() ? 'Wallet · demo credits' : 'Wallet' ?></span>
                <strong class="tile-value"><?= money($me['wallet_balance'] ?? '0') ?></strong>
                <span class="tile-link">Top up or see history <?= icon('arrow-right') ?></span>
            </a>

            <a class="bento-tile tile-todo<?= $nTodo === 0 ? ' is-clear' : '' ?>" href="<?= base_url($nTodo > 0 ? $todoUrl : 'browse-requests') ?>">
                <span class="tile-label"><?= icon($nTodo > 0 ? 'bell' : 'check-circle') ?> Needs you</span>
                <strong class="tile-value"><?= $nTodo > 0 ? $nTodo . ' errand' . ($nTodo === 1 ? '' : 's') : 'All caught up' ?></strong>
                <span class="tile-link"><?= $nTodo > 0 ? 'Pay, rate or update ' . icon('arrow-right') : 'Find an errand to run ' . icon('arrow-right') ?></span>
            </a>
            </div>

            <?php if (! empty($attention)): ?>
                <section aria-labelledby="attentionHeading">
                    <h2 id="attentionHeading" class="section-label"><?= icon('bell') ?> Needs your attention</h2>
                    <ul class="attention">
                        <?php foreach ($attention as $item): ?>
                            <li>
                                <a class="attention-item" href="<?= base_url($item['url']) ?>">
                                    <?= icon($item['icon']) ?>
                                    <div>
                                        <strong><?= esc($item['title']) ?></strong>
                                        <span><?= esc($item['text']) ?></span>
                                    </div>
                                    <span class="attention-action"><?= esc($item['action']) ?> <?= icon('arrow-right') ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section aria-labelledby="myActiveHeading">
                <div class="section-head">
                    <h2 id="myActiveHeading" class="section-label"><?= icon('list') ?> My active requests</h2>
                    <a href="<?= base_url('my-requests') ?>" class="link">View all <?= icon('chevron') ?></a>
                </div>
                <?php if (! empty($myActiveRequests)): ?>
                    <div class="row-list active-grid">
                        <?php foreach ($myActiveRequests as $request): ?>
                            <a href="<?= base_url('request-details/' . (int) $request['id']) ?>" class="row-item row-item-stack">
                                <span class="row-line">
                                    <span class="row-main">
                                        <strong><?= esc($request['item_name']) ?></strong>
                                        <span><?= esc($request['store_name']) ?> · <?= money($request['total_amount']) ?></span>
                                    </span>
                                    <span class="status-pill <?= esc($request['status'], 'attr') ?>"><?= esc(status_label($request['status'])) ?></span>
                                </span>
                                <?= request_steps($request, true) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-inline">
                        <p>No active requests. <a class="link" href="<?= base_url('post-request') ?>">Post one <?= icon('arrow-right') ?></a></p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="market" aria-labelledby="openHeading">
                <div class="section-head">
                    <div>
                        <h2 id="openHeading" class="section-label"><?= icon('store') ?> Requests you can deliver</h2>
                        <p class="market-lead"><?= (int) $openRequests ?> open <?= (int) $openRequests === 1 ? 'errand' : 'errands' ?> from your neighbours. Accept one, buy the items, and earn the service fee.</p>
                    </div>
                    <a href="<?= base_url('browse-requests') ?>" class="link">Browse all <?= icon('chevron') ?></a>
                </div>
                <nav class="market-cats" aria-label="Browse by category">
                    <?php foreach (['food' => 'Food', 'medicine' => 'Medicine', 'school_supplies' => 'School supplies', 'household' => 'Household', 'personal_care' => 'Personal care', 'other' => 'Other'] as $value => $label): ?>
                        <a class="chip chip-cat cat-<?= esc($value, 'attr') ?>" href="<?= base_url('browse-requests?category=' . $value) ?>"><?= esc($label) ?></a>
                    <?php endforeach; ?>
                </nav>
                <?php if (! empty($availableRequests)): ?>
                    <div class="request-card-grid">
                        <?php foreach ($availableRequests as $request): ?>
                            <?= view('includes/request_card', ['request' => $request]) ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($openRequests > count($availableRequests)): ?>
                        <div class="market-more">
                            <a href="<?= base_url('browse-requests') ?>" class="btn btn-secondary"><?= icon('search') ?> See all <?= (int) $openRequests ?> open requests</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="card empty-state">
                        <?= icon('store') ?>
                        <h3>No open requests right now</h3>
                        <p>New requests from your neighbours will appear here. Check back soon.</p>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
