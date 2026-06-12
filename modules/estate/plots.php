<?php
/**
 * modules/estate/plots.php
 * Manage plots belonging to a single block.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('estate.view');

$blockId = (int)input('block_id');
if ($blockId <= 0) {
    set_flash('danger', 'Invalid block.');
    redirect('modules/estate/blocks.php');
}

$blkStmt = $pdo->prepare(
    'SELECT b.*, e.estate_name, d.division_name
     FROM blocks b
     JOIN estates e ON e.id = b.estate_id
     JOIN divisions d ON d.id = b.division_id
     WHERE b.id = ? LIMIT 1'
);
$blkStmt->execute([$blockId]);
$block = $blkStmt->fetch();
if (!$block) {
    set_flash('danger', 'Block not found.');
    redirect('modules/estate/blocks.php');
}

// --- Handle create / update / delete ----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('estate.manage');
    $action  = input('action');
    $code    = trim((string)input('plot_code'));
    $name    = trim((string)input('plot_name'));
    $acreage = to_decimal_or_null(input('acreage'));
    $palms   = to_int_or_null(input('palm_count'));
    $year    = to_int_or_null(input('planting_year'));
    $status  = input('status') === 'inactive' ? 'inactive' : 'active';

    if ($action === 'delete') {
        $plotId = (int)input('plot_id');
        $pdo->prepare('DELETE FROM plots WHERE id = ? AND block_id = ?')->execute([$plotId, $blockId]);
        log_activity($pdo, current_user_id(), 'delete', 'estate', 'Deleted plot #' . $plotId);
        set_flash('success', 'Plot deleted.');
        redirect('modules/estate/plots.php?block_id=' . $blockId);
    }

    if ($code === '') {
        set_flash('danger', 'Plot code is required.');
        redirect('modules/estate/plots.php?block_id=' . $blockId);
    }

    if ($action === 'update') {
        $plotId = (int)input('plot_id');
        $pdo->prepare('UPDATE plots SET plot_code=?, plot_name=?, acreage=?, palm_count=?, planting_year=?,
                              status=?, updated_by=?, updated_at=NOW() WHERE id=? AND block_id=?')
            ->execute([$code, $name ?: null, $acreage, $palms, $year, $status, current_user_id(), $plotId, $blockId]);
        log_activity($pdo, current_user_id(), 'update', 'estate', 'Updated plot ' . $code);
        set_flash('success', 'Plot updated.');
    } else {
        $pdo->prepare('INSERT INTO plots (block_id, plot_code, plot_name, acreage, palm_count, planting_year,
                              status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$blockId, $code, $name ?: null, $acreage, $palms, $year, $status, current_user_id()]);
        log_activity($pdo, current_user_id(), 'create', 'estate', 'Created plot ' . $code);
        set_flash('success', 'Plot added.');
    }
    redirect('modules/estate/plots.php?block_id=' . $blockId);
}

$plots = $pdo->prepare('SELECT * FROM plots WHERE block_id = ? ORDER BY plot_code ASC');
$plots->execute([$blockId]);
$plots = $plots->fetchAll();

$canManage = can('estate.manage');
$page_title = 'Plots — ' . $block['block_code'];
require __DIR__ . '/../../inc/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('modules/estate/index.php')) ?>">Estates</a></li>
        <li class="breadcrumb-item">
            <a href="<?= e(url('modules/estate/blocks.php?estate_id=' . (int)$block['estate_id'])) ?>">
                <?= e($block['estate_name']) ?>
            </a>
        </li>
        <li class="breadcrumb-item active"><?= e($block['block_code']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h4 mb-0">Plots</h1>
        <small class="text-muted"><?= e($block['division_name']) ?> &middot; Block <?= e($block['block_code']) ?></small>
    </div>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addPlotModal">
        <i class="bi bi-plus-lg me-1"></i> Add Plot
    </button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Plot</th><th class="text-end">Acreage</th><th class="text-end">Palms</th>
                        <th class="text-center">Year</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($plots)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No plots yet.</td></tr>
                <?php else: foreach ($plots as $p): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($p['plot_code']) ?>
                            <?php if ($p['plot_name']): ?><div class="text-muted small"><?= e($p['plot_name']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-end"><?= num($p['acreage']) ?></td>
                        <td class="text-end"><?= num($p['palm_count'], 0) ?></td>
                        <td class="text-center"><?= $p['planting_year'] !== null ? (int)$p['planting_year'] : '—' ?></td>
                        <td><?= status_badge($p['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($canManage): ?>
                            <button class="btn btn-outline-primary btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#editPlotModal<?= (int)$p['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" class="d-inline" data-confirm="Delete this plot?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="plot_id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if ($canManage): ?>
                    <div class="modal fade" id="editPlotModal<?= (int)$p['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="post" class="modal-content">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="plot_id" value="<?= (int)$p['id'] ?>">
                                <div class="modal-header"><h5 class="modal-title">Edit Plot</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <?php require __DIR__ . '/_plot_fields.php'; ?>
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
<div class="modal fade" id="addPlotModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header"><h5 class="modal-title">Add Plot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <?php $p = ['plot_code'=>'','plot_name'=>'','acreage'=>'','palm_count'=>'','planting_year'=>'','status'=>'active'];
                      require __DIR__ . '/_plot_fields.php'; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Add Plot</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
