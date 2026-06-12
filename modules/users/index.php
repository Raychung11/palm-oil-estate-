<?php
/**
 * modules/users/index.php
 * Searchable, paginated user list.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('user.view');

$search  = trim((string)input('q'));
$page    = current_page();
$offset  = ($page - 1) * PER_PAGE;

// Build a filtered query with bound parameters.
$where  = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE u.name LIKE ? OR u.email LIKE ?';
    $like  = '%' . $search . '%';
    $params = [$like, $like];
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT u.id, u.name, u.email, u.phone, u.status, u.last_login_at, r.role_name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        $where
        ORDER BY u.name ASC
        LIMIT $offset, " . PER_PAGE;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$page_title = 'Users';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Users</h1>
    <?php if (can('user.create')): ?>
    <a href="<?= e(url('modules/users/create.php')) ?>" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Add User
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-8 col-md-5">
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Search name or email…" value="<?= e($search) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i> Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= e(url('modules/users/index.php')) ?>" class="btn btn-link btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th><th>Email</th><th>Role</th>
                        <th>Status</th><th>Last Login</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
                <?php else: foreach ($users as $u): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($u['name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['role_name']) ?></td>
                        <td><?= status_badge($u['status']) ?></td>
                        <td class="text-muted small"><?= e(fmt_datetime($u['last_login_at'])) ?></td>
                        <td class="text-end">
                            <?php if (can('user.edit')): ?>
                            <a href="<?= e(url('modules/users/edit.php?id=' . (int)$u['id'])) ?>"
                               class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($total, PER_PAGE, $page, 'modules/users/index.php') ?>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
