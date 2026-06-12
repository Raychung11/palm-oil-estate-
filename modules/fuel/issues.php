<?php
/**
 * modules/fuel/issues.php
 * Fuel issued to assets — deducts the tank balance and costs at the
 * weighted-average purchase price.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once dirname(__DIR__) . '/harvest/_logic.php';
require_permission('asset.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('asset.manage');
    $action = input('action');

    if ($action === 'delete') {
        $iid = (int)input('id');
        $litres = (float)($pdo->query('SELECT fuel_litre FROM fuel_issues WHERE id = ' . $iid)->fetchColumn() ?: 0);
        $pdo->prepare('DELETE FROM fuel_issues WHERE id = ?')->execute([$iid]);
        $pdo->prepare('UPDATE fuel_tank_balance SET current_litres = current_litres + ?, updated_at = NOW() WHERE id = 1')->execute([$litres]);
        set_flash('success', 'Issue deleted.');
        redirect('modules/fuel/issues.php');
    }

    $date    = trim((string)input('issue_date')) ?: date('Y-m-d');
    $assetId = to_int_or_null(input('asset_id'));
    $driver  = to_int_or_null(input('driver_worker_id'));
    $blockId = to_int_or_null(input('block_id'));
    $litres  = (float)input('fuel_litre');
    if ($litres <= 0) {
        set_flash('danger', 'Fuel quantity must be greater than zero.');
        redirect('modules/fuel/issues.php');
    }

    // Weighted-average cost of fuel purchased to date.
    $avg = $pdo->query('SELECT COALESCE(SUM(total_cost),0) / NULLIF(SUM(quantity_litre),0) FROM fuel_purchases')->fetchColumn();
    $unit = (float)($avg ?: 0);
    $total = $unit * $litres;

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO fuel_issues (issue_date, asset_id, driver_worker_id, block_id, fuel_litre, unit_cost, total_cost, mileage_reading, hour_meter, remarks, created_by, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$date, $assetId, $driver, $blockId, $litres, $unit, $total,
                to_decimal_or_null(input('mileage_reading')), to_decimal_or_null(input('hour_meter')),
                trim((string)input('remarks')) ?: null, current_user_id()]);
        $pdo->prepare('UPDATE fuel_tank_balance SET current_litres = current_litres - ?, updated_at = NOW() WHERE id = 1')->execute([$litres]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('danger', 'Could not record the issue.');
        redirect('modules/fuel/issues.php');
    }
    log_activity($pdo, current_user_id(), 'create', 'fuel', "Fuel issue $litres L");
    set_flash('success', 'Fuel issue recorded.');
    redirect('modules/fuel/issues.php');
}

$assets  = $pdo->query("SELECT id, asset_code, asset_name FROM assets WHERE status = 'active' ORDER BY asset_name")->fetchAll();
$drivers = $pdo->query("SELECT id, name FROM workers WHERE status = 'active' ORDER BY name")->fetchAll();
$blocks  = $pdo->query('SELECT id, block_code FROM blocks ORDER BY block_code')->fetchAll();

$page = current_page();
$offset = ($page - 1) * PER_PAGE;
$total = (int)$pdo->query('SELECT COUNT(*) FROM fuel_issues')->fetchColumn();
$rows = $pdo->query(
    "SELECT i.*, a.asset_name, a.asset_code, w.name AS driver_name, b.block_code
     FROM fuel_issues i
     LEFT JOIN assets a ON a.id = i.asset_id
     LEFT JOIN workers w ON w.id = i.driver_worker_id
     LEFT JOIN blocks b ON b.id = i.block_id
     ORDER BY i.issue_date DESC, i.id DESC LIMIT $offset, " . PER_PAGE
)->fetchAll();

$canManage = can('asset.manage');
$page_title = 'Fuel Issues';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Issues</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i> Issue Fuel</button>
    <?php endif; ?>
</div>

<div class="card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Date</th><th>Asset</th><th>Driver</th><th>Block</th><th class="text-end">Litres</th><th class="text-end">Cost</th><th class="text-end">Meter</th><th class="text-end"></th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">No fuel issues yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="text-nowrap"><?= e(fmt_datetime($r['issue_date'], 'd M Y')) ?></td>
                    <td><?= e($r['asset_name'] ?? '—') ?></td>
                    <td class="small"><?= e($r['driver_name'] ?? '—') ?></td>
                    <td><?= e($r['block_code'] ?? '—') ?></td>
                    <td class="text-end"><?= num($r['fuel_litre']) ?></td>
                    <td class="text-end"><?= num($r['total_cost']) ?></td>
                    <td class="text-end small"><?= $r['mileage_reading'] !== null ? num($r['mileage_reading']) . ' km' : ($r['hour_meter'] !== null ? num($r['hour_meter']) . ' h' : '—') ?></td>
                    <td class="text-end"><?php if ($canManage): ?><form method="post" class="d-inline" data-confirm="Delete issue and restore tank balance?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($total, PER_PAGE, $page, 'modules/fuel/issues.php') ?>
</div></div>

<?php if ($canManage): ?>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title">Issue Fuel</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-6"><label class="form-label">Date</label><input type="date" name="issue_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-6"><label class="form-label">Asset</label><select name="asset_id" class="form-select"><option value="">—</option><?php foreach ($assets as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['asset_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Driver</label><select name="driver_worker_id" class="form-select"><option value="">—</option><?php foreach ($drivers as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Block / Operation</label><select name="block_id" class="form-select"><option value="">—</option><?php foreach ($blocks as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['block_code']) ?></option><?php endforeach; ?></select></div>
            <div class="col-4"><label class="form-label">Litres</label><input type="number" step="0.01" min="0" name="fuel_litre" class="form-control" required></div>
            <div class="col-4"><label class="form-label">Mileage (km)</label><input type="number" step="0.01" name="mileage_reading" class="form-control"></div>
            <div class="col-4"><label class="form-label">Hour Meter</label><input type="number" step="0.01" name="hour_meter" class="form-control"></div>
            <div class="col-12"><label class="form-label">Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
