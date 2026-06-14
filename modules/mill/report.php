<?php
/**
 * modules/mill/report.php
 * Mill delivery report — totals, value, OER and reconciliation variance.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('mill.view');

$dateFrom = trim((string)input('date_from')) ?: date('Y-m-01');
$dateTo   = trim((string)input('date_to'))   ?: date('Y-m-t');
if (!strtotime($dateFrom)) $dateFrom = date('Y-m-01');
if (!strtotime($dateTo))   $dateTo   = date('Y-m-t');
$params = [$dateFrom, $dateTo];

$totals = $pdo->prepare(
    "SELECT COUNT(*) AS trips, COALESCE(SUM(net_weight_kg),0) AS net, COALESCE(SUM(total_value),0) AS value,
            AVG(NULLIF(oer,0)) AS avg_oer
     FROM mill_deliveries WHERE delivery_date BETWEEN ? AND ?"
);
$totals->execute($params);
$t = $totals->fetch();

// Per-mill breakdown.
$byMill = $pdo->prepare(
    "SELECT COALESCE(m.mill_name, 'Unassigned') AS mill_name,
            COUNT(*) AS trips, SUM(d.net_weight_kg) AS net, SUM(d.total_value) AS value
     FROM mill_deliveries d LEFT JOIN mills m ON m.id = d.mill_id
     WHERE d.delivery_date BETWEEN ? AND ? GROUP BY d.mill_id ORDER BY value DESC"
);
$byMill->execute($params);
$byMill = $byMill->fetchAll();

// Reconciliation: weighbridge net vs linked harvest total, per delivery.
$recon = $pdo->prepare(
    "SELECT d.id, d.delivery_date, d.net_weight_kg, d.status,
            COALESCE((SELECT SUM(i.ffb_weight_kg) FROM mill_delivery_items i WHERE i.mill_delivery_id = d.id), 0) AS harvest_total
     FROM mill_deliveries d
     WHERE d.delivery_date BETWEEN ? AND ?
     ORDER BY d.delivery_date DESC LIMIT 100"
);
$recon->execute($params);
$recon = $recon->fetchAll();

$page_title = 'Mill Delivery Report';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Delivery Report</h1>
    <a href="<?= e(url('modules/mill/index.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="card mb-3"><div class="card-body">
    <form class="row g-2" method="get">
        <div class="col-6 col-md-3"><label class="form-label small mb-1">From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>"></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> View</button></div>
    </form>
</div></div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= (int)$t['trips'] ?></div><div class="kpi-label">Trips</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= kg_to_tonnes($t['net']) ?></div><div class="kpi-label">Net FFB (tonnes)</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= num($t['value']) ?></div><div class="kpi-label">Total Value (RM)</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= $t['avg_oer'] !== null ? num($t['avg_oer']) . '%' : '—' ?></div><div class="kpi-label">Avg OER</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-5"><div class="card h-100">
        <div class="card-header bg-white fw-semibold">By Mill</div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Mill</th><th class="text-end">Trips</th><th class="text-end">Net (t)</th><th class="text-end">Value (RM)</th></tr></thead>
            <tbody>
            <?php if (empty($byMill)): ?><tr><td colspan="4" class="text-center text-muted py-3">No deliveries.</td></tr>
            <?php else: foreach ($byMill as $r): ?>
                <tr><td><?= e($r['mill_name']) ?></td><td class="text-end"><?= (int)$r['trips'] ?></td><td class="text-end"><?= kg_to_tonnes($r['net']) ?></td><td class="text-end"><?= num($r['value']) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>

    <div class="col-md-7"><div class="card h-100">
        <div class="card-header bg-white fw-semibold">Reconciliation Variance</div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Date</th><th class="text-end">Net (kg)</th><th class="text-end">Harvest (kg)</th><th class="text-end">Variance</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($recon)): ?><tr><td colspan="5" class="text-center text-muted py-3">No deliveries.</td></tr>
            <?php else: foreach ($recon as $r):
                $var = (float)$r['net_weight_kg'] - (float)$r['harvest_total']; ?>
                <tr>
                    <td class="text-nowrap small"><?= e(fmt_datetime($r['delivery_date'], 'd M Y')) ?></td>
                    <td class="text-end"><?= num($r['net_weight_kg']) ?></td>
                    <td class="text-end"><?= num($r['harvest_total']) ?></td>
                    <td class="text-end <?= abs($var) > 0.01 ? 'text-danger' : 'text-success' ?>"><?= ($var >= 0 ? '+' : '') . num($var) ?></td>
                    <td><span class="badge bg-<?= $r['status'] === 'reconciled' ? 'success' : 'warning text-dark' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
