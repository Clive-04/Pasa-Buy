<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
use App\Libraries\Money;

$status        = $request['status'];
$requestId     = (int) $request['id'];
$isCod         = $request['payment_method'] === 'cod';
$estimateCents = Money::toCents($request['estimated_price']);

// R5: once approved, the actual item price (from the receipt) is what gets paid.
$price         = (string) ($request['price_status'] ?? 'not_required');
$hasApproved   = $price === 'approved' && $request['approved_item_price'] !== null;
$itemCents     = $hasApproved ? Money::toCents($request['approved_item_price']) : $estimateCents;
$priceHistory  = $priceHistory ?? [];
$latestPrice   = $priceHistory[0] ?? null;
$priceOpen     = in_array($status, ['accepted', 'on_the_way'], true)
    && in_array($request['payment_status'], ['unpaid', 'cod_pending'], true)
    && $price !== 'not_required';
$canSendPrice  = $isRunner && $priceOpen;
$canDecide     = $isRequester && $priceOpen && $price === 'submitted' && $latestPrice !== null && $latestPrice['status'] === 'pending';
$showPriceCard = ($isRequester || $isRunner || session('role') === 'admin') && ($priceHistory !== [] || $priceOpen);
$priceLabels   = ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'rejected' => 'Not approved', 'superseded' => 'Replaced'];
$feeCents      = Money::toCents($request['service_fee']);
$platformCents = Money::toCents($request['platform_fee']);
$totalCents    = $itemCents + $feeCents;
$balanceCents  = Money::toCents($walletBalance);
$isAdminViewer = session('role') === 'admin';

$viewer    = $isRequester ? 'requester' : ($isRunner ? 'runner' : 'other');
$next      = next_step($request, $viewer);
$canAccept = ! $isRequester && $status === 'pending' && ! $isAdminViewer;
$canPay    = $isRequester && ! $isCod && in_array($status, ['accepted', 'on_the_way'], true) && $request['payment_status'] === 'unpaid'
    && in_array($price, ['approved', 'not_required'], true);
$canCancel = $isRequester && $status === 'pending';
$hidden    = 'Shared with the runner after they accept';

$crumbs = $isAdminViewer
    ? [['Admin overview', 'admin/dashboard'], ['Request #' . $requestId, null]]
    : ($isRequester
        ? [['My requests', 'my-requests'], ['Request #' . $requestId, null]]
        : ($isRunner ? [['My deliveries', 'accepted-requests'], ['Request #' . $requestId, null]] : [['Find requests', 'browse-requests'], ['Request #' . $requestId, null]]));

$toneIcon = ['action' => 'arrow-right', 'info' => 'info', 'success' => 'check-circle', 'muted' => 'x-circle'][$next['tone']] ?? 'info';
$messageTo = $isRequester ? ($runner['id'] ?? null) : (int) $request['user_id'];

