<?php
/**
 * modules/estate/_plot_fields.php
 * Shared plot form fields for the add/edit modals.
 * Expects $p (array) with plot field values.
 */
?>
<div class="row g-3">
    <div class="col-6">
        <label class="form-label">Plot Code <span class="text-danger">*</span></label>
        <input type="text" name="plot_code" class="form-control" value="<?= e($p['plot_code']) ?>" required>
    </div>
    <div class="col-6">
        <label class="form-label">Plot Name</label>
        <input type="text" name="plot_name" class="form-control" value="<?= e($p['plot_name']) ?>">
    </div>
    <div class="col-6">
        <label class="form-label">Acreage</label>
        <input type="number" step="0.01" min="0" name="acreage" class="form-control" value="<?= e($p['acreage']) ?>">
    </div>
    <div class="col-6">
        <label class="form-label">Palm Count</label>
        <input type="number" min="0" name="palm_count" class="form-control" value="<?= e($p['palm_count']) ?>">
    </div>
    <div class="col-6">
        <label class="form-label">Planting Year</label>
        <input type="number" min="1900" max="2100" name="planting_year" class="form-control" value="<?= e($p['planting_year']) ?>">
    </div>
    <div class="col-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active"   <?= $p['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $p['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
</div>
