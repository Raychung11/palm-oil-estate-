<?php
/**
 * modules/users/edit.php
 * Edit an existing user, optionally reset password, and toggle status.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('user.edit');

$id = (int)input('id');
if ($id <= 0) {
    set_flash('danger', 'Invalid user.');
    redirect('modules/users/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) {
    set_flash('danger', 'User not found.');
    redirect('modules/users/index.php');
}

$roles = $pdo->query("SELECT id, role_name FROM roles WHERE status = 'active' ORDER BY role_name")->fetchAll();

$errors = [];
$form = [
    'name'    => $user['name'],
    'email'   => $user['email'],
    'phone'   => $user['phone'],
    'role_id' => (int)$user['role_id'],
    'status'  => $user['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form['name']    = trim((string)input('name'));
    $form['email']   = trim((string)input('email'));
    $form['phone']   = trim((string)input('phone'));
    $form['role_id'] = (int)input('role_id');
    $form['status']  = input('status') === 'inactive' ? 'inactive' : 'active';
    $password        = (string)input('password');
    $confirm         = (string)input('password_confirm');

    if ($form['name'] === '')                               $errors[] = 'Name is required.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($form['role_id'] <= 0)                              $errors[] = 'Please select a role.';
    if ($password !== '' && strlen($password) < 8)          $errors[] = 'Password must be at least 8 characters.';
    if ($password !== '' && $password !== $confirm)         $errors[] = 'Passwords do not match.';

    // Guard against locking out the last Super Admin / yourself.
    if ($id === current_user_id() && $form['status'] === 'inactive') {
        $errors[] = 'You cannot deactivate your own account.';
    }

    if (!$errors) {
        $dupe = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $dupe->execute([$form['email'], $id]);
        if ($dupe->fetchColumn()) {
            $errors[] = 'That email is already used by another user.';
        }
    }

    if (!$errors) {
        if ($password !== '') {
            $sql = 'UPDATE users SET name=?, email=?, phone=?, role_id=?, status=?,
                        password_hash=?, updated_by=?, updated_at=NOW() WHERE id=?';
            $args = [$form['name'], $form['email'], $form['phone'] ?: null, $form['role_id'],
                     $form['status'], password_hash($password, PASSWORD_DEFAULT), current_user_id(), $id];
        } else {
            $sql = 'UPDATE users SET name=?, email=?, phone=?, role_id=?, status=?,
                        updated_by=?, updated_at=NOW() WHERE id=?';
            $args = [$form['name'], $form['email'], $form['phone'] ?: null, $form['role_id'],
                     $form['status'], current_user_id(), $id];
        }
        $pdo->prepare($sql)->execute($args);
        log_activity($pdo, current_user_id(), 'update', 'users', 'Updated user ' . $form['email']);
        set_flash('success', 'User updated successfully.');
        redirect('modules/users/index.php');
    }
}

$page_title = 'Edit User';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Edit User</h1>
    <a href="<?= e(url('modules/users/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
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
        <form method="post" autocomplete="off" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label" for="name">Full Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($form['name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($form['email']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= e($form['phone']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="role_id">Role <span class="text-danger">*</span></label>
                <select id="role_id" name="role_id" class="form-select" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= $form['role_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                            <?= e($r['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active"   <?= $form['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12"><hr class="text-muted"><small class="text-muted">Leave password fields blank to keep the current password.</small></div>
            <div class="col-md-6">
                <label class="form-label" for="password">New Password</label>
                <input type="password" id="password" name="password" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password_confirm">Confirm New Password</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
