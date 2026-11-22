<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
use App\Libraries\FeePolicy;
use App\Libraries\Money;

$hour     = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= $this->include('includes/flash') ?>

            <section class="hero-card welcome" aria-label="Overview">
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

            <div class="two-col">
                <section aria-labelledby="myActiveHeading">
                    <div class="section-head">
                        <h2 id="myActiveHeading" class="section-label"><?= icon('list') ?> My active requests</h2>
                        <a href="<?= base_url('my-requests') ?>" class="link">View all <?= icon('chevron') ?></a>
                    </div>
                    <?php if (! empty($myActiveRequests)): ?>
                        <div class="row-list">
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

                <section aria-labelledby="openHeading">
                    <div class="section-head">
                        <h2 id="openHeading" class="section-label"><?= icon('search') ?> Requests you can deliver</h2>
                        <a href="<?= base_url('browse-requests') ?>" class="link">Browse all <?= icon('chevron') ?></a>
                    </div>
                    <?php if (! empty($availableRequests)): ?>
                        <div class="row-list">
                            <?php foreach ($availableRequests as $request): ?>
                                <a href="<?= base_url('request-details/' . (int) $request['id']) ?>" class="row-item">
                                    <span class="row-main">
                                        <strong><?= esc($request['item_name']) ?></strong>
                                        <span><?= esc($request['store_name']) ?> · <?= esc(time_ago($request['created_at'])) ?></span>
                                    </span>
                                    <span class="earn-tag">+<?= money_cents(FeePolicy::runnerServiceEarningsCents(Money::toCents($request['service_fee']))) ?></span>
                                    <?= icon('chevron') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-inline"><p>No open requests nearby right now.</p></div>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
