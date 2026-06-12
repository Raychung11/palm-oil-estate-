<?php
/**
 * modules/fertilizer/applications.php
 * Record fertilizer applied to a block; each application issues stock.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/stock.php';
require_once dirname(__DIR__) . '/harvest/_logic.php';
require_permission('fertilizer.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('fertilizer.manage');

    $date      = trim((string)input('application_date')) ?: date('Y-m-d');
    $productId = (int)input('product_id');
    $qty       = (float)input('quantity');
    $blockId   = to_int_or_null(input('block_id'));
    $estateId  = to_int_or_null(input('estate_id'));
    $divId     = to_int_or_null(input('division_id'));
    $workerId  = to_int_or_null(input('worker_id'));
    $remarks   = trim((string)input('remarks')) ?: null;

    $prod = $pdo->prepare('SELECT unit, cost_per_unit FROM fertilizer_products WHERE id = ?');
    $prod->execute([$productId]);
    $p = $prod->fetch();

    if (!$p || $qty <= 0) {
        set_flash('danger', 'Select a product and a positive quantity.');
        redirect('modules/fertilizer/applications.php');
    }

    $cost = (float)$p['cost_per_unit'];
    $total = $cost * $qty;

    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO fertilizer_applications (application_date, product_id, estate_id, division_id, block_id,
                        worker_id, quantity, unit, cost_per_unit, total_cost, remarks, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$date, $productId, $estateId, $divId, $blockId, $workerId, $qty, $p['unit'], $cost, $total, $remarks, current_user_id()]);
        $appId = (int)$pdo->lastInsertId();

        record_stock_movement($pdo, 'fertilizer_products', 'fertilizer_stock_movements', $productId, 'out', $qty, [
            'movement_date'  => $date,
            'reference_type' => 'application',
            'reference_id'   => $appId,
            'remarks'        => 'Field application',
        ]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('danger', 'Could not record the application.');
        redirect('modules/fertilizer/applications.php');
    }

    log_activity($pdo, current_user_id(), 'create', 'fertilizer', 'Application of product #' . $productId);
    set_flash('success', 'Application recorded and stock issued.');
    redirect('modules/fertilizer/applications.php');
}

$loc = harvest_location_data($pdo);
$estates = $loc['estates']; $allDivisions = $loc['divisions']; $allBlocks = $loc['blocks'];
$products = $pdo->query("SELECT id, product_name, unit, current_stock FROM fertilizer_products WHERE status = 'active' ORDER BY product_name")->fetchAll();
$workers  = $pdo->query("SELECT id, name FROM workers WHERE status = 'active' ORDER BY name")->fetchAll();

$pageNum = current_page();
$offset = ($pageNum - 1) * PER_PAGE;
$total = (int)$pdo->query('SELECT COUNT(*) FROM fertilizer_applications')->fetchColumn();
$apps = $pdo->query(
    "SELECT a.*, p.product_name, b.block_code, w.name AS worker_name
     FROM fertilizer_applications a
     JOIN fertilizer_products p ON p.id = a.product_id
     LEFT JOIN blocks b ON b.id = a.block_id
     LEFT JOIN workers w ON w.id = a.worker_id
     ORDER BY a.application_date DESC, a.id DESC LIMIT $offset, " . PER_PAGE
)->fetchAll();

$form = ['estate_id' => '', 'division_id' => '', 'block_id' => ''];
$canManage = can('fertilizer.manage');
$page_title = 'Fertilizer Applications';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Applications</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#appModal"><i class="bi bi-plus-lg me-1"></i> Record Application</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Date</th><th>Product</th><th>Block</th><th>Worker</th>
                    <th class="text-end">Qty</th><th class="text-end">Cost</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php if (empty($apps)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No applications yet.</td></tr>
                <?php else: foreach ($apps as $a): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fmt_datetime($a['application_date'], 'd M Y')) ?></td>
                        <td><?= e($a['product_name']) ?></td>
                        <td><?= e($a['block_code'] ?? '—') ?></td>
                        <td class="small"><?= e($a['worker_name'] ?? '—') ?></td>
                        <td class="text-end"><?= num($a['quantity']) ?> <?= e($a['unit']) ?></td>
                        <td class="text-end"><?= num($a['total_cost']) ?></td>
                        <td class="small text-muted"><?= e($a['remarks'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($total, PER_PAGE, $pageNum, 'modules/fertilizer/applications.php') ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="appModal" tabindex="-1"><div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Record Application</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="application_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-md-5">
                <label class="form-label">Product <span class="text-danger">*</span></label>
                <select name="product_id" class="form-select" required>
                    <option value="">— Select —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['product_name']) ?> (<?= num($p['current_stock']) ?> <?= e($p['unit']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Quantity</label><input type="number" step="0.01" min="0" name="quantity" class="form-control" required></div>
            <?php $required = false; require dirname(__DIR__) . '/harvest/_location_picker.php'; ?>
            <div class="col-md-6">
                <label class="form-label">Worker</label>
                <select name="worker_id" class="form-select">
                    <option value="">— None —</option>
                    <?php foreach ($workers as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save Application</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
