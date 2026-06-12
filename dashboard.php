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

// Harvest KPIs — computed defensively so the dashboard still works
// before the Phase 3 tables have been imported.
$todayFfb = $mtdFfb = $pendingApprovals = $activeWorkers = $todayAttendance = $expiringPermits = '—';
try {
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $todayFfb = num($pdo->query("SELECT COALESCE(SUM(ffb_weight_kg),0) FROM harvest_records WHERE harvest_date = '$today'")->fetchColumn());
    $mtdFfb   = kg_to_tonnes($pdo->query("SELECT COALESCE(SUM(ffb_weight_kg),0) FROM harvest_records WHERE harvest_date BETWEEN '$monthStart' AND '$today'")->fetchColumn());
    $pendingApprovals = (string)(int)$pdo->query("SELECT COUNT(*) FROM harvest_records WHERE approval_status = 'pending'")->fetchColumn();
    $activeWorkers = (string)(int)$pdo->query("SELECT COUNT(*) FROM workers WHERE status = 'active'")->fetchColumn();
    $todayAttendance = (string)(int)$pdo->query("SELECT COUNT(*) FROM worker_attendance WHERE attendance_date = '$today' AND status = 'present'")->fetchColumn();
    $soon = date('Y-m-d', strtotime('+30 days'));
    $expiringPermits = (string)(int)$pdo->query("SELECT COUNT(*) FROM workers WHERE status = 'active' AND permit_expiry IS NOT NULL AND permit_expiry <= '$soon'")->fetchColumn();
} catch (Throwable $e) {
    // Tables not yet present — leave the placeholder dashes.
}

$lowStock = $vehiclesDue = '—';
try {
    $soon = date('Y-m-d', strtotime('+30 days'));
    $lowStock = (string)(int)$pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status = 'active' AND current_stock <= minimum_stock")->fetchColumn();
    $vehiclesDue = (string)(int)$pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'active' AND ((road_tax_expiry IS NOT NULL AND road_tax_expiry <= '$soon') OR (insurance_expiry IS NOT NULL AND insurance_expiry <= '$soon'))")->fetchColumn();
} catch (Throwable $e) {
    // Assets/inventory tables not yet present.
}

// KPI cards from the blueprint (Section 11).
$kpis = [
    ['label' => 'Today FFB (kg)',        'value' => $todayFfb,        'icon' => 'bi-basket',       'color' => 'success'],
    ['label' => 'Month-to-Date FFB (t)', 'value' => $mtdFfb,          'icon' => 'bi-graph-up',     'color' => 'primary'],
    ['label' => 'Today Attendance',      'value' => $todayAttendance, 'icon' => 'bi-calendar-check','color' => 'info'],
    ['label' => 'Pending Approvals',     'value' => $pendingApprovals,'icon' => 'bi-hourglass',    'color' => 'warning'],
    ['label' => 'Low Stock Items',       'value' => $lowStock,        'icon' => 'bi-box-seam',     'color' => 'danger'],
    ['label' => 'Vehicles Due (30d)',    'value' => $vehiclesDue,     'icon' => 'bi-truck',        'color' => 'secondary'],
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
