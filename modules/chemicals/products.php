<?php
/**
 * modules/chemicals/products.php
 * Chemical product master — modal create/edit, low-stock highlight.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('chemical.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('chemical.manage');
    $action = input('action');
    $name   = trim((string)input('product_name'));
    $code   = trim((string)input('product_code'));
    $type   = trim((string)input('chemical_type'));
    $ai     = trim((string)input('active_ingredient'));
    $unit   = trim((string)input('unit')) ?: 'litre';
    $cost   = (float)(input('cost_per_unit') ?: 0);
    $minStk = (float)(input('minimum_stock') ?: 0);
    $status = input('status') === 'inactive' ? 'inactive' : 'active';

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM chemical_products WHERE id = ?')->execute([(int)input('id')]);
        set_flash('success', 'Product deleted.');
        redirect('modules/chemicals/products.php');
    }
    if ($name === '') {
        set_flash('danger', 'Product name is required.');
        redirect('modules/chemicals/products.php');
    }

    if ($action === 'update') {
        $pdo->prepare('UPDATE chemical_products SET product_code=?, product_name=?, chemical_type=?, active_ingredient=?, unit=?,
                        cost_per_unit=?, minimum_stock=?, status=?, updated_by=?, updated_at=NOW() WHERE id=?')
            ->execute([$code ?: null, $name, $type ?: null, $ai ?: null, $unit, $cost, $minStk, $status, current_user_id(), (int)input('id')]);
        set_flash('success', 'Product updated.');
    } else {
        $opening = (float)(input('current_stock') ?: 0);
        $pdo->prepare('INSERT INTO chemical_products (product_code, product_name, chemical_type, active_ingredient, unit, cost_per_unit,
                        current_stock, minimum_stock, status, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$code ?: null, $name, $type ?: null, $ai ?: null, $unit, $cost, $opening, $minStk, $status, current_user_id()]);
        set_flash('success', 'Product added.');
    }
    log_activity($pdo, current_user_id(), $action ?: 'create', 'chemical', 'Product ' . $name);
    redirect('modules/chemicals/products.php');
}

$products = $pdo->query('SELECT * FROM chemical_products ORDER BY product_name')->fetchAll();
$canManage = can('chemical.manage');
$page_title = 'Chemical Products';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Chemical Products</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i> Add Product</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Product</th><th>Type</th><th>Active Ingredient</th><th>Unit</th><th class="text-end">Cost/Unit</th>
                    <th class="text-end">Stock</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No products yet.</td></tr>
                <?php else: foreach ($products as $p):
                    $low = (float)$p['current_stock'] <= (float)$p['minimum_stock']; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($p['product_name']) ?><?php if ($p['product_code']): ?><div class="text-muted small"><?= e($p['product_code']) ?></div><?php endif; ?></td>
                        <td class="small"><?= e($p['chemical_type'] ?? '—') ?></td>
                        <td class="small"><?= e($p['active_ingredient'] ?? '—') ?></td>
                        <td><?= e($p['unit']) ?></td>
                        <td class="text-end"><?= num($p['cost_per_unit']) ?></td>
                        <td class="text-end <?= $low ? 'text-danger fw-semibold' : '' ?>"><?= num($p['current_stock']) ?><?php if ($low): ?> <i class="bi bi-exclamation-triangle-fill" title="Low stock"></i><?php endif; ?></td>
                        <td><?= status_badge($p['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($canManage): ?>
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$p['id'] ?>"><i class="bi bi-pencil"></i></button>
                            <form method="post" class="d-inline" data-confirm="Delete this product and its stock history?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($canManage): ?>
                    <div class="modal fade" id="editModal<?= (int)$p['id'] ?>" tabindex="-1"><div class="modal-dialog">
                        <form method="post" class="modal-content">
                            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <div class="modal-header"><h5 class="modal-title">Edit Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body"><?php $row = $p; require __DIR__ . '/_product_fields.php'; ?></div>
                            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save</button></div>
                        </form>
                    </div></div>
                    <?php endif; ?>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title">Add Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><?php $row = ['product_code'=>'','product_name'=>'','chemical_type'=>'','active_ingredient'=>'','unit'=>'litre','cost_per_unit'=>'','minimum_stock'=>'','current_stock'=>'','status'=>'active']; require __DIR__ . '/_product_fields.php'; ?></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add Product</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
