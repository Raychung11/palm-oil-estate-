<?php
/**
 * modules/roles/edit.php
 * Edit role details and manage its permission matrix.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('role.manage');

$id = (int)input('id');
if ($id <= 0) {
    set_flash('danger', 'Invalid role.');
    redirect('modules/roles/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$role = $stmt->fetch();
if (!$role) {
    set_flash('danger', 'Role not found.');
    redirect('modules/roles/index.php');
}

// Permission groups defined in config; actual ids come from the DB.
$groups = require dirname(__DIR__, 2) . '/config/permissions.php';

$allPerms = $pdo->query('SELECT id, permission_key FROM permissions')->fetchAll();
$permIdByKey = array_column($allPerms, 'id', 'permission_key');

$errors = [];
$form = [
    'role_name'   => $role['role_name'],
    'description' => $role['description'],
    'status'      => $role['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form['role_name']   = trim((string)input('role_name'));
    $form['description'] = trim((string)input('description'));
    $form['status']      = input('status') === 'inactive' ? 'inactive' : 'active';
    $selectedKeys        = (array)($_POST['permissions'] ?? []);

    if ($form['role_name'] === '') {
        $errors[] = 'Role name is required.';
    }

    if (!$errors) {
        // Update role details.
        $pdo->prepare('UPDATE roles SET role_name=?, description=?, status=?, updated_at=NOW() WHERE id=?')
            ->execute([$form['role_name'], $form['description'] ?: null, $form['status'], $id]);

        // Rebuild the permission matrix for this role inside a transaction.
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())');
            foreach ($selectedKeys as $key) {
                if (isset($permIdByKey[$key])) {
                    $ins->execute([$id, $permIdByKey[$key]]);
                }
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        log_activity($pdo, current_user_id(), 'update', 'roles', 'Updated role ' . $form['role_name']);
        set_flash('success', 'Role and permissions updated.');
        redirect('modules/roles/index.php');
    }
}

// Currently granted permission keys.
$grantedStmt = $pdo->prepare(
    'SELECT p.permission_key FROM role_permissions rp
     JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?'
);
$grantedStmt->execute([$id]);
$granted = array_column($grantedStmt->fetchAll(), 'permission_key');

$page_title = 'Edit Role';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Edit Role</h1>
    <a href="<?= e(url('modules/roles/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label" for="role_name">Role Name <span class="text-danger">*</span></label>
                <input type="text" id="role_name" name="role_name" class="form-control" value="<?= e($form['role_name']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="description">Description</label>
                <input type="text" id="description" name="description" class="form-control" value="<?= e($form['description']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active"   <?= $form['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Permission Matrix</span>
            <?php if ($role['role_slug'] === 'super_admin'): ?>
                <span class="badge bg-success">Super Admin always has full access</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($groups as $groupName => $perms): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2"><?= e($groupName) ?></div>
                        <?php foreach ($perms as $key => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="permissions[]" value="<?= e($key) ?>"
                                   id="perm_<?= e($key) ?>"
                                   <?= in_array($key, $granted, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="perm_<?= e($key) ?>">
                                <?= e($label) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Save Role</button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
