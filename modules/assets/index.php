<?php
/**
 * modules/assets/index.php
 * Asset list — searchable, filterable, with expiry highlighting.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('asset.view');

$search = trim((string)input('q'));
$type   = trim((string)input('type'));
$page   = current_page();
$offset = ($page - 1) * PER_PAGE;

$conds = []; $params = [];
if ($search !== '') { $conds[] = '(asset_code LIKE ? OR asset_name LIKE ? OR registration_no LIKE ?)'; $like = "%$search%"; array_push($params, $like, $like, $like); }
if ($type !== '')   { $conds[] = 'asset_type = ?'; $params[] = $type; }
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM assets $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM assets $where ORDER BY asset_name ASC LIMIT $offset, " . PER_PAGE);
$stmt->execute($params);
$assets = $stmt->fetchAll();

$page_title = 'Assets';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Vehicles &amp; Machinery</h1>
    <div class="d-flex gap-2">
        <a href="<?= e(url('modules/assets/reminders.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bell me-1"></i> Reminders</a>
        <?php if (can('asset.manage')): ?>
        <a href="<?= e(url('modules/assets/create.php')) ?>" class="btn btn-success btn-sm"><i class="bi bi-plus-lg me-1"></i> Add Asset</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-5 col-md-4"><input type="text" name="q" class="form-control form-control-sm" placeholder="Search code, name or registration…" value="<?= e($search) ?>"></div>
            <div class="col-sm-4 col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    <?php foreach (asset_types() as $t): ?><option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
                <a href="<?= e(url('modules/assets/index.php')) ?>" class="btn btn-link btn-sm">Reset</a></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Asset</th><th>Type</th><th>Registration</th><th>Road Tax</th><th>Insurance</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($assets)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No assets found.</td></tr>
                <?php else: foreach ($assets as $a): ?>
                    <tr>
                        <td class="fw-semibold"><a href="<?= e(url('modules/assets/view.php?id=' . (int)$a['id'])) ?>" class="text-decoration-none"><?= e($a['asset_name']) ?></a>
                            <div class="text-muted small"><?= e($a['asset_code']) ?></div></td>
                        <td><?= e($a['asset_type'] ?? '—') ?></td>
                        <td class="small"><?= e($a['registration_no'] ?? '—') ?></td>
                        <td><?= expiry_badge($a['road_tax_expiry']) ?></td>
                        <td><?= expiry_badge($a['insurance_expiry']) ?></td>
                        <td><?= status_badge($a['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('modules/assets/view.php?id=' . (int)$a['id'])) ?>" class="btn btn-outline-secondary btn-sm" title="Profile"><i class="bi bi-eye"></i></a>
                            <?php if (can('asset.manage')): ?>
                            <a href="<?= e(url('modules/assets/edit.php?id=' . (int)$a['id'])) ?>" class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($total, PER_PAGE, $page, 'modules/assets/index.php') ?>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
