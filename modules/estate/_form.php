<?php
/**
 * modules/estate/_form.php
 * Shared estate create/edit form body.
 * Expects: $form (array), $errors (array), $page_title (string).
 * The form posts back to the current page.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($page_title) ?></h1>
    <a href="<?= e(url('modules/estate/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label" for="estate_name">Estate Name <span class="text-danger">*</span></label>
                <input type="text" id="estate_name" name="estate_name" class="form-control"
                       value="<?= e($form['estate_name']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="estate_code">Estate Code</label>
                <input type="text" id="estate_code" name="estate_code" class="form-control"
                       value="<?= e($form['estate_code']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active"   <?= $form['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="location">Location</label>
                <input type="text" id="location" name="location" class="form-control"
                       value="<?= e($form['location']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="total_acreage">Total Acreage</label>
                <input type="number" step="0.01" min="0" id="total_acreage" name="total_acreage"
                       class="form-control" value="<?= e($form['total_acreage']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="total_hectare">Total Hectare</label>
                <input type="number" step="0.01" min="0" id="total_hectare" name="total_hectare"
                       class="form-control" value="<?= e($form['total_hectare']) ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Save Estate</button>
            </div>
        </form>
    </div>
</div>
