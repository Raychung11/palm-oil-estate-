<?php
/**
 * login.php
 * Authentication entry point.
 */
require_once __DIR__ . '/inc/auth.php';

// Already signed in? Go straight to the dashboard.
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = trim((string)input('email'));
    $password = (string)input('password');

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (attempt_login($pdo, $email, $password)) {
        redirect('dashboard.php');
    } else {
        $error = 'Invalid credentials or inactive account.';
        log_activity($pdo, null, 'login_failed', 'auth', 'Failed login for ' . $email);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in &middot; <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="card auth-card shadow-lg">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="brand-mark mx-auto mb-2" style="width:52px;height:52px;font-size:1.6rem;">
                    <i class="bi bi-tree-fill"></i>
                </span>
                <h1 class="h4 mb-1" style="color:var(--brand-green);"><?= e(APP_NAME) ?></h1>
                <p class="text-muted small mb-0"><?= e(APP_TAGLINE) ?></p>
            </div>

            <?= render_flash() ?>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('login.php')) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control"
                               value="<?= e(input('email')) ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
