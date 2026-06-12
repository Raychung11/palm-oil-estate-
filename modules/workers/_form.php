<?php
/**
 * modules/workers/_form.php
 * Shared worker create/edit form body.
 * Expects: $form, $errors, $page_title.
 */
$categories = worker_categories();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($page_title) ?></h1>
    <a href="<?= e(url('modules/workers/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
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
            <div class="col-md-3">
                <label class="form-label" for="worker_code">Worker Code <span class="text-danger">*</span></label>
                <input type="text" id="worker_code" name="worker_code" class="form-control" value="<?= e($form['worker_code']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="name">Full Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($form['name']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active"   <?= $form['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="worker_type">Category</label>
                <select id="worker_type" name="worker_type" class="form-select">
                    <option value="">— Select category —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $form['worker_type'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" class="form-control" value="<?= e($form['nationality']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="phone">Phone</label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= e($form['phone']) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="ic_passport">IC / Passport No.</label>
                <input type="text" id="ic_passport" name="ic_passport" class="form-control" value="<?= e($form['ic_passport']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="permit_expiry">Permit Expiry</label>
                <input type="date" id="permit_expiry" name="permit_expiry" class="form-control" value="<?= e($form['permit_expiry']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="contract_expiry">Contract Expiry</label>
                <input type="date" id="contract_expiry" name="contract_expiry" class="form-control" value="<?= e($form['contract_expiry']) ?>">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Save Worker</button>
            </div>
        </form>
    </div>
</div>
