<?php
/**
 * modules/assets/view.php
 * Asset profile: details, maintenance, usage, documents and fuel summary.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('asset.view');

$id = (int)input('id');
if ($id <= 0) { set_flash('danger', 'Invalid asset.'); redirect('modules/assets/index.php'); }

$stmt = $pdo->prepare('SELECT * FROM assets WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$asset = $stmt->fetch();
if (!$asset) { set_flash('danger', 'Asset not found.'); redirect('modules/assets/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('asset.manage');
    $action = input('action');
    try {
        if ($action === 'add_maintenance') {
            $stored = handle_upload($_FILES['file'] ?? [], 'assets');
            $pdo->prepare('INSERT INTO asset_maintenance_logs (asset_id, service_date, maintenance_type, description, cost, odometer, next_service_date, file_path, created_by, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$id, trim((string)input('service_date')) ?: date('Y-m-d'),
                    in_array(input('maintenance_type'), ['service','repair','breakdown'], true) ? input('maintenance_type') : 'service',
                    trim((string)input('description')) ?: null, (float)(input('cost') ?: 0),
                    to_decimal_or_null(input('odometer')), trim((string)input('next_service_date')) ?: null, $stored, current_user_id()]);
            set_flash('success', 'Maintenance log added.');
        } elseif ($action === 'delete_maintenance') {
            $pdo->prepare('DELETE FROM asset_maintenance_logs WHERE id = ? AND asset_id = ?')->execute([(int)input('log_id'), $id]);
            set_flash('success', 'Maintenance log deleted.');
        } elseif ($action === 'add_usage') {
            $start = to_decimal_or_null(input('start_meter'));
            $end   = to_decimal_or_null(input('end_meter'));
            $hours = to_decimal_or_null(input('hours_used'));
            $pdo->prepare('INSERT INTO asset_usage_logs (asset_id, usage_date, driver_worker_id, block_id, purpose, start_meter, end_meter, hours_used, created_by, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$id, trim((string)input('usage_date')) ?: date('Y-m-d'), to_int_or_null(input('driver_worker_id')),
                    to_int_or_null(input('block_id')), trim((string)input('purpose')) ?: null, $start, $end, $hours, current_user_id()]);
            set_flash('success', 'Usage log added.');
        } elseif ($action === 'delete_usage') {
            $pdo->prepare('DELETE FROM asset_usage_logs WHERE id = ? AND asset_id = ?')->execute([(int)input('log_id'), $id]);
            set_flash('success', 'Usage log deleted.');
        } elseif ($action === 'add_document') {
            $stored = handle_upload($_FILES['file'] ?? [], 'assets');
            $pdo->prepare('INSERT INTO asset_documents (asset_id, doc_type, doc_number, expiry_date, file_path, created_by, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$id, trim((string)input('doc_type')) ?: 'Document', trim((string)input('doc_number')) ?: null,
                    trim((string)input('expiry_date')) ?: null, $stored, current_user_id()]);
            set_flash('success', 'Document added.');
        } elseif ($action === 'delete_document') {
            $pdo->prepare('DELETE FROM asset_documents WHERE id = ? AND asset_id = ?')->execute([(int)input('doc_id'), $id]);
            set_flash('success', 'Document deleted.');
        }
    } catch (RuntimeException $ex) {
        set_flash('danger', $ex->getMessage());
    }
    log_activity($pdo, current_user_id(), $action, 'assets', 'Asset #' . $id);
    redirect('modules/assets/view.php?id=' . $id);
}

$maint = $pdo->prepare('SELECT * FROM asset_maintenance_logs WHERE asset_id = ? ORDER BY service_date DESC');
$maint->execute([$id]); $maint = $maint->fetchAll();
$usage = $pdo->prepare('SELECT u.*, w.name AS driver_name, b.block_code FROM asset_usage_logs u LEFT JOIN workers w ON w.id = u.driver_worker_id LEFT JOIN blocks b ON b.id = u.block_id WHERE u.asset_id = ? ORDER BY u.usage_date DESC LIMIT 20');
$usage->execute([$id]); $usage = $usage->fetchAll();
$docs = $pdo->prepare('SELECT * FROM asset_documents WHERE asset_id = ? ORDER BY expiry_date IS NULL, expiry_date ASC');
$docs->execute([$id]); $docs = $docs->fetchAll();

$fuelStmt = $pdo->prepare('SELECT COALESCE(SUM(fuel_litre),0) AS litres, COALESCE(SUM(total_cost),0) AS cost, COUNT(*) AS issues FROM fuel_issues WHERE asset_id = ?');
$fuelStmt->execute([$id]); $fuel = $fuelStmt->fetch();
$maintCost = (float)$pdo->query('SELECT COALESCE(SUM(cost),0) FROM asset_maintenance_logs WHERE asset_id = ' . $id)->fetchColumn();

$drivers = $pdo->query("SELECT id, name FROM workers WHERE status = 'active' ORDER BY name")->fetchAll();
$blocks  = $pdo->query('SELECT id, block_code FROM blocks ORDER BY block_code')->fetchAll();

$canManage = can('asset.manage');
$page_title = $asset['asset_name'];
require __DIR__ . '/../../inc/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?= e(url('modules/assets/index.php')) ?>">Assets</a></li>
    <li class="breadcrumb-item active"><?= e($asset['asset_name']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0"><?= e($asset['asset_name']) ?> <?= status_badge($asset['status']) ?></h1>
    <?php if ($canManage): ?>
    <a href="<?= e(url('modules/assets/edit.php?id=' . $id)) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header bg-white fw-semibold">Details</div><div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted">Code</dt><dd class="col-7"><?= e($asset['asset_code']) ?></dd>
                <dt class="col-5 text-muted">Type</dt><dd class="col-7"><?= e($asset['asset_type'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Registration</dt><dd class="col-7"><?= e($asset['registration_no'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Make / Model</dt><dd class="col-7"><?= e($asset['make_model'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Purchase Date</dt><dd class="col-7"><?= e(fmt_datetime($asset['purchase_date'], 'd M Y')) ?></dd>
                <dt class="col-5 text-muted">Purchase Cost</dt><dd class="col-7"><?= num($asset['purchase_cost']) ?></dd>
                <dt class="col-5 text-muted">Road Tax</dt><dd class="col-7"><?= expiry_badge($asset['road_tax_expiry']) ?></dd>
                <dt class="col-5 text-muted">Insurance</dt><dd class="col-7"><?= expiry_badge($asset['insurance_expiry']) ?></dd>
            </dl>
        </div></div>

        <div class="card mb-3"><div class="card-header bg-white fw-semibold">Cost Summary</div><div class="card-body">
            <div class="d-flex justify-content-between small py-1"><span class="text-muted">Fuel issued</span><strong><?= num($fuel['litres']) ?> L</strong></div>
            <div class="d-flex justify-content-between small py-1"><span class="text-muted">Fuel cost</span><strong><?= num($fuel['cost']) ?></strong></div>
            <div class="d-flex justify-content-between small py-1"><span class="text-muted">Maintenance cost</span><strong><?= num($maintCost) ?></strong></div>
        </div></div>
    </div>

    <div class="col-lg-8">
        <!-- Maintenance -->
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Maintenance &amp; Repairs</span>
                <?php if ($canManage): ?><button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#maintModal"><i class="bi bi-plus-lg"></i> Add</button><?php endif; ?>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>Type</th><th>Description</th><th class="text-end">Cost</th><th>Next Service</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($maint)): ?><tr><td colspan="6" class="text-center text-muted py-3">No maintenance logs.</td></tr>
                <?php else: foreach ($maint as $m): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fmt_datetime($m['service_date'], 'd M Y')) ?></td>
                        <td><span class="badge bg-<?= ['service'=>'info','repair'=>'warning text-dark','breakdown'=>'danger'][$m['maintenance_type']] ?? 'secondary' ?>"><?= e(ucfirst($m['maintenance_type'])) ?></span></td>
                        <td class="small"><?= e($m['description'] ?? '—') ?><?php if ($m['file_path']): ?> <a href="<?= e(UPLOAD_URL . '/' . $m['file_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i></a><?php endif; ?></td>
                        <td class="text-end"><?= num($m['cost']) ?></td>
                        <td><?= $m['next_service_date'] ? expiry_badge($m['next_service_date']) : '—' ?></td>
                        <td class="text-end"><?php if ($canManage): ?><form method="post" class="d-inline" data-confirm="Delete log?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_maintenance"><input type="hidden" name="log_id" value="<?= (int)$m['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php endif; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
        </div>

        <!-- Usage -->
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Usage Logs</span>
                <?php if ($canManage): ?><button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#usageModal"><i class="bi bi-plus-lg"></i> Add</button><?php endif; ?>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>Driver</th><th>Block</th><th>Purpose</th><th class="text-end">Meter</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($usage)): ?><tr><td colspan="6" class="text-center text-muted py-3">No usage logs.</td></tr>
                <?php else: foreach ($usage as $u): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fmt_datetime($u['usage_date'], 'd M Y')) ?></td>
                        <td class="small"><?= e($u['driver_name'] ?? '—') ?></td>
                        <td><?= e($u['block_code'] ?? '—') ?></td>
                        <td class="small"><?= e($u['purpose'] ?? '—') ?></td>
                        <td class="text-end small"><?= $u['start_meter'] !== null || $u['end_meter'] !== null ? num($u['start_meter']) . ' → ' . num($u['end_meter']) : ($u['hours_used'] !== null ? num($u['hours_used']) . ' h' : '—') ?></td>
                        <td class="text-end"><?php if ($canManage): ?><form method="post" class="d-inline" data-confirm="Delete log?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_usage"><input type="hidden" name="log_id" value="<?= (int)$u['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php endif; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
        </div>

        <!-- Documents -->
        <div class="card">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Documents</span>
                <?php if ($canManage): ?><button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#docModal"><i class="bi bi-plus-lg"></i> Add</button><?php endif; ?>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Type</th><th>Number</th><th>Expiry</th><th>File</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($docs)): ?><tr><td colspan="5" class="text-center text-muted py-3">No documents.</td></tr>
                <?php else: foreach ($docs as $d): ?>
                    <tr>
                        <td><?= e($d['doc_type']) ?></td><td class="small"><?= e($d['doc_number'] ?? '—') ?></td>
                        <td><?= expiry_badge($d['expiry_date']) ?></td>
                        <td><?php if ($d['file_path']): ?><a href="<?= e(UPLOAD_URL . '/' . $d['file_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> View</a><?php else: ?>—<?php endif; ?></td>
                        <td class="text-end"><?php if ($canManage): ?><form method="post" class="d-inline" data-confirm="Delete document?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_document"><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php endif; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<!-- Maintenance modal -->
<div class="modal fade" id="maintModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" enctype="multipart/form-data" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_maintenance">
        <div class="modal-header"><h5 class="modal-title">Add Maintenance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-6"><label class="form-label">Date</label><input type="date" name="service_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-6"><label class="form-label">Type</label><select name="maintenance_type" class="form-select"><option value="service">Service</option><option value="repair">Repair</option><option value="breakdown">Breakdown</option></select></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="col-4"><label class="form-label">Cost</label><input type="number" step="0.01" min="0" name="cost" class="form-control"></div>
            <div class="col-4"><label class="form-label">Odometer</label><input type="number" step="0.01" name="odometer" class="form-control"></div>
            <div class="col-4"><label class="form-label">Next Service</label><input type="date" name="next_service_date" class="form-control"></div>
            <div class="col-12"><label class="form-label">File</label><input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add</button></div>
    </form>
</div></div>

<!-- Usage modal -->
<div class="modal fade" id="usageModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_usage">
        <div class="modal-header"><h5 class="modal-title">Add Usage Log</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-6"><label class="form-label">Date</label><input type="date" name="usage_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-6"><label class="form-label">Driver</label><select name="driver_worker_id" class="form-select"><option value="">—</option><?php foreach ($drivers as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Block</label><select name="block_id" class="form-select"><option value="">—</option><?php foreach ($blocks as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['block_code']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label">Purpose</label><input type="text" name="purpose" class="form-control"></div>
            <div class="col-4"><label class="form-label">Start Meter</label><input type="number" step="0.01" name="start_meter" class="form-control"></div>
            <div class="col-4"><label class="form-label">End Meter</label><input type="number" step="0.01" name="end_meter" class="form-control"></div>
            <div class="col-4"><label class="form-label">Hours Used</label><input type="number" step="0.01" name="hours_used" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add</button></div>
    </form>
</div></div>

<!-- Document modal -->
<div class="modal fade" id="docModal" tabindex="-1"><div class="modal-dialog">
    <form method="post" enctype="multipart/form-data" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_document">
        <div class="modal-header"><h5 class="modal-title">Add Document</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-6"><label class="form-label">Type</label><input type="text" name="doc_type" class="form-control" placeholder="Road Tax, Insurance…" required></div>
            <div class="col-6"><label class="form-label">Number</label><input type="text" name="doc_number" class="form-control"></div>
            <div class="col-6"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control"></div>
            <div class="col-12"><label class="form-label">File</label><input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
