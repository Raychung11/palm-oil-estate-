<?php
/**
 * inc/csrf.php
 * CSRF token generation and verification.
 *
 * Usage:
 *   - In every POST form:   echo csrf_field();
 *   - At the top of every POST handler:   csrf_verify();
 *
 * Assumes a session has already been started (see inc/auth.php).
 */

/**
 * Return the current CSRF token, generating one if needed.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden input field to embed inside a form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify the submitted token on POST. Aborts the request on mismatch.
 */
function csrf_verify(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($submitted) || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(419);
        die('Invalid or expired CSRF token. Please reload the page and try again.');
    }
}
