<?php
/**
 * Marketplace card for an open request (Find requests + Dashboard).
 * Expects $request with id, item_name, quantity, category, store_name,
 * landmark, payment_method, urgency, estimated_price, service_fee, created_at.
 */
use App\Libraries\FeePolicy;
use App\Libraries\Money;

$earn = FeePolicy::runnerServiceEarningsCents(Money::toCents($request['service_fee']));
$categoryLabels = [
    'food' => 'Food', 'medicine' => 'Medicine', 'school_supplies' => 'School supplies',
    'household' => 'Household', 'personal_care' => 'Personal care', 'other' => 'Other',
];
?>
<article class="req-card">
    <div class="req-card-head">
        <span class="req-card-tags">
            <span class="request-tag <?= esc($request['urgency'], 'attr') ?>"><?= esc(ucfirst($request['urgency'])) ?></span>
            <?php if (! empty($request['category'])): ?>
                <span class="cat-tag"><?= esc($categoryLabels[$request['category']] ?? ucfirst($request['category'])) ?></span>
            <?php endif; ?>
        </span>
        <span class="text-muted" style="font-size:.8rem;"><?= icon('clock') ?> <?= esc(time_ago($request['created_at'])) ?></span>
    </div>
    <div>
        <h3 style="margin:0 0 8px;"><a href="<?= base_url('request-details/' . (int) $request['id']) ?>" style="color:inherit; text-decoration:none;"><?= esc($request['item_name']) ?></a> <small class="text-muted">×<?= (int) $request['quantity'] ?></small></h3>
        <ul class="req-meta" style="flex-direction:column; gap:6px;">
            <li><?= icon('store') ?> <?= esc($request['store_name']) ?></li>
            <?php if (! empty($request['landmark'])): ?>
                <li><?= icon('map-pin') ?> Near <?= esc($request['landmark']) ?></li>
            <?php endif; ?>
            <li><?= icon($request['payment_method'] === 'cod' ? 'cash' : 'wallet') ?> <?= esc(payment_method_label($request['payment_method'])) ?></li>
        </ul>
    </div>
    <div class="money-breakdown" style="padding:10px 12px;">
        <div class="row"><span>Item (you buy)</span><strong><?= money($request['estimated_price']) ?></strong></div>
        <div class="row total"><span>You earn</span><strong style="color: var(--pb-success-ink);"><?= money_cents($earn) ?></strong></div>
    </div>
    <div class="req-actions">
        <a href="<?= base_url('request-details/' . (int) $request['id']) ?>" class="btn btn-ghost btn-sm">Details</a>
        <form action="<?= base_url('request/accept/' . (int) $request['id']) ?>" method="post" class="inline-form" data-submit-once>
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-sm" data-busy-label="Accepting…"><?= icon('check') ?> Accept</button>
        </form>
    </div>
</article>
