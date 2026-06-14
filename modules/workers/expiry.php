<?php
/**
 * modules/workers/expiry.php
 * Permit / contract / document expiry alerts.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('worker.view');

$window = (int)input('days');
if ($window <= 0) {
    $window = 60;
}
$limitDate = date('Y-m-d', strtotime("+$window days"));

// Workers whose permit OR contract is expired or expiring within the window.
$wStmt = $pdo->prepare(
    "SELECT * FROM workers
     WHERE status = 'active'
       AND (
         (permit_expiry IS NOT NULL AND permit_expiry <= ?)
         OR (contract_expiry IS NOT NULL AND contract_expiry <= ?)
       )
     ORDER BY LEAST(COALESCE(permit_expiry, '9999-12-31'), COALESCE(contract_expiry, '9999-12-31')) ASC"
);
$wStmt->execute([$limitDate, $limitDate]);
$workers = $wStmt->fetchAll();

// Documents expiring within the window.
$dStmt = $pdo->prepare(
    "SELECT wd.*, w.name AS worker_name, w.worker_code
     FROM worker_documents wd
     JOIN workers w ON w.id = wd.worker_id
     WHERE wd.expiry_date IS NOT NULL AND wd.expiry_date <= ?
     ORDER BY wd.expiry_date ASC"
);
$dStmt->execute([$limitDate]);
$docs = $dStmt->fetchAll();

$page_title = 'Expiry Alerts';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Expiry Alerts</h1>
    <a href="<?= e(url('modules/workers/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Workers
    </a>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-auto">
        <label class="form-label small mb-1">Window (days ahead)</label>
        <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ([30, 60, 90, 180] as $d): ?>
                <option value="<?= $d ?>" <?= $window === $d ? 'selected' : '' ?>>Next <?= $d ?> days</option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Permits &amp; Contracts (<?= count($workers) ?>)</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Worker</th><th>Category</th><th>Permit Expiry</th><th>Contract Expiry</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($workers)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No permits or contracts expiring in this window.</td></tr>
            <?php else: foreach ($workers as $w): ?>
                <tr>
                    <td class="fw-semibold"><?= e($w['name']) ?><div class="text-muted small"><?= e($w['worker_code']) ?></div></td>
                    <td><?= e($w['worker_type'] ?? '—') ?></td>
                    <td><?= expiry_badge($w['permit_expiry']) ?></td>
                    <td><?= expiry_badge($w['contract_expiry']) ?></td>
                    <td class="text-end">
                        <a href="<?= e(url('modules/workers/view.php?id=' . (int)$w['id'])) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Documents (<?= count($docs) ?>)</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Worker</th><th>Document</th><th>Number</th><th>Expiry</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($docs)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No documents expiring in this window.</td></tr>
            <?php else: foreach ($docs as $d): ?>
                <tr>
                    <td class="fw-semibold"><?= e($d['worker_name']) ?><div class="text-muted small"><?= e($d['worker_code']) ?></div></td>
                    <td><?= e($d['doc_type']) ?></td>
                    <td class="small"><?= e($d['doc_number'] ?? '—') ?></td>
                    <td><?= expiry_badge($d['expiry_date']) ?></td>
                    <td class="text-end">
                        <a href="<?= e(url('modules/workers/view.php?id=' . (int)$d['worker_id'])) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
