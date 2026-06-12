<?php
/**
 * modules/workers/attendance.php
 * Daily attendance register — mark all active workers for a date.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('worker.view');

$date = trim((string)input('date')) ?: date('Y-m-d');
if (!strtotime($date)) {
    $date = date('Y-m-d');
}
$statuses = ['present' => 'Present', 'absent' => 'Absent', 'leave' => 'Leave', 'mc' => 'MC', 'half_day' => 'Half Day'];

// --- Save the register -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('worker.manage');
    $date = trim((string)input('date')) ?: date('Y-m-d');

    $statusInput  = (array)($_POST['status'] ?? []);
    $checkIn      = (array)($_POST['check_in'] ?? []);
    $remarksInput = (array)($_POST['remarks'] ?? []);

    $upsert = $pdo->prepare(
        'INSERT INTO worker_attendance (worker_id, attendance_date, status, check_in_time, check_in_method, remarks, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE status = VALUES(status), check_in_time = VALUES(check_in_time),
             remarks = VALUES(remarks), updated_by = VALUES(created_by), updated_at = NOW()'
    );

    $saved = 0;
    foreach ($statusInput as $wid => $st) {
        $wid = (int)$wid;
        if ($wid <= 0 || !isset($statuses[$st])) {
            continue;
        }
        $upsert->execute([
            $wid, $date, $st,
            trim((string)($checkIn[$wid] ?? '')) ?: null,
            'manual',
            trim((string)($remarksInput[$wid] ?? '')) ?: null,
            current_user_id(),
        ]);
        $saved++;
    }
    log_activity($pdo, current_user_id(), 'update', 'workers', "Saved attendance for $date ($saved workers)");
    set_flash('success', "Attendance saved for $saved worker(s).");
    redirect('modules/workers/attendance.php?date=' . urlencode($date));
}

// Active workers + any existing attendance for the date.
$workers = $pdo->query("SELECT id, worker_code, name, worker_type FROM workers WHERE status = 'active' ORDER BY name")->fetchAll();

$existing = [];
$exStmt = $pdo->prepare('SELECT worker_id, status, check_in_time, remarks FROM worker_attendance WHERE attendance_date = ?');
$exStmt->execute([$date]);
foreach ($exStmt->fetchAll() as $row) {
    $existing[(int)$row['worker_id']] = $row;
}

$canManage = can('worker.manage');
$page_title = 'Attendance';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Daily Attendance</h1>
    <a href="<?= e(url('modules/workers/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Workers
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="get">
            <div class="col-auto">
                <label class="form-label small mb-1">Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>"
                       max="<?= e(date('Y-m-d')) ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <div class="card">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><?= e(date('l, d M Y', strtotime($date))) ?></span>
            <span class="text-muted small"><?= count($workers) ?> active worker(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr><th>Worker</th><th style="width:160px;">Status</th><th style="width:140px;">Check-in</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                <?php if (empty($workers)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No active workers.</td></tr>
                <?php else: foreach ($workers as $w):
                    $wid = (int)$w['id'];
                    $cur = $existing[$wid] ?? null;
                    $curStatus = $cur['status'] ?? 'present'; ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('modules/workers/view.php?id=' . $wid)) ?>" class="text-decoration-none"><?= e($w['name']) ?></a>
                            <div class="text-muted small"><?= e($w['worker_code']) ?><?= $w['worker_type'] ? ' · ' . e($w['worker_type']) : '' ?></div>
                        </td>
                        <td>
                            <select name="status[<?= $wid ?>]" class="form-select form-select-sm" <?= $canManage ? '' : 'disabled' ?>>
                                <?php foreach ($statuses as $val => $label): ?>
                                    <option value="<?= e($val) ?>" <?= $curStatus === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="time" name="check_in[<?= $wid ?>]" class="form-control form-control-sm"
                                   value="<?= e($cur && $cur['check_in_time'] ? substr($cur['check_in_time'], 0, 5) : '') ?>" <?= $canManage ? '' : 'disabled' ?>>
                        </td>
                        <td>
                            <input type="text" name="remarks[<?= $wid ?>]" class="form-control form-control-sm"
                                   value="<?= e($cur['remarks'] ?? '') ?>" <?= $canManage ? '' : 'disabled' ?>>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($canManage && !empty($workers)): ?>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Save Attendance</button>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
