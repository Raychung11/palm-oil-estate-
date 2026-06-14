<?php
/**
 * index.php
 * Landing route — logged-in users go to the dashboard; guests see the
 * public marketing landing page.
 */
require_once __DIR__ . '/inc/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

// Public homepage for visitors who are not signed in.
require __DIR__ . '/landing.php';
