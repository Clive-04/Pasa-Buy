<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="site-main" id="main-content">
    <div class="auth reverse">
        <section class="hero-card auth-aside" aria-labelledby="registerHeroTitle">
            <div>
                <p class="eyebrow" style="background: rgba(255,255,255,.12); color: var(--mint);">Join the community</p>
                <h1 id="registerHeroTitle">Need it or <em>deliver it</em> — one account does both.</h1>
                <p class="lead">Help neighbours get urgent items faster, and support the stores around you.</p>
            </div>
            <ul class="feature-list">
                <li><span class="feature-icon"><?= icon('plus') ?></span><div><strong>Request items</strong><span>From nearby stores, in a clear and organised way.</span></div></li>
                <li><span class="feature-icon"><?= icon('cash') ?></span><div><strong>Earn as a runner</strong><span>Keep the service fee minus a 10% platform fee.</span></div></li>
                <li><span class="feature-icon"><?= icon('shield') ?></span><div><strong>Your details stay private</strong><span>Your address and number go only to the runner on your errand.</span></div></li>
            </ul>
        </section>

        <section class="card auth-card" aria-labelledby="registerTitle">
            <h2 id="registerTitle">Create your account</h2>
            <p class="lead">It takes a minute. All fields are required.</p>

            <?= $this->include('includes/flash') ?>

            <form action="<?= base_url('register/process') ?>" method="post" data-submit-once novalidate style="margin-top: var(--sp-4);">
                <?= csrf_field() ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First name</label>
                        <input type="text" id="first_name" name="first_name" value="<?= esc(form_value('first_name')) ?>"
                               autocomplete="given-name" maxlength="20" required<?= field_invalid_attrs('first_name') ?>>
                        <?= field_error('first_name') ?>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last name</label>
                        <input type="text" id="last_name" name="last_name" value="<?= esc(form_value('last_name')) ?>"
                               autocomplete="family-name" maxlength="20" required<?= field_invalid_attrs('last_name') ?>>
                        <?= field_error('last_name') ?>
                    </div>

                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= esc(form_value('email')) ?>"
                               autocomplete="email" maxlength="50" required<?= field_invalid_attrs('email') ?>>
                        <?= field_error('email') ?>
                    </div>

                    <div class="form-group">
                        <label for="phone">Mobile number</label>
                        <input type="tel" id="phone" name="phone" placeholder="09171234567" inputmode="tel" value="<?= esc(form_value('phone')) ?>"
                               autocomplete="tel" maxlength="16" required<?= field_invalid_attrs('phone') ?>>
                        <?= field_error('phone') ?>
                    </div>

                    <div class="form-group full-width">
                        <label for="address">Address / barangay</label>
                        <input type="text" id="address" name="address" placeholder="Street, purok or barangay" value="<?= esc(form_value('address')) ?>"
                               autocomplete="street-address" maxlength="255" required<?= field_invalid_attrs('address') ?>>
                        <?= field_error('address') ?>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="At least 8 characters"
                               autocomplete="new-password" minlength="8" required<?= field_invalid_attrs('password') ?>>
                        <?= field_error('password') ?: '<p class="field-hint">Use at least 8 characters.</p>' ?>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Type it again"
                               autocomplete="new-password" required<?= field_invalid_attrs('confirm_password') ?>>
                        <?= field_error('confirm_password') ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" data-busy-label="Creating account…"><?= icon('check') ?> Create account</button>
            </form>

            <p class="auth-switch">Already have an account? <a class="link" href="<?= base_url('login') ?>">Log in</a></p>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
