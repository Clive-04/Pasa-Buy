<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>
<?php
// Two steps in one form. Without JavaScript both show at once and the form
// works as before. After a failed submit, reopen the step with the problem.
$errors    = session('errors');
$errors    = is_array($errors) ? $errors : [];
$stepOne   = ['first_name', 'last_name', 'email', 'phone', 'address'];
$startStep = array_intersect_key($errors, array_flip($stepOne)) === [] && $errors !== [] ? 2 : 1;
?>

<main class="site-main" id="main-content">
    <div class="auth reverse">
        <section class="hero-card auth-aside" aria-labelledby="registerHeroTitle">
            <div>
                <p class="eyebrow">Join the community</p>
                <h1 id="registerHeroTitle">Need it or <em>deliver it</em> — one account does both.</h1>
                <p class="lead">Help neighbours get urgent items faster, and support the stores around you.</p>
            </div>
            <?= $this->include('includes/auth_scene') ?>
            <ul class="auth-points">
                <li><?= icon('check') ?> Request items from nearby stores</li>
                <li><?= icon('check') ?> Earn as a runner: the service fee minus 10%</li>
                <li><?= icon('check') ?> Your address and number go only to your runner</li>
            </ul>
        </section>

        <section class="card auth-card" aria-labelledby="registerTitle">
            <h2 id="registerTitle">Create your account</h2>
            <p class="lead">Two quick steps. All fields are required.</p>

            <ol class="stepper" data-stepper hidden>
                <li class="is-current" data-step-indicator="1"><span>1</span> About you</li>
                <li data-step-indicator="2"><span>2</span> Password</li>
            </ol>

            <?= $this->include('includes/flash') ?>

            <form action="<?= base_url('register/process') ?>" method="post" data-submit-once novalidate data-turbo="false"
                  data-auth-form data-register data-start-step="<?= (int) $startStep ?>" class="auth-form">
                <?= csrf_field() ?>

                <fieldset class="auth-step" data-step="1">
                    <legend class="visually-hidden">Step 1 of 2: About you</legend>
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
                                   autocomplete="email" maxlength="50" required data-check="email"<?= field_invalid_attrs('email') ?>>
                            <?= field_error('email') ?>
                            <p class="field-hint-live" data-hint-for="email" aria-live="polite"></p>
                        </div>

                        <div class="form-group">
                            <label for="phone">Mobile number</label>
                            <input type="tel" id="phone" name="phone" placeholder="0917 123 4567" inputmode="tel" value="<?= esc(form_value('phone')) ?>"
                                   autocomplete="tel" maxlength="16" required data-check="phone"<?= field_invalid_attrs('phone') ?>>
                            <?= field_error('phone') ?>
                            <p class="field-hint-live" data-hint-for="phone" aria-live="polite"></p>
                        </div>

                        <div class="form-group full-width">
                            <label for="address">Address / barangay</label>
                            <input type="text" id="address" name="address" placeholder="Street, purok or barangay" value="<?= esc(form_value('address')) ?>"
                                   autocomplete="street-address" maxlength="255" required<?= field_invalid_attrs('address') ?>>
                            <?= field_error('address') ?>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg btn-block" data-step-next hidden>Continue <?= icon('arrow-right') ?></button>
                </fieldset>

                <fieldset class="auth-step" data-step="2">
                    <legend class="visually-hidden">Step 2 of 2: Secure your account</legend>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="password">Password</label>
                            <div class="pw-field">
                                <input type="password" id="password" name="password" placeholder="At least 8 characters"
                                       autocomplete="new-password" minlength="8" maxlength="72" required data-check="strength"
                                       aria-describedby="strengthText capsHint"<?= field_invalid_attrs('password') ?>>
                                <button type="button" class="pw-toggle" data-pw-toggle aria-controls="password" aria-pressed="false" aria-label="Show password" title="Show password">
                                    <?= icon('eye', 'pw-show') ?><?= icon('eye-off', 'pw-hide') ?>
                                </button>
                            </div>
                            <?= field_error('password') ?>
                            <div class="strength" data-strength hidden>
                                <span class="strength-bar"><span></span></span>
                                <span class="strength-text" id="strengthText" aria-live="polite"></span>
                            </div>
                            <p class="caps-hint" id="capsHint" aria-live="polite" hidden><?= icon('alert') ?> Caps Lock is on</p>
                        </div>

                        <div class="form-group full-width">
                            <label for="confirm_password">Confirm password</label>
                            <div class="pw-field">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Type it again"
                                       autocomplete="new-password" required data-check="match"<?= field_invalid_attrs('confirm_password') ?>>
                                <button type="button" class="pw-toggle" data-pw-toggle aria-controls="confirm_password" aria-pressed="false" aria-label="Show password" title="Show password">
                                    <?= icon('eye', 'pw-show') ?><?= icon('eye-off', 'pw-hide') ?>
                                </button>
                            </div>
                            <?= field_error('confirm_password') ?>
                            <p class="field-hint-live" data-hint-for="confirm_password" aria-live="polite"></p>
                        </div>
                    </div>

                    <div class="auth-step-actions">
                        <button type="button" class="btn btn-secondary btn-lg" data-step-back hidden><?= icon('arrow-left') ?> Back</button>
                        <button type="submit" class="btn btn-primary btn-lg" data-busy-label="Creating account…"><?= icon('check') ?> Create account</button>
                    </div>
                </fieldset>
            </form>

            <p class="auth-switch">Already have an account? <a class="link" href="<?= base_url('login') ?>">Log in</a></p>
        </section>
    </div>
</main>

<script src="<?= asset('js/auth.js') ?>"></script>
<?= $this->include('includes/footer') ?>
