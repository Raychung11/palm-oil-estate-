<?php
/**
 * modules/workers/index.php
 * Worker list — searchable, filterable, paginated, with expiry alerts.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('worker.view');

$search   = trim((string)input('q'));
$type     = trim((string)input('type'));
$status   = trim((string)input('status'));
$page     = current_page();
$offset   = ($page - 1) * PER_PAGE;

$conds = [];
$params = [];
if ($search !== '') {
    $conds[] = '(worker_code LIKE ? OR name LIKE ? OR ic_passport LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($type !== '')   { $conds[] = 'worker_type = ?'; $params[] = $type; }
if (in_array($status, ['active', 'inactive'], true)) { $conds[] = 'status = ?'; $params[] = $status; }
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM workers $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT * FROM workers $where ORDER BY name ASC LIMIT $offset, " . PER_PAGE;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$workers = $stmt->fetchAll();

$page_title = 'Workers';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Workers</h1>
    <div class="d-flex gap-2">
        <a href="<?= e(url('modules/workers/attendance.php')) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-calendar-check me-1"></i> Attendance
        </a>
        <a href="<?= e(url('modules/workers/expiry.php')) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-exclamation-triangle me-1"></i> Expiry Alerts
        </a>
        <?php if (can('worker.manage')): ?>
        <a href="<?= e(url('modules/workers/create.php')) ?>" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Worker
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-5 col-md-4">
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Search code, name or IC/passport…" value="<?= e($search) ?>">
            </div>
            <div class="col-sm-4 col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    <?php foreach (worker_categories() as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $type === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All status</option>
                    <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
                <a href="<?= e(url('modules/workers/index.php')) ?>" class="btn btn-link btn-sm">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Worker</th><th>Category</th><th>Nationality</th>
                        <th>Permit Expiry</th><th>Contract Expiry</th><th>Status</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($workers)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No workers found.</td></tr>
                <?php else: foreach ($workers as $w): ?>
                    <tr>
                        <td class="fw-semibold">
                            <a href="<?= e(url('modules/workers/view.php?id=' . (int)$w['id'])) ?>" class="text-decoration-none">
                                <?= e($w['name']) ?>
                            </a>
                            <div class="text-muted small"><?= e($w['worker_code']) ?></div>
                        </td>
                        <td><?= e($w['worker_type'] ?? '—') ?></td>
                        <td class="small text-muted"><?= e($w['nationality'] ?? '—') ?></td>
                        <td><?= expiry_badge($w['permit_expiry']) ?></td>
                        <td><?= expiry_badge($w['contract_expiry']) ?></td>
                        <td><?= status_badge($w['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('modules/workers/view.php?id=' . (int)$w['id'])) ?>"
                               class="btn btn-outline-secondary btn-sm" title="Profile"><i class="bi bi-eye"></i></a>
                            <?php if (can('worker.manage')): ?>
                            <a href="<?= e(url('modules/workers/edit.php?id=' . (int)$w['id'])) ?>"
                               class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($total, PER_PAGE, $page, 'modules/workers/index.php') ?>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
