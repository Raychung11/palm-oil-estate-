<?php
/**
 * modules/users/create.php
 * Create a new user account.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('user.create');

$roles = $pdo->query("SELECT id, role_name FROM roles WHERE status = 'active' ORDER BY role_name")->fetchAll();

$errors = [];
$form = ['name' => '', 'email' => '', 'phone' => '', 'role_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form['name']    = trim((string)input('name'));
    $form['email']   = trim((string)input('email'));
    $form['phone']   = trim((string)input('phone'));
    $form['role_id'] = (int)input('role_id');
    $password        = (string)input('password');
    $confirm         = (string)input('password_confirm');

    if ($form['name'] === '')                      $errors[] = 'Name is required.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($form['role_id'] <= 0)                      $errors[] = 'Please select a role.';
    if (strlen($password) < 8)                      $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                     $errors[] = 'Passwords do not match.';

    if (!$errors) {
        // Unique email check.
        $dupe = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $dupe->execute([$form['email']]);
        if ($dupe->fetchColumn()) {
            $errors[] = 'That email is already registered.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, phone, password_hash, role_id, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $form['name'],
            $form['email'],
            $form['phone'] ?: null,
            password_hash($password, PASSWORD_DEFAULT),
            $form['role_id'],
            input('status') === 'inactive' ? 'inactive' : 'active',
            current_user_id(),
        ]);
        log_activity($pdo, current_user_id(), 'create', 'users', 'Created user ' . $form['email']);
        set_flash('success', 'User created successfully.');
        redirect('modules/users/index.php');
    }
}

$page_title = 'Add User';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Add User</h1>
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
                    <option value="">— Select role —</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= (int)$form['role_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                            <?= e($r['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                <input type="password" id="password" name="password" class="form-control" required>
                <div class="form-text">Minimum 8 characters.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password_confirm">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
