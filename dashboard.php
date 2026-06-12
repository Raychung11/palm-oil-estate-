<?php
/**
 * dashboard.php
 * Management overview. KPI cards are placeholders until the operational
 * modules (harvest, workers, inventory...) come online in later phases.
 */
require_once __DIR__ . '/inc/auth.php';
require_login();

// --- Foundation stats we can show today --------------------------------
$totalUsers  = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$totalRoles  = (int)$pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();

$recent = $pdo->query(
    'SELECT l.action, l.module, l.description, l.created_at, u.name AS user_name
     FROM user_activity_logs l
     LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.id DESC LIMIT 8'
)->fetchAll();

// KPI cards from the blueprint (Section 11). Operational values arrive later.
$kpis = [
    ['label' => 'Today FFB (kg)',        'value' => '—', 'icon' => 'bi-basket',      'color' => 'success'],
    ['label' => 'Month-to-Date FFB',     'value' => '—', 'icon' => 'bi-graph-up',    'color' => 'primary'],
    ['label' => 'Active Workers',        'value' => '—', 'icon' => 'bi-person-badge', 'color' => 'info'],
    ['label' => 'Pending Approvals',     'value' => '—', 'icon' => 'bi-hourglass',   'color' => 'warning'],
    ['label' => 'Low Stock Items',       'value' => '—', 'icon' => 'bi-box-seam',    'color' => 'danger'],
    ['label' => 'Vehicles Due Service',  'value' => '—', 'icon' => 'bi-truck',       'color' => 'secondary'],
];

$page_title = 'Dashboard';
require __DIR__ . '/inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">Welcome back, <?= e($_SESSION['user_name']) ?></h1>
        <p class="text-muted small mb-0">Estate operations overview — <?= e(date('l, d M Y')) ?></p>
    </div>
</div>

<!-- Operational KPI cards (placeholders for upcoming phases) -->
<div class="row g-3 mb-4">
    <?php foreach ($kpis as $kpi): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="kpi-icon bg-<?= e($kpi['color']) ?> bg-opacity-10 text-<?= e($kpi['color']) ?>">
                    <i class="bi <?= e($kpi['icon']) ?>"></i>
                </span>
                <div>
                    <div class="kpi-value"><?= e($kpi['value']) ?></div>
                    <div class="kpi-label"><?= e($kpi['label']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- Foundation snapshot -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">System Snapshot</div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Total users</span><strong><?= $totalUsers ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Active users</span><strong><?= $activeUsers ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Roles configured</span><strong><?= $totalRoles ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Recent Activity</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>User</th><th>Action</th><th>Module</th><th>When</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recent)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No activity yet.</td></tr>
                    <?php else: foreach ($recent as $row): ?>
                        <tr>
                            <td><?= e($row['user_name'] ?? 'System') ?></td>
                            <td><?= e($row['action']) ?></td>
                            <td><?= e($row['module'] ?? '—') ?></td>
                            <td class="text-muted small"><?= e(fmt_datetime($row['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
