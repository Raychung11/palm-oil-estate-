<?php
/**
 * modules/mill/mills.php
 * Mill master — modal create/edit.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('mill.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('mill.manage');
    $action = input('action');
    $name   = trim((string)input('mill_name'));
    $loc    = trim((string)input('location'));
    $contact= trim((string)input('contact'));
    $price  = to_decimal_or_null(input('default_price_per_tonne'));
    $status = input('status') === 'inactive' ? 'inactive' : 'active';

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM mills WHERE id = ?')->execute([(int)input('id')]);
        set_flash('success', 'Mill deleted.');
        redirect('modules/mill/mills.php');
    }
    if ($name === '') {
        set_flash('danger', 'Mill name is required.');
        redirect('modules/mill/mills.php');
    }
    if ($action === 'update') {
        $pdo->prepare('UPDATE mills SET mill_name=?, location=?, contact=?, default_price_per_tonne=?, status=?, updated_by=?, updated_at=NOW() WHERE id=?')
            ->execute([$name, $loc ?: null, $contact ?: null, $price, $status, current_user_id(), (int)input('id')]);
        set_flash('success', 'Mill updated.');
    } else {
        $pdo->prepare('INSERT INTO mills (mill_name, location, contact, default_price_per_tonne, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$name, $loc ?: null, $contact ?: null, $price, $status, current_user_id()]);
        set_flash('success', 'Mill added.');
    }
    redirect('modules/mill/mills.php');
}

$mills = $pdo->query('SELECT * FROM mills ORDER BY mill_name')->fetchAll();
$canManage = can('mill.manage');
$page_title = 'Mills';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Mills</h1>
    <div class="d-flex gap-2">
        <a href="<?= e(url('modules/mill/index.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Deliveries</a>
        <?php if ($canManage): ?>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i> Add Mill</button>
        <?php endif; ?>
    </div>
</div>

<div class="card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Mill</th><th>Location</th><th>Contact</th><th class="text-end">Default Price/Tonne</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($mills)): ?><tr><td colspan="6" class="text-center text-muted py-4">No mills yet.</td></tr>
            <?php else: foreach ($mills as $m): ?>
                <tr>
                    <td class="fw-semibold"><?= e($m['mill_name']) ?></td>
                    <td class="small"><?= e($m['location'] ?? '—') ?></td>
                    <td class="small"><?= e($m['contact'] ?? '—') ?></td>
                    <td class="text-end"><?= num($m['default_price_per_tonne']) ?></td>
                    <td><?= status_badge($m['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($canManage): ?>
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$m['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <form method="post" class="d-inline" data-confirm="Delete this mill?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($canManage): ?>
                <div class="modal fade" id="editModal<?= (int)$m['id'] ?>" tabindex="-1"><div class="modal-dialog">
                    <form method="post" class="modal-content">
                        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <div class="modal-header"><h5 class="modal-title">Edit Mill</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body"><?php $row = $m; require __DIR__ . '/_mill_fields.php'; ?></div>
                        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save</button></div>
                    </form>
                </div></div>
                <?php endif; ?>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div></div>

<?php if ($canManage): ?>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title">Add Mill</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><?php $row = ['mill_name'=>'','location'=>'','contact'=>'','default_price_per_tonne'=>'','status'=>'active']; require __DIR__ . '/_mill_fields.php'; ?></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add Mill</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
