<?php
/**
 * inc/auth.php
 * Bootstraps the request: config, session, database, helpers and
 * the authentication / authorization helpers used across the app.
 *
 * Include this at the very top of every protected page:
 *   require_once __DIR__ . '/../inc/auth.php';   // (adjust depth)
 *   require_login();
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/inc/functions.php';
require_once dirname(__DIR__) . '/inc/csrf.php';
require_once dirname(__DIR__) . '/inc/audit.php';

// --- Secure session start -------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

/**
 * Attempt to log a user in with email + password.
 * Returns true on success, false on failure.
 */
function attempt_login(PDO $pdo, string $email, string $password): bool
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role_id, status
         FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        return false;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Transparently upgrade legacy hashes.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$newHash, $user['id']]);
    }

    // Prevent session fixation.
    session_regenerate_id(true);

    $_SESSION['user_id']      = (int)$user['id'];
    $_SESSION['user_name']    = $user['name'];
    $_SESSION['user_email']   = $user['email'];
    $_SESSION['role_id']      = (int)$user['role_id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['permissions']  = load_permissions($pdo, (int)$user['role_id']);

    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
        ->execute([$user['id']]);

    log_activity($pdo, (int)$user['id'], 'login', 'auth', 'User logged in');
    return true;
}

/**
 * Load the set of permission keys granted to a role.
 *
 * @return string[]
 */
function load_permissions(PDO $pdo, int $roleId): array
{
    $stmt = $pdo->prepare(
        'SELECT p.permission_key
         FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = ?'
    );
    $stmt->execute([$roleId]);
    return array_column($stmt->fetchAll(), 'permission_key');
}

/**
 * Is there an authenticated, non-expired session?
 */
function is_logged_in(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    // Idle timeout.
    if (isset($_SESSION['last_activity'])
        && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        logout_user();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Require an authenticated session or bounce to the login page.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }
}

/**
 * Does the current user hold the given permission?
 * Super Admin (role_id = 1) is granted everything.
 */
function can(string $permission): bool
{
    if (($_SESSION['role_id'] ?? 0) === 1) {
        return true;
    }
    return in_array($permission, $_SESSION['permissions'] ?? [], true);
}

/**
 * Require a permission or stop with a 403.
 */
function require_permission(string $permission): void
{
    require_login();
    if (!can($permission)) {
        http_response_code(403);
        die('403 Forbidden — you do not have permission to access this page.');
    }
}

/**
 * Current user id, or null.
 */
function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Destroy the session and log the user out.
 */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
