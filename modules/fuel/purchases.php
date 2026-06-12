<?php
/**
 * modules/fuel/purchases.php
 * Fuel purchases — each purchase tops up the tank balance.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('asset.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('asset.manage');
    $action = input('action');

    if ($action === 'delete') {
        $pid = (int)input('id');
        $litres = (float)($pdo->query('SELECT quantity_litre FROM fuel_purchases WHERE id = ' . $pid)->fetchColumn() ?: 0);
        $pdo->prepare('DELETE FROM fuel_purchases WHERE id = ?')->execute([$pid]);
        $pdo->prepare('UPDATE fuel_tank_balance SET current_litres = current_litres - ?, updated_at = NOW() WHERE id = 1')->execute([$litres]);
        set_flash('success', 'Purchase deleted.');
        redirect('modules/fuel/purchases.php');
    }

    $date = trim((string)input('purchase_date')) ?: date('Y-m-d');
    $supplier = trim((string)input('supplier')) ?: null;
    $litres = (float)input('quantity_litre');
    $unit = (float)input('unit_cost');
    if ($litres <= 0) {
        set_flash('danger', 'Quantity must be greater than zero.');
        redirect('modules/fuel/purchases.php');
    }
    $total = $litres * $unit;

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO fuel_purchases (purchase_date, supplier, quantity_litre, unit_cost, total_cost, remarks, created_by, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$date, $supplier, $litres, $unit, $total, trim((string)input('remarks')) ?: null, current_user_id()]);
        $pdo->prepare('UPDATE fuel_tank_balance SET current_litres = current_litres + ?, updated_at = NOW() WHERE id = 1')->execute([$litres]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('danger', 'Could not record the purchase.');
        redirect('modules/fuel/purchases.php');
    }
    log_activity($pdo, current_user_id(), 'create', 'fuel', "Fuel purchase $litres L");
    set_flash('success', 'Fuel purchase recorded.');
    redirect('modules/fuel/purchases.php');
}

$page = current_page();
$offset = ($page - 1) * PER_PAGE;
$total = (int)$pdo->query('SELECT COUNT(*) FROM fuel_purchases')->fetchColumn();
$rows = $pdo->query("SELECT * FROM fuel_purchases ORDER BY purchase_date DESC, id DESC LIMIT $offset, " . PER_PAGE)->fetchAll();

$canManage = can('asset.manage');
$page_title = 'Fuel Purchases';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Purchases</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i> Record Purchase</button>
    <?php endif; ?>
</div>

<div class="card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Date</th><th>Supplier</th><th class="text-end">Litres</th><th class="text-end">Unit Cost</th><th class="text-end">Total</th><th>Remarks</th><th class="text-end"></th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">No purchases yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="text-nowrap"><?= e(fmt_datetime($r['purchase_date'], 'd M Y')) ?></td>
                    <td><?= e($r['supplier'] ?? '—') ?></td>
                    <td class="text-end"><?= num($r['quantity_litre']) ?></td>
                    <td class="text-end"><?= num($r['unit_cost']) ?></td>
                    <td class="text-end"><?= num($r['total_cost']) ?></td>
                    <td class="small text-muted"><?= e($r['remarks'] ?? '—') ?></td>
                    <td class="text-end"><?php if ($canManage): ?><form method="post" class="d-inline" data-confirm="Delete purchase and reverse tank balance?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($total, PER_PAGE, $page, 'modules/fuel/purchases.php') ?>
</div></div>

<?php if ($canManage): ?>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title">Record Fuel Purchase</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-6"><label class="form-label">Date</label><input type="date" name="purchase_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-6"><label class="form-label">Supplier</label><input type="text" name="supplier" class="form-control"></div>
            <div class="col-6"><label class="form-label">Quantity (L)</label><input type="number" step="0.01" min="0" name="quantity_litre" class="form-control" required></div>
            <div class="col-6"><label class="form-label">Unit Cost</label><input type="number" step="0.01" min="0" name="unit_cost" class="form-control"></div>
            <div class="col-12"><label class="form-label">Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