// Ratings: one per participant per delivered request; they add up on profiles.
$otherParty   = $isRequester ? $runner : $requester;
$otherRole    = $isRequester ? 'runner' : 'requester';
$canRate      = $status === 'delivered' && ($isRequester || $isRunner) && ! $isAdminViewer && empty($myRating) && ! empty($otherParty);
$alreadyRated = $status === 'delivered' && ! empty($myRating);
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs'  => $crumbs,
                'eyebrow' => 'Request #' . $requestId . ' · posted ' . time_ago($request['created_at']),
                'title'   => $request['item_name'],
                'actions' => '<span class="status-pill ' . esc($status, 'attr') . '">' . esc(status_label($status)) . '</span>'
                    . ' <span class="request-tag ' . esc($request['urgency'], 'attr') . '">' . esc(ucfirst($request['urgency'])) . '</span>',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <section class="card" aria-label="Progress">
                <?= request_steps($request) ?>
            </section>

            <div class="detail-grid">
                <div class="detail-main">
                    <?php if ($alreadyRated): ?>
                    <section class="rated-note" aria-label="Completed">
                        <?= icon('check-circle') ?>
                        <p><strong>Completed.</strong> You rated <?= esc($otherParty['first_name'] ?? 'them') ?>
                            <span class="stars" aria-label="<?= (int) $myRating['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $myRating['rating']) ?><span class="stars-off"><?= str_repeat('★', 5 - (int) $myRating['rating']) ?></span></span>
                            <?php if (! empty($myRating['feedback'])): ?><small>“<?= esc($myRating['feedback']) ?>”</small><?php endif; ?>
                        </p>
                    </section>
                    <?php else: ?>
                    <section class="next-step tone-<?= esc($next['tone'], 'attr') ?>" aria-labelledby="nextHeading">
                        <?= icon($toneIcon) ?>
                        <h2 id="nextHeading"><?= esc($next['title']) ?></h2>
                        <p><?= esc($next['text']) ?></p>

                        <div class="next-step-actions">
                            <?php if ($canAccept): ?>
                                <form action="<?= base_url('request/accept/' . $requestId) ?>" method="post" class="inline-form" data-submit-once>
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-primary" data-busy-label="Accepting…"><?= icon('check') ?> Accept this request</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($canDecide): ?>
                                <a href="#priceHeading" class="btn btn-primary"><?= icon('eye') ?> Check the price</a>
                            <?php endif; ?>

                            <?php if ($canSendPrice && $price !== 'submitted'): ?>
                                <button type="button" class="btn btn-primary" data-open-dialog="priceDialog"><?= icon('paperclip') ?> <?= $price === 'rejected' ? 'Send corrected price' : 'Send actual price' ?></button>
                            <?php endif; ?>

                            <?php if ($canPay): ?>
                                <button type="button" class="btn btn-primary" data-open-dialog="payRunnerDialog"><?= icon('wallet') ?> Pay <?= money_cents($totalCents) ?></button>
                            <?php endif; ?>

                            <?php if ($isRunner && in_array($status, ['accepted', 'on_the_way'], true)): ?>
                                <a href="<?= base_url('accepted-requests?status=' . $status) ?>" class="btn btn-primary"><?= icon('truck') ?> Update delivery status</a>
                            <?php endif; ?>

                            <?php // Chat opens once the request is accepted, for its requester and runner only (ChatPolicy). ?>
                            <?php if ($messageTo && ($isRequester || $isRunner) && in_array($status, ['accepted', 'on_the_way', 'delivered'], true)): ?>
                                <a href="<?= base_url('messages?user_id=' . (int) $messageTo . '&request_id=' . $requestId) ?>" class="btn btn-secondary">
                                    <?= icon('message') ?> <?= $isRequester ? 'Message runner' : 'Message requester' ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($canRate): ?>
                                <button type="button" class="btn btn-primary" data-open-dialog="rateDialog"><?= icon('star') ?> Rate <?= $otherRole ?></button>
                            <?php endif; ?>

                            <?php if ($canCancel): ?>
                                <button type="button" class="btn btn-danger" data-open-dialog="cancelDialog"><?= icon('x-circle') ?> Cancel request</button>
                            <?php endif; ?>
                        </div>

                        <?php if ($canAccept && $isCod && $balanceCents < $platformCents): ?>
                            <p class="field-hint" style="grid-column:2; margin-top:10px;">
                                Cash-on-delivery runners need <?= money_cents($platformCents) ?> in their wallet for the platform fee.
                                <a class="link" href="<?= base_url('wallet') ?>">Add demo credits <?= icon('chevron') ?></a>
                            </p>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>

                    <?php if ($showPriceCard): ?>
                    <section class="card price-card" aria-labelledby="priceHeading">
                        <div class="card-head">
                            <h2 id="priceHeading"><?= icon('paperclip') ?> Actual price</h2>
                            <span class="price-state is-<?= esc($price, 'attr') ?>"><?= esc([
                                'awaiting_receipt' => 'Receipt not sent yet', 'submitted' => 'Waiting for approval',
                                'approved' => 'Approved', 'rejected' => 'Not approved', 'not_required' => 'Settled before receipts',
                            ][$price] ?? $price) ?></span>
                        </div>

                        <?php if ($latestPrice === null): ?>
                            <p class="price-empty">
                                <?= $isRunner
                                    ? 'After buying the items, send the actual amount with a photo or PDF of the receipt. The requester approves it, then pays exactly that amount plus your service fee.'
                                    : 'After buying the items, your runner sends the actual amount with the receipt. You approve it before paying, so you only ever pay the real price.' ?>
                            </p>
                        <?php else:
                            $latestCents = Money::toCents($latestPrice['item_price']);
                            $diffCents   = $latestCents - $estimateCents; ?>
                            <div class="price-latest">
                                <div class="price-figures">
                                    <span class="price-amount"><?= money_cents($latestCents) ?></span>
                                    <span class="price-diff <?= $diffCents > 0 ? 'is-up' : ($diffCents < 0 ? 'is-down' : '') ?>">
                                        <?= $diffCents === 0 ? 'Same as the estimate' : ($diffCents > 0 ? '+' : '−') . money_cents(abs($diffCents)) . ' vs estimate ' . money_cents($estimateCents) ?>
                                    </span>
                                </div>
                                <a class="price-receipt" href="<?= base_url('request/' . $requestId . '/receipt/' . (int) $latestPrice['id']) ?>" target="_blank" rel="noopener" data-turbo="false">
                                    <?= icon(str_starts_with((string) $latestPrice['receipt_type'], 'image/') ? 'eye' : 'paperclip') ?> View receipt
                                    <small><?= esc($latestPrice['receipt_name']) ?></small>
                                </a>
                            </div>
                            <?php if (! empty($latestPrice['note'])): ?>
                                <p class="price-note"><?= icon('message') ?> “<?= esc($latestPrice['note']) ?>”</p>
                            <?php endif; ?>
                            <?php if ($latestPrice['status'] === 'rejected' && ! empty($latestPrice['decision_reason'])): ?>
                                <p class="price-note is-rejected"><?= icon('alert') ?> Not approved: “<?= esc($latestPrice['decision_reason']) ?>”</p>
                            <?php endif; ?>

                            <?php if ($canDecide): ?>
                                <div class="price-actions">
                                    <form action="<?= base_url('request/' . $requestId . '/price/' . (int) $latestPrice['id'] . '/approve') ?>" method="post" class="inline-form" data-submit-once>
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-primary" data-busy-label="Approving…"><?= icon('check') ?> Approve <?= money_cents($latestCents) ?></button>
                                    </form>
                                    <button type="button" class="btn btn-secondary" data-open-dialog="rejectPriceDialog"><?= icon('x-circle') ?> Don’t approve</button>
                                </div>
                                <p class="price-hint">Approving sets the total to <?= money_cents($latestCents + $feeCents) ?> (item + <?= money_cents($feeCents) ?> service fee). If the runner changes the price later, you’ll be asked again.</p>
                            <?php elseif ($canSendPrice && $price === 'submitted'): ?>
                                <div class="price-actions">
                                    <button type="button" class="btn btn-secondary" data-open-dialog="priceDialog"><?= icon('edit') ?> Change the price</button>
                                </div>
                            <?php elseif ($canSendPrice && $price === 'approved'): ?>
                                <p class="price-hint">Approved. If you change it now, the requester has to approve the new price before paying.
                                    <button type="button" class="link-btn" data-open-dialog="priceDialog">Change the price</button></p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (count($priceHistory) > 1): ?>
                            <details class="price-history">
                                <summary>Price history (<?= count($priceHistory) ?>)</summary>
                                <ol>
                                    <?php foreach ($priceHistory as $p): ?>
                                        <li>
                                            <span class="price-history-main">
                                                <strong><?= money($p['item_price']) ?></strong>
                                                <span class="price-state is-<?= esc($p['status'], 'attr') ?>"><?= esc($priceLabels[$p['status']] ?? $p['status']) ?></span>
                                                <?php if (! empty($p['decision_reason'])): ?><small>“<?= esc($p['decision_reason']) ?>”</small><?php endif; ?>
                                            </span>
                                            <span class="price-history-meta">
                                                <time datetime="<?= esc($p['created_at'], 'attr') ?>"><?= esc(time_ago($p['created_at'])) ?></time>
                                                · <a class="link" href="<?= base_url('request/' . $requestId . '/receipt/' . (int) $p['id']) ?>" target="_blank" rel="noopener" data-turbo="false">receipt</a>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </details>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>

                    <section class="card" aria-labelledby="itemHeading">
                        <div class="card-head"><h2 id="itemHeading"><?= icon('package') ?> Details</h2></div>
                        <dl class="dl">
                            <div><dt>Item</dt><dd><?= esc($request['item_name']) ?></dd></div>
                            <div><dt>Quantity</dt><dd><?= (int) $request['quantity'] ?></dd></div>
                            <div><dt>Preferred brand</dt><dd><?= esc($request['brand'] ?: 'Any brand') ?></dd></div>
                            <div><dt>Category</dt><dd><?= esc(ucfirst(str_replace('_', ' ', $request['category']))) ?></dd></div>
                        </dl>
                        <?php if (! empty($request['item_notes'])): ?>
                            <p class="flash flash-info" style="margin:14px 0 0; animation:none;"><?= icon('info') ?> <span><?= esc($request['item_notes']) ?></span></p>
                        <?php endif; ?>

                        <div class="card-divider"></div>
                        <h3 class="subhead" id="whereHeading"><?= icon('map-pin') ?> Where</h3>
                        <dl class="dl">
                            <div><dt><?= icon('store') ?> Buy from</dt><dd><?= esc($request['store_name']) ?><?= $request['alt_store'] ? '<br><small class="text-muted">or ' . esc($request['alt_store']) . '</small>' : '' ?></dd></div>
                            <div><dt><?= icon('map-pin') ?> Store address</dt><dd><?= esc($request['store_address']) ?></dd></div>
                            <div><dt><?= icon('home') ?> Deliver to</dt><dd class="<?= $canSeeContact ? '' : 'is-private' ?>"><?= $canSeeContact ? esc($request['delivery_address']) : esc($hidden) ?></dd></div>
                            <div><dt><?= icon('info') ?> Landmark</dt><dd><?= esc($request['landmark'] ?: 'None given') ?></dd></div>
                            <div><dt><?= icon('phone') ?> Contact number</dt><dd class="<?= $canSeeContact ? '' : 'is-private' ?>"><?= $canSeeContact ? esc($request['contact_number']) : esc($hidden) ?></dd></div>
                        </dl>
                        <?php if ($canSeeContact && ! empty($request['delivery_notes'])): ?>
                            <p class="flash flash-info" style="margin:14px 0 0; animation:none;"><?= icon('info') ?> <span>Delivery notes: <?= esc($request['delivery_notes']) ?></span></p>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="detail-aside">
                    <section class="card" aria-labelledby="payHeading">
                        <div class="card-head"><h2 id="payHeading"><?= icon($isCod ? 'cash' : 'wallet') ?> Payment</h2></div>
                        <div class="money-breakdown">
                            <?php if ($hasApproved): ?>
                                <div class="row muted"><span>Item (estimated)</span><s><?= money_cents($estimateCents) ?></s></div>
                                <div class="row"><span>Item (actual, approved)</span><strong><?= money_cents($itemCents) ?></strong></div>
                            <?php else: ?>
                                <div class="row"><span>Item (estimated)</span><strong><?= money_cents($itemCents) ?></strong></div>
                            <?php endif; ?>
                            <div class="row"><span>Service fee</span><strong><?= money_cents($feeCents) ?></strong></div>
                            <div class="row total"><span><?= $hasApproved || $price === 'not_required' ? 'Requester pays' : 'Requester pays (estimate)' ?></span><strong><?= money_cents($totalCents) ?></strong></div>
                            <div class="row"><span>Platform fee (10%)</span><strong>−<?= money_cents($platformCents) ?></strong></div>
                            <div class="row total"><span>Runner receives</span><strong><?= money_cents($totalCents - $platformCents) ?></strong></div>
                            <p class="note">
                                <?= esc(payment_method_label($request['payment_method'])) ?> · <?= esc(payment_status_label($request['payment_status'])) ?>.
                                <?= $isCod ? 'The requester pays the runner in cash; the platform fee is taken from the runner\'s wallet at delivery.' : 'Paid from the requester\'s wallet before delivery.' ?>
                            </p>
                        </div>

                        <div class="card-divider"></div>
                        <h3 class="subhead" id="peopleHeading"><?= icon('users') ?> People</h3>
                        <div class="row-list">
                            <a class="row-item" href="<?= base_url('users/' . (int) $request['user_id']) ?>">
                                <?= avatar($requester, 'sm') ?>
                                <span class="row-main">
                                    <strong><?= esc(trim(($requester['first_name'] ?? 'Unknown') . ' ' . ($requester['last_name'] ?? ''))) ?><?= $isRequester ? ' (you)' : '' ?></strong>
                                    <span>Requester</span>
                                </span>
                                <?= icon('chevron') ?>
                            </a>
                            <?php if ($runner !== null): ?>
                                <a class="row-item" href="<?= base_url('users/' . (int) $runner['id']) ?>">
                                    <?= avatar($runner, 'sm') ?>
                                    <span class="row-main">
                                        <strong><?= esc(trim($runner['first_name'] . ' ' . $runner['last_name'])) ?><?= $isRunner ? ' (you)' : '' ?></strong>
                                        <span>Runner</span>
                                    </span>
                                    <?= icon('chevron') ?>
                                </a>
                            <?php else: ?>
                                <div class="row-item"><?= icon('clock') ?><span class="row-main"><strong>No runner yet</strong><span>Waiting for someone to accept</span></span></div>
                            <?php endif; ?>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
    </div>
