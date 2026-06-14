<?php
/**
 * modules/harvest/view.php
 * Harvest record detail + supervisor approval workflow.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('harvest.view');

$id = (int)input('id');
if ($id <= 0) {
    set_flash('danger', 'Invalid harvest record.');
    redirect('modules/harvest/index.php');
}

// --- Approval / rejection actions -------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('harvest.approve');
    $action  = input('action');
    $remarks = trim((string)input('remarks')) ?: null;

    if (in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $pdo->prepare('UPDATE harvest_records SET approval_status = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW()
                       WHERE id = ?')
            ->execute([$newStatus, current_user_id(), $id]);
        $pdo->prepare('INSERT INTO harvest_approvals (harvest_record_id, action, remarks, approved_by, created_at)
                       VALUES (?, ?, ?, ?, NOW())')
            ->execute([$id, $newStatus, $remarks, current_user_id()]);
        log_activity($pdo, current_user_id(), $action, 'harvest', 'Harvest #' . $id . ' ' . $newStatus);
        set_flash('success', 'Harvest record ' . $newStatus . '.');
    }
    redirect('modules/harvest/view.php?id=' . $id);
}

$stmt = $pdo->prepare(
    'SELECT h.*, e.estate_name, d.division_name, b.block_code, b.block_name, b.hectare, b.acreage,
            u.name AS supervisor_name, a.name AS approver_name
     FROM harvest_records h
     JOIN estates e   ON e.id = h.estate_id
     JOIN divisions d ON d.id = h.division_id
     JOIN blocks b    ON b.id = h.block_id
     LEFT JOIN users u ON u.id = h.supervisor_id
     LEFT JOIN users a ON a.id = h.approved_by
     WHERE h.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) {
    set_flash('danger', 'Harvest record not found.');
    redirect('modules/harvest/index.php');
}

$team = $pdo->prepare(
    'SELECT hrw.role_in_task, hrw.productivity_value, w.worker_code, w.name
     FROM harvest_record_workers hrw JOIN workers w ON w.id = hrw.worker_id
     WHERE hrw.harvest_record_id = ? ORDER BY w.name'
);
$team->execute([$id]);
$team = $team->fetchAll();

$photos = $pdo->prepare('SELECT file_path, caption FROM harvest_photos WHERE harvest_record_id = ? ORDER BY id');
$photos->execute([$id]);
$photos = $photos->fetchAll();

// Yield per hectare for this record (FFB + loose fruit).
$totalKg = (float)$r['ffb_weight_kg'] + (float)$r['loose_fruit_kg'];
$perHa = ($r['hectare'] && (float)$r['hectare'] > 0) ? $totalKg / (float)$r['hectare'] : null;

$page_title = 'Harvest ' . $r['block_code'] . ' — ' . date('d M Y', strtotime($r['harvest_date']));
require __DIR__ . '/../../inc/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('modules/harvest/index.php')) ?>">Harvest Records</a></li>
        <li class="breadcrumb-item active"><?= e($r['block_code']) ?> &middot; <?= e(fmt_datetime($r['harvest_date'], 'd M Y')) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Harvest Detail <?= status_badge($r['approval_status']) ?></h1>
    <?php if (can('harvest.create') && $r['approval_status'] !== 'approved'): ?>
    <a href="<?= e(url('modules/harvest/edit.php?id=' . $id)) ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-pencil me-1"></i> Edit
    </a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Summary</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-4"><div class="text-muted small">Date</div><?= e(fmt_datetime($r['harvest_date'], 'd M Y')) ?></div>
                    <div class="col-sm-4"><div class="text-muted small">Collection Time</div><?= e($r['collection_time'] ? substr($r['collection_time'], 0, 5) : '—') ?></div>
                    <div class="col-sm-4"><div class="text-muted small">Supervisor</div><?= e($r['supervisor_name'] ?? '—') ?></div>
                    <div class="col-sm-4"><div class="text-muted small">Estate</div><?= e($r['estate_name']) ?></div>
                    <div class="col-sm-4"><div class="text-muted small">Division</div><?= e($r['division_name']) ?></div>
                    <div class="col-sm-4"><div class="text-muted small">Block</div><?= e($r['block_code']) ?><?= $r['block_name'] ? ' — ' . e($r['block_name']) : '' ?></div>
                </div>
                <hr>
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-3"><div class="kpi-value"><?= num($r['bunches_count'], 0) ?></div><div class="kpi-label">Bunches</div></div>
                    <div class="col-6 col-md-3"><div class="kpi-value"><?= num($r['ffb_weight_kg']) ?></div><div class="kpi-label">FFB (kg)</div></div>
                    <div class="col-6 col-md-3"><div class="kpi-value"><?= num($r['loose_fruit_kg']) ?></div><div class="kpi-label">Loose Fruit (kg)</div></div>
                    <div class="col-6 col-md-3"><div class="kpi-value"><?= num($r['rejected_bunches'], 0) ?></div><div class="kpi-label">Rejected</div></div>
                </div>
                <?php if ($perHa !== null): ?>
                <hr>
                <div class="text-muted small">Yield per hectare (FFB + loose fruit): <strong class="text-dark"><?= num($perHa) ?> kg/ha</strong>
                    (<?= kg_to_tonnes($totalKg) ?> t over <?= num($r['hectare']) ?> ha)</div>
                <?php endif; ?>
                <?php if ($r['remarks']): ?>
                <hr><div class="text-muted small">Remarks</div><div><?= nl2br(e($r['remarks'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Harvest Team (<?= count($team) ?>)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Worker</th><th>Role</th><th class="text-end">Productivity</th></tr></thead>
                    <tbody>
                    <?php if (empty($team)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No workers assigned.</td></tr>
                    <?php else: foreach ($team as $t): ?>
                        <tr>
                            <td><?= e($t['name']) ?> <span class="text-muted small">(<?= e($t['worker_code']) ?>)</span></td>
                            <td><?= e($t['role_in_task'] ?? '—') ?></td>
                            <td class="text-end"><?= num($t['productivity_value']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($photos)): ?>
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Field Photos</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <?php foreach ($photos as $ph): ?>
                    <a href="<?= e(UPLOAD_URL . '/' . $ph['file_path']) ?>" target="_blank">
                        <img src="<?= e(UPLOAD_URL . '/' . $ph['file_path']) ?>" alt="Field photo" style="height:120px;border-radius:8px;">
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Approval</div>
            <div class="card-body">
                <p class="mb-2">Status: <?= status_badge($r['approval_status']) ?></p>
                <?php if ($r['approved_by']): ?>
                    <p class="text-muted small mb-3">
                        <?= e(ucfirst($r['approval_status'])) ?> by <?= e($r['approver_name'] ?? 'user') ?>
                        on <?= e(fmt_datetime($r['approved_at'])) ?>.
                    </p>
                <?php endif; ?>

                <?php if (can('harvest.approve') && $r['approval_status'] === 'pending'): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small">Remarks (optional)</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                            <i class="bi bi-check-circle me-1"></i> Approve
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm"
                                data-confirm="Reject this harvest record?">
                            <i class="bi bi-x-circle me-1"></i> Reject
                        </button>
                    </div>
                </form>
                <?php elseif (!can('harvest.approve') && $r['approval_status'] === 'pending'): ?>
                    <p class="text-muted small mb-0">Awaiting supervisor approval.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
