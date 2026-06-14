<?php
/**
 * modules/inventory/stock.php
 * Inventory stock movements / stock card (filter by item_id).
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/stock.php';
require_permission('inventory.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('inventory.approve');

    $itemId  = (int)input('item_id');
    $type    = input('movement_type');
    $qty     = (float)input('quantity');
    $date    = trim((string)input('movement_date')) ?: date('Y-m-d');
    $remarks = trim((string)input('remarks')) ?: null;

    if ($itemId <= 0 || !in_array($type, ['in', 'out', 'adjustment'], true) || $qty == 0) {
        set_flash('danger', 'Select an item, movement type and non-zero quantity.');
        redirect('modules/inventory/stock.php');
    }

    $cost = (float)($pdo->query('SELECT cost_per_unit FROM inventory_items WHERE id = ' . $itemId)->fetchColumn() ?: 0);
    record_stock_movement($pdo, 'inventory_items', 'inventory_stock_movements', $itemId, $type, $qty, [
        'movement_date'  => $date,
        'unit_cost'      => $type === 'in' ? $cost : null,
        'reference_type' => 'manual',
        'remarks'        => $remarks,
    ]);
    log_activity($pdo, current_user_id(), $type, 'inventory', "Stock $type qty $qty for item #$itemId");
    set_flash('success', 'Stock movement recorded.');
    redirect('modules/inventory/stock.php?item_id=' . $itemId);
}

$itemId = (int)input('item_id');
$item = null;
if ($itemId > 0) {
    $s = $pdo->prepare('SELECT * FROM inventory_items WHERE id = ?');
    $s->execute([$itemId]);
    $item = $s->fetch();
}

$items = $pdo->query("SELECT id, item_name, unit, current_stock FROM inventory_items WHERE status = 'active' ORDER BY item_name")->fetchAll();

$page = current_page();
$offset = ($page - 1) * PER_PAGE;
$cond = $itemId > 0 ? 'WHERE m.item_id = ' . $itemId : '';
$total = (int)$pdo->query("SELECT COUNT(*) FROM inventory_stock_movements m $cond")->fetchColumn();
$moves = $pdo->query(
    "SELECT m.*, i.item_name, i.unit FROM inventory_stock_movements m
     JOIN inventory_items i ON i.id = m.item_id
     $cond ORDER BY m.movement_date DESC, m.id DESC LIMIT $offset, " . PER_PAGE
)->fetchAll();

$canManage = can('inventory.approve');
$page_title = 'Inventory Stock';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Stock Movements<?= $item ? ' — ' . e($item['item_name']) : '' ?></h1>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#moveModal"><i class="bi bi-plus-lg me-1"></i> Record Movement</button>
    <?php endif; ?>
</div>

<?php if ($item): ?>
<div class="alert alert-light border d-flex justify-content-between">
    <span>Current stock: <strong><?= num($item['current_stock']) ?> <?= e($item['unit']) ?></strong></span>
    <a href="<?= e(url('modules/inventory/stock.php')) ?>" class="small">View all items</a>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Date</th><th>Item</th><th>Type</th><th class="text-end">Quantity</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php if (empty($moves)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No stock movements yet.</td></tr>
                <?php else: foreach ($moves as $m):
                    $badge = ['in' => 'success', 'out' => 'danger', 'adjustment' => 'secondary'][$m['movement_type']] ?? 'secondary'; ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fmt_datetime($m['movement_date'], 'd M Y')) ?></td>
                        <td><?= e($m['item_name']) ?></td>
                        <td><span class="badge bg-<?= $badge ?>"><?= e(ucfirst($m['movement_type'])) ?></span></td>
                        <td class="text-end"><?= num($m['quantity']) ?> <?= e($m['unit']) ?></td>
                        <td class="small text-muted"><?= e($m['remarks'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($total, PER_PAGE, $page, 'modules/inventory/stock.php') ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="moveModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Record Stock Movement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12">
                <label class="form-label">Item <span class="text-danger">*</span></label>
                <select name="item_id" class="form-select" required>
                    <option value="">— Select —</option>
                    <?php foreach ($items as $it): ?>
                        <option value="<?= (int)$it['id'] ?>" <?= $itemId === (int)$it['id'] ? 'selected' : '' ?>><?= e($it['item_name']) ?> (<?= num($it['current_stock']) ?> <?= e($it['unit']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">Type</label>
                <select name="movement_type" class="form-select">
                    <option value="in">Receive (in)</option>
                    <option value="out">Issue (out)</option>
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
