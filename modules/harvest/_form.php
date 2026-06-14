<?php
/**
 * modules/harvest/_form.php
 * Shared daily-harvest entry form (create + edit).
 *
 * Expects:
 *   $form, $errors, $page_title
 *   $estates, $allDivisions, $allBlocks   (location picker datasets)
 *   $supervisors  [{id, name}]            (active users)
 *   $workers      [{id, worker_code, name, worker_type}]  (active workers)
 *   $assigned     [worker_id => ['role_in_task'=>, 'productivity_value'=>]]
 *   $existingPhotos (array, optional)     (edit only)
 *   $submitLabel
 */
$assigned = $assigned ?? [];
$existingPhotos = $existingPhotos ?? [];
$submitLabel = $submitLabel ?? 'Save Harvest';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($page_title) ?></h1>
    <a href="<?= e(url('modules/harvest/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Harvest Details</div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="harvest_date">Harvest Date <span class="text-danger">*</span></label>
                <input type="date" id="harvest_date" name="harvest_date" class="form-control"
                       value="<?= e($form['harvest_date']) ?>" max="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="collection_time">Collection Time</label>
                <input type="time" id="collection_time" name="collection_time" class="form-control"
                       value="<?= e($form['collection_time']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="supervisor_id">Supervisor</label>
                <select id="supervisor_id" name="supervisor_id" class="form-select">
                    <option value="">— None —</option>
                    <?php foreach ($supervisors as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)$form['supervisor_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php require __DIR__ . '/_location_picker.php'; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Production</div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label" for="bunches_count">Bunches</label>
                <input type="number" min="0" id="bunches_count" name="bunches_count" class="form-control"
                       value="<?= e($form['bunches_count']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="ffb_weight_kg">FFB Weight (kg)</label>
                <input type="number" step="0.01" min="0" id="ffb_weight_kg" name="ffb_weight_kg" class="form-control"
                       value="<?= e($form['ffb_weight_kg']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="loose_fruit_kg">Loose Fruit (kg)</label>
                <input type="number" step="0.01" min="0" id="loose_fruit_kg" name="loose_fruit_kg" class="form-control"
                       value="<?= e($form['loose_fruit_kg']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="rejected_bunches">Rejected Bunches</label>
                <input type="number" min="0" id="rejected_bunches" name="rejected_bunches" class="form-control"
                       value="<?= e($form['rejected_bunches']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" class="form-control" rows="2"><?= e($form['remarks']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Harvest Team</span>
            <small class="text-muted">Tick workers, set role &amp; productivity (optional)</small>
        </div>
        <div class="card-body">
            <?php if (empty($workers)): ?>
                <p class="text-muted mb-0">No active workers found. Add workers in the Workers module to assign a team.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th style="width:40px;"></th><th>Worker</th><th>Role in Task</th><th style="width:180px;">Productivity</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($workers as $w):
                        $wid = (int)$w['id'];
                        $isOn = isset($assigned[$wid]); ?>
                        <tr>
                            <td>
                                <input class="form-check-input" type="checkbox"
                                       name="worker_check[]" value="<?= $wid ?>" <?= $isOn ? 'checked' : '' ?>>
                            </td>
                            <td>
                                <?= e($w['name']) ?>
                                <div class="text-muted small"><?= e($w['worker_code']) ?><?= $w['worker_type'] ? ' · ' . e($w['worker_type']) : '' ?></div>
                            </td>
                            <td>
                                <input type="text" name="role[<?= $wid ?>]" class="form-control form-control-sm"
                                       value="<?= e($assigned[$wid]['role_in_task'] ?? ($w['worker_type'] ?? '')) ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="prod[<?= $wid ?>]"
                                       class="form-control form-control-sm"
                                       value="<?= e($assigned[$wid]['productivity_value'] ?? '') ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Field Photo</div>
        <div class="card-body">
            <?php if (!empty($existingPhotos)): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($existingPhotos as $ph): ?>
                        <a href="<?= e(UPLOAD_URL . '/' . $ph['file_path']) ?>" target="_blank">
                            <img src="<?= e(UPLOAD_URL . '/' . $ph['file_path']) ?>" alt="Field photo"
                                 style="height:80px;border-radius:6px;">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            <div class="form-text">Optional. JPG/PNG/WEBP up to 5&nbsp;MB.</div>
        </div>
    </div>

    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> <?= e($submitLabel) ?></button>
</form>