</main>

<?php if ($canPay): ?>
<dialog class="pb-dialog" id="payRunnerDialog" aria-labelledby="payRunnerTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="payRunnerTitle">Pay your runner?</h2>
            <p>This sends the payment from your wallet<?= is_demo_mode() ? ' (demo credits)' : '' ?>. It cannot be undone.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <div class="money-breakdown">
        <div class="row"><span><?= $hasApproved ? 'Item (approved actual price)' : 'Item' ?></span><strong><?= money_cents($itemCents) ?></strong></div>
        <div class="row"><span>Service fee</span><strong><?= money_cents($feeCents) ?></strong></div>
        <div class="row total"><span>Total to pay</span><strong><?= money_cents($totalCents) ?></strong></div>
        <div class="row"><span>Your wallet balance</span><strong><?= money_cents($balanceCents) ?></strong></div>
        <div class="row"><span>Balance after paying</span><strong><?= money_cents(max(0, $balanceCents - $totalCents)) ?></strong></div>
        <?php if ($balanceCents < $totalCents): ?>
            <p class="note" role="alert" style="color: var(--pb-error-ink);">Your balance is too low. <a class="link" href="<?= base_url('wallet') ?>">Add <?= is_demo_mode() ? 'demo credits' : 'funds' ?> first <?= icon('chevron') ?></a></p>
        <?php endif; ?>
    </div>

    <form action="<?= base_url('request/pay-runner/' . $requestId) ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Not now</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Paying…" <?= $balanceCents < $totalCents ? 'disabled' : '' ?>><?= icon('wallet') ?> Pay <?= money_cents($totalCents) ?></button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canSendPrice): ?>
