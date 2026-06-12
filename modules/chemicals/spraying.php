<?php
/**
 * modules/chemicals/spraying.php
 * Spraying records with PPE / weather / safety and worker team.
 * Each record issues chemical stock.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/stock.php';
require_once dirname(__DIR__) . '/harvest/_logic.php';
require_permission('chemical.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('chemical.manage');

    $date      = trim((string)input('spray_date')) ?: date('Y-m-d');
    $productId = (int)input('product_id');
    $qty       = (float)input('quantity');
    $estateId  = to_int_or_null(input('estate_id'));
    $divId     = to_int_or_null(input('division_id'));
    $blockId   = to_int_or_null(input('block_id'));
    $ppe       = input('ppe_confirmed') ? 1 : 0;
    $weather   = trim((string)input('weather')) ?: null;
    $safety    = trim((string)input('safety_remarks')) ?: null;
    $remarks   = trim((string)input('remarks')) ?: null;
    $checked   = (array)($_POST['worker_check'] ?? []);

    $prod = $pdo->prepare('SELECT unit, cost_per_unit FROM chemical_products WHERE id = ?');
    $prod->execute([$productId]);
    $p = $prod->fetch();

    if (!$p || $qty <= 0) {
        set_flash('danger', 'Select a product and a positive quantity.');
        redirect('modules/chemicals/spraying.php');
    }

    $cost = (float)$p['cost_per_unit'];
    $total = $cost * $qty;

    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO spraying_records (spray_date, product_id, estate_id, division_id, block_id, quantity, unit,
                        cost_per_unit, total_cost, ppe_confirmed, weather, safety_remarks, remarks, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$date, $productId, $estateId, $divId, $blockId, $qty, $p['unit'], $cost, $total, $ppe, $weather, $safety, $remarks, current_user_id()]);
        $sprayId = (int)$pdo->lastInsertId();

        $insW = $pdo->prepare('INSERT INTO spraying_record_workers (spraying_record_id, worker_id, created_at) VALUES (?, ?, NOW())');
        foreach ($checked as $wid) {
            $wid = (int)$wid;
            if ($wid > 0) $insW->execute([$sprayId, $wid]);
        }

        record_stock_movement($pdo, 'chemical_products', 'chemical_stock_movements', $productId, 'out', $qty, [
            'movement_date'  => $date,
            'reference_type' => 'spraying',
            'reference_id'   => $sprayId,
            'remarks'        => 'Spraying operation',
        ]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('danger', 'Could not record the spraying.');
        redirect('modules/chemicals/spraying.php');
    }

    log_activity($pdo, current_user_id(), 'create', 'chemical', 'Spraying with product #' . $productId);
    set_flash('success', 'Spraying recorded and stock issued.');
    redirect('modules/chemicals/spraying.php');
}

$loc = harvest_location_data($pdo);
$estates = $loc['estates']; $allDivisions = $loc['divisions']; $allBlocks = $loc['blocks'];
$products = $pdo->query("SELECT id, product_name, unit, current_stock FROM chemical_products WHERE status = 'active' ORDER BY product_name")->fetchAll();
$workers  = $pdo->query("SELECT id, worker_code, name FROM workers WHERE status = 'active' ORDER BY name")->fetchAll();

$pageNum = current_page();
$offset = ($pageNum - 1) * PER_PAGE;
$total = (int)$pdo->query('SELECT COUNT(*) FROM spraying_records')->fetchColumn();
$rows = $pdo->query(
    "SELECT s.*, p.product_name, b.block_code,
            (SELECT COUNT(*) FROM spraying_record_workers w WHERE w.spraying_record_id = s.id) AS worker_count
     FROM spraying_records s
     JOIN chemical_products p ON p.id = s.product_id
     LEFT JOIN blocks b ON b.id = s.block_id
     ORDER BY s.spray_date DESC, s.id DESC LIMIT $offset, " . PER_PAGE
)->fetchAll();

$form = ['estate_id' => '', 'division_id' => '', 'block_id' => ''];
$canManage = can('chemical.manage');
$page_title = 'Spraying Records';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_tabs.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Spraying Records</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#sprayModal"><i class="bi bi-plus-lg me-1"></i> Record Spraying</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Date</th><th>Product</th><th>Block</th><th class="text-end">Qty</th>
                    <th class="text-center">PPE</th><th>Weather</th><th class="text-center">Workers</th><th class="text-end">Cost</th></tr></thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No spraying records yet.</td></tr>
                <?php else: foreach ($rows as $s): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fmt_datetime($s['spray_date'], 'd M Y')) ?></td>
                        <td><?= e($s['product_name']) ?></td>
                        <td><?= e($s['block_code'] ?? '—') ?></td>
                        <td class="text-end"><?= num($s['quantity']) ?> <?= e($s['unit']) ?></td>
                        <td class="text-center"><?= $s['ppe_confirmed'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?></td>
                        <td class="small"><?= e($s['weather'] ?? '—') ?></td>
                        <td class="text-center"><?= (int)$s['worker_count'] ?></td>
                        <td class="text-end"><?= num($s['total_cost']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($total, PER_PAGE, $pageNum, 'modules/chemicals/spraying.php') ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="sprayModal" tabindex="-1"><div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
        <?= csrf_field() ?>
        <div class="modal-header"><h5 class="modal-title">Record Spraying</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="spray_date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-md-5">
                <label class="form-label">Product <span class="text-danger">*</span></label>
                <select name="product_id" class="form-select" required>
                    <option value="">— Select —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['product_name']) ?> (<?= num($p['current_stock']) ?> <?= e($p['unit']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Quantity</label><input type="number" step="0.01" min="0" name="quantity" class="form-control" required></div>

            <?php $required = false; require dirname(__DIR__) . '/harvest/_location_picker.php'; ?>

            <div class="col-md-4"><label class="form-label">Weather</label><input type="text" name="weather" class="form-control" placeholder="Dry, cloudy…"></div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="ppe_confirmed" id="ppe_confirmed" value="1" checked>
                    <label class="form-check-label" for="ppe_confirmed">PPE confirmed</label>
                </div>
            </div>
            <div class="col-md-4"><label class="form-label">Safety Remarks</label><input type="text" name="safety_remarks" class="form-control"></div>

            <div class="col-12">
                <label class="form-label">Workers Involved</label>
                <?php if (empty($workers)): ?>
                    <p class="text-muted small mb-0">No active workers.</p>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($workers as $w): ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="worker_check[]" value="<?= (int)$w['id'] ?>" id="w<?= (int)$w['id'] ?>">
                            <label class="form-check-label small" for="w<?= (int)$w['id'] ?>"><?= e($w['name']) ?></label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-12"><label class="form-label">Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save Spraying</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
