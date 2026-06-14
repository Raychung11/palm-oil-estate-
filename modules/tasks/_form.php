<?php
/**
 * modules/tasks/_form.php
 * Shared field-task create/edit form.
 *
 * Expects:
 *   $form, $errors, $page_title
 *   $estates, $allDivisions, $allBlocks  (location picker datasets)
 *   $supervisors  [{id, name}]
 *   $workers      [{id, worker_code, name, worker_type}]
 *   $assigned     [worker_id => ['role_in_task' => ...]]
 *   $submitLabel
 */
$assigned = $assigned ?? [];
$submitLabel = $submitLabel ?? 'Save Task';
$types = task_types();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($page_title) ?></h1>
    <a href="<?= e(url('modules/tasks/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Task Details</div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label" for="task_date">Task Date <span class="text-danger">*</span></label>
                <input type="date" id="task_date" name="task_date" class="form-control" value="<?= e($form['task_date']) ?>" required>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="task_type">Task Type <span class="text-danger">*</span></label>
                <select id="task_type" name="task_type" class="form-select" required>
                    <option value="">— Select type —</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= e($t) ?>" <?= $form['task_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="priority">Priority</label>
                <select id="priority" name="priority" class="form-select">
                    <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $form['priority'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="title">Title <span class="text-danger">*</span></label>
                <input type="text" id="title" name="title" class="form-control" value="<?= e($form['title']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="2"><?= e($form['description']) ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="assigned_to">Assigned Supervisor</label>
                <select id="assigned_to" name="assigned_to" class="form-select">
                    <option value="">— None —</option>
                    <?php foreach ($supervisors as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)$form['assigned_to'] === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Location <small class="text-muted fw-normal">(optional)</small></div>
        <div class="card-body row g-3">
            <?php $required = false; require dirname(__DIR__) . '/harvest/_location_picker.php'; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Assigned Workers</div>
        <div class="card-body">
            <?php if (empty($workers)): ?>
                <p class="text-muted mb-0">No active workers found.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th style="width:40px;"></th><th>Worker</th><th>Role in Task</th></tr></thead>
                    <tbody>
                    <?php foreach ($workers as $w):
                        $wid = (int)$w['id'];
                        $isOn = isset($assigned[$wid]); ?>
                        <tr>
                            <td><input class="form-check-input" type="checkbox" name="worker_check[]" value="<?= $wid ?>" <?= $isOn ? 'checked' : '' ?>></td>
                            <td><?= e($w['name']) ?><div class="text-muted small"><?= e($w['worker_code']) ?><?= $w['worker_type'] ? ' · ' . e($w['worker_type']) : '' ?></div></td>
                            <td><input type="text" name="role[<?= $wid ?>]" class="form-control form-control-sm" value="<?= e($assigned[$wid]['role_in_task'] ?? ($w['worker_type'] ?? '')) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> <?= e($submitLabel) ?></button>
</form>
