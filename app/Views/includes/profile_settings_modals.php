<?php
$openModal = session()->getFlashdata('open_modal');
$autopen   = static fn (string $id): string => $openModal === $id ? ' data-autopen' : '';
$ratingNow = (string) (old('rating') ?? ($myWebsiteRating['rating'] ?? ''));
?>

<dialog class="pb-dialog" id="editProfileModal" aria-labelledby="editProfileTitle"<?= $autopen('editProfileModal') ?>>
    <div class="pb-dialog-head">
        <h2 id="editProfileTitle">Profile Settings</h2>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <form action="<?= base_url('profile/update') ?>" method="post" enctype="multipart/form-data" class="modal-form" data-submit-once novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" maxlength="20" required autocomplete="given-name"
                   value="<?= esc(form_value('first_name', $user['first_name'] ?? '')) ?>"<?= field_invalid_attrs('first_name') ?>>
            <?= field_error('first_name') ?>
        </div>

        <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" maxlength="20" required autocomplete="family-name"
                   value="<?= esc(form_value('last_name', $user['last_name'] ?? '')) ?>"<?= field_invalid_attrs('last_name') ?>>
            <?= field_error('last_name') ?>
        </div>

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" maxlength="100" autocomplete="username"
                   value="<?= esc(form_value('username', $user['username'] ?? '')) ?>"<?= field_invalid_attrs('username') ?>>
            <?= field_error('username') ?>
        </div>

        <div class="form-group">
            <label for="about_me">About Me</label>
            <textarea id="about_me" name="about_me" rows="4" maxlength="1000"<?= field_invalid_attrs('about_me') ?>><?= esc(form_value('about_me', $user['about_me'] ?? '')) ?></textarea>
            <?= field_error('about_me') ?>
        </div>

        <div class="form-group">
            <label for="profile_photo">Profile Photo</label>
            <input type="file" id="profile_photo" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"<?= field_invalid_attrs('profile_photo') ?>>
            <?= field_error('profile_photo') ?: '<p class="field-hint">JPG, PNG or WEBP, up to 2 MB.</p>' ?>
        </div>

        <div class="pb-dialog-actions">
            <button type="button" class="btn-light" data-close-dialog>Cancel</button>
            <button type="submit" class="btn-gradient" data-busy-label="Saving…">Save Changes</button>
        </div>
    </form>
</dialog>

<dialog class="pb-dialog" id="rateWebsiteModal" aria-labelledby="rateWebsiteTitle"<?= $autopen('rateWebsiteModal') ?>>
    <div class="pb-dialog-head">
        <h2 id="rateWebsiteTitle"><?= $myWebsiteRating ? 'Update your rating' : 'Rate Pasa-Buy' ?></h2>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <form action="<?= base_url('profile/rate-website') ?>" method="post" class="modal-form" data-submit-once>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="rating">Your Rating</label>
            <select id="rating" name="rating" required>
                <option value="">Select rating</option>
                <?php foreach ([5 => 'Excellent', 4 => 'Very Good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $ratingNow === (string) $value ? 'selected' : '' ?>><?= $value ?> - <?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="feedback">Feedback</label>
            <textarea id="feedback" name="feedback" rows="4" maxlength="1000" placeholder="Share your feedback..."><?= esc((string) (old('feedback') ?? ($myWebsiteRating['feedback'] ?? ''))) ?></textarea>
        </div>

        <div class="pb-dialog-actions">
            <button type="button" class="btn-light" data-close-dialog>Cancel</button>
            <button type="submit" class="btn-gradient" data-busy-label="Submitting…">Submit Rating</button>
        </div>
    </form>
</dialog>

<dialog class="pb-dialog" id="referFriendModal" aria-labelledby="referFriendTitle"<?= $autopen('referFriendModal') ?>>
    <div class="pb-dialog-head">
        <div>
            <h2 id="referFriendTitle">Refer a Friend</h2>
            <p>We'll save your referral. Pasa-Buy doesn't send emails, so share the link with your friend yourself.</p>
        </div>
        <button type="button" class="pb-dialog-close" data-close-dialog aria-label="Close">&times;</button>
    </div>

    <form action="<?= base_url('profile/refer-friend') ?>" method="post" class="modal-form" data-submit-once>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="friend_name">Friend's Name</label>
            <input type="text" id="friend_name" name="friend_name" maxlength="100" placeholder="Enter your friend's name" required
                   value="<?= esc((string) old('friend_name')) ?>">
        </div>

        <div class="form-group">
            <label for="friend_email">Friend's Email</label>
            <input type="email" id="friend_email" name="friend_email" maxlength="150" placeholder="Enter your friend's email" required
                   value="<?= esc((string) old('friend_email')) ?>">
        </div>

        <div class="pb-dialog-actions">
            <button type="button" class="btn-light" data-close-dialog>Cancel</button>
            <button type="submit" class="btn-gradient" data-busy-label="Saving…">Save Referral</button>
        </div>
    </form>
</dialog>