<dialog class="pb-dialog" id="priceDialog" aria-labelledby="priceTitle"<?= session('open_dialog') === 'priceDialog' ? ' data-autopen' : '' ?>>
    <div class="pb-dialog-head">
        <div>
            <h2 id="priceTitle"><?= $latestPrice !== null ? 'Send a corrected price' : 'Send the actual price' ?></h2>
            <p>Enter what the items actually cost and attach the receipt. The requester approves it before paying<?= $isCod ? ' you in cash' : '' ?>. Estimate: <?= money_cents($estimateCents) ?>.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form action="<?= base_url('request/' . $requestId . '/price') ?>" method="post" enctype="multipart/form-data" data-submit-once data-turbo="false">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="itemPrice">Actual item amount (₱)</label>
            <input type="number" id="itemPrice" name="item_price" min="0.01" step="0.01" inputmode="decimal" required
                   value="<?= esc((string) (old('item_price') ?? '')) ?>" placeholder="<?= esc(Money::toDecimal($estimateCents)) ?>">
            <p class="field-hint">Items only. Your service fee (<?= money_cents($feeCents) ?>) is added on top.</p>
        </div>
        <div class="form-group">
            <label for="receiptFile">Receipt (photo or PDF, up to 5 MB)</label>
            <input type="file" id="receiptFile" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" required>
            <p class="field-hint">Only the requester, you and Pasa-Buy admins can see it.</p>
        </div>
        <div class="form-group">
            <label for="priceNote">Note <span class="text-muted">(optional)</span></label>
            <input type="text" id="priceNote" name="note" maxlength="255" value="<?= esc((string) (old('note') ?? '')) ?>" placeholder="e.g. Brand you wanted was sold out, got the next size">
        </div>
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Sending…"><?= icon('send') ?> Send for approval</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canDecide): ?>
<dialog class="pb-dialog" id="rejectPriceDialog" aria-labelledby="rejectPriceTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="rejectPriceTitle">Don’t approve <?= money($latestPrice['item_price']) ?>?</h2>
            <p>Tell the runner what’s wrong so they can send a corrected price or receipt. Nothing is paid until you approve a price.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form action="<?= base_url('request/' . $requestId . '/price/' . (int) $latestPrice['id'] . '/reject') ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="rejectReason">Reason</label>
            <textarea id="rejectReason" name="reason" rows="3" minlength="3" maxlength="255" required placeholder="e.g. The receipt total doesn’t match the amount"></textarea>
        </div>
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Back</button>
            <button type="submit" class="btn btn-danger-solid" data-busy-label="Sending…"><?= icon('x-circle') ?> Don’t approve</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canCancel): ?>
