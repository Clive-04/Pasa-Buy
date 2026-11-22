<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="page-section">
    <div class="about-wrapper">
        <section class="about-intro">
            <h1>About Neighborly</h1>
            <p>
                Neighborly is a local technology startup founded in 2026 in Lucban, Quezon.
            </p>
            <p>
                It develops community-based digital platforms that connect residents, support local businesses, and make everyday transactions faster, easier, and more accessible.
            </p>
        </section>

        <section class="team-section">
            <h2>Meet the Team</h2>

            <div class="team-grid">
                <div class="team-card">
                    <div class="team-image">
                        <img src="<?= base_url('img/team/reniedo.png') ?>" alt="Bea Nicole Reniedo">
                    </div>
                    <h3>Bea Nicole Reniedo</h3>
                    <span class="team-role">Project Lead</span>
                    <p>Oversees the system design and keeps the platform efficient and organized.</p>
                </div>

                <div class="team-card">
                    <div class="team-image">
                        <img src="<?= base_url('img/team/luce.jpg') ?>" alt="Alexa May Cy Luce">
                    </div>
                    <h3>Alexa May "Cy" Luce</h3>
                    <span class="team-role">Market & Operations Strategist</span>
                    <p>Handles market planning and helps ensure the platform meets community needs.</p>
                </div>

                <div class="team-card">
                    <div class="team-image">
                        <img src="<?= base_url('img/team/sales.jpg') ?>" alt="Marjhelly P. Sales">
                    </div>
                    <h3>Marjhelly P. Sales</h3>
                    <span class="team-role">User Experience & Design Specialist</span>
                    <p>Designs a simple and user-friendly interface for a smooth experience.</p>
                </div>
            </div>
        </section>
    </div>
</main>

<?= $this->include('includes/footer') ?>