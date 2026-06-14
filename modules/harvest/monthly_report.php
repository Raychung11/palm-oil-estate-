<?php
/**
 * modules/harvest/monthly_report.php
 * Monthly yield report — per block, per hectare, plus worker productivity.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('harvest.view');

$month    = trim((string)input('month')) ?: date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$estateId = (int)input('estate_id');
$start    = $month . '-01';
$end      = date('Y-m-t', strtotime($start));

$conds  = ['h.harvest_date BETWEEN ? AND ?'];
$params = [$start, $end];
if ($estateId > 0) { $conds[] = 'h.estate_id = ?'; $params[] = $estateId; }
$where = 'WHERE ' . implode(' AND ', $conds);

// Per-block aggregation.
$blockRows = $pdo->prepare(
    "SELECT b.block_code, b.hectare, e.estate_name, d.division_name,
            SUM(h.bunches_count) AS bunches,
            SUM(h.ffb_weight_kg) AS ffb,
            SUM(h.loose_fruit_kg) AS loose
     FROM harvest_records h
     JOIN estates e   ON e.id = h.estate_id
     JOIN divisions d ON d.id = h.division_id
     JOIN blocks b    ON b.id = h.block_id
     $where
     GROUP BY h.block_id
     ORDER BY ffb DESC"
);
$blockRows->execute($params);
$blockRows = $blockRows->fetchAll();

$totalFfb = 0; $totalLoose = 0; $totalBunches = 0;
foreach ($blockRows as $r) {
    $totalFfb += (float)$r['ffb']; $totalLoose += (float)$r['loose']; $totalBunches += (int)$r['bunches'];
}

// Worker productivity (top 10) for the same window.
$workerRows = $pdo->prepare(
    "SELECT w.worker_code, w.name,
            COUNT(DISTINCT h.id) AS entries,
            SUM(hrw.productivity_value) AS productivity
     FROM harvest_record_workers hrw
     JOIN harvest_records h ON h.id = hrw.harvest_record_id
     JOIN workers w ON w.id = hrw.worker_id
     $where
     GROUP BY hrw.worker_id
     ORDER BY productivity DESC
     LIMIT 10"
);
$workerRows->execute($params);
$workerRows = $workerRows->fetchAll();

$estates = $pdo->query('SELECT id, estate_name FROM estates ORDER BY estate_name')->fetchAll();

$page_title = 'Monthly Harvest Report';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Monthly Harvest Report</h1>
    <a href="<?= e(url('modules/harvest/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to records
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="get">
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Month</label>
                <input type="month" name="month" class="form-control form-control-sm" value="<?= e($month) ?>" max="<?= e(date('Y-m')) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Estate</label>
                <select name="estate_id" class="form-select form-select-sm">
                    <option value="">All estates</option>
                    <?php foreach ($estates as $es): ?>
                        <option value="<?= (int)$es['id'] ?>" <?= $estateId === (int)$es['id'] ? 'selected' : '' ?>>
                            <?= e($es['estate_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> View</button>
            </div>
        </form>
    </div>
</div>

<!-- Month totals -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-value"><?= kg_to_tonnes($totalFfb) ?></div><div class="kpi-label">FFB (tonnes)</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-value"><?= kg_to_tonnes($totalLoose) ?></div><div class="kpi-label">Loose Fruit (tonnes)</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-value"><?= num($totalBunches, 0) ?></div><div class="kpi-label">Bunches</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-value"><?= count($blockRows) ?></div><div class="kpi-label">Blocks Harvested</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Yield by Block — <?= e(date('F Y', strtotime($start))) ?></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Block</th><th>Estate / Division</th><th class="text-end">FFB (kg)</th>
                            <th class="text-end">Tonnes</th><th class="text-end">FFB/ha</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($blockRows)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No harvest recorded this month.</td></tr>
                    <?php else: foreach ($blockRows as $r):
                        $perHa = ($r['hectare'] && (float)$r['hectare'] > 0)
                            ? ((float)$r['ffb'] + (float)$r['loose']) / (float)$r['hectare'] : null; ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['block_code']) ?></td>
                            <td class="small text-muted"><?= e($r['estate_name']) ?> / <?= e($r['division_name']) ?></td>
                            <td class="text-end"><?= num($r['ffb']) ?></td>
                            <td class="text-end"><?= kg_to_tonnes($r['ffb']) ?></td>
                            <td class="text-end"><?= $perHa !== null ? num($perHa) : '—' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Top Worker Productivity</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Worker</th><th class="text-end">Entries</th><th class="text-end">Productivity</th></tr></thead>
                    <tbody>
                    <?php if (empty($workerRows)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No worker productivity recorded.</td></tr>
                    <?php else: foreach ($workerRows as $w): ?>
                        <tr>
                            <td><?= e($w['name']) ?> <span class="text-muted small">(<?= e($w['worker_code']) ?>)</span></td>
                            <td class="text-end"><?= (int)$w['entries'] ?></td>
                            <td class="text-end"><?= num($w['productivity']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
