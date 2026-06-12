<?php
/**
 * modules/assets/_form.php
 * Shared asset create/edit form. Expects $form, $errors, $page_title.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($page_title) ?></h1>
    <a href="<?= e(url('modules/assets/index.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card"><div class="card-body">
    <form method="post" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-3"><label class="form-label" for="asset_code">Asset Code <span class="text-danger">*</span></label>
            <input type="text" id="asset_code" name="asset_code" class="form-control" value="<?= e($form['asset_code']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="asset_name">Asset Name <span class="text-danger">*</span></label>
            <input type="text" id="asset_name" name="asset_name" class="form-control" value="<?= e($form['asset_name']) ?>" required></div>
        <div class="col-md-3"><label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="active"   <?= $form['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select></div>

        <div class="col-md-4"><label class="form-label" for="asset_type">Type</label>
            <select id="asset_type" name="asset_type" class="form-select">
                <option value="">— Select —</option>
                <?php foreach (asset_types() as $t): ?><option value="<?= e($t) ?>" <?= $form['asset_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label" for="registration_no">Registration No.</label>
            <input type="text" id="registration_no" name="registration_no" class="form-control" value="<?= e($form['registration_no']) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="make_model">Make / Model</label>
            <input type="text" id="make_model" name="make_model" class="form-control" value="<?= e($form['make_model']) ?>"></div>

        <div class="col-md-3"><label class="form-label" for="purchase_date">Purchase Date</label>
            <input type="date" id="purchase_date" name="purchase_date" class="form-control" value="<?= e($form['purchase_date']) ?>"></div>
        <div class="col-md-3"><label class="form-label" for="purchase_cost">Purchase Cost</label>
            <input type="number" step="0.01" min="0" id="purchase_cost" name="purchase_cost" class="form-control" value="<?= e($form['purchase_cost']) ?>"></div>
        <div class="col-md-3"><label class="form-label" for="road_tax_expiry">Road Tax Expiry</label>
            <input type="date" id="road_tax_expiry" name="road_tax_expiry" class="form-control" value="<?= e($form['road_tax_expiry']) ?>"></div>
        <div class="col-md-3"><label class="form-label" for="insurance_expiry">Insurance Expiry</label>
            <input type="date" id="insurance_expiry" name="insurance_expiry" class="form-control" value="<?= e($form['insurance_expiry']) ?>"></div>

        <div class="col-12"><label class="form-label" for="remarks">Remarks</label>
            <input type="text" id="remarks" name="remarks" class="form-control" value="<?= e($form['remarks']) ?>"></div>

        <div class="col-12"><button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Save Asset</button></div>
    </form>
</div></div>
