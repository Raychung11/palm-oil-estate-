<?php
/**
 * modules/inventory/report.php
 * Inventory valuation and low-stock report.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('inventory.view');

// Valuation grouped by category.
$byCat = $pdo->query(
    "SELECT COALESCE(category, 'Uncategorised') AS category,
            COUNT(*) AS items,
            SUM(current_stock * cost_per_unit) AS value
     FROM inventory_items WHERE status = 'active'
     GROUP BY category ORDER BY value DESC"
)->fetchAll();

$totalValue = 0;
foreach ($byCat as $r) { $totalValue += (float)$r['value']; }

// Low-stock items.
$low = $pdo->query(
    "SELECT * FROM inventory_items
     WHERE status = 'active' AND current_stock <= minimum_stock
     ORDER BY (minimum_stock - current_stock) DESC"
)->fetchAll();

$page_title = 'Inventory Valuation & Low Stock';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<h1 class="h4 mb-3">Valuation &amp; Low Stock</h1>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= num($totalValue) ?></div><div class="kpi-label">Total Stock Value (RM)</div></div></div></div>
    <div class="col-6 col-md-4"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= count($low) ?></div><div class="kpi-label">Low Stock Items</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-5"><div class="card h-100">
        <div class="card-header bg-white fw-semibold">Value by Category</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Category</th><th class="text-end">Items</th><th class="text-end">Value (RM)</th></tr></thead>
            <tbody>
            <?php if (empty($byCat)): ?><tr><td colspan="3" class="text-center text-muted py-3">No items.</td></tr>
            <?php else: foreach ($byCat as $r): ?>
                <tr><td><?= e($r['category']) ?></td><td class="text-end"><?= (int)$r['items'] ?></td><td class="text-end"><?= num($r['value']) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>

    <div class="col-md-7"><div class="card h-100">
        <div class="card-header bg-white fw-semibold">Low Stock Items</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Item</th><th>Category</th><th class="text-end">Stock</th><th class="text-end">Minimum</th></tr></thead>
            <tbody>
            <?php if (empty($low)): ?><tr><td colspan="4" class="text-center text-muted py-3">No low-stock items. </td></tr>
            <?php else: foreach ($low as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['item_name']) ?></td>
                    <td class="small"><?= e($r['category'] ?? '—') ?></td>
                    <td class="text-end text-danger fw-semibold"><?= num($r['current_stock']) ?></td>
                    <td class="text-end"><?= num($r['minimum_stock']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
