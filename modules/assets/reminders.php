<?php
/**
 * modules/assets/reminders.php
 * Road tax / insurance expiry and upcoming service reminders.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('asset.view');

$window = (int)input('days');
if ($window <= 0) { $window = 60; }
$limit = date('Y-m-d', strtotime("+$window days"));

$assets = $pdo->prepare(
    "SELECT * FROM assets
     WHERE status = 'active'
       AND ((road_tax_expiry IS NOT NULL AND road_tax_expiry <= ?)
         OR (insurance_expiry IS NOT NULL AND insurance_expiry <= ?))
     ORDER BY LEAST(COALESCE(road_tax_expiry,'9999-12-31'), COALESCE(insurance_expiry,'9999-12-31')) ASC"
);
$assets->execute([$limit, $limit]);
$assets = $assets->fetchAll();

// Latest next_service_date per asset that falls due within the window.
$service = $pdo->prepare(
    "SELECT a.asset_code, a.asset_name, m.next_service_date, m.maintenance_type
     FROM asset_maintenance_logs m
     JOIN assets a ON a.id = m.asset_id
     WHERE m.next_service_date IS NOT NULL AND m.next_service_date <= ?
       AND m.id = (SELECT MAX(m2.id) FROM asset_maintenance_logs m2 WHERE m2.asset_id = m.asset_id)
     ORDER BY m.next_service_date ASC"
);
$service->execute([$limit]);
$service = $service->fetchAll();

$page_title = 'Asset Reminders';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Reminders</h1>
    <a href="<?= e(url('modules/assets/index.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Assets</a>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-auto">
        <label class="form-label small mb-1">Window</label>
        <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ([30, 60, 90, 180] as $d): ?><option value="<?= $d ?>" <?= $window === $d ? 'selected' : '' ?>>Next <?= $d ?> days</option><?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Road Tax &amp; Insurance (<?= count($assets) ?>)</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Asset</th><th>Type</th><th>Road Tax</th><th>Insurance</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($assets)): ?><tr><td colspan="5" class="text-center text-muted py-4">Nothing expiring in this window.</td></tr>
        <?php else: foreach ($assets as $a): ?>
            <tr>
                <td class="fw-semibold"><?= e($a['asset_name']) ?><div class="text-muted small"><?= e($a['asset_code']) ?></div></td>
                <td><?= e($a['asset_type'] ?? '—') ?></td>
                <td><?= expiry_badge($a['road_tax_expiry']) ?></td>
                <td><?= expiry_badge($a['insurance_expiry']) ?></td>
                <td class="text-end"><a href="<?= e(url('modules/assets/view.php?id=' . (int)$a['id'])) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye"></i></a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Service Due (<?= count($service) ?>)</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Asset</th><th>Last Type</th><th>Next Service</th></tr></thead>
        <tbody>
        <?php if (empty($service)): ?><tr><td colspan="3" class="text-center text-muted py-4">No service due in this window.</td></tr>
        <?php else: foreach ($service as $s): ?>
            <tr><td class="fw-semibold"><?= e($s['asset_name']) ?><div class="text-muted small"><?= e($s['asset_code']) ?></div></td>
                <td><?= e(ucfirst($s['maintenance_type'])) ?></td><td><?= expiry_badge($s['next_service_date']) ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
