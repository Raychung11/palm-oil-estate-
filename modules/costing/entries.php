<?php
/**
 * modules/costing/entries.php
 * Manual cost and revenue entries (labour, overheads, ad-hoc income).
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('costing.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = input('action');

    if ($action === 'add_cost') {
        $pdo->prepare('INSERT INTO estate_cost_entries (entry_date, category, block_id, amount, description, created_by, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, NOW())')
            ->execute([trim((string)input('entry_date')) ?: date('Y-m-d'), trim((string)input('category')) ?: 'General',
                to_int_or_null(input('block_id')), (float)input('amount'), trim((string)input('description')) ?: null, current_user_id()]);
        set_flash('success', 'Cost entry added.');
    } elseif ($action === 'delete_cost') {
        $pdo->prepare('DELETE FROM estate_cost_entries WHERE id = ?')->execute([(int)input('id')]);
        set_flash('success', 'Cost entry deleted.');
    } elseif ($action === 'add_revenue') {
        $pdo->prepare('INSERT INTO estate_revenue_entries (entry_date, source, block_id, amount, description, created_by, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, NOW())')
            ->execute([trim((string)input('entry_date')) ?: date('Y-m-d'), trim((string)input('source')) ?: 'Other',
                to_int_or_null(input('block_id')), (float)input('amount'), trim((string)input('description')) ?: null, current_user_id()]);
        set_flash('success', 'Revenue entry added.');
    } elseif ($action === 'delete_revenue') {
        $pdo->prepare('DELETE FROM estate_revenue_entries WHERE id = ?')->execute([(int)input('id')]);
        set_flash('success', 'Revenue entry deleted.');
    }
    redirect('modules/costing/entries.php');
}

$blocks = $pdo->query('SELECT id, block_code FROM blocks ORDER BY block_code')->fetchAll();
$costs = $pdo->query('SELECT c.*, b.block_code FROM estate_cost_entries c LEFT JOIN blocks b ON b.id = c.block_id ORDER BY c.entry_date DESC, c.id DESC LIMIT 100')->fetchAll();
$revs  = $pdo->query('SELECT r.*, b.block_code FROM estate_revenue_entries r LEFT JOIN blocks b ON b.id = r.block_id ORDER BY r.entry_date DESC, r.id DESC LIMIT 100')->fetchAll();

$page_title = 'Cost & Revenue Entries';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Cost &amp; Revenue Entries</h1>
    <a href="<?= e(url('modules/costing/index.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
</div>

<div class="row g-3">
    <!-- Cost entries -->
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Manual Costs</span>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#costModal"><i class="bi bi-plus-lg"></i> Add</button>
        </div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Date</th><th>Category</th><th>Block</th><th class="text-end">Amount</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($costs)): ?><tr><td colspan="5" class="text-center text-muted py-3">No cost entries.</td></tr>
            <?php else: foreach ($costs as $c): ?>
                <tr><td class="text-nowrap small"><?= e(fmt_datetime($c['entry_date'], 'd M Y')) ?></td>
                    <td><?= e($c['category']) ?></td><td><?= e($c['block_code'] ?? '—') ?></td>
                    <td class="text-end"><?= num($c['amount']) ?></td>
                    <td class="text-end"><form method="post" class="d-inline" data-confirm="Delete entry?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_cost"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>

    <!-- Revenue entries -->
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Manual Revenue</span>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#revModal"><i class="bi bi-plus-lg"></i> Add</button>
        </div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Date</th><th>Source</th><th>Block</th><th class="text-end">Amount</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($revs)): ?><tr><td colspan="5" class="text-center text-muted py-3">No revenue entries.</td></tr>
            <?php else: foreach ($revs as $r): ?>
                <tr><td class="text-nowrap small"><?= e(fmt_datetime($r['entry_date'], 'd M Y')) ?></td>
                    <td><?= e($r['source']) ?></td><td><?= e($r['block_code'] ?? '—') ?></td>
                    <td class="text-end"><?= num($r['amount']) ?></td>
                    <td class="text-end"><form method="post" class="d-inline" data-confirm="Delete entry?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_revenue"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>
</div>

<!-- Cost modal -->
<div class="modal fade" id="costModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_cost">
        <div class="modal-header"><h5 class="modal-title">Add Cost Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-6"><label class="form-label">Date</label><input type="date" name="entry_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-6"><label class="form-label">Category</label><select name="category" class="form-select"><?php foreach (cost_categories() as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Block (optional)</label><select name="block_id" class="form-select"><option value="">Estate-wide</option><?php foreach ($blocks as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['block_code']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Amount (RM)</label><input type="number" step="0.01" min="0" name="amount" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Description</label><input type="text" name="description" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add</button></div>
    </form>
</div></div>

<!-- Revenue modal -->
<div class="modal fade" id="revModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_revenue">
        <div class="modal-header"><h5 class="modal-title">Add Revenue Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-6"><label class="form-label">Date</label><input type="date" name="entry_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-6"><label class="form-label">Source</label><input type="text" name="source" class="form-control" placeholder="Kernel, scrap…"></div>
            <div class="col-6"><label class="form-label">Block (optional)</label><select name="block_id" class="form-select"><option value="">Estate-wide</option><?php foreach ($blocks as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['block_code']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Amount (RM)</label><input type="number" step="0.01" min="0" name="amount" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Description</label><input type="text" name="description" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add</button></div>
    </form>
</div></div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
