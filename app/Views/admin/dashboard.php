<?= $this->include('includes/header') ?>
<?= $this->include('includes/navbar') ?>

<main class="dashboard-page admin-page">
    <div class="dashboard-container">

        <?= $this->include('includes/admin_sidebar') ?>

        <section class="dashboard-main" style="width: 100%;">

            <!-- HERO -->
            <div class="dashboard-hero">
                <div>
                    <span class="dashboard-badge">Admin Panel</span>
                    <h1>Admin Dashboard</h1>
                    <p>
                        Monitor platform activity, total tax collected, and ongoing requests.
                    </p>
                </div>
            </div>

            <!-- STATS -->
            <div class="dashboard-stats">

                <div class="stat-box">
                    <span class="stat-label">Total Tax Collected</span>
                    <h2>₱<?= number_format($totalTax ?? 0, 2) ?></h2>
                    <p>Total earnings from all requests</p>
                </div>

                <div class="stat-box">
                    <span class="stat-label">Ongoing Requests</span>
                    <h2><?= esc($ongoingCount ?? 0) ?></h2>
                    <p>Requests kept until deleted by admin</p>
                </div>

            </div>

            <!-- REQUESTS -->
            <div class="dashboard-card">

                <div class="card-header">
                    <h3>Delivered Requests</h3>
                </div>

                <div class="request-list">

                    <?php if (!empty($ongoing)): ?>
                        <?php foreach ($ongoing as $request): ?>
                            <div class="request-item">

                                <div>
                                    <h4><?= esc($request['item_name']) ?></h4>
                                    <p>
                                        User ID: <?= esc($request['user_id']) ?> •
                                        Tax: ₱<?= number_format((float) ($request['tax'] ?? 0), 2) ?>
                                    </p>
                                </div>

                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="status-badge <?= esc($request['status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                                    </span>

                                    <form action="<?= base_url('/admin/request/delete/' . $request['id']) ?>" method="post">
                                        <?= csrf_field() ?>
                                        <button 
                                            type="submit" 
                                            onclick="return confirm('Delete this request?')"
                                            style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer;"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="request-item">
                            <div>
                                <h4>No delivered requests</h4>
                                <p>No completed deliveries yet.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </section>
    </div>
</main>

<script>
    setInterval(function () {
        location.reload();
    }, 5000);
</script>

<?= $this->include('includes/footer') ?>