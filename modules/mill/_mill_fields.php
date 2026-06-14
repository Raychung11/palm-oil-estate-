<?php
/**
 * modules/mill/_mill_fields.php
 * Shared mill fields for the add/edit modals. Expects $row.
 */
?>
<div class="row g-3">
    <div class="col-12"><label class="form-label">Mill Name <span class="text-danger">*</span></label>
        <input type="text" name="mill_name" class="form-control" value="<?= e($row['mill_name']) ?>" required></div>
    <div class="col-12"><label class="form-label">Location</label><input type="text" name="location" class="form-control" value="<?= e($row['location'] ?? '') ?>"></div>
    <div class="col-6"><label class="form-label">Contact</label><input type="text" name="contact" class="form-control" value="<?= e($row['contact'] ?? '') ?>"></div>
    <div class="col-6"><label class="form-label">Default Price / Tonne</label><input type="number" step="0.01" min="0" name="default_price_per_tonne" class="form-control" value="<?= e($row['default_price_per_tonne'] ?? '') ?>"></div>
    <div class="col-6"><label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active"   <?= $row['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $row['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select></div>
</div>
