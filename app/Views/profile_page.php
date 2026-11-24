<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$field = static function (?string $value, string $empty = 'Not added yet'): string {
    return $value !== null && trim($value) !== '' ? esc($value) : '<span class="is-empty-text">' . esc($empty) . '</span>';
};
?>

<main class="dashboard-page" id="main-content">
    <div class="dashboard-container">
        <?= $this->include('includes/sidebar2') ?>

        <section class="dashboard-main">
            <?= page_header([
                'crumbs' => [['Dashboard', 'dashboard'], ['My profile', null]],
                'title'  => 'My profile',
            ]) ?>

            <?= $this->include('includes/flash') ?>

            <section class="hero-card profile-hero" aria-label="Profile summary">
                <?= avatar($user, 'lg') ?>
                <div class="profile-hero-text">
                    <h2 class="profile-name"><?= esc($fullName) ?></h2>
                    <p class="handle"><?= ! empty($user['username']) ? '@' . esc($user['username']) : 'No username yet' ?></p>
                    <div class="chips">
                        <span class="chip chip-on-hero"><?= icon('calendar') ?> Member since <?= esc(date('F Y', strtotime($user['created_at']))) ?></span>
                        <span class="chip chip-on-hero"><?= icon('star') ?>
                            <?= $rating !== null ? esc(number_format($rating, 1)) . ' from ' . (int) $ratingCount . ' rating' . ($ratingCount === 1 ? '' : 's') : 'No ratings yet' ?>
                        </span>
                        <span class="chip chip-on-hero"><?= icon('shield') ?> <?= ($user['role'] ?? 'user') === 'admin' ? 'Administrator' : 'Community member' ?></span>
                    </div>
                </div>
                <button type="button" class="btn btn-accent" data-open-dialog="editProfileModal"><?= icon('edit') ?> Edit profile</button>
            </section>

            <div class="profile-grid">
                <div>
                    <section class="card" aria-labelledby="personalHeading">
                        <div class="card-head">
                            <h2 id="personalHeading"><?= icon('user') ?> Personal information</h2>
                        </div>
                        <dl class="info-list">
                            <div>
                                <span class="info-icon"><?= icon('user') ?></span>
                                <dt>First name</dt>
                                <dd><?= $field($user['first_name']) ?></dd>
                            </div>
                            <div>
                                <span class="info-icon"><?= icon('user') ?></span>
                                <dt>Last name</dt>
                                <dd><?= $field($user['last_name']) ?></dd>
                            </div>
                            <div>
                                <span class="info-icon"><?= icon('mail') ?></span>
                                <dt>Email address</dt>
                                <dd><?= $field($user['email']) ?><small>Used to log in · can't be changed here</small></dd>
                            </div>
                            <div>
                                <span class="info-icon"><?= icon('phone') ?></span>
                                <dt>Mobile number</dt>
                                <dd><?= $field($user['phone_number']) ?><small>Shared only with runners/requesters on your active errands</small></dd>
                            </div>
                            <div>
                                <span class="info-icon"><?= icon('map-pin') ?></span>
                                <dt>Address / barangay</dt>
                                <dd><?= $field($user['address']) ?></dd>
                            </div>
                            <div>
                                <span class="info-icon"><?= icon('users') ?></span>
                                <dt>Username</dt>
                                <dd><?= ! empty($user['username']) ? '@' . esc($user['username']) : $field(null, 'Not set — add one so people can find you') ?></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="card" aria-labelledby="aboutHeading">
                        <div class="card-head">
                            <h2 id="aboutHeading"><?= icon('info') ?> About me</h2>
                        </div>
                        <p style="margin:0; line-height:1.6; color: var(--pb-ink-2);">
                            <?= ! empty($user['about_me']) ? nl2br(esc($user['about_me'])) : 'Tell your neighbours a little about yourself, for example when you are usually free to run errands.' ?>
                        </p>
                    </section>
                </div>

                <div>
                    <section class="card" aria-labelledby="activityHeading">
                        <div class="card-head">
                            <h2 id="activityHeading"><?= icon('dashboard') ?> My activity</h2>
                        </div>
                        <div class="mini-stats">
                            <a href="<?= base_url('my-requests') ?>" class="mini-stat"><strong><?= (int) $totalRequests ?></strong><span>Requests posted</span></a>
                            <a href="<?= base_url('my-requests') ?>" class="mini-stat"><strong><?= (int) $activeRequests ?></strong><span>Still in progress</span></a>
                            <a href="<?= base_url('accepted-requests') ?>" class="mini-stat"><strong><?= (int) $acceptedRequests ?></strong><span>Errands accepted</span></a>
                            <a href="<?= base_url('accepted-requests?status=delivered') ?>" class="mini-stat"><strong><?= (int) $deliveredRequests ?></strong><span>Delivered</span></a>
                        </div>
                        <a href="<?= base_url('wallet') ?>" class="profile-wallet">
                            <span><?= icon('wallet') ?> <?= is_demo_mode() ? 'Demo wallet balance' : 'Wallet balance' ?></span>
                            <strong><?= money($user['wallet_balance']) ?></strong>
                        </a>
                    </section>

                    <section class="card" aria-labelledby="settingsHeading">
                        <div class="card-head">
                            <h2 id="settingsHeading"><?= icon('settings') ?> Settings</h2>
                        </div>
                        <div class="settings-list">
                            <button type="button" class="settings-item" data-open-dialog="passwordModal">
                                <?= icon('lock') ?><span>Change password<small>Keep your account secure</small></span><?= icon('chevron') ?>
                            </button>
                            <button type="button" class="settings-item" data-open-dialog="rateWebsiteModal">
                                <?= icon('star') ?><span><?= $myWebsiteRating ? 'Update your rating' : 'Rate Pasa-Buy' ?><small><?= $myWebsiteRating ? 'You gave ' . (int) $myWebsiteRating['rating'] . ' stars' : 'Tell us how we are doing' ?></small></span><?= icon('chevron') ?>
                            </button>
                            <button type="button" class="settings-item" data-open-dialog="referFriendModal">
                                <?= icon('gift') ?><span>Refer a friend<small><?= (int) $referralCount ?> referral<?= $referralCount === 1 ? '' : 's' ?> saved</small></span><?= icon('chevron') ?>
                            </button>
                        </div>
                    </section>
                </div>
            </div>

            <?= $this->include('includes/profile_settings_modals') ?>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
