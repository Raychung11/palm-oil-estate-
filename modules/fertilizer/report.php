<?php
/**
 * modules/fertilizer/report.php
 * Fertilizer usage & cost report (per block) for a date range.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('fertilizer.view');

$dateFrom = trim((string)input('date_from')) ?: date('Y-m-01');
$dateTo   = trim((string)input('date_to'))   ?: date('Y-m-t');
if (!strtotime($dateFrom)) $dateFrom = date('Y-m-01');
if (!strtotime($dateTo))   $dateTo   = date('Y-m-t');
$params = [$dateFrom, $dateTo];

// Usage & cost by block.
$byBlock = $pdo->prepare(
    "SELECT COALESCE(b.block_code, 'Unassigned') AS block_code,
            SUM(a.quantity) AS qty, SUM(a.total_cost) AS cost
     FROM fertilizer_applications a
     LEFT JOIN blocks b ON b.id = a.block_id
     WHERE a.application_date BETWEEN ? AND ?
     GROUP BY a.block_id ORDER BY cost DESC"
);
$byBlock->execute($params);
$byBlock = $byBlock->fetchAll();

// Usage by product.
$byProduct = $pdo->prepare(
    "SELECT p.product_name, p.unit, SUM(a.quantity) AS qty, SUM(a.total_cost) AS cost
     FROM fertilizer_applications a JOIN fertilizer_products p ON p.id = a.product_id
     WHERE a.application_date BETWEEN ? AND ?
     GROUP BY a.product_id ORDER BY cost DESC"
);
$byProduct->execute($params);
$byProduct = $byProduct->fetchAll();

$totalCost = 0;
foreach ($byBlock as $r) { $totalCost += (float)$r['cost']; }

// Actual vs planned (current month schedules).
$planned = (float)$pdo->query("SELECT COALESCE(SUM(planned_quantity),0) FROM fertilizer_schedules WHERE status = 'planned'")->fetchColumn();

$page_title = 'Fertilizer Report';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<h1 class="h4 mb-3">Usage &amp; Cost Report</h1>

<div class="card mb-3"><div class="card-body">
    <form class="row g-2" method="get">
        <div class="col-6 col-md-3"><label class="form-label small mb-1">From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>"></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> View</button></div>
    </form>
</div></div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= num($totalCost) ?></div><div class="kpi-label">Total Cost (RM)</div></div></div></div>
    <div class="col-6 col-md-4"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= count($byBlock) ?></div><div class="kpi-label">Blocks Applied</div></div></div></div>
    <div class="col-6 col-md-4"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= num($planned) ?></div><div class="kpi-label">Planned Qty Outstanding</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-6"><div class="card h-100">
        <div class="card-header bg-white fw-semibold">By Block</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Block</th><th class="text-end">Quantity</th><th class="text-end">Cost (RM)</th></tr></thead>
            <tbody>
            <?php if (empty($byBlock)): ?><tr><td colspan="3" class="text-center text-muted py-3">No applications in this period.</td></tr>
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
            <?php if (empty($byProduct)): ?><tr><td colspan="3" class="text-center text-muted py-3">No applications in this period.</td></tr>
            <?php else: foreach ($byProduct as $r): ?>
                <tr><td><?= e($r['product_name']) ?></td><td class="text-end"><?= num($r['qty']) ?> <?= e($r['unit']) ?></td><td class="text-end"><?= num($r['cost']) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
