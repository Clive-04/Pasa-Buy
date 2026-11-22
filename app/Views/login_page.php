<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="auth-page" id="main-content">
    <section class="auth-section">
        <div class="auth-container login-layout">
            <div class="auth-visual">
                <div class="visual-overlay"></div>
                <div class="visual-content">
                    <span class="visual-badge">Welcome Back</span>
                    <h1>Login to Pasa-Buy</h1>
                    <p>
                        Access your requests, track deliveries, contact users,
                        and manage your local errands in one place.
                    </p>

                    <div class="visual-features">
                        <div class="visual-feature-card">
                            <h3>Track Requests</h3>
                            <p>Follow each request from accepted to delivered.</p>
                        </div>
                        <div class="visual-feature-card">
                            <h3>Message Other Users</h3>
                            <p>Stay connected for faster coordination.</p>
                        </div>
                        <div class="visual-feature-card">
                            <h3><?= is_demo_mode() ? 'Demo Wallet' : 'Wallet' ?></h3>
                            <p><?= is_demo_mode() ? 'Try payments with simulated credits. No real money is used.' : 'Pay runners from your wallet balance.' ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-form-side">
                <div class="auth-card">
                    <div class="auth-header">
                        <h2>Sign In</h2>
                        <p>Enter your account details to continue.</p>
                    </div>

                    <?= $this->include('includes/flash') ?>

                    <form action="<?= base_url('login/process') ?>" method="post" class="auth-form" data-submit-once>
                        <?= csrf_field() ?>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="Enter your email"
                                   value="<?= esc(form_value('email')) ?>" autocomplete="email" maxlength="50" required
                                   <?= form_value('email') === '' ? 'autofocus' : '' ?>>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Enter your password"
                                   autocomplete="current-password" required
                                   <?= form_value('email') !== '' ? 'autofocus' : '' ?>>
                        </div>

                        <button type="submit" class="auth-btn primary-btn" data-busy-label="Signing in…">Login</button>

                        <p class="auth-footer-text">
                            Don’t have an account?
                            <a href="<?= base_url('register') ?>">Register here</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?= $this->include('includes/footer') ?>
