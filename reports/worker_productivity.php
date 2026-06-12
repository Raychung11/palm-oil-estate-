<?php
/**
 * reports/worker_productivity.php
 * Worker productivity report over a date range, drawn from harvest teams
 * and attendance. Supports CSV export (?export=csv).
 */
require_once __DIR__ . '/../inc/auth.php';
require_permission('report.view');

$from = trim((string)input('date_from')) ?: date('Y-m-01');
$to   = trim((string)input('date_to'))   ?: date('Y-m-t');
if (!strtotime($from)) $from = date('Y-m-01');
if (!strtotime($to))   $to   = date('Y-m-t');

$sql =
    "SELECT w.worker_code, w.name, w.worker_type,
            COUNT(DISTINCT hrw.harvest_record_id) AS harvest_entries,
            COALESCE(SUM(hrw.productivity_value), 0) AS productivity,
            (SELECT COUNT(*) FROM worker_attendance a
             WHERE a.worker_id = w.id AND a.status = 'present' AND a.attendance_date BETWEEN ? AND ?) AS days_present
     FROM workers w
     LEFT JOIN harvest_record_workers hrw ON hrw.worker_id = w.id
     LEFT JOIN harvest_records h ON h.id = hrw.harvest_record_id AND h.harvest_date BETWEEN ? AND ?
     WHERE w.status = 'active'
     GROUP BY w.id
     HAVING productivity > 0 OR harvest_entries > 0 OR days_present > 0
     ORDER BY productivity DESC, days_present DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$from, $to, $from, $to]);
$rows = $stmt->fetchAll();

// CSV export.
if (input('export') === 'csv' && can('report.export')) {
    $csv = [];
    foreach ($rows as $r) {
        $perDay = (int)$r['days_present'] > 0 ? $r['productivity'] / (int)$r['days_present'] : 0;
        $csv[] = [$r['worker_code'], $r['name'], $r['worker_type'], (int)$r['harvest_entries'],
            number_format((float)$r['productivity'], 2, '.', ''), (int)$r['days_present'],
            number_format($perDay, 2, '.', '')];
    }
    log_activity($pdo, current_user_id(), 'export', 'reports', "Worker productivity CSV $from..$to");
    send_csv('worker_productivity_' . $from . '_to_' . $to,
        ['Code', 'Worker', 'Category', 'Harvest Entries', 'Productivity', 'Days Present', 'Productivity/Day'], $csv);
}

$page_title = 'Worker Productivity Report';
require __DIR__ . '/../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Worker Productivity</h1>
    <?php if (can('report.export')): ?>
    <a href="<?= e(url('reports/worker_productivity.php?' . http_build_query(['date_from' => $from, 'date_to' => $to, 'export' => 'csv']))) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i> Excel</a>
    <?php endif; ?>
</div>

<div class="card mb-3"><div class="card-body">
    <form class="row g-2" method="get">
        <div class="col-6 col-md-3"><label class="form-label small mb-1">From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> View</button></div>
    </form>
</div></div>

<div class="card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Worker</th><th>Category</th><th class="text-end">Harvest Entries</th>
                <th class="text-end">Productivity</th><th class="text-end">Days Present</th><th class="text-end">Per Day</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted py-4">No productivity data in this period.</td></tr>
            <?php else: foreach ($rows as $r):
                $perDay = (int)$r['days_present'] > 0 ? (float)$r['productivity'] / (int)$r['days_present'] : 0; ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['name']) ?><div class="text-muted small"><?= e($r['worker_code']) ?></div></td>
                    <td class="small"><?= e($r['worker_type'] ?? '—') ?></td>
                    <td class="text-end"><?= (int)$r['harvest_entries'] ?></td>
                    <td class="text-end"><?= num($r['productivity']) ?></td>
                    <td class="text-end"><?= (int)$r['days_present'] ?></td>
                    <td class="text-end"><?= num($perDay) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div></div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
