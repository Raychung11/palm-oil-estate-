<?php
/**
 * modules/tasks/index.php
 * Field task list — filter by date range, status, type, estate; paginated.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('task.view');

$dateFrom = trim((string)input('date_from'));
$dateTo   = trim((string)input('date_to'));
$status   = trim((string)input('status'));
$type     = trim((string)input('type'));
$estateId = (int)input('estate_id');
$page     = current_page();
$offset   = ($page - 1) * PER_PAGE;

$conds = [];
$params = [];
if ($dateFrom !== '' && strtotime($dateFrom)) { $conds[] = 't.task_date >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '' && strtotime($dateTo))     { $conds[] = 't.task_date <= ?'; $params[] = $dateTo; }
if (array_key_exists($status, task_statuses())) { $conds[] = 't.status = ?'; $params[] = $status; }
if ($type !== '')     { $conds[] = 't.task_type = ?'; $params[] = $type; }
if ($estateId > 0)    { $conds[] = 't.estate_id = ?'; $params[] = $estateId; }
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM field_tasks t $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT t.*, b.block_code, u.name AS supervisor_name
        FROM field_tasks t
        LEFT JOIN blocks b ON b.id = t.block_id
        LEFT JOIN users u ON u.id = t.assigned_to
        $where
        ORDER BY t.task_date DESC, t.id DESC
        LIMIT $offset, " . PER_PAGE;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$estates = $pdo->query('SELECT id, estate_name FROM estates ORDER BY estate_name')->fetchAll();

$page_title = 'Field Tasks';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Field Tasks</h1>
    <div class="d-flex gap-2">
        <a href="<?= e(url('modules/tasks/report.php')) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clipboard-data me-1"></i> Report
        </a>
        <?php if (can('task.create')): ?>
        <a href="<?= e(url('modules/tasks/create.php')) ?>" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg me-1"></i> New Task
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (task_statuses() as $st => $c): ?>
                        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    <?php foreach (task_types() as $t): ?>
                        <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Estate</label>
                <select name="estate_id" class="form-select form-select-sm">
                    <option value="">All estates</option>
                    <?php foreach ($estates as $es): ?>
                        <option value="<?= (int)$es['id'] ?>" <?= $estateId === (int)$es['id'] ? 'selected' : '' ?>><?= e($es['estate_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
                <a href="<?= e(url('modules/tasks/index.php')) ?>" class="btn btn-link btn-sm">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Date</th><th>Title</th><th>Type</th><th>Block</th><th>Supervisor</th><th>Priority</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($tasks)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No tasks found.</td></tr>
                <?php else: foreach ($tasks as $t): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fmt_datetime($t['task_date'], 'd M Y')) ?></td>
                        <td class="fw-semibold">
                            <a href="<?= e(url('modules/tasks/view.php?id=' . (int)$t['id'])) ?>" class="text-decoration-none"><?= e($t['title']) ?></a>
                        </td>
                        <td class="small"><?= e($t['task_type']) ?></td>
                        <td><?= e($t['block_code'] ?? '—') ?></td>
                        <td class="small text-muted"><?= e($t['supervisor_name'] ?? '—') ?></td>
                        <td>
                            <?php $pc = ['low' => 'secondary', 'normal' => 'light text-dark', 'high' => 'danger'][$t['priority']] ?? 'light text-dark'; ?>
                            <span class="badge bg-<?= $pc ?>"><?= e(ucfirst($t['priority'])) ?></span>
                        </td>
                        <td><?= task_status_badge($t['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('modules/tasks/view.php?id=' . (int)$t['id'])) ?>" class="btn btn-outline-secondary btn-sm" title="View"><i class="bi bi-eye"></i></a>
                            <?php if (can('task.create') && $t['status'] !== 'approved'): ?>
                            <a href="<?= e(url('modules/tasks/edit.php?id=' . (int)$t['id'])) ?>" class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($total, PER_PAGE, $page, 'modules/tasks/index.php') ?>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
