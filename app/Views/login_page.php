<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="site-main" id="main-content">
    <div class="auth">
        <section class="hero-card auth-aside" aria-labelledby="loginHeroTitle">
            <div>
                <p class="eyebrow">Welcome back</p>
                <h1 id="loginHeroTitle">Errands done by <em>your neighbours</em>.</h1>
                <p class="lead">Post what you need from a nearby store, or earn by delivering for people in your community.</p>
            </div>
            <?= $this->include('includes/auth_scene') ?>
            <ul class="auth-points">
                <li><?= icon('check') ?> Post an errand in a minute</li>
                <li><?= icon('check') ?> Follow it from store to door</li>
                <li><?= icon('check') ?> <?= is_demo_mode() ? 'Demo wallet: simulated credits, no real money' : 'Pay from your wallet' ?></li>
            </ul>
        </section>

        <section class="card auth-card" aria-labelledby="loginTitle">
            <h2 id="loginTitle">Log in</h2>
            <p class="lead">Enter your account details to continue.</p>

            <?= $this->include('includes/flash') ?>

            <form action="<?= base_url('login/process') ?>" method="post" data-submit-once data-turbo="false" data-auth-form class="auth-form">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com"
                           value="<?= esc(form_value('email')) ?>" autocomplete="email" maxlength="50" required
                           aria-describedby="emailHint" data-check="email"
                           <?= form_value('email') === '' ? 'autofocus' : '' ?>>
                    <p class="field-hint-live" id="emailHint" aria-live="polite"></p>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="pw-field">
                        <input type="password" id="password" name="password" placeholder="Your password"
                               autocomplete="current-password" required aria-describedby="capsHint"
                               <?= form_value('email') !== '' ? 'autofocus' : '' ?>>
                        <button type="button" class="pw-toggle" data-pw-toggle aria-controls="password" aria-pressed="false" aria-label="Show password" title="Show password">
                            <?= icon('eye', 'pw-show') ?><?= icon('eye-off', 'pw-hide') ?>
                        </button>
                    </div>
                    <p class="caps-hint" id="capsHint" aria-live="polite" hidden><?= icon('alert') ?> Caps Lock is on</p>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" data-busy-label="Logging in…">Log in <?= icon('arrow-right') ?></button>
            </form>

            <p class="auth-switch">New to Pasa-Buy? <a class="link" href="<?= base_url('register') ?>">Create an account</a></p>
        </section>
    </div>
</main>

<script src="<?= asset('js/auth.js') ?>"></script>
<?= $this->include('includes/footer') ?>
