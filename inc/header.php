<?php
/**
 * inc/header.php
 * Opens the HTML document and the admin layout shell.
 *
 * Expects $page_title to optionally be set before inclusion.
 * Must be included after inc/auth.php + require_login().
 */
$page_title = $page_title ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> &middot; <?= e(APP_NAME) ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="app-main">
        <nav class="navbar navbar-expand bg-white border-bottom px-3 py-2 sticky-top">
            <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle" type="button">
                <i class="bi bi-list"></i>
            </button>
            <span class="navbar-brand mb-0 h6 ms-2 d-none d-sm-inline"><?= e($page_title) ?></span>
            <div class="ms-auto dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle"
                   data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle fs-5 me-1"></i>
                    <span class="d-none d-sm-inline"><?= e($_SESSION['user_name'] ?? 'User') ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted"><?= e($_SESSION['user_email'] ?? '') ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= e(url('logout.php')) ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </nav>

        <main class="app-content p-3 p-md-4">
            <?= render_flash() ?>
