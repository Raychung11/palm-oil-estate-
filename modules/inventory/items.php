<?php
/**
 * modules/inventory/items.php
 * Inventory item master — modal create/edit, searchable, low-stock highlight.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('inventory.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('inventory.approve');
    $action = input('action');
    $name   = trim((string)input('item_name'));
    $code   = trim((string)input('item_code'));
    $cat    = trim((string)input('category'));
    $unit   = trim((string)input('unit')) ?: 'unit';
    $loc    = trim((string)input('location'));
    $cost   = (float)(input('cost_per_unit') ?: 0);
    $minStk = (float)(input('minimum_stock') ?: 0);
    $status = input('status') === 'inactive' ? 'inactive' : 'active';

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM inventory_items WHERE id = ?')->execute([(int)input('id')]);
        set_flash('success', 'Item deleted.');
        redirect('modules/inventory/items.php');
    }
    if ($name === '') {
        set_flash('danger', 'Item name is required.');
        redirect('modules/inventory/items.php');
    }

    if ($action === 'update') {
        $pdo->prepare('UPDATE inventory_items SET item_code=?, item_name=?, category=?, unit=?, location=?,
                        cost_per_unit=?, minimum_stock=?, status=?, updated_by=?, updated_at=NOW() WHERE id=?')
            ->execute([$code ?: null, $name, $cat ?: null, $unit, $loc ?: null, $cost, $minStk, $status, current_user_id(), (int)input('id')]);
        set_flash('success', 'Item updated.');
    } else {
        $opening = (float)(input('current_stock') ?: 0);
        $pdo->prepare('INSERT INTO inventory_items (item_code, item_name, category, unit, location, cost_per_unit,
                        current_stock, minimum_stock, status, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$code ?: null, $name, $cat ?: null, $unit, $loc ?: null, $cost, $opening, $minStk, $status, current_user_id()]);
        set_flash('success', 'Item added.');
    }
    log_activity($pdo, current_user_id(), $action ?: 'create', 'inventory', 'Item ' . $name);
    redirect('modules/inventory/items.php');
}

$search = trim((string)input('q'));
$cat    = trim((string)input('category'));
$page   = current_page();
$offset = ($page - 1) * PER_PAGE;

$conds = [];
$params = [];
if ($search !== '') { $conds[] = '(item_name LIKE ? OR item_code LIKE ?)'; $like = "%$search%"; $params[] = $like; $params[] = $like; }
if ($cat !== '')    { $conds[] = 'category = ?'; $params[] = $cat; }
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_items $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM inventory_items $where ORDER BY item_name ASC LIMIT $offset, " . PER_PAGE);
$stmt->execute($params);
$items = $stmt->fetchAll();

$canManage = can('inventory.approve');
$page_title = 'Inventory Items';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Inventory Items</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i> Add Item</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-5 col-md-4"><input type="text" name="q" class="form-control form-control-sm" placeholder="Search name or code…" value="<?= e($search) ?>"></div>
            <div class="col-sm-4 col-md-3">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    <?php foreach (inventory_categories() as $c): ?><option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
                <a href="<?= e(url('modules/inventory/items.php')) ?>" class="btn btn-link btn-sm">Reset</a></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Item</th><th>Category</th><th>Location</th><th>Unit</th><th class="text-end">Cost</th>
                    <th class="text-end">Stock</th><th class="text-end">Value</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No items found.</td></tr>
                <?php else: foreach ($items as $it):
                    $low = (float)$it['current_stock'] <= (float)$it['minimum_stock'];
                    $value = (float)$it['current_stock'] * (float)$it['cost_per_unit']; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($it['item_name']) ?><?php if ($it['item_code']): ?><div class="text-muted small"><?= e($it['item_code']) ?></div><?php endif; ?></td>
                        <td class="small"><?= e($it['category'] ?? '—') ?></td>
                        <td class="small"><?= e($it['location'] ?? '—') ?></td>
                        <td><?= e($it['unit']) ?></td>
                        <td class="text-end"><?= num($it['cost_per_unit']) ?></td>
                        <td class="text-end <?= $low ? 'text-danger fw-semibold' : '' ?>"><?= num($it['current_stock']) ?><?php if ($low): ?> <i class="bi bi-exclamation-triangle-fill" title="Low stock"></i><?php endif; ?></td>
                        <td class="text-end"><?= num($value) ?></td>
                        <td><?= status_badge($it['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('modules/inventory/stock.php?item_id=' . (int)$it['id'])) ?>" class="btn btn-outline-secondary btn-sm" title="Stock card"><i class="bi bi-card-list"></i></a>
                            <?php if ($canManage): ?>
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$it['id'] ?>"><i class="bi bi-pencil"></i></button>
                            <form method="post" class="d-inline" data-confirm="Delete this item and its stock history?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($canManage): ?>
                    <div class="modal fade" id="editModal<?= (int)$it['id'] ?>" tabindex="-1"><div class="modal-dialog">
                        <form method="post" class="modal-content">
                            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                            <div class="modal-header"><h5 class="modal-title">Edit Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body"><?php $row = $it; require __DIR__ . '/_item_fields.php'; ?></div>
                            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save</button></div>
                        </form>
                    </div></div>
                    <?php endif; ?>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($total, PER_PAGE, $page, 'modules/inventory/items.php') ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title">Add Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><?php $row = ['item_code'=>'','item_name'=>'','category'=>'','unit'=>'unit','location'=>'','cost_per_unit'=>'','minimum_stock'=>'','current_stock'=>'','status'=>'active']; require __DIR__ . '/_item_fields.php'; ?></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add Item</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