<dialog class="pb-dialog" id="cancelDialog" aria-labelledby="cancelTitle">
    <div class="pb-dialog-head">
        <div>
            <h2 id="cancelTitle">Cancel this request?</h2>
            <p>Runners will no longer see it. It stays in your history as cancelled.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form action="<?= base_url('request/cancel/' . $requestId) ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Keep request</button>
            <button type="submit" class="btn btn-danger-solid btn" data-busy-label="Cancelling…"><?= icon('x-circle') ?> Yes, cancel it</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canRate): ?>
<dialog class="pb-dialog" id="rateDialog" aria-labelledby="rateTitle"<?= old('rating') !== null ? ' data-autopen' : '' ?>>
    <div class="pb-dialog-head">
        <div>
            <h2 id="rateTitle">Rate <?= esc(trim($otherParty['first_name'] . ' ' . $otherParty['last_name'])) ?></h2>
            <p>How did this delivery go? Each errand gets its own rating, and they add up on <?= esc($otherParty['first_name']) ?>'s profile. You can rate this delivery once.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>
    <form action="<?= base_url('request/' . $requestId . '/rate') ?>" method="post" data-submit-once>
        <?= csrf_field() ?>
        <fieldset class="star-input">
            <legend class="visually-hidden">Rating</legend>
            <?php foreach ([5 => 'Excellent', 4 => 'Very good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'] as $value => $label): ?>
                <input type="radio" id="star<?= $value ?>" name="rating" value="<?= $value ?>" required<?= (string) old('rating') === (string) $value ? ' checked' : '' ?>>
                <label for="star<?= $value ?>" title="<?= $label ?>">★<span class="visually-hidden"> <?= $value ?> star<?= $value > 1 ? 's' : '' ?>, <?= $label ?></span></label>
            <?php endforeach; ?>
        </fieldset>
        <div class="form-group">
            <label for="feedback">Feedback <span class="text-muted">(optional)</span></label>
            <textarea id="feedback" name="feedback" rows="3" maxlength="1000" placeholder="Fast, careful with the items, friendly…"><?= esc((string) old('feedback')) ?></textarea>
        </div>
        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Not now</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Saving…"><?= icon('star') ?> Submit rating</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?= $this->include('includes/footer') ?>
