<?php
/**
 * modules/mill/index.php
 * Mill deliveries list — date range, mill and status filters; paginated.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('mill.view');

$dateFrom = trim((string)input('date_from'));
$dateTo   = trim((string)input('date_to'));
$millId   = (int)input('mill_id');
$status   = trim((string)input('status'));
$page     = current_page();
$offset   = ($page - 1) * PER_PAGE;

$conds = []; $params = [];
if ($dateFrom !== '' && strtotime($dateFrom)) { $conds[] = 'd.delivery_date >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '' && strtotime($dateTo))     { $conds[] = 'd.delivery_date <= ?'; $params[] = $dateTo; }
if ($millId > 0) { $conds[] = 'd.mill_id = ?'; $params[] = $millId; }
if (in_array($status, ['pending', 'reconciled'], true)) { $conds[] = 'd.status = ?'; $params[] = $status; }
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM mill_deliveries d $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT d.*, m.mill_name, a.asset_name
        FROM mill_deliveries d
        LEFT JOIN mills m ON m.id = d.mill_id
        LEFT JOIN assets a ON a.id = d.vehicle_asset_id
        $where ORDER BY d.delivery_date DESC, d.id DESC LIMIT $offset, " . PER_PAGE;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deliveries = $stmt->fetchAll();

$mills = $pdo->query('SELECT id, mill_name FROM mills ORDER BY mill_name')->fetchAll();

$page_title = 'Mill Deliveries';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Mill Deliveries</h1>
    <div class="d-flex gap-2">
        <a href="<?= e(url('modules/mill/mills.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-buildings me-1"></i> Mills</a>
        <a href="<?= e(url('modules/mill/report.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clipboard-data me-1"></i> Report</a>
        <?php if (can('mill.manage')): ?>
        <a href="<?= e(url('modules/mill/create.php')) ?>" class="btn btn-success btn-sm"><i class="bi bi-plus-lg me-1"></i> New Delivery</a>
        <?php endif; ?>
    </div>
</div>

<div class="card"><div class="card-body">
    <form class="row g-2 mb-3" method="get">
        <div class="col-6 col-md-2"><label class="form-label small mb-1">From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">Mill</label>
            <select name="mill_id" class="form-select form-select-sm"><option value="">All mills</option>
            <?php foreach ($mills as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $millId === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['mill_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm"><option value="">All</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="reconciled" <?= $status === 'reconciled' ? 'selected' : '' ?>>Reconciled</option></select></div>
        <div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
            <a href="<?= e(url('modules/mill/index.php')) ?>" class="btn btn-link btn-sm">Reset</a></div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Date</th><th>Mill</th><th>Lorry</th><th class="text-end">Net (kg)</th><th class="text-end">Value</th><th class="text-end">OER</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($deliveries)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No deliveries found.</td></tr>
            <?php else: foreach ($deliveries as $d): ?>
                <tr>
                    <td class="text-nowrap"><?= e(fmt_datetime($d['delivery_date'], 'd M Y')) ?></td>
                    <td><?= e($d['mill_name'] ?? '—') ?></td>
                    <td class="small"><?= e($d['asset_name'] ?? '—') ?></td>
                    <td class="text-end"><?= num($d['net_weight_kg']) ?></td>
                    <td class="text-end"><?= num($d['total_value']) ?></td>
                    <td class="text-end"><?= $d['oer'] !== null ? num($d['oer']) . '%' : '—' ?></td>
                    <td><?= status_badge($d['status'] === 'reconciled' ? 'approved' : 'pending') ?> <span class="small text-muted"><?= e(ucfirst($d['status'])) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('modules/mill/view.php?id=' . (int)$d['id'])) ?>" class="btn btn-outline-secondary btn-sm" title="View"><i class="bi bi-eye"></i></a>
                        <?php if (can('mill.manage') && $d['status'] !== 'reconciled'): ?>
                        <a href="<?= e(url('modules/mill/edit.php?id=' . (int)$d['id'])) ?>" class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($total, PER_PAGE, $page, 'modules/mill/index.php') ?>
</div></div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
