<?php
/**
 * modules/tasks/view.php
 * Task detail + workflow (start / complete / approve / reject) + photos.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('task.view');

$id = (int)input('id');
if ($id <= 0) {
    set_flash('danger', 'Invalid task.');
    redirect('modules/tasks/index.php');
}

$load = function () use ($pdo, $id) {
    $stmt = $pdo->prepare(
        'SELECT t.*, b.block_code, b.block_name, e.estate_name, d.division_name,
                u.name AS supervisor_name, a.name AS approver_name
         FROM field_tasks t
         LEFT JOIN blocks b    ON b.id = t.block_id
         LEFT JOIN estates e   ON e.id = t.estate_id
         LEFT JOIN divisions d ON d.id = t.division_id
         LEFT JOIN users u     ON u.id = t.assigned_to
         LEFT JOIN users a     ON a.id = t.approved_by
         WHERE t.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    return $stmt->fetch();
};

$task = $load();
if (!$task) {
    set_flash('danger', 'Task not found.');
    redirect('modules/tasks/index.php');
}

// --- Workflow actions --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action  = input('action');
    $remarks = trim((string)input('remarks')) ?: null;
    $from    = $task['status'];

    // Map each action to its required permission, allowed source status,
    // resulting status and the columns to stamp.
    $transitions = [
        'start'    => ['perm' => 'task.manage',  'from' => ['planned'],            'to' => 'in_progress', 'stamp' => 'started_at'],
        'complete' => ['perm' => 'task.manage',  'from' => ['in_progress'],        'to' => 'completed',   'stamp' => 'completed_at'],
        'approve'  => ['perm' => 'task.approve', 'from' => ['completed'],          'to' => 'approved',    'stamp' => 'approved'],
        'reject'   => ['perm' => 'task.approve', 'from' => ['completed'],          'to' => 'rejected',    'stamp' => 'approved'],
        'reopen'   => ['perm' => 'task.manage',  'from' => ['rejected', 'completed'], 'to' => 'in_progress', 'stamp' => null],
    ];

    if (isset($transitions[$action])) {
        $tr = $transitions[$action];
        require_permission($tr['perm']);

        if (!in_array($from, $tr['from'], true)) {
            set_flash('warning', 'That action is not allowed from the current status.');
            redirect('modules/tasks/view.php?id=' . $id);
        }

        try {
            $pdo->beginTransaction();

            if ($tr['stamp'] === 'started_at') {
                $pdo->prepare('UPDATE field_tasks SET status=?, started_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=?')
                    ->execute([$tr['to'], current_user_id(), $id]);
            } elseif ($tr['stamp'] === 'completed_at') {
                $pdo->prepare('UPDATE field_tasks SET status=?, completed_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=?')
                    ->execute([$tr['to'], current_user_id(), $id]);
            } elseif ($tr['stamp'] === 'approved') {
                $pdo->prepare('UPDATE field_tasks SET status=?, approved_by=?, approved_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=?')
                    ->execute([$tr['to'], current_user_id(), current_user_id(), $id]);
            } else {
                $pdo->prepare('UPDATE field_tasks SET status=?, updated_by=?, updated_at=NOW() WHERE id=?')
                    ->execute([$tr['to'], current_user_id(), $id]);
            }

            // Optional proof photo on completion.
            $stored = handle_upload($_FILES['photo'] ?? [], 'tasks', ['jpg','jpeg','png','webp']);
            if ($stored !== null) {
                $phase = $action === 'start' ? 'start' : 'proof';
                $pdo->prepare('INSERT INTO field_task_photos (field_task_id, file_path, phase, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')
                    ->execute([$id, $stored, $phase, current_user_id()]);
            }

            log_task_status($pdo, $id, $from, $tr['to'], $remarks);
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            set_flash('danger', $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not update the task.');
            redirect('modules/tasks/view.php?id=' . $id);
        }

        log_activity($pdo, current_user_id(), $action, 'tasks', 'Task #' . $id . ' -> ' . $tr['to']);
        set_flash('success', 'Task ' . str_replace('_', ' ', $tr['to']) . '.');
    }
    redirect('modules/tasks/view.php?id=' . $id);
}

// --- Related datasets --------------------------------------------------
$team = $pdo->prepare('SELECT ftw.role_in_task, w.worker_code, w.name FROM field_task_workers ftw JOIN workers w ON w.id = ftw.worker_id WHERE ftw.field_task_id = ? ORDER BY w.name');
$team->execute([$id]);
$team = $team->fetchAll();

$photos = $pdo->prepare('SELECT file_path, phase, caption FROM field_task_photos WHERE field_task_id = ? ORDER BY id');
$photos->execute([$id]);
$photos = $photos->fetchAll();

$logs = $pdo->prepare('SELECT l.*, u.name AS user_name FROM field_task_status_logs l LEFT JOIN users u ON u.id = l.changed_by WHERE l.field_task_id = ? ORDER BY l.id DESC');
$logs->execute([$id]);
$logs = $logs->fetchAll();

$page_title = $task['title'];
require __DIR__ . '/../../inc/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('modules/tasks/index.php')) ?>">Field Tasks</a></li>
        <li class="breadcrumb-item active"><?= e($task['title']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0"><?= e($task['title']) ?> <?= task_status_badge($task['status']) ?></h1>
    <?php if (can('task.create') && $task['status'] !== 'approved'): ?>
    <a href="<?= e(url('modules/tasks/edit.php?id=' . $id)) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-4"><div class="text-muted small">Date</div><?= e(fmt_datetime($task['task_date'], 'd M Y')) ?></div>
                    <div class="col-sm-4"><div class="text-muted small">Type</div><?= e($task['task_type']) ?></div>
                    <div class="col-sm-4"><div class="text-muted small">Priority</div><?= e(ucfirst($task['priority'])) ?></div>
                    <div class="col-sm-4"><div class="text-muted small">Supervisor</div><?= e($task['supervisor_name'] ?? '—') ?></div>
                    <div class="col-sm-8"><div class="text-muted small">Location</div>
                        <?= e($task['estate_name'] ?? '—') ?><?= $task['division_name'] ? ' / ' . e($task['division_name']) : '' ?>
                        <?= $task['block_code'] ? ' · Block ' . e($task['block_code']) : '' ?>
                    </div>
                </div>
                <?php if ($task['description']): ?>
                <hr><div class="text-muted small">Description</div><div><?= nl2br(e($task['description'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Assigned Workers (<?= count($team) ?>)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Worker</th><th>Role</th></tr></thead>
                    <tbody>
                    <?php if (empty($team)): ?>
                        <tr><td colspan="2" class="text-center text-muted py-3">No workers assigned.</td></tr>
                    <?php else: foreach ($team as $t): ?>
                        <tr><td><?= e($t['name']) ?> <span class="text-muted small">(<?= e($t['worker_code']) ?>)</span></td><td><?= e($t['role_in_task'] ?? '—') ?></td></tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($photos)): ?>
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Photos</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <?php foreach ($photos as $ph): ?>
                    <a href="<?= e(UPLOAD_URL . '/' . $ph['file_path']) ?>" target="_blank" class="text-center">
                        <img src="<?= e(UPLOAD_URL . '/' . $ph['file_path']) ?>" alt="Task photo" style="height:110px;border-radius:8px;">
                        <div class="small text-muted"><?= e(ucfirst($ph['phase'])) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-white fw-semibold">Status History</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>When</th><th>Change</th><th>By</th><th>Remarks</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="text-nowrap small"><?= e(fmt_datetime($l['created_at'])) ?></td>
                            <td class="small"><?= e($l['from_status'] ?? 'new') ?> &rarr; <?= e($l['to_status']) ?></td>
                            <td class="small"><?= e($l['user_name'] ?? 'System') ?></td>
                            <td class="small text-muted"><?= e($l['remarks'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Workflow panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Workflow</div>
            <div class="card-body">
                <p class="mb-3">Current status: <?= task_status_badge($task['status']) ?></p>

                <?php if ($task['status'] === 'planned' && can('task.manage')): ?>
                <form method="post" class="d-grid mb-2">
                    <?= csrf_field() ?>
                    <button name="action" value="start" class="btn btn-primary btn-sm"><i class="bi bi-play-circle me-1"></i> Start Task</button>
                </form>
                <?php endif; ?>

                <?php if ($task['status'] === 'in_progress' && can('task.manage')): ?>
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small">Proof Photo (optional)</label>
                        <input type="file" name="photo" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="d-grid">
                        <button name="action" value="complete" class="btn btn-info btn-sm text-white"><i class="bi bi-check2-circle me-1"></i> Mark Completed</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if ($task['status'] === 'completed' && can('task.approve')): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small">Remarks (optional)</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button name="action" value="approve" class="btn btn-success btn-sm"><i class="bi bi-check-circle me-1"></i> Approve</button>
                        <button name="action" value="reject" class="btn btn-outline-danger btn-sm" data-confirm="Reject this task?"><i class="bi bi-x-circle me-1"></i> Reject</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if ($task['status'] === 'rejected' && can('task.manage')): ?>
                <form method="post" class="d-grid">
                    <?= csrf_field() ?>
                    <button name="action" value="reopen" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i> Reopen Task</button>
                </form>
                <?php endif; ?>

                <?php if ($task['status'] === 'approved'): ?>
                <p class="text-muted small mb-0">Approved by <?= e($task['approver_name'] ?? 'user') ?> on <?= e(fmt_datetime($task['approved_at'])) ?>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
