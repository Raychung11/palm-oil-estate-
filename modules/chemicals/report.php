<?php
/**
 * modules/chemicals/report.php
 * Chemical usage & cost report (per block / product) plus a PPE safety summary.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('chemical.view');

$dateFrom = trim((string)input('date_from')) ?: date('Y-m-01');
$dateTo   = trim((string)input('date_to'))   ?: date('Y-m-t');
if (!strtotime($dateFrom)) $dateFrom = date('Y-m-01');
if (!strtotime($dateTo))   $dateTo   = date('Y-m-t');
$params = [$dateFrom, $dateTo];

$byBlock = $pdo->prepare(
    "SELECT COALESCE(b.block_code, 'Unassigned') AS block_code, SUM(s.quantity) AS qty, SUM(s.total_cost) AS cost
     FROM spraying_records s LEFT JOIN blocks b ON b.id = s.block_id
     WHERE s.spray_date BETWEEN ? AND ? GROUP BY s.block_id ORDER BY cost DESC"
);
$byBlock->execute($params);
$byBlock = $byBlock->fetchAll();

$byProduct = $pdo->prepare(
    "SELECT p.product_name, p.unit, SUM(s.quantity) AS qty, SUM(s.total_cost) AS cost
     FROM spraying_records s JOIN chemical_products p ON p.id = s.product_id
     WHERE s.spray_date BETWEEN ? AND ? GROUP BY s.product_id ORDER BY cost DESC"
);
$byProduct->execute($params);
$byProduct = $byProduct->fetchAll();

$safety = $pdo->prepare(
    "SELECT COUNT(*) AS total, SUM(ppe_confirmed = 1) AS ppe_yes
     FROM spraying_records WHERE spray_date BETWEEN ? AND ?"
);
$safety->execute($params);
$safety = $safety->fetch();
$totalSpray = (int)($safety['total'] ?? 0);
$ppeYes = (int)($safety['ppe_yes'] ?? 0);
$ppeRate = $totalSpray > 0 ? round(($ppeYes / $totalSpray) * 100) : 0;

$totalCost = 0;
foreach ($byBlock as $r) { $totalCost += (float)$r['cost']; }

$page_title = 'Chemical Report';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<h1 class="h4 mb-3">Usage, Cost &amp; Safety Report</h1>

<div class="card mb-3"><div class="card-body">
    <form class="row g-2" method="get">
        <div class="col-6 col-md-3"><label class="form-label small mb-1">From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>"></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> View</button></div>
    </form>
</div></div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= num($totalCost) ?></div><div class="kpi-label">Total Cost (RM)</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= $totalSpray ?></div><div class="kpi-label">Spraying Operations</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= count($byBlock) ?></div><div class="kpi-label">Blocks Sprayed</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= $ppeRate ?>%</div><div class="kpi-label">PPE Compliance</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-6"><div class="card h-100">
        <div class="card-header bg-white fw-semibold">By Block</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Block</th><th class="text-end">Quantity</th><th class="text-end">Cost (RM)</th></tr></thead>
            <tbody>
            <?php if (empty($byBlock)): ?><tr><td colspan="3" class="text-center text-muted py-3">No spraying in this period.</td></tr>
            <?php else: foreach ($byBlock as $r): ?>
                <tr><td><?= e($r['block_code']) ?></td><td class="text-end"><?= num($r['qty']) ?></td><td class="text-end"><?= num($r['cost']) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>
    <div class="col-md-6"><div class="card h-100">
        <div class="card-header bg-white fw-semibold">By Product</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Product</th><th class="text-end">Quantity</th><th class="text-end">Cost (RM)</th></tr></thead>
            <tbody>
            <?php if (empty($byProduct)): ?><tr><td colspan="3" class="text-center text-muted py-3">No spraying in this period.</td></tr>
            <?php else: foreach ($byProduct as $r): ?>
                <tr><td><?= e($r['product_name']) ?></td><td class="text-end"><?= num($r['qty']) ?> <?= e($r['unit']) ?></td><td class="text-end"><?= num($r['cost']) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
