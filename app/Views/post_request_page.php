<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$categoryLabels = [
    'food' => 'Food', 'medicine' => 'Medicine', 'school_supplies' => 'School Supplies',
    'household' => 'Household', 'personal_care' => 'Personal Care', 'other' => 'Other',
];
$selected = static fn (string $field, string $value): string => form_value($field) === $value ? ' selected' : '';
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <div class="post-request-content-panel">
                <?= page_header([
                    'crumbs'  => [['Dashboard', 'dashboard'], ['Post a request', null]],
                    'eyebrow' => 'I need something',
                    'title'   => 'Post a request',
                    'lead'    => 'Tell runners what to buy, where, and where to bring it. Fields marked * are required. Your exact address and number are only shown to the runner who accepts.',
                ]) ?>

                <?= $this->include('includes/flash') ?>

                <p class="flash flash-info" id="draftNotice" role="status" hidden>
                    A saved draft was restored. <button type="button" class="btn btn-secondary btn-sm" id="discardDraft">Discard draft</button>
                </p>

                <div class="post-grid">
                    <section>
                        <form action="<?= base_url('request/process') ?>" method="post" id="postRequestForm" data-submit-once novalidate>
                            <?= csrf_field() ?>

                            <fieldset class="form-section">
                                <legend><h2><?= icon('package') ?> What do you need?</h2></legend>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="item_name">Item Name <span class="required-mark">*</span></label>
                                        <input type="text" id="item_name" name="item_name" placeholder="e.g. Rice (5 kg)" maxlength="255" required
                                               value="<?= esc(form_value('item_name')) ?>"<?= field_invalid_attrs('item_name') ?>>
                                        <?= field_error('item_name') ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="category">Category <span class="required-mark">*</span></label>
                                        <select id="category" name="category" required<?= field_invalid_attrs('category') ?>>
                                            <option value="">Select category</option>
                                            <?php foreach ($categoryLabels as $value => $label): ?>
                                                <option value="<?= $value ?>"<?= $selected('category', $value) ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?= field_error('category') ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="quantity">Quantity <span class="required-mark">*</span></label>
                                        <input type="number" id="quantity" name="quantity" min="1" max="999" step="1" inputmode="numeric" required
                                               value="<?= esc(form_value('quantity', '1')) ?>"<?= field_invalid_attrs('quantity') ?>>
                                        <?= field_error('quantity') ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="brand">Preferred Brand</label>
                                        <input type="text" id="brand" name="brand" placeholder="Optional" maxlength="255"
                                               value="<?= esc(form_value('brand')) ?>"<?= field_invalid_attrs('brand') ?>>
                                        <?= field_error('brand') ?>
                                    </div>

                                    <div class="form-group full-width">
                                        <label for="item_notes">Item Notes</label>
                                        <textarea id="item_notes" name="item_notes" rows="4" maxlength="1000" placeholder="Flavor, size, color, or other notes"<?= field_invalid_attrs('item_notes') ?>><?= esc(form_value('item_notes')) ?></textarea>
                                        <?= field_error('item_notes') ?>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend><h2><?= icon('store') ?> Where to buy it</h2></legend>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="store_name">Preferred Store <span class="required-mark">*</span></label>
                                        <input type="text" id="store_name" name="store_name" placeholder="Store name" maxlength="255" required
                                               value="<?= esc(form_value('store_name')) ?>"<?= field_invalid_attrs('store_name') ?>>
                                        <?= field_error('store_name') ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="alt_store">Alternative Store</label>
                                        <input type="text" id="alt_store" name="alt_store" placeholder="Optional" maxlength="255"
                                               value="<?= esc(form_value('alt_store')) ?>"<?= field_invalid_attrs('alt_store') ?>>
                                        <?= field_error('alt_store') ?>
                                    </div>

                                    <div class="form-group full-width">
                                        <label for="store_address">Store Address <span class="required-mark">*</span></label>
                                        <input type="text" id="store_address" name="store_address" placeholder="Store address or landmark" maxlength="500" required
                                               value="<?= esc(form_value('store_address')) ?>"<?= field_invalid_attrs('store_address') ?>>
                                        <?= field_error('store_address') ?>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend><h2><?= icon('map-pin') ?> Where to deliver<?php if (! empty($me['address']) || ! empty($me['phone_number'])): ?> <span class="chip legend-chip"><?= icon('user') ?> From your profile</span><?php endif; ?></h2></legend>

                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label for="delivery_address">Delivery Address / Barangay <span class="required-mark">*</span></label>
                                        <input type="text" id="delivery_address" name="delivery_address" placeholder="Where should the runner deliver?" maxlength="500" required autocomplete="street-address"
                                               value="<?= esc(form_value('delivery_address', $me['address'] ?? '')) ?>"<?= field_invalid_attrs('delivery_address') ?>>
                                        <?= field_error('delivery_address') ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="contact_number">Contact Number <span class="required-mark">*</span></label>
                                        <input type="tel" id="contact_number" name="contact_number" placeholder="09171234567" inputmode="tel" maxlength="16" required autocomplete="tel"
                                               value="<?= esc(form_value('contact_number', $me['phone_number'] ?? '')) ?>"<?= field_invalid_attrs('contact_number') ?>>
                                        <?= field_error('contact_number') ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="landmark">Landmark</label>
                                        <input type="text" id="landmark" name="landmark" placeholder="Optional, visible to runners" maxlength="255"
                                               value="<?= esc(form_value('landmark')) ?>"<?= field_invalid_attrs('landmark') ?>>
                                        <?= field_error('landmark') ?>
                                    </div>

                                    <div class="form-group full-width">
                                        <label for="delivery_notes">Delivery Notes</label>
                                        <textarea id="delivery_notes" name="delivery_notes" rows="3" maxlength="1000" placeholder="Instructions for the runner"<?= field_invalid_attrs('delivery_notes') ?>><?= esc(form_value('delivery_notes')) ?></textarea>
                                        <?= field_error('delivery_notes') ?>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend><h2><?= icon('wallet') ?> Price, payment and urgency</h2></legend>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="estimated_price">Estimated Item Price (₱) <span class="required-mark">*</span></label>
                                        <input type="number" id="estimated_price" name="estimated_price" step="0.01" min="0" max="100000" inputmode="decimal" placeholder="0.00" required
                                               value="<?= esc(form_value('estimated_price')) ?>"<?= field_invalid_attrs('estimated_price') ?>>
                                        <?= field_error('estimated_price') ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="service_fee">Service Fee Offer (₱) <span class="required-mark">*</span></label>
                                        <input type="number" id="service_fee" name="service_fee" step="0.01" min="0" max="10000" inputmode="decimal" placeholder="0.00" required
                                               value="<?= esc(form_value('service_fee')) ?>"<?= field_invalid_attrs('service_fee') ?>>
                                        <?= field_error('service_fee') ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="payment_method">Payment Method <span class="required-mark">*</span></label>
                                        <select id="payment_method" name="payment_method" required<?= field_invalid_attrs('payment_method') ?>>
                                            <option value="">Select payment method</option>
                                            <option value="wallet"<?= $selected('payment_method', 'wallet') ?>><?= esc(payment_method_label('wallet')) ?></option>
                                            <option value="cod"<?= $selected('payment_method', 'cod') ?>>Cash on delivery</option>
                                        </select>
                                        <?= field_error('payment_method') ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="urgency">Urgency <span class="required-mark">*</span></label>
                                        <select id="urgency" name="urgency" required<?= field_invalid_attrs('urgency') ?>>
                                            <option value="">Select urgency</option>
                                            <option value="normal"<?= $selected('urgency', 'normal') ?>>Normal</option>
                                            <option value="urgent"<?= $selected('urgency', 'urgent') ?>>Urgent</option>
                                            <option value="asap"<?= $selected('urgency', 'asap') ?>>ASAP</option>
                                        </select>
                                        <?= field_error('urgency') ?>
                                    </div>
                                </div>
                            </fieldset>

                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" id="saveDraft"><?= icon('save') ?> Save draft</button>
                                <button type="submit" class="btn btn-primary btn-lg" data-busy-label="Posting…"><?= icon('send') ?> Post request</button>
                            </div>
                            <p class="field-hint" id="draftStatus" role="status" aria-live="polite"></p>
                        </form>
                    </section>

                    <aside class="post-summary" aria-labelledby="summaryHeading"><div class="card">
                        <div class="card-head"><h2 id="summaryHeading"><?= icon('list') ?> Summary</h2></div>

                        <dl class="summary-list">
                            <div><dt>Item</dt><dd id="summaryItem">Not set</dd></div>
                            <div><dt>Quantity</dt><dd id="summaryQuantity">1</dd></div>
                            <div><dt>Store</dt><dd id="summaryStore">Not set</dd></div>
                            <div><dt>Urgency</dt><dd id="summaryUrgency">Not set</dd></div>
                            <div><dt>Payment</dt><dd id="summaryPayment">Not set</dd></div>
                        </dl>

                        <div class="money-breakdown" aria-live="polite" style="margin-top: var(--sp-3);">
                            <div class="row"><span>Estimated item price</span><strong id="summaryEstimated">₱0.00</strong></div>
                            <div class="row"><span>Service fee</span><strong id="summaryServiceFee">₱0.00</strong></div>
                            <div class="row total"><span>You pay</span><strong id="summaryTotal">₱0.00</strong></div>
                            <p class="note">
                                The runner pays a platform fee of <?= esc((string) $platformFeeRate) ?>% of the service fee
                                (<strong id="summaryPlatformFee">₱0.00</strong>), so they receive <strong id="summaryRunnerNet">₱0.00</strong>.
                                You are not charged extra.
                            </p>
                        </div>

                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('postRequestForm');
    var DRAFT_KEY = 'pasabuy:post-request-draft:v1';
    var FEE_BASIS_POINTS = <?= (int) \App\Libraries\FeePolicy::PLATFORM_FEE_BASIS_POINTS ?>;
    var hadServerInput = <?= session('_ci_old_input') ? 'true' : 'false' ?>;

    function cents(value) {
        var match = /^(\d{1,8})(?:\.(\d{1,2}))?$/.exec(String(value || '').trim());
        if (!match) { return 0; }
        return parseInt(match[1], 10) * 100 + parseInt((match[2] || '').padEnd(2, '0') || '0', 10);
    }

    function peso(c) {
        return '₱' + Math.floor(c / 100).toLocaleString('en-PH') + '.' + String(c % 100).padStart(2, '0');
    }

    function text(id, value) { document.getElementById(id).textContent = value; }

    function selectedLabel(select) {
        return select.value ? select.options[select.selectedIndex].text : 'Not set';
    }

    function update() {
        var item = cents(form.estimated_price.value);
        var fee = cents(form.service_fee.value);
        var platform = Math.floor((fee * FEE_BASIS_POINTS + 5000) / 10000); // same half-up rule as the server

        text('summaryItem', form.item_name.value.trim() || 'Not set');
        text('summaryQuantity', form.quantity.value || '1');
        text('summaryStore', form.store_name.value.trim() || 'Not set');
        text('summaryUrgency', selectedLabel(form.urgency));
        text('summaryPayment', selectedLabel(form.payment_method));
        text('summaryEstimated', peso(item));
        text('summaryServiceFee', peso(fee));
        text('summaryTotal', peso(item + fee));
        text('summaryPlatformFee', peso(platform));
        text('summaryRunnerNet', peso(item + fee - platform));
    }

    // ------------------------------------------------------ local draft
    function fields() {
        return Array.prototype.filter.call(form.elements, function (el) {
            return el.name && el.name !== '<?= csrf_token() ?>' && el.type !== 'hidden' && el.type !== 'submit' && el.type !== 'button';
        });
    }

    function saveDraft() {
        var data = {};
        fields().forEach(function (el) { data[el.name] = el.value; });
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
            text('draftStatus', 'Draft saved on this device.');
        } catch (e) {
            text('draftStatus', 'Could not save a draft in this browser.');
        }
    }

    function restoreDraft() {
        if (hadServerInput) { return; } // server re-filled the form after an error
        var raw = null;
        try { raw = localStorage.getItem(DRAFT_KEY); } catch (e) { return; }
        if (!raw) { return; }
        try {
            var data = JSON.parse(raw);
            fields().forEach(function (el) {
                if (Object.prototype.hasOwnProperty.call(data, el.name)) { el.value = data[el.name]; }
            });
            document.getElementById('draftNotice').hidden = false;
        } catch (e) { /* ignore corrupt draft */ }
    }

    document.getElementById('saveDraft').addEventListener('click', saveDraft);
    document.getElementById('discardDraft').addEventListener('click', function () {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
        form.reset();
        document.getElementById('draftNotice').hidden = true;
        update();
    });

    form.addEventListener('submit', function () {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
    });

    form.addEventListener('input', update);
    form.addEventListener('change', update);

    restoreDraft();
    update();
});
</script>

<?= $this->include('includes/footer') ?>
