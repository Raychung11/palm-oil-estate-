<?php
/**
 * modules/chemicals/stock.php
 * Chemical stock movements — receive, issue and adjust.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/stock.php';
require_permission('chemical.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('chemical.manage');

    $productId = (int)input('product_id');
    $type      = input('movement_type');
    $qty       = (float)input('quantity');
    $date      = trim((string)input('movement_date')) ?: date('Y-m-d');
    $remarks   = trim((string)input('remarks')) ?: null;

    if ($productId <= 0 || !in_array($type, ['in', 'out', 'adjustment'], true) || $qty == 0) {
        set_flash('danger', 'Select a product, movement type and non-zero quantity.');
        redirect('modules/chemicals/stock.php');
    }

    $prod = $pdo->prepare('SELECT cost_per_unit FROM chemical_products WHERE id = ?');
    $prod->execute([$productId]);
    $cost = (float)($prod->fetchColumn() ?: 0);

    record_stock_movement($pdo, 'chemical_products', 'chemical_stock_movements', $productId, $type, $qty, [
        'movement_date'  => $date,
        'unit_cost'      => $type === 'in' ? $cost : null,
        'reference_type' => 'manual',
        'remarks'        => $remarks,
    ]);
    log_activity($pdo, current_user_id(), $type, 'chemical', "Stock $type qty $qty for product #$productId");
    set_flash('success', 'Stock movement recorded.');
    redirect('modules/chemicals/stock.php');
}

$products = $pdo->query("SELECT id, product_name, unit, current_stock FROM chemical_products WHERE status = 'active' ORDER BY product_name")->fetchAll();

$page = current_page();
$offset = ($page - 1) * PER_PAGE;
$total = (int)$pdo->query('SELECT COUNT(*) FROM chemical_stock_movements')->fetchColumn();
$moves = $pdo->query(
    "SELECT m.*, p.product_name, p.unit FROM chemical_stock_movements m
     JOIN chemical_products p ON p.id = m.product_id
     ORDER BY m.movement_date DESC, m.id DESC LIMIT $offset, " . PER_PAGE
)->fetchAll();

$canManage = can('chemical.manage');
$page_title = 'Chemical Stock';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Stock Movements</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#moveModal"><i class="bi bi-plus-lg me-1"></i> Record Movement</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Date</th><th>Product</th><th>Type</th><th class="text-end">Quantity</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php if (empty($moves)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No stock movements yet.</td></tr>
                <?php else: foreach ($moves as $m):
                    $badge = ['in' => 'success', 'out' => 'danger', 'adjustment' => 'secondary'][$m['movement_type']] ?? 'secondary'; ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fmt_datetime($m['movement_date'], 'd M Y')) ?></td>
                        <td><?= e($m['product_name']) ?></td>
                        <td><span class="badge bg-<?= $badge ?>"><?= e(ucfirst($m['movement_type'])) ?></span></td>
                        <td class="text-end"><?= num($m['quantity']) ?> <?= e($m['unit']) ?></td>
                        <td class="small text-muted"><?= e($m['remarks'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($total, PER_PAGE, $page, 'modules/chemicals/stock.php') ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="moveModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Record Stock Movement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12">
                <label class="form-label">Product <span class="text-danger">*</span></label>
                <select name="product_id" class="form-select" required>
                    <option value="">— Select —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['product_name']) ?> (<?= num($p['current_stock']) ?> <?= e($p['unit']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">Type</label>
                <select name="movement_type" class="form-select">
                    <option value="in">Stock In (receive)</option>
                    <option value="out">Stock Out (issue)</option>
                    <option value="adjustment">Adjustment (+/-)</option>
                </select>
            </div>
            <div class="col-6"><label class="form-label">Quantity</label><input type="number" step="0.01" name="quantity" class="form-control" required></div>
            <div class="col-6"><label class="form-label">Date</label><input type="date" name="movement_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-12"><label class="form-label">Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
