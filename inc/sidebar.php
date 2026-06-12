<?php
/**
 * inc/sidebar.php
 * Left navigation menu. Items are only shown when the current user
 * holds the relevant permission. Modules not yet built are marked
 * as upcoming so the structure mirrors the blueprint.
 */

// Resolve the active menu key from the current script path.
$current = $_SERVER['SCRIPT_NAME'] ?? '';
$is = function (string $needle) use ($current): string {
    return str_contains($current, $needle) ? ' active' : '';
};
?>
<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-brand px-3 py-3">
        <a href="<?= e(url('dashboard.php')) ?>" class="text-decoration-none">
            <span class="brand-mark"><i class="bi bi-tree-fill"></i></span>
            <span class="brand-text">
                <strong><?= e(APP_NAME) ?></strong>
                <small class="d-block text-muted"><?= e(APP_TAGLINE) ?></small>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link<?= $is('dashboard.php') ?>" href="<?= e(url('dashboard.php')) ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>

            <li class="nav-section">Administration</li>

            <?php if (can('user.view')): ?>
            <li class="nav-item">
                <a class="nav-link<?= $is('/users/') ?>" href="<?= e(url('modules/users/index.php')) ?>">
                    <i class="bi bi-people"></i> Users
                </a>
            </li>
            <?php endif; ?>

            <?php if (can('role.view')): ?>
            <li class="nav-item">
                <a class="nav-link<?= $is('/roles/') ?>" href="<?= e(url('modules/roles/index.php')) ?>">
                    <i class="bi bi-shield-lock"></i> Roles &amp; Permissions
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-section">Operations</li>
            <li class="nav-item"><span class="nav-link disabled"><i class="bi bi-geo-alt"></i> Estate Setup <span class="badge bg-light text-muted ms-1">Soon</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="bi bi-basket"></i> Harvest &amp; FFB <span class="badge bg-light text-muted ms-1">Soon</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="bi bi-person-badge"></i> Workers <span class="badge bg-light text-muted ms-1">Soon</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="bi bi-box-seam"></i> Inventory <span class="badge bg-light text-muted ms-1">Soon</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="bi bi-truck"></i> Mill Delivery <span class="badge bg-light text-muted ms-1">Soon</span></span></li>

            <li class="nav-section">Insight</li>
            <li class="nav-item"><span class="nav-link disabled"><i class="bi bi-graph-up"></i> Costing <span class="badge bg-light text-muted ms-1">Soon</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="bi bi-robot"></i> AI Assistant <span class="badge bg-light text-muted ms-1">Soon</span></span></li>
        </ul>
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
