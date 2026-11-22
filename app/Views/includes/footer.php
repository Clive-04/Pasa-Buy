<footer class="site-footer">
    <div class="site-footer-inner">
        <div>
            <?= brand_mark(base_url(session('isLoggedIn') ? 'dashboard' : 'login')) ?>
            <p style="margin-top:8px;">A community errand platform: neighbours post what they need from nearby stores, and local runners buy and deliver it.</p>
        </div>
        <nav aria-label="Footer">
            <a href="<?= base_url('about') ?>">About</a>
            <a href="<?= base_url('contact') ?>">Contact</a>
            <?php if (session('isLoggedIn')): ?>
                <a href="<?= base_url('profile') ?>">My profile</a>
            <?php else: ?>
                <a href="<?= base_url('register') ?>">Create an account</a>
            <?php endif; ?>
        </nav>
        <p class="copyright">&copy; 2026 Pasa-Buy · Partnered with Neighborly. All rights reserved.</p>
    </div>
</footer>
</body>
</html>
