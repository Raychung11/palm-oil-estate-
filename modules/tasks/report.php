<?php
/**
 * modules/tasks/report.php
 * Task completion report for a date range.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('task.view');

$dateFrom = trim((string)input('date_from')) ?: date('Y-m-01');
$dateTo   = trim((string)input('date_to'))   ?: date('Y-m-t');
if (!strtotime($dateFrom)) $dateFrom = date('Y-m-01');
if (!strtotime($dateTo))   $dateTo   = date('Y-m-t');

$params = [$dateFrom, $dateTo];

// Counts by status.
$statusRows = $pdo->prepare("SELECT status, COUNT(*) AS n FROM field_tasks WHERE task_date BETWEEN ? AND ? GROUP BY status");
$statusRows->execute($params);
$byStatus = array_column($statusRows->fetchAll(), 'n', 'status');

$total = array_sum($byStatus);
$approved = (int)($byStatus['approved'] ?? 0);
$completed = (int)($byStatus['completed'] ?? 0);
$completionRate = $total > 0 ? round((($approved + $completed) / $total) * 100, 1) : 0;

// Counts by type.
$typeRows = $pdo->prepare(
    "SELECT task_type,
            COUNT(*) AS total,
            SUM(status IN ('approved','completed')) AS done
     FROM field_tasks WHERE task_date BETWEEN ? AND ? GROUP BY task_type ORDER BY total DESC"
);
$typeRows->execute($params);
$typeRows = $typeRows->fetchAll();

$page_title = 'Task Report';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Task Completion Report</h1>
    <a href="<?= e(url('modules/tasks/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to tasks
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="get">
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> View</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
        <div class="kpi-value"><?= (int)$total ?></div><div class="kpi-label">Total Tasks</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
        <div class="kpi-value"><?= (int)($byStatus['in_progress'] ?? 0) ?></div><div class="kpi-label">In Progress</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
        <div class="kpi-value"><?= $approved ?></div><div class="kpi-label">Approved</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
        <div class="kpi-value"><?= $completionRate ?>%</div><div class="kpi-label">Completion Rate</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">By Status</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Status</th><th class="text-end">Count</th></tr></thead>
                    <tbody>
                    <?php foreach (task_statuses() as $st => $c): ?>
                        <tr><td><?= task_status_badge($st) ?></td><td class="text-end"><?= (int)($byStatus[$st] ?? 0) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">By Task Type</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Type</th><th class="text-end">Total</th><th class="text-end">Done</th><th class="text-end">Rate</th></tr></thead>
                    <tbody>
                    <?php if (empty($typeRows)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No tasks in this period.</td></tr>
                    <?php else: foreach ($typeRows as $row):
                        $rate = (int)$row['total'] > 0 ? round(((int)$row['done'] / (int)$row['total']) * 100) : 0; ?>
                        <tr>
                            <td><?= e($row['task_type']) ?></td>
                            <td class="text-end"><?= (int)$row['total'] ?></td>
                            <td class="text-end"><?= (int)$row['done'] ?></td>
                            <td class="text-end"><?= $rate ?>%</td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
