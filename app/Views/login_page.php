<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="site-main" id="main-content">
    <div class="auth">
        <section class="hero-card auth-aside" aria-labelledby="loginHeroTitle">
            <div>
                <p class="eyebrow" style="background: rgba(255,255,255,.12); color: var(--mint);">Welcome back</p>
                <h1 id="loginHeroTitle">Errands done by <em>your neighbours</em>.</h1>
                <p class="lead">Post what you need from a nearby store, or earn by delivering for people in your community.</p>
            </div>
            <ul class="feature-list">
                <li><span class="feature-icon"><?= icon('package') ?></span><div><strong>Post a request</strong><span>Item, store and where to bring it — that's it.</span></div></li>
                <li><span class="feature-icon"><?= icon('truck') ?></span><div><strong>Track every step</strong><span>Accepted, on the way, delivered — with updates.</span></div></li>
                <li><span class="feature-icon"><?= icon('wallet') ?></span><div><strong><?= is_demo_mode() ? 'Demo wallet' : 'Wallet' ?></strong><span><?= is_demo_mode() ? 'Try payments with simulated credits. No real money.' : 'Pay runners from your wallet balance.' ?></span></div></li>
            </ul>
        </section>

        <section class="card auth-card" aria-labelledby="loginTitle">
            <h2 id="loginTitle">Log in</h2>
            <p class="lead">Enter your account details to continue.</p>

            <?= $this->include('includes/flash') ?>

            <form action="<?= base_url('login/process') ?>" method="post" data-submit-once data-turbo="false" style="margin-top: var(--sp-4);">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com"
                           value="<?= esc(form_value('email')) ?>" autocomplete="email" maxlength="50" required
                           <?= form_value('email') === '' ? 'autofocus' : '' ?>>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Your password"
                           autocomplete="current-password" required
                           <?= form_value('email') !== '' ? 'autofocus' : '' ?>>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" data-busy-label="Logging in…"><?= icon('arrow-right') ?> Log in</button>
            </form>

            <p class="auth-switch">New to Pasa-Buy? <a class="link" href="<?= base_url('register') ?>">Create an account</a></p>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>
