<?php
/**
 * modules/fertilizer/schedules.php
 * Planned fertilizer application schedule.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('fertilizer.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('fertilizer.manage');
    $action = input('action');

    if ($action === 'status') {
        $st = in_array(input('status'), ['planned', 'done', 'cancelled'], true) ? input('status') : 'planned';
        $pdo->prepare('UPDATE fertilizer_schedules SET status = ? WHERE id = ?')->execute([$st, (int)input('id')]);
        set_flash('success', 'Schedule updated.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM fertilizer_schedules WHERE id = ?')->execute([(int)input('id')]);
        set_flash('success', 'Schedule deleted.');
    } else {
        $productId = (int)input('product_id');
        $date = trim((string)input('scheduled_date')) ?: date('Y-m-d');
        $qty  = (float)input('planned_quantity');
        $blockId = to_int_or_null(input('block_id'));
        if ($productId > 0) {
            $pdo->prepare('INSERT INTO fertilizer_schedules (product_id, block_id, scheduled_date, planned_quantity, remarks, created_by, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$productId, $blockId, $date, $qty, trim((string)input('remarks')) ?: null, current_user_id()]);
            set_flash('success', 'Schedule added.');
        }
    }
    redirect('modules/fertilizer/schedules.php');
}

$products = $pdo->query("SELECT id, product_name FROM fertilizer_products WHERE status = 'active' ORDER BY product_name")->fetchAll();
$blocks   = $pdo->query('SELECT id, block_code FROM blocks ORDER BY block_code')->fetchAll();

$rows = $pdo->query(
    "SELECT s.*, p.product_name, b.block_code FROM fertilizer_schedules s
     JOIN fertilizer_products p ON p.id = s.product_id
     LEFT JOIN blocks b ON b.id = s.block_id
     ORDER BY s.scheduled_date DESC, s.id DESC"
)->fetchAll();

$canManage = can('fertilizer.manage');
$page_title = 'Fertilizer Schedule';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Application Schedule</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#schedModal"><i class="bi bi-plus-lg me-1"></i> Add Schedule</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Date</th><th>Product</th><th>Block</th><th class="text-end">Planned Qty</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No schedules yet.</td></tr>
                <?php else: foreach ($rows as $s): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fmt_datetime($s['scheduled_date'], 'd M Y')) ?></td>
                        <td><?= e($s['product_name']) ?></td>
                        <td><?= e($s['block_code'] ?? '—') ?></td>
                        <td class="text-end"><?= num($s['planned_quantity']) ?></td>
                        <td><?= status_badge($s['status'] === 'done' ? 'approved' : ($s['status'] === 'cancelled' ? 'inactive' : 'scheduled')) ?>
                            <span class="small text-muted"><?= e(ucfirst($s['status'])) ?></span></td>
                        <td class="text-end text-nowrap">
                            <?php if ($canManage): ?>
                            <?php if ($s['status'] === 'planned'): ?>
                            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="status" value="done"><button class="btn btn-outline-success btn-sm" title="Mark done"><i class="bi bi-check2"></i></button></form>
                            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="btn btn-outline-secondary btn-sm" title="Cancel"><i class="bi bi-x"></i></button></form>
                            <?php endif; ?>
                            <form method="post" class="d-inline" data-confirm="Delete schedule?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="schedModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title">Add Schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12"><label class="form-label">Product</label>
                <select name="product_id" class="form-select" required><option value="">— Select —</option>
                <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['product_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Block</label>
                <select name="block_id" class="form-select"><option value="">— None —</option>
                <?php foreach ($blocks as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['block_code']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Scheduled Date</label><input type="date" name="scheduled_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-6"><label class="form-label">Planned Quantity</label><input type="number" step="0.01" min="0" name="planned_quantity" class="form-control"></div>
            <div class="col-12"><label class="form-label">Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
