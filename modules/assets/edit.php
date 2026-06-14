<?php
/**
 * modules/assets/edit.php
 * Edit an existing asset.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('asset.manage');

$id = (int)input('id');
if ($id <= 0) { set_flash('danger', 'Invalid asset.'); redirect('modules/assets/index.php'); }

$stmt = $pdo->prepare('SELECT * FROM assets WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$asset = $stmt->fetch();
if (!$asset) { set_flash('danger', 'Asset not found.'); redirect('modules/assets/index.php'); }

$fields = ['asset_code','asset_name','asset_type','registration_no','make_model',
           'purchase_date','purchase_cost','road_tax_expiry','insurance_expiry','remarks','status'];
$errors = [];
$form = [];
foreach ($fields as $f) { $form[$f] = (string)($asset[$f] ?? ''); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($fields as $f) { $form[$f] = trim((string)input($f)); }
    $errors = validate_asset($pdo, $form, $id);
    if (!$errors) {
        save_asset($pdo, $form, $id);
        log_activity($pdo, current_user_id(), 'update', 'assets', 'Updated asset ' . $form['asset_code']);
        set_flash('success', 'Asset updated.');
        redirect('modules/assets/view.php?id=' . $id);
    }
}

$page_title = 'Edit Asset';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
