<?php
/**
 * index.php
 * Landing route — send users to the dashboard or login.
 */
require_once __DIR__ . '/inc/auth.php';

redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
