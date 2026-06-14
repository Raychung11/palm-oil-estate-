<?php
/**
 * modules/workers/view.php
 * Worker profile: details, documents, warnings, attendance & productivity.
 * Document / warning add + delete handled inline (modal forms).
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('worker.view');

$id = (int)input('id');
if ($id <= 0) {
    set_flash('danger', 'Invalid worker.');
    redirect('modules/workers/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM workers WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$worker = $stmt->fetch();
if (!$worker) {
    set_flash('danger', 'Worker not found.');
    redirect('modules/workers/index.php');
}

// --- Document / warning actions ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('worker.manage');
    $action = input('action');

    try {
        if ($action === 'add_document') {
            $stored = handle_upload($_FILES['file'] ?? [], 'workers');
            $pdo->prepare('INSERT INTO worker_documents (worker_id, doc_type, doc_number, issue_date, expiry_date, file_path, remarks, created_by, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([
                    $id,
                    trim((string)input('doc_type')) ?: 'Document',
                    trim((string)input('doc_number')) ?: null,
                    trim((string)input('issue_date')) ?: null,
                    trim((string)input('expiry_date')) ?: null,
                    $stored,
                    trim((string)input('remarks')) ?: null,
                    current_user_id(),
                ]);
            log_activity($pdo, current_user_id(), 'create', 'workers', 'Added document for worker #' . $id);
            set_flash('success', 'Document added.');
        } elseif ($action === 'delete_document') {
            $pdo->prepare('DELETE FROM worker_documents WHERE id = ? AND worker_id = ?')
                ->execute([(int)input('doc_id'), $id]);
            set_flash('success', 'Document deleted.');
        } elseif ($action === 'add_warning') {
            $stored = handle_upload($_FILES['file'] ?? [], 'workers');
            $pdo->prepare('INSERT INTO worker_warnings (worker_id, warning_date, warning_type, description, file_path, issued_by, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, NOW())')
                ->execute([
                    $id,
                    trim((string)input('warning_date')) ?: date('Y-m-d'),
                    trim((string)input('warning_type')) ?: null,
                    trim((string)input('description')) ?: null,
                    $stored,
                    current_user_id(),
                ]);
            log_activity($pdo, current_user_id(), 'create', 'workers', 'Added warning for worker #' . $id);
            set_flash('success', 'Warning recorded.');
        } elseif ($action === 'delete_warning') {
            $pdo->prepare('DELETE FROM worker_warnings WHERE id = ? AND worker_id = ?')
                ->execute([(int)input('warning_id'), $id]);
            set_flash('success', 'Warning deleted.');
        }
    } catch (RuntimeException $ex) {
        set_flash('danger', $ex->getMessage());
    }
    redirect('modules/workers/view.php?id=' . $id);
}

// --- Profile datasets -------------------------------------------------
$docs = $pdo->prepare('SELECT * FROM worker_documents WHERE worker_id = ? ORDER BY expiry_date IS NULL, expiry_date ASC');
$docs->execute([$id]);
$docs = $docs->fetchAll();

$warnings = $pdo->prepare('SELECT * FROM worker_warnings WHERE worker_id = ? ORDER BY warning_date DESC');
$warnings->execute([$id]);
$warnings = $warnings->fetchAll();

// Attendance summary — current month.
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');
$attStmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS n FROM worker_attendance
     WHERE worker_id = ? AND attendance_date BETWEEN ? AND ? GROUP BY status"
);
$attStmt->execute([$id, $monthStart, $monthEnd]);
$attCounts = array_column($attStmt->fetchAll(), 'n', 'status');

// Productivity from harvest — current month.
$prodStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT h.id) AS entries, COALESCE(SUM(hrw.productivity_value),0) AS productivity
     FROM harvest_record_workers hrw
     JOIN harvest_records h ON h.id = hrw.harvest_record_id
     WHERE hrw.worker_id = ? AND h.harvest_date BETWEEN ? AND ?"
);
$prodStmt->execute([$id, $monthStart, $monthEnd]);
$prod = $prodStmt->fetch();

$canManage = can('worker.manage');
$page_title = $worker['name'];
require __DIR__ . '/../../inc/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('modules/workers/index.php')) ?>">Workers</a></li>
        <li class="breadcrumb-item active"><?= e($worker['name']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0"><?= e($worker['name']) ?> <?= status_badge($worker['status']) ?></h1>
    <?php if ($canManage): ?>
    <a href="<?= e(url('modules/workers/edit.php?id=' . $id)) ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-pencil me-1"></i> Edit
    </a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- Profile + productivity -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Profile</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Code</dt><dd class="col-7"><?= e($worker['worker_code']) ?></dd>
                    <dt class="col-5 text-muted">Category</dt><dd class="col-7"><?= e($worker['worker_type'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Nationality</dt><dd class="col-7"><?= e($worker['nationality'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Phone</dt><dd class="col-7"><?= e($worker['phone'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">IC / Passport</dt><dd class="col-7"><?= e($worker['ic_passport'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Permit Expiry</dt><dd class="col-7"><?= expiry_badge($worker['permit_expiry']) ?></dd>
                    <dt class="col-5 text-muted">Contract Expiry</dt><dd class="col-7"><?= expiry_badge($worker['contract_expiry']) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">This Month</div>
            <div class="card-body">
                <div class="row text-center g-2">
                    <div class="col-4"><div class="kpi-value"><?= (int)($attCounts['present'] ?? 0) ?></div><div class="kpi-label">Present</div></div>
                    <div class="col-4"><div class="kpi-value"><?= (int)($attCounts['absent'] ?? 0) ?></div><div class="kpi-label">Absent</div></div>
                    <div class="col-4"><div class="kpi-value"><?= (int)(($attCounts['leave'] ?? 0) + ($attCounts['mc'] ?? 0)) ?></div><div class="kpi-label">Leave/MC</div></div>
                </div>
                <hr>
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">Harvest entries</span><strong><?= (int)$prod['entries'] ?></strong>
                </div>
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">Productivity total</span><strong><?= num($prod['productivity']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Documents -->
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Documents</span>
                <?php if ($canManage): ?>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addDocModal">
                    <i class="bi bi-plus-lg"></i> Add
                </button>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Type</th><th>Number</th><th>Expiry</th><th>File</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($docs)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No documents.</td></tr>
                    <?php else: foreach ($docs as $d): ?>
                        <tr>
                            <td><?= e($d['doc_type']) ?></td>
                            <td class="small"><?= e($d['doc_number'] ?? '—') ?></td>
                            <td><?= expiry_badge($d['expiry_date']) ?></td>
                            <td>
                                <?php if ($d['file_path']): ?>
                                    <a href="<?= e(UPLOAD_URL . '/' . $d['file_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> View</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($canManage): ?>
                                <form method="post" class="d-inline" data-confirm="Delete this document?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_document">
                                    <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Warnings -->
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Warnings / Disciplinary</span>
                <?php if ($canManage): ?>
                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#addWarnModal">
                    <i class="bi bi-plus-lg"></i> Add
                </button>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>File</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($warnings)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No warnings recorded.</td></tr>
                    <?php else: foreach ($warnings as $wn): ?>
                        <tr>
                            <td class="text-nowrap"><?= e(fmt_datetime($wn['warning_date'], 'd M Y')) ?></td>
                            <td><?= e($wn['warning_type'] ?? '—') ?></td>
                            <td class="small"><?= nl2br(e($wn['description'] ?? '')) ?: '—' ?></td>
                            <td>
                                <?php if ($wn['file_path']): ?>
                                    <a href="<?= e(UPLOAD_URL . '/' . $wn['file_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i></a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($canManage): ?>
                                <form method="post" class="d-inline" data-confirm="Delete this warning?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_warning">
                                    <input type="hidden" name="warning_id" value="<?= (int)$wn['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<!-- Add document modal -->
<div class="modal fade" id="addDocModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_document">
            <div class="modal-header"><h5 class="modal-title">Add Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-6">
                    <label class="form-label">Document Type</label>
                    <input type="text" name="doc_type" class="form-control" placeholder="Passport, Permit…" required>
                </div>
                <div class="col-6"><label class="form-label">Number</label><input type="text" name="doc_number" class="form-control"></div>
                <div class="col-6"><label class="form-label">Issue Date</label><input type="date" name="issue_date" class="form-control"></div>
                <div class="col-6"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control"></div>
                <div class="col-12"><label class="form-label">Remarks</label><input type="text" name="remarks" class="form-control"></div>
                <div class="col-12">
                    <label class="form-label">File</label>
                    <input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <div class="form-text">Optional. JPG/PNG/WEBP/PDF up to 5&nbsp;MB.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Add Document</button>
            </div>
        </form>
    </div>
</div>

<!-- Add warning modal -->
<div class="modal fade" id="addWarnModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_warning">
            <div class="modal-header"><h5 class="modal-title">Add Warning</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-6"><label class="form-label">Date</label><input type="date" name="warning_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
                <div class="col-6"><label class="form-label">Type</label><input type="text" name="warning_type" class="form-control" placeholder="Verbal, Written…"></div>
                <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="col-12">
                    <label class="form-label">File</label>
                    <input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Add Warning</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
