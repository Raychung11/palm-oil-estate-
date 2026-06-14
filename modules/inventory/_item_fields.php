<?php
/**
 * modules/inventory/_item_fields.php
 * Shared item fields for the add/edit modals. Expects $row.
 */
$isNew = empty($row['id']);
?>
<div class="row g-3">
    <div class="col-8">
        <label class="form-label">Item Name <span class="text-danger">*</span></label>
        <input type="text" name="item_name" class="form-control" value="<?= e($row['item_name']) ?>" required>
    </div>
    <div class="col-4"><label class="form-label">Code</label><input type="text" name="item_code" class="form-control" value="<?= e($row['item_code']) ?>"></div>
    <div class="col-6">
        <label class="form-label">Category</label>
        <select name="category" class="form-select">
            <option value="">—</option>
            <?php foreach (inventory_categories() as $c): ?><option value="<?= e($c) ?>" <?= ($row['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-6"><label class="form-label">Location</label><input type="text" name="location" class="form-control" value="<?= e($row['location'] ?? '') ?>" placeholder="Main store"></div>
    <div class="col-4"><label class="form-label">Unit</label><input type="text" name="unit" class="form-control" value="<?= e($row['unit']) ?>"></div>
    <div class="col-4"><label class="form-label">Cost / Unit</label><input type="number" step="0.01" min="0" name="cost_per_unit" class="form-control" value="<?= e($row['cost_per_unit']) ?>"></div>
    <div class="col-4"><label class="form-label">Minimum Stock</label><input type="number" step="0.01" min="0" name="minimum_stock" class="form-control" value="<?= e($row['minimum_stock']) ?>"></div>
    <?php if ($isNew): ?>
    <div class="col-6"><label class="form-label">Opening Stock</label><input type="number" step="0.01" min="0" name="current_stock" class="form-control" value="<?= e($row['current_stock']) ?>"></div>
    <?php endif; ?>
    <div class="col-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active"   <?= $row['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $row['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
</div>
