<?php
/**
 * modules/chemicals/_product_fields.php
 * Shared chemical product fields. Expects $row.
 */
$isNew = empty($row['id']);
?>
<div class="row g-3">
    <div class="col-8">
        <label class="form-label">Product Name <span class="text-danger">*</span></label>
        <input type="text" name="product_name" class="form-control" value="<?= e($row['product_name']) ?>" required>
    </div>
    <div class="col-4">
        <label class="form-label">Code</label>
        <input type="text" name="product_code" class="form-control" value="<?= e($row['product_code']) ?>">
    </div>
    <div class="col-6">
        <label class="form-label">Type</label>
        <input type="text" name="chemical_type" class="form-control" value="<?= e($row['chemical_type']) ?>" placeholder="Herbicide, Pesticide…">
    </div>
    <div class="col-6">
        <label class="form-label">Active Ingredient</label>
        <input type="text" name="active_ingredient" class="form-control" value="<?= e($row['active_ingredient']) ?>">
    </div>
    <div class="col-4">
        <label class="form-label">Unit</label>
        <input type="text" name="unit" class="form-control" value="<?= e($row['unit']) ?>" placeholder="litre">
    </div>
    <div class="col-4">
        <label class="form-label">Cost per Unit</label>
        <input type="number" step="0.01" min="0" name="cost_per_unit" class="form-control" value="<?= e($row['cost_per_unit']) ?>">
    </div>
    <div class="col-4">
        <label class="form-label">Minimum Stock</label>
        <input type="number" step="0.01" min="0" name="minimum_stock" class="form-control" value="<?= e($row['minimum_stock']) ?>">
    </div>
    <?php if ($isNew): ?>
    <div class="col-6">
        <label class="form-label">Opening Stock</label>
        <input type="number" step="0.01" min="0" name="current_stock" class="form-control" value="<?= e($row['current_stock']) ?>">
    </div>
    <?php endif; ?>
    <div class="col-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active"   <?= $row['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $row['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
</div>
