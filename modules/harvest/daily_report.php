<?php
/**
 * modules/harvest/daily_report.php
 * Daily harvest summary grouped by block.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('harvest.view');

$date     = trim((string)input('date')) ?: date('Y-m-d');
if (!strtotime($date)) {
    $date = date('Y-m-d');
}
$estateId = (int)input('estate_id');

$conds  = ['h.harvest_date = ?'];
$params = [$date];
if ($estateId > 0) { $conds[] = 'h.estate_id = ?'; $params[] = $estateId; }
$where = 'WHERE ' . implode(' AND ', $conds);

$rows = $pdo->prepare(
    "SELECT b.block_code, b.block_name, b.hectare, e.estate_name, d.division_name,
            COUNT(*) AS entries,
            SUM(h.bunches_count) AS bunches,
            SUM(h.ffb_weight_kg) AS ffb,
            SUM(h.loose_fruit_kg) AS loose,
            SUM(h.rejected_bunches) AS rejected
     FROM harvest_records h
     JOIN estates e   ON e.id = h.estate_id
     JOIN divisions d ON d.id = h.division_id
     JOIN blocks b    ON b.id = h.block_id
     $where
     GROUP BY h.block_id
     ORDER BY ffb DESC"
);
$rows->execute($params);
$rows = $rows->fetchAll();

$totals = ['bunches' => 0, 'ffb' => 0, 'loose' => 0, 'rejected' => 0];
foreach ($rows as $r) {
    $totals['bunches']  += (int)$r['bunches'];
    $totals['ffb']      += (float)$r['ffb'];
    $totals['loose']    += (float)$r['loose'];
    $totals['rejected'] += (int)$r['rejected'];
}

$estates = $pdo->query('SELECT id, estate_name FROM estates ORDER BY estate_name')->fetchAll();

$page_title = 'Daily Harvest Report';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Daily Harvest Report</h1>
    <a href="<?= e(url('modules/harvest/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to records
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>">
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

        <h2 class="h6 text-muted"><?= e(date('l, d M Y', strtotime($date))) ?></h2>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Block</th><th>Estate / Division</th><th class="text-end">Entries</th>
                        <th class="text-end">Bunches</th><th class="text-end">FFB (kg)</th>
                        <th class="text-end">Loose (kg)</th><th class="text-end">Rejected</th>
                        <th class="text-end">FFB/ha</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No harvest recorded on this date.</td></tr>
                <?php else: foreach ($rows as $r):
                    $perHa = ($r['hectare'] && (float)$r['hectare'] > 0)
                        ? ((float)$r['ffb'] + (float)$r['loose']) / (float)$r['hectare'] : null; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['block_code']) ?></td>
                        <td class="small text-muted"><?= e($r['estate_name']) ?> / <?= e($r['division_name']) ?></td>
                        <td class="text-end"><?= (int)$r['entries'] ?></td>
                        <td class="text-end"><?= num($r['bunches'], 0) ?></td>
                        <td class="text-end"><?= num($r['ffb']) ?></td>
                        <td class="text-end"><?= num($r['loose']) ?></td>
                        <td class="text-end"><?= num($r['rejected'], 0) ?></td>
                        <td class="text-end"><?= $perHa !== null ? num($perHa) : '—' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                <tfoot>
                    <tr class="fw-semibold border-top">
                        <td colspan="3" class="text-end">Totals</td>
                        <td class="text-end"><?= num($totals['bunches'], 0) ?></td>
                        <td class="text-end"><?= num($totals['ffb']) ?></td>
                        <td class="text-end"><?= num($totals['loose']) ?></td>
                        <td class="text-end"><?= num($totals['rejected'], 0) ?></td>
                        <td class="text-end"><?= kg_to_tonnes($totals['ffb'] + $totals['loose']) ?> t</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
