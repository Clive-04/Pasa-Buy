<?php
/**
 * Marketplace card for an open request (Find requests + Dashboard).
 * Expects $request with id, item_name, quantity, category, store_name,
 * landmark, payment_method, urgency, estimated_price, service_fee, created_at.
 *
 * Layout: category + time, the item as the headline, a short facts list,
 * and a footer led by what the runner earns (the number people scan for).
 */
use App\Libraries\FeePolicy;
use App\Libraries\Money;

$earn = FeePolicy::runnerServiceEarningsCents(Money::toCents($request['service_fee']));
$categoryLabels = [
    'food' => 'Food', 'medicine' => 'Medicine', 'school_supplies' => 'School supplies',
    'household' => 'Household', 'personal_care' => 'Personal care', 'other' => 'Other',
];
$category = (string) ($request['category'] ?? 'other');
$url      = base_url('request-details/' . (int) $request['id']);
?>
<article class="req-card cat-<?= esc($category, 'attr') ?>">
    <header class="rc-top">
        <span class="rc-cat"><?= esc($categoryLabels[$category] ?? ucfirst($category)) ?></span>
        <?php if (($request['urgency'] ?? 'normal') !== 'normal'): ?>
            <span class="request-tag <?= esc($request['urgency'], 'attr') ?>"><?= esc(ucfirst($request['urgency'])) ?></span>
        <?php endif; ?>
        <time class="rc-time" datetime="<?= esc((string) $request['created_at'], 'attr') ?>"><?= esc(time_ago($request['created_at'])) ?></time>
    </header>

    <h3 class="rc-title">
        <a href="<?= $url ?>"><?= esc($request['item_name']) ?></a>
        <?php if ((int) $request['quantity'] > 1): ?><span class="rc-qty">×<?= (int) $request['quantity'] ?></span><?php endif; ?>
    </h3>

    <dl class="rc-facts">
        <div><dt>Store</dt><dd><?= esc($request['store_name']) ?></dd></div>
        <?php if (! empty($request['landmark'])): ?>
            <div><dt>Near</dt><dd><?= esc($request['landmark']) ?></dd></div>
        <?php endif; ?>
        <div><dt>Payment</dt><dd><?= esc(payment_method_label($request['payment_method'])) ?></dd></div>
        <div><dt>You buy for</dt><dd><?= money($request['estimated_price']) ?></dd></div>
    </dl>

    <footer class="rc-foot">
        <p class="rc-earn"><span>You earn</span><strong><?= money_cents($earn) ?></strong></p>
        <div class="rc-actions">
            <a href="<?= $url ?>" class="btn btn-ghost btn-sm">Details</a>
            <form action="<?= base_url('request/accept/' . (int) $request['id']) ?>" method="post" class="inline-form" data-submit-once>
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm" data-busy-label="Accepting…">Accept</button>
            </form>
        </div>
    </footer>
</article>
