<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="auth-page" id="main-content">
    <section class="auth-section">
        <div class="auth-container register-layout">
            <div class="auth-form-side">
                <div class="auth-card register-card">
                    <div class="auth-header">
                        <h2>Create Account</h2>
                        <p>Join Pasa-Buy and start requesting or delivering nearby items.</p>
                    </div>

                    <?= $this->include('includes/flash') ?>

                    <form action="<?= base_url('register/process') ?>" method="post" class="auth-form" data-submit-once novalidate>
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" placeholder="Enter your first name"
                                       value="<?= esc(form_value('first_name')) ?>" autocomplete="given-name" maxlength="20" required<?= field_invalid_attrs('first_name') ?>>
                                <?= field_error('first_name') ?>
                            </div>

                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" placeholder="Enter your last name"
                                       value="<?= esc(form_value('last_name')) ?>" autocomplete="family-name" maxlength="20" required<?= field_invalid_attrs('last_name') ?>>
                                <?= field_error('last_name') ?>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="Enter your email"
                                       value="<?= esc(form_value('email')) ?>" autocomplete="email" maxlength="50" required<?= field_invalid_attrs('email') ?>>
                                <?= field_error('email') ?>
                            </div>

                            <div class="form-group">
                                <label for="phone">Mobile Number</label>
                                <input type="tel" id="phone" name="phone" placeholder="09171234567" inputmode="tel"
                                       value="<?= esc(form_value('phone')) ?>" autocomplete="tel" maxlength="16" required<?= field_invalid_attrs('phone') ?>>
                                <?= field_error('phone') ?>
                            </div>

                            <div class="form-group full-width">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" placeholder="At least 8 characters"
                                       autocomplete="new-password" minlength="8" required<?= field_invalid_attrs('password') ?>>
                                <?= field_error('password') ?: '<p class="field-hint">Use at least 8 characters.</p>' ?>
                            </div>

                            <div class="form-group full-width">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password"
                                       autocomplete="new-password" required<?= field_invalid_attrs('confirm_password') ?>>
                                <?= field_error('confirm_password') ?>
                            </div>

                            <div class="form-group full-width">
                                <label for="address">Address / Barangay</label>
                                <input type="text" id="address" name="address" placeholder="Enter your address or barangay"
                                       value="<?= esc(form_value('address')) ?>" autocomplete="street-address" maxlength="255" required<?= field_invalid_attrs('address') ?>>
                                <?= field_error('address') ?>
                            </div>
                        </div>

                        <button type="submit" class="auth-btn primary-btn" data-busy-label="Creating account…">Create Account</button>

                        <p class="auth-footer-text">
                            Already have an account?
                            <a href="<?= base_url('login') ?>">Login here</a>
                        </p>
                    </form>
                </div>
            </div>

            <div class="auth-visual register-visual">
                <div class="visual-overlay"></div>
                <div class="visual-content">
                    <span class="visual-badge">Join the Community</span>
                    <h1>Start using Pasa-Buy today</h1>
                    <p>
                        Help your community by requesting or delivering urgent items
                        faster and more easily.
                    </p>

                    <div class="visual-features">
                        <div class="visual-feature-card">
                            <h3>Post Requests</h3>
                            <p>Request items from nearby stores in a clear and organized way.</p>
                        </div>
                        <div class="visual-feature-card">
                            <h3>Accept Requests</h3>
                            <p>Accept nearby requests and earn the service fee (minus a 10% platform fee).</p>
                        </div>
                        <div class="visual-feature-card">
                            <h3>Community First</h3>
                            <p>Support local stores and connect with nearby residents.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?= $this->include('includes/footer') ?>
