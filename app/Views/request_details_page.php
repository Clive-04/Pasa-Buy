<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
use App\Libraries\Money;

$status        = $request['status'];
$isCod         = $request['payment_method'] === 'cod';
$itemCents     = Money::toCents($request['estimated_price']);
$feeCents      = Money::toCents($request['service_fee']);
$platformCents = Money::toCents($request['platform_fee']);
$totalCents    = $itemCents + $feeCents;
$balanceCents  = Money::toCents($walletBalance);

$canAccept = ! $isRequester && $status === 'pending' && session('role') !== 'admin';
$canPay    = $isRequester && ! $isCod && in_array($status, ['accepted', 'on_the_way'], true) && $request['payment_status'] === 'unpaid';
$canCancel = $isRequester && $status === 'pending';
$hidden    = 'Shared with the assigned runner after acceptance';
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="request-content-panel">
                <?= $this->include('includes/flash') ?>

                <div class="page-top">
                    <div>
                        <span class="page-badge">Request Information</span>
                        <h1>Request #<?= (int) $request['id'] ?></h1>
                        <p>Review the request before accepting or paying.</p>
                    </div>

                    <a href="<?= base_url($isRequester ? 'my-requests' : ($isRunner ? 'accepted-requests' : 'browse-requests')) ?>" class="page-back-btn">Back</a>
                </div>

                <div class="details-layout">
                    <section class="details-card">
                        <div class="details-section">
                            <h2>Request Summary</h2>
                            <div class="details-grid">
                                <div><span>Status</span><strong class="status-pill <?= esc($status, 'attr') ?>"><?= esc(status_label($status)) ?></strong></div>
                                <div><span>Urgency</span><strong class="request-tag <?= esc($request['urgency'], 'attr') ?>"><?= esc(ucfirst($request['urgency'])) ?></strong></div>
                                <div><span>Category</span><strong><?= esc(ucfirst(str_replace('_', ' ', $request['category']))) ?></strong></div>
                                <div><span>Posted</span><strong><?= esc(date('M j, Y g:i A', strtotime($request['created_at']))) ?></strong></div>
                            </div>
                        </div>

                        <div class="details-section">
                            <h2>Item Details</h2>
                            <div class="details-grid">
                                <div><span>Item Name</span><strong><?= esc($request['item_name']) ?></strong></div>
                                <div><span>Quantity</span><strong><?= (int) $request['quantity'] ?> <?= (int) $request['quantity'] === 1 ? 'item' : 'items' ?></strong></div>
                                <div><span>Preferred Brand</span><strong><?= esc($request['brand'] ?: 'Any') ?></strong></div>
                                <div><span>Estimated Price</span><strong><?= money_cents($itemCents) ?></strong></div>
                            </div>
                            <?php if (! empty($request['item_notes'])): ?>
                                <p class="details-note"><?= esc($request['item_notes']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="details-section">
                            <h2>Store and Delivery</h2>
                            <div class="details-grid">
                                <div><span>Preferred Store</span><strong><?= esc($request['store_name']) ?></strong></div>
                                <div><span>Alternative Store</span><strong><?= esc($request['alt_store'] ?: 'None') ?></strong></div>
                                <div><span>Store Address</span><strong><?= esc($request['store_address']) ?></strong></div>
                                <div><span>Landmark</span><strong><?= esc($request['landmark'] ?: 'None') ?></strong></div>
                                <div><span>Delivery Address</span><strong><?= $canSeeContact ? esc($request['delivery_address']) : esc($hidden) ?></strong></div>
                                <div><span>Contact Number</span><strong><?= $canSeeContact ? esc($request['contact_number']) : esc($hidden) ?></strong></div>
                            </div>
                            <?php if ($canSeeContact && ! empty($request['delivery_notes'])): ?>
                                <p class="details-note">Delivery notes: <?= esc($request['delivery_notes']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="details-section">
                            <h2>Payment</h2>
                            <div class="details-grid">
                                <div><span>Payment Method</span><strong><?= esc(payment_method_label($request['payment_method'])) ?></strong></div>
                                <div><span>Payment Status</span><strong><?= esc(payment_status_label($request['payment_status'])) ?></strong></div>
                            </div>
                            <div class="money-breakdown" style="margin-top:14px;">
                                <div class="row"><span>Estimated item price</span><strong><?= money_cents($itemCents) ?></strong></div>
                                <div class="row"><span>Service fee</span><strong><?= money_cents($feeCents) ?></strong></div>
                                <div class="row total"><span>Requester pays</span><strong><?= money_cents($totalCents) ?></strong></div>
                                <div class="row"><span>Platform fee (10% of service fee, paid by runner)</span><strong>−<?= money_cents($platformCents) ?></strong></div>
                                <div class="row total"><span>Runner receives</span><strong><?= money_cents($totalCents - $platformCents) ?></strong></div>
                                <?php if ($isCod): ?>
                                    <p class="note">Cash on delivery: the requester pays the runner <?= money_cents($totalCents) ?> in cash. The platform fee is taken from the runner's wallet when they confirm delivery.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <aside class="details-side-card">
                        <h2>Requester</h2>
                        <div class="profile-mini">
                            <div class="profile-mini-avatar">
                                <?php if (avatar_url($requester['profile_photo'] ?? null)): ?>
                                    <img src="<?= esc(avatar_url($requester['profile_photo']), 'attr') ?>" alt="">
                                <?php else: ?>
                                    <?= esc(initials($requester['first_name'] ?? 'U', $requester['last_name'] ?? '')) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3><?= esc(trim(($requester['first_name'] ?? 'Unknown') . ' ' . ($requester['last_name'] ?? ''))) ?></h3>
                                <p><a href="<?= base_url('users/' . (int) $request['user_id']) ?>">View profile</a></p>
                            </div>
                        </div>

                        <?php if ($runner !== null): ?>
                            <div class="details-side-info">
                                <div class="summary-row">
                                    <span>Runner</span>
                                    <strong><?= esc(trim($runner['first_name'] . ' ' . $runner['last_name'])) ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="details-side-actions">
                            <?php if ($canAccept): ?>
                                <form action="<?= base_url('request/accept/' . (int) $request['id']) ?>" method="post" data-submit-once>
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-gradient full-btn" data-busy-label="Accepting…">Accept Request</button>
                                </form>
                                <?php if ($isCod && $balanceCents < $platformCents): ?>
                                    <p class="field-hint">You need <?= money_cents($platformCents) ?> in your wallet to cover this request's platform fee. <a href="<?= base_url('wallet') ?>">Add demo credits</a>.</p>
                                <?php endif; ?>
                                <a href="<?= base_url('messages?user_id=' . (int) $request['user_id'] . '&request_id=' . (int) $request['id']) ?>" class="btn-light full-btn">Ask the requester</a>
                            <?php elseif ($isRunner): ?>
                                <a href="<?= base_url('accepted-requests') ?>" class="btn-gradient full-btn">Manage in Accepted Requests</a>
                                <a href="<?= base_url('messages?user_id=' . (int) $request['user_id'] . '&request_id=' . (int) $request['id']) ?>" class="btn-light full-btn">Message requester</a>
                            <?php elseif ($isRequester && $runner !== null): ?>
                                <a href="<?= base_url('messages?user_id=' . (int) $runner['id'] . '&request_id=' . (int) $request['id']) ?>" class="btn-light full-btn">Message runner</a>
                            <?php endif; ?>

                            <?php if ($canPay): ?>
                                <button type="button" class="btn-gradient full-btn" data-open-dialog="payRunnerDialog">Pay Runner from Wallet</button>
                            <?php elseif ($isRequester && $isCod && in_array($status, ['pending', 'accepted', 'on_the_way'], true)): ?>
                                <p class="flash flash-warn">Cash on delivery: pay the runner <?= money_cents($totalCents) ?> in cash when your items arrive.</p>
                            <?php endif; ?>

                            <?php if ($canCancel): ?>
                                <button type="button" class="btn-light full-btn" data-open-dialog="cancelDialog">Cancel Request</button>
                            <?php endif; ?>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</main>

<?php if ($canPay): ?>
<dialog class="pb-dialog" id="payRunnerDialog" aria-labelledby="payRunnerTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="payRunnerTitle">Confirm payment</h2>
            <p>This pays the runner from your wallet<?= is_demo_mode() ? ' using demo credits' : '' ?>. It cannot be undone.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <div class="money-breakdown">
        <div class="row"><span>Estimated item price</span><strong><?= money_cents($itemCents) ?></strong></div>
        <div class="row"><span>Service fee</span><strong><?= money_cents($feeCents) ?></strong></div>
        <div class="row total"><span>Total to pay</span><strong><?= money_cents($totalCents) ?></strong></div>
        <div class="row"><span>Your wallet balance</span><strong><?= money_cents($balanceCents) ?></strong></div>
        <?php if ($balanceCents < $totalCents): ?>
            <p class="note" role="alert">Your balance is too low. <a href="<?= base_url('wallet') ?>">Add <?= is_demo_mode() ? 'demo credits' : 'funds' ?></a> first.</p>
        <?php endif; ?>
    </div>

    <form action="<?= base_url('request/pay-runner/' . (int) $request['id']) ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <div class="pb-dialog-actions">
            <button type="button" class="btn-light" data-close-dialog>Cancel</button>
            <button type="submit" class="btn-gradient" data-busy-label="Paying…" <?= $balanceCents < $totalCents ? 'disabled' : '' ?>>Pay <?= money_cents($totalCents) ?></button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canCancel): ?>
<dialog class="pb-dialog" id="cancelDialog" aria-labelledby="cancelTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="cancelTitle">Cancel this request?</h2>
            <p>Runners will no longer see it. The request stays in your history as cancelled.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form action="<?= base_url('request/cancel/' . (int) $request['id']) ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <div class="pb-dialog-actions">
            <button type="button" class="btn-light" data-close-dialog>Keep request</button>
            <button type="submit" class="btn-gradient" data-busy-label="Cancelling…">Cancel request</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?= $this->include('includes/footer') ?>
