<?php
/**
 * modules/roles/index.php
 * List roles with user counts and permission counts.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('role.view');

$roles = $pdo->query(
    'SELECT r.id, r.role_name, r.role_slug, r.description, r.status,
            (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count,
            (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count
     FROM roles r
     ORDER BY r.id ASC'
)->fetchAll();

$page_title = 'Roles & Permissions';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Roles &amp; Permissions</h1>
    <?php if (can('role.manage')): ?>
    <a href="<?= e(url('modules/roles/create.php')) ?>" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Add Role
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Role</th><th>Description</th><th class="text-center">Users</th>
                        <th class="text-center">Permissions</th><th>Status</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($roles as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['role_name']) ?>
                            <div class="text-muted small"><?= e($r['role_slug']) ?></div>
                        </td>
                        <td class="text-muted small"><?= e($r['description'] ?? '—') ?></td>
                        <td class="text-center"><?= (int)$r['user_count'] ?></td>
                        <td class="text-center"><?= (int)$r['perm_count'] ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="text-end">
                            <?php if (can('role.manage')): ?>
                            <a href="<?= e(url('modules/roles/edit.php?id=' . (int)$r['id'])) ?>"
                               class="btn btn-outline-primary btn-sm" title="Edit permissions">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
