<?php
/**
 * modules/mill/view.php
 * Delivery detail + reconciliation (weighbridge net vs linked harvest FFB).
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('mill.view');

$id = (int)input('id');
if ($id <= 0) { set_flash('danger', 'Invalid delivery.'); redirect('modules/mill/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('mill.manage');
    $action = input('action');
    if ($action === 'reconcile') {
        $pdo->prepare("UPDATE mill_deliveries SET status = 'reconciled', updated_by = ?, updated_at = NOW() WHERE id = ?")
            ->execute([current_user_id(), $id]);
        log_activity($pdo, current_user_id(), 'reconcile', 'mill', 'Reconciled delivery #' . $id);
        set_flash('success', 'Delivery reconciled.');
    } elseif ($action === 'reopen') {
        $pdo->prepare("UPDATE mill_deliveries SET status = 'pending', updated_by = ?, updated_at = NOW() WHERE id = ?")
            ->execute([current_user_id(), $id]);
        set_flash('success', 'Delivery reopened.');
    }
    redirect('modules/mill/view.php?id=' . $id);
}

$stmt = $pdo->prepare(
    'SELECT d.*, m.mill_name, a.asset_name, w.name AS driver_name
     FROM mill_deliveries d
     LEFT JOIN mills m ON m.id = d.mill_id
     LEFT JOIN assets a ON a.id = d.vehicle_asset_id
     LEFT JOIN workers w ON w.id = d.driver_worker_id
     WHERE d.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$d = $stmt->fetch();
if (!$d) { set_flash('danger', 'Delivery not found.'); redirect('modules/mill/index.php'); }

$items = $pdo->prepare(
    'SELECT i.ffb_weight_kg, h.harvest_date, h.id AS harvest_id, b.block_code
     FROM mill_delivery_items i JOIN harvest_records h ON h.id = i.harvest_record_id
     JOIN blocks b ON b.id = h.block_id WHERE i.mill_delivery_id = ? ORDER BY h.harvest_date'
);
$items->execute([$id]);
$items = $items->fetchAll();

$tickets = $pdo->prepare('SELECT * FROM weighbridge_tickets WHERE mill_delivery_id = ? ORDER BY id');
$tickets->execute([$id]);
$tickets = $tickets->fetchAll();

$harvestTotal = 0;
foreach ($items as $it) { $harvestTotal += (float)$it['ffb_weight_kg']; }
$variance = (float)$d['net_weight_kg'] - $harvestTotal;
$variancePct = $harvestTotal > 0 ? ($variance / $harvestTotal) * 100 : null;

$page_title = 'Delivery ' . date('d M Y', strtotime($d['delivery_date']));
require __DIR__ . '/../../inc/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?= e(url('modules/mill/index.php')) ?>">Mill Deliveries</a></li>
    <li class="breadcrumb-item active"><?= e(fmt_datetime($d['delivery_date'], 'd M Y')) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Delivery Detail <span class="badge bg-<?= $d['status'] === 'reconciled' ? 'success' : 'warning text-dark' ?>"><?= e(ucfirst($d['status'])) ?></span></h1>
    <?php if (can('mill.manage') && $d['status'] !== 'reconciled'): ?>
    <a href="<?= e(url('modules/mill/edit.php?id=' . $id)) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header bg-white fw-semibold">Trip &amp; Weighbridge</div><div class="card-body">
            <div class="row g-3">
                <div class="col-sm-4"><div class="text-muted small">Date</div><?= e(fmt_datetime($d['delivery_date'], 'd M Y')) ?></div>
                <div class="col-sm-4"><div class="text-muted small">Mill</div><?= e($d['mill_name'] ?? '—') ?></div>
                <div class="col-sm-4"><div class="text-muted small">Lorry</div><?= e($d['asset_name'] ?? '—') ?></div>
                <div class="col-sm-4"><div class="text-muted small">Driver</div><?= e($d['driver_name'] ?? '—') ?></div>
                <div class="col-sm-4"><div class="text-muted small">Time Out → In</div><?= e($d['time_out'] ? substr($d['time_out'],0,5) : '—') ?> → <?= e($d['time_in'] ? substr($d['time_in'],0,5) : '—') ?></div>
                <div class="col-sm-4"><div class="text-muted small">OER</div><?= $d['oer'] !== null ? num($d['oer']) . '%' : '—' ?></div>
            </div>
            <hr>
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3"><div class="kpi-value"><?= num($d['gross_weight_kg']) ?></div><div class="kpi-label">Gross (kg)</div></div>
                <div class="col-6 col-md-3"><div class="kpi-value"><?= num($d['tare_weight_kg']) ?></div><div class="kpi-label">Tare (kg)</div></div>
                <div class="col-6 col-md-3"><div class="kpi-value"><?= num($d['net_weight_kg']) ?></div><div class="kpi-label">Net (kg)</div></div>
                <div class="col-6 col-md-3"><div class="kpi-value"><?= num($d['total_value']) ?></div><div class="kpi-label">Value (RM)</div></div>
            </div>
            <?php if ($d['remarks']): ?><hr><div class="text-muted small">Remarks</div><div><?= nl2br(e($d['remarks'])) ?></div><?php endif; ?>
        </div></div>

        <div class="card mb-3"><div class="card-header bg-white fw-semibold">Linked Harvest Records (<?= count($items) ?>)</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>Block</th><th class="text-end">FFB (kg)</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?><tr><td colspan="3" class="text-center text-muted py-3">No harvest records linked.</td></tr>
                <?php else: foreach ($items as $it): ?>
                    <tr><td class="text-nowrap"><?= e(fmt_datetime($it['harvest_date'], 'd M Y')) ?></td><td><?= e($it['block_code']) ?></td><td class="text-end"><?= num($it['ffb_weight_kg']) ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
                <tfoot><tr class="fw-semibold border-top"><td colspan="2" class="text-end">Harvest total</td><td class="text-end"><?= num($harvestTotal) ?></td></tr></tfoot>
            </table></div>
        </div>

        <?php if (!empty($tickets)): ?>
        <div class="card"><div class="card-header bg-white fw-semibold">Weighbridge Tickets</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Ticket No.</th><th>File</th><th>Added</th></tr></thead>
                <tbody>
                <?php foreach ($tickets as $t): ?>
                    <tr><td><?= e($t['ticket_no'] ?? '—') ?></td>
                        <td><?php if ($t['file_path']): ?><a href="<?= e(UPLOAD_URL . '/' . $t['file_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> View</a><?php else: ?>—<?php endif; ?></td>
                        <td class="small text-muted"><?= e(fmt_datetime($t['created_at'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Reconciliation -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Reconciliation</div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Weighbridge net</span><strong><?= num($d['net_weight_kg']) ?> kg</strong></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Harvest total</span><strong><?= num($harvestTotal) ?> kg</strong></div>
                <hr>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Variance</span>
                    <strong class="<?= abs($variance) > 0.01 ? ($variance < 0 ? 'text-danger' : 'text-warning') : 'text-success' ?>">
                        <?= ($variance >= 0 ? '+' : '') . num($variance) ?> kg<?= $variancePct !== null ? ' (' . num($variancePct) . '%)' : '' ?>
                    </strong>
                </div>
                <?php if ($harvestTotal <= 0): ?>
                    <p class="text-muted small mt-2 mb-0">Link harvest records to reconcile against the weighbridge net weight.</p>
                <?php endif; ?>

                <?php if (can('mill.manage')): ?>
                <hr>
                <?php if ($d['status'] !== 'reconciled'): ?>
                <form method="post" class="d-grid">
                    <?= csrf_field() ?>
                    <button name="action" value="reconcile" class="btn btn-success btn-sm" data-confirm="Mark this delivery as reconciled? It will be locked from editing."><i class="bi bi-check-circle me-1"></i> Mark Reconciled</button>
                </form>
                <?php else: ?>
                <form method="post" class="d-grid">
                    <?= csrf_field() ?>
                    <button name="action" value="reopen" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i> Reopen</button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
