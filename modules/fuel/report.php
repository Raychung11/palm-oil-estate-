<?php
/**
 * modules/fuel/report.php
 * Fuel cost per vehicle, consumption (km/L) and abnormal-usage alerts.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('asset.view');

$dateFrom = trim((string)input('date_from')) ?: date('Y-m-01');
$dateTo   = trim((string)input('date_to'))   ?: date('Y-m-t');
if (!strtotime($dateFrom)) $dateFrom = date('Y-m-01');
if (!strtotime($dateTo))   $dateTo   = date('Y-m-t');
$params = [$dateFrom, $dateTo];

// Per-asset fuel usage. Distance derived from min/max mileage readings.
$byAsset = $pdo->prepare(
    "SELECT a.asset_code, a.asset_name,
            SUM(i.fuel_litre) AS litres, SUM(i.total_cost) AS cost,
            MIN(i.mileage_reading) AS min_km, MAX(i.mileage_reading) AS max_km,
            COUNT(*) AS issues
     FROM fuel_issues i JOIN assets a ON a.id = i.asset_id
     WHERE i.issue_date BETWEEN ? AND ?
     GROUP BY i.asset_id ORDER BY cost DESC"
);
$byAsset->execute($params);
$assets = $byAsset->fetchAll();

// Compute km/L and fleet average for the abnormal check.
$rows = [];
$rateSum = 0; $rateCount = 0;
foreach ($assets as $a) {
    $km = ($a['max_km'] !== null && $a['min_km'] !== null) ? (float)$a['max_km'] - (float)$a['min_km'] : 0;
    $rate = ($km > 0 && (float)$a['litres'] > 0) ? $km / (float)$a['litres'] : null;
    if ($rate !== null) { $rateSum += $rate; $rateCount++; }
    $a['km'] = $km; $a['rate'] = $rate;
    $rows[] = $a;
}
$fleetAvg = $rateCount > 0 ? $rateSum / $rateCount : null;

// Per block / operation.
$byBlock = $pdo->prepare(
    "SELECT COALESCE(b.block_code, 'Unassigned') AS block_code, SUM(i.fuel_litre) AS litres, SUM(i.total_cost) AS cost
     FROM fuel_issues i LEFT JOIN blocks b ON b.id = i.block_id
     WHERE i.issue_date BETWEEN ? AND ? GROUP BY i.block_id ORDER BY cost DESC"
);
$byBlock->execute($params);
$byBlock = $byBlock->fetchAll();

$totalCost = 0; $totalLitres = 0;
foreach ($rows as $r) { $totalCost += (float)$r['cost']; $totalLitres += (float)$r['litres']; }

$page_title = 'Fuel Report';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<h1 class="h4 mb-3">Fuel Cost &amp; Consumption</h1>

<div class="card mb-3"><div class="card-body">
    <form class="row g-2" method="get">
        <div class="col-6 col-md-3"><label class="form-label small mb-1">From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>"></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> View</button></div>
    </form>
</div></div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= num($totalLitres) ?></div><div class="kpi-label">Litres Issued</div></div></div></div>
    <div class="col-6 col-md-4"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= num($totalCost) ?></div><div class="kpi-label">Fuel Cost (RM)</div></div></div></div>
    <div class="col-6 col-md-4"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= $fleetAvg !== null ? num($fleetAvg) : '—' ?></div><div class="kpi-label">Fleet Avg (km/L)</div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">By Vehicle <small class="text-muted fw-normal">— rows flagged when consumption is below 60% of the fleet average</small></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Asset</th><th class="text-end">Litres</th><th class="text-end">Cost</th><th class="text-end">Distance (km)</th><th class="text-end">km/L</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted py-4">No fuel issues in this period.</td></tr>
        <?php else: foreach ($rows as $r):
            $abnormal = ($r['rate'] !== null && $fleetAvg !== null && $r['rate'] < $fleetAvg * 0.6); ?>
            <tr class="<?= $abnormal ? 'table-warning' : '' ?>">
                <td class="fw-semibold"><?= e($r['asset_name']) ?><div class="text-muted small"><?= e($r['asset_code']) ?></div></td>
                <td class="text-end"><?= num($r['litres']) ?></td>
                <td class="text-end"><?= num($r['cost']) ?></td>
                <td class="text-end"><?= $r['km'] > 0 ? num($r['km']) : '—' ?></td>
                <td class="text-end"><?= $r['rate'] !== null ? num($r['rate']) : '—' ?></td>
                <td><?php if ($abnormal): ?><span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Abnormal</span><?php endif; ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">By Block / Operation</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Block</th><th class="text-end">Litres</th><th class="text-end">Cost (RM)</th></tr></thead>
        <tbody>
        <?php if (empty($byBlock)): ?><tr><td colspan="3" class="text-center text-muted py-3">No data.</td></tr>
        <?php else: foreach ($byBlock as $r): ?>
            <tr><td><?= e($r['block_code']) ?></td><td class="text-end"><?= num($r['litres']) ?></td><td class="text-end"><?= num($r['cost']) ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
