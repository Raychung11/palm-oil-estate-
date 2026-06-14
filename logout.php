<?php
/**
 * logout.php
 * Ends the session and returns to the login page.
 */
require_once __DIR__ . '/inc/auth.php';

if (!empty($_SESSION['user_id'])) {
    log_activity($pdo, (int)$_SESSION['user_id'], 'logout', 'auth', 'User logged out');
}

logout_user();
session_start();
set_flash('success', 'You have been signed out.');
redirect('login.php');
