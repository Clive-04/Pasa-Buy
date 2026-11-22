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

            <div class="welcome">
                <div>
                    <h1><?= $greeting ?>, <?= esc($me['first_name'] ?? 'there') ?></h1>
                    <p><?= esc(date('l, F j')) ?> · Here's what's happening with your errands.</p>
                </div>
            </div>

            <?php if (! empty($attention)): ?>
                <section class="card" aria-labelledby="attentionHeading" style="margin-bottom: var(--sp-5);">
                    <div class="card-head">
                        <h2 id="attentionHeading"><?= icon('bell') ?> Needs your attention</h2>
                    </div>
                    <ul class="attention">
                        <?php foreach ($attention as $item): ?>
                            <li class="attention-item">
                                <?= icon($item['icon']) ?>
                                <div>
                                    <strong><?= esc($item['title']) ?></strong>
                                    <span><?= esc($item['text']) ?></span>
                                </div>
                                <a href="<?= base_url($item['url']) ?>" class="btn btn-primary btn-sm"><?= esc($item['action']) ?> <?= icon('arrow-right') ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <div class="paths">
                <a href="<?= base_url('post-request') ?>" class="path-card">
                    <span class="path-icon"><?= icon('package') ?></span>
                    <span class="kicker">I need something</span>
                    <h2>Post a request</h2>
                    <p>Tell runners what to buy and where to deliver it. You only pay the item price plus your service fee.</p>
                    <span class="link">Start a request <?= icon('arrow-right') ?></span>
                </a>
                <a href="<?= base_url('browse-requests') ?>" class="path-card path-earn">
                    <span class="path-icon"><?= icon('truck') ?></span>
                    <span class="kicker">I want to earn</span>
                    <h2>Find requests to deliver</h2>
                    <p><?= (int) $openRequests ?> open request<?= $openRequests === 1 ? '' : 's' ?> nearby. Earn the service fee minus a 10% platform fee.</p>
                    <span class="link">Browse requests <?= icon('arrow-right') ?></span>
                </a>
            </div>

            <div class="stat-links">
                <a href="<?= base_url('my-requests') ?>" class="stat-link">
                    <span class="label"><?= icon('list') ?> My active requests</span>
                    <span class="value"><?= (int) $activeRequests ?></span>
                    <span class="hint">View my requests <?= icon('chevron') ?></span>
                </a>
                <a href="<?= base_url('accepted-requests') ?>" class="stat-link">
                    <span class="label"><?= icon('truck') ?> Deliveries in progress</span>
                    <span class="value"><?= (int) $activeDeliveries ?></span>
                    <span class="hint">View my deliveries <?= icon('chevron') ?></span>
                </a>
                <a href="<?= base_url('accepted-requests?status=delivered') ?>" class="stat-link">
                    <span class="label"><?= icon('check-circle') ?> Deliveries completed</span>
                    <span class="value"><?= (int) $completedDeliveries ?></span>
                    <span class="hint">See history <?= icon('chevron') ?></span>
                </a>
                <a href="<?= base_url('wallet') ?>" class="stat-link">
                    <span class="label"><?= icon('wallet') ?> <?= is_demo_mode() ? 'Demo wallet' : 'Wallet' ?></span>
                    <span class="value"><?= money($me['wallet_balance'] ?? '0') ?></span>
                    <span class="hint">Open wallet <?= icon('chevron') ?></span>
                </a>
            </div>

            <div class="two-col">
                <section class="card" aria-labelledby="myActiveHeading">
                    <div class="card-head">
                        <h2 id="myActiveHeading"><?= icon('list') ?> My active requests</h2>
                        <a href="<?= base_url('my-requests') ?>" class="link">View all <?= icon('chevron') ?></a>
                    </div>
                    <?php if (! empty($myActiveRequests)): ?>
                        <div class="row-list">
                            <?php foreach ($myActiveRequests as $request): ?>
                                <a href="<?= base_url('request-details/' . (int) $request['id']) ?>" class="row-item" style="display:grid; gap:10px;">
                                    <span style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
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
                        <div class="empty-state">
                            <?= icon('package') ?>
                            <h3>No active requests</h3>
                            <p>Need something from a nearby store? Post a request and a runner will get it for you.</p>
                            <a href="<?= base_url('post-request') ?>" class="btn btn-primary btn-sm"><?= icon('plus') ?> Post a request</a>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="card" aria-labelledby="openHeading">
                    <div class="card-head">
                        <h2 id="openHeading"><?= icon('search') ?> Requests you can deliver</h2>
                        <a href="<?= base_url('browse-requests') ?>" class="link">Browse all <?= icon('chevron') ?></a>
                    </div>
                    <?php if (! empty($availableRequests)): ?>
                        <div class="row-list">
                            <?php foreach ($availableRequests as $request): ?>
                                <a href="<?= base_url('request-details/' . (int) $request['id']) ?>" class="row-item">
                                    <span class="row-main">
                                        <strong><?= esc($request['item_name']) ?></strong>
                                        <span><?= esc($request['store_name']) ?> · you earn <?= money_cents(\App\Libraries\FeePolicy::runnerServiceEarningsCents(\App\Libraries\Money::toCents($request['service_fee']))) ?> · <?= esc(time_ago($request['created_at'])) ?></span>
                                    </span>
                                    <span class="request-tag <?= esc($request['urgency'], 'attr') ?>"><?= esc(ucfirst($request['urgency'])) ?></span>
                                    <?= icon('chevron') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <?= icon('search') ?>
                            <h3>No open requests right now</h3>
                            <p>New requests from your neighbours will show up here.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="card" aria-labelledby="activityHeading" style="margin-top: var(--sp-4);">
                <div class="card-head">
                    <h2 id="activityHeading"><?= icon('bell') ?> Recent updates</h2>
                    <a href="<?= base_url('notifications') ?>" class="link">All notifications <?= icon('chevron') ?></a>
                </div>
                <?php if (! empty($recentActivity)): ?>
                    <div class="row-list">
                        <?php foreach ($recentActivity as $activity): ?>
                            <a href="<?= base_url('notifications/open/' . (int) $activity['id']) ?>" class="row-item<?= ! $activity['is_read'] ? ' is-unread' : '' ?>">
                                <?= icon(match ($activity['type']) {
                                    'request_accepted' => 'user', 'on_the_way' => 'truck', 'delivered' => 'check-circle',
                                    'runner_paid' => 'wallet', 'cod_paid' => 'cash', 'request_cancelled' => 'x-circle', default => 'bell',
                                }) ?>
                                <span class="row-main">
                                    <strong><?= esc($activity['title']) ?><?= ! $activity['is_read'] ? ' <span class="visually-hidden">(unread)</span>' : '' ?></strong>
                                    <span><?= esc($activity['content']) ?> · <?= esc(time_ago($activity['created_at'])) ?></span>
                                </span>
                                <?= icon('chevron') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <?= icon('bell') ?>
                        <h3>No updates yet</h3>
                        <p>When someone accepts, pays for or delivers your request, you'll see it here.</p>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
