<?php
$openModal = session()->getFlashdata('open_modal');
$autopen   = static fn (string $id): string => $openModal === $id ? ' data-autopen' : '';
$ratingNow = (string) (old('rating') ?? ($myWebsiteRating['rating'] ?? ''));
?>

<dialog class="pb-dialog" id="editProfileModal" aria-labelledby="editProfileTitle"<?= $autopen('editProfileModal') ?>>
    <div class="pb-dialog-head">
        <div>
            <h2 id="editProfileTitle">Edit profile</h2>
            <p>Update the details you registered with. Fields marked <span class="required-mark">*</span> are required.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <form action="<?= base_url('profile/update') ?>" method="post" enctype="multipart/form-data" class="modal-form" data-submit-once novalidate>
        <?= csrf_field() ?>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="first_name">First name <span class="required-mark">*</span></label>
                <input type="text" id="first_name" name="first_name" maxlength="20" required autocomplete="given-name"
                       value="<?= esc(form_value('first_name', $user['first_name'] ?? '')) ?>"<?= field_invalid_attrs('first_name') ?>>
                <?= field_error('first_name') ?>
            </div>

            <div class="form-group">
                <label for="last_name">Last name <span class="required-mark">*</span></label>
                <input type="text" id="last_name" name="last_name" maxlength="20" required autocomplete="family-name"
                       value="<?= esc(form_value('last_name', $user['last_name'] ?? '')) ?>"<?= field_invalid_attrs('last_name') ?>>
                <?= field_error('last_name') ?>
            </div>
        </div>

        <div class="form-group">
            <label>Email address</label>
            <div class="readonly-field"><?= icon('lock') ?> <?= esc($user['email'] ?? '') ?></div>
            <p class="field-hint">Your email is used to log in, so it can't be changed here.</p>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="phone_number">Mobile number <span class="required-mark">*</span></label>
                <input type="tel" id="phone_number" name="phone_number" maxlength="16" required inputmode="tel" autocomplete="tel" placeholder="09171234567"
                       value="<?= esc(form_value('phone_number', $user['phone_number'] ?? '')) ?>"<?= field_invalid_attrs('phone_number') ?>>
                <?= field_error('phone_number') ?>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" maxlength="100" autocomplete="username" placeholder="e.g. maria.santos"
                       value="<?= esc(form_value('username', $user['username'] ?? '')) ?>"<?= field_invalid_attrs('username') ?>>
                <?= field_error('username') ?: '<p class="field-hint">Helps people find you in search.</p>' ?>
            </div>
        </div>

        <div class="form-group">
            <label for="address">Address / barangay <span class="required-mark">*</span></label>
            <input type="text" id="address" name="address" maxlength="255" required autocomplete="street-address"
                   value="<?= esc(form_value('address', $user['address'] ?? '')) ?>"<?= field_invalid_attrs('address') ?>>
            <?= field_error('address') ?>
        </div>

        <div class="form-group">
            <label for="about_me">About me</label>
            <textarea id="about_me" name="about_me" rows="3" maxlength="1000"<?= field_invalid_attrs('about_me') ?>><?= esc(form_value('about_me', $user['about_me'] ?? '')) ?></textarea>
            <?= field_error('about_me') ?>
        </div>

        <div class="form-group">
            <label for="profile_photo">Profile photo</label>
            <input type="file" id="profile_photo" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"<?= field_invalid_attrs('profile_photo') ?>>
            <?= field_error('profile_photo') ?: '<p class="field-hint">JPG, PNG or WEBP, up to 2 MB. Leave empty to keep your current photo.</p>' ?>
        </div>

        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Saving…"><?= icon('save') ?> Save changes</button>
        </div>
    </form>
</dialog>

<dialog class="pb-dialog" id="passwordModal" aria-labelledby="passwordTitle"<?= $autopen('passwordModal') ?>>
    <div class="pb-dialog-head">
        <div>
            <h2 id="passwordTitle">Change password</h2>
            <p>Enter your current password, then choose a new one with at least 8 characters.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <form action="<?= base_url('profile/password') ?>" method="post" class="modal-form" data-submit-once>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password"<?= field_invalid_attrs('current_password') ?>>
            <?= field_error('current_password') ?>
        </div>

        <div class="form-group">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password"<?= field_invalid_attrs('new_password') ?>>
            <?= field_error('new_password') ?>
        </div>

        <div class="form-group">
            <label for="confirm_new_password">Confirm new password</label>
            <input type="password" id="confirm_new_password" name="confirm_password" required autocomplete="new-password"<?= field_invalid_attrs('confirm_password') ?>>
            <?= field_error('confirm_password') ?>
        </div>

        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Updating…"><?= icon('lock') ?> Change password</button>
        </div>
    </form>
</dialog>

<dialog class="pb-dialog" id="rateWebsiteModal" aria-labelledby="rateWebsiteTitle"<?= $autopen('rateWebsiteModal') ?>>
    <div class="pb-dialog-head">
        <div>
            <h2 id="rateWebsiteTitle"><?= $myWebsiteRating ? 'Update your rating' : 'Rate Pasa-Buy' ?></h2>
            <p>Your feedback helps improve Pasa-Buy for your community.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <form action="<?= base_url('profile/rate-website') ?>" method="post" class="modal-form" data-submit-once>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="rating">Your rating</label>
            <select id="rating" name="rating" required>
                <option value="">Select rating</option>
                <?php foreach ([5 => 'Excellent', 4 => 'Very good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $ratingNow === (string) $value ? 'selected' : '' ?>><?= str_repeat('★', $value) ?> <?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="feedback">Feedback <span class="text-muted">(optional)</span></label>
            <textarea id="feedback" name="feedback" rows="4" maxlength="1000" placeholder="What do you like, or what should we improve?"><?= esc((string) (old('feedback') ?? ($myWebsiteRating['feedback'] ?? ''))) ?></textarea>
        </div>

        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Submitting…"><?= icon('star') ?> Submit rating</button>
        </div>
    </form>
</dialog>

<dialog class="pb-dialog" id="referFriendModal" aria-labelledby="referFriendTitle"<?= $autopen('referFriendModal') ?>>
    <div class="pb-dialog-head">
        <div>
            <h2 id="referFriendTitle">Refer a friend</h2>
            <p>We'll save your referral. Pasa-Buy doesn't send emails, so share the link with your friend yourself.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <form action="<?= base_url('profile/refer-friend') ?>" method="post" class="modal-form" data-submit-once>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="friend_name">Friend's name</label>
            <input type="text" id="friend_name" name="friend_name" maxlength="100" placeholder="Enter your friend's name" required
                   value="<?= esc((string) old('friend_name')) ?>">
        </div>

        <div class="form-group">
            <label for="friend_email">Friend's email</label>
            <input type="email" id="friend_email" name="friend_email" maxlength="150" placeholder="name@example.com" required
                   value="<?= esc((string) old('friend_email')) ?>">
        </div>

        <div class="pb-dialog-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary" data-busy-label="Saving…"><?= icon('gift') ?> Save referral</button>
        </div>
    </form>
</dialog>
