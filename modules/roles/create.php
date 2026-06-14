<?php
/**
 * modules/roles/create.php
 * Create a new role.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('role.manage');

$errors = [];
$form = ['role_name' => '', 'description' => '', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form['role_name']   = trim((string)input('role_name'));
    $form['description'] = trim((string)input('description'));
    $form['status']      = input('status') === 'inactive' ? 'inactive' : 'active';

    if ($form['role_name'] === '') {
        $errors[] = 'Role name is required.';
    }

    // Generate a unique slug from the name.
    $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($form['role_name']));
    $slug = trim($slug, '_');

    if (!$errors) {
        $dupe = $pdo->prepare('SELECT 1 FROM roles WHERE role_slug = ? LIMIT 1');
        $dupe->execute([$slug]);
        if ($dupe->fetchColumn()) {
            $errors[] = 'A role with a similar name already exists.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO roles (role_name, role_slug, description, status, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$form['role_name'], $slug, $form['description'] ?: null, $form['status']]);
        $roleId = (int)$pdo->lastInsertId();
        log_activity($pdo, current_user_id(), 'create', 'roles', 'Created role ' . $form['role_name']);
        set_flash('success', 'Role created. Now assign its permissions.');
        redirect('modules/roles/edit.php?id=' . $roleId);
    }
}

$page_title = 'Add Role';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Add Role</h1>
    <a href="<?= e(url('modules/roles/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label" for="role_name">Role Name <span class="text-danger">*</span></label>
                <input type="text" id="role_name" name="role_name" class="form-control" value="<?= e($form['role_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <input type="text" id="description" name="description" class="form-control" value="<?= e($form['description']) ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Create Role</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
