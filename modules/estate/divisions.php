<?php
/**
 * modules/estate/divisions.php
 * Manage divisions belonging to a single estate.
 * Create / edit handled via Bootstrap modal forms posting back here.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('estate.view');

$estateId = (int)input('estate_id');
if ($estateId <= 0) {
    set_flash('danger', 'Invalid estate.');
    redirect('modules/estate/index.php');
}

$estStmt = $pdo->prepare('SELECT * FROM estates WHERE id = ? LIMIT 1');
$estStmt->execute([$estateId]);
$estate = $estStmt->fetch();
if (!$estate) {
    set_flash('danger', 'Estate not found.');
    redirect('modules/estate/index.php');
}

// --- Handle create / update / delete ----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('estate.manage');
    $action = input('action');
    $name   = trim((string)input('division_name'));
    $code   = trim((string)input('division_code'));
    $status = input('status') === 'inactive' ? 'inactive' : 'active';

    if ($action === 'delete') {
        $divId = (int)input('division_id');
        $pdo->prepare('DELETE FROM divisions WHERE id = ? AND estate_id = ?')->execute([$divId, $estateId]);
        log_activity($pdo, current_user_id(), 'delete', 'estate', 'Deleted division #' . $divId);
        set_flash('success', 'Division deleted.');
        redirect('modules/estate/divisions.php?estate_id=' . $estateId);
    }

    if ($name === '') {
        set_flash('danger', 'Division name is required.');
        redirect('modules/estate/divisions.php?estate_id=' . $estateId);
    }

    if ($action === 'update') {
        $divId = (int)input('division_id');
        $pdo->prepare('UPDATE divisions SET division_name=?, division_code=?, status=?, updated_by=?, updated_at=NOW()
                       WHERE id=? AND estate_id=?')
            ->execute([$name, $code ?: null, $status, current_user_id(), $divId, $estateId]);
        log_activity($pdo, current_user_id(), 'update', 'estate', 'Updated division ' . $name);
        set_flash('success', 'Division updated.');
    } else {
        $pdo->prepare('INSERT INTO divisions (estate_id, division_name, division_code, status, created_by, created_at)
                       VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute([$estateId, $name, $code ?: null, $status, current_user_id()]);
        log_activity($pdo, current_user_id(), 'create', 'estate', 'Created division ' . $name);
        set_flash('success', 'Division added.');
    }
    redirect('modules/estate/divisions.php?estate_id=' . $estateId);
}

$divisions = $pdo->prepare(
    'SELECT d.*, (SELECT COUNT(*) FROM blocks b WHERE b.division_id = d.id) AS block_count
     FROM divisions d WHERE d.estate_id = ? ORDER BY d.division_name ASC'
);
$divisions->execute([$estateId]);
$divisions = $divisions->fetchAll();

$canManage = can('estate.manage');
$page_title = 'Divisions — ' . $estate['estate_name'];
require __DIR__ . '/../../inc/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('modules/estate/index.php')) ?>">Estates</a></li>
        <li class="breadcrumb-item active"><?= e($estate['estate_name']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Divisions</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addDivisionModal">
        <i class="bi bi-plus-lg me-1"></i> Add Division
    </button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Division</th><th>Code</th><th class="text-center">Blocks</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($divisions)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No divisions yet.</td></tr>
                <?php else: foreach ($divisions as $d): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($d['division_name']) ?></td>
                        <td class="text-muted small"><?= e($d['division_code'] ?? '—') ?></td>
                        <td class="text-center">
                            <a href="<?= e(url('modules/estate/blocks.php?estate_id=' . $estateId . '&division_id=' . (int)$d['id'])) ?>">
                                <?= (int)$d['block_count'] ?>
                            </a>
                        </td>
                        <td><?= status_badge($d['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($canManage): ?>
                            <button class="btn btn-outline-primary btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#editDivisionModal<?= (int)$d['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" class="d-inline" data-confirm="Delete this division and all its blocks?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="division_id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if ($canManage): ?>
                    <!-- Edit modal for this division -->
                    <div class="modal fade" id="editDivisionModal<?= (int)$d['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="post" class="modal-content">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="division_id" value="<?= (int)$d['id'] ?>">
                                <div class="modal-header"><h5 class="modal-title">Edit Division</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Division Name <span class="text-danger">*</span></label>
                                        <input type="text" name="division_name" class="form-control" value="<?= e($d['division_name']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Code</label>
                                        <input type="text" name="division_code" class="form-control" value="<?= e($d['division_code']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="active"   <?= $d['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $d['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<!-- Add modal -->
<div class="modal fade" id="addDivisionModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header"><h5 class="modal-title">Add Division</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Division Name <span class="text-danger">*</span></label>
                    <input type="text" name="division_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Code</label>
                    <input type="text" name="division_code" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Add Division</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
