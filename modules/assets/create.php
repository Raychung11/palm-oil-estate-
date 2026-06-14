<?php
/**
 * modules/assets/create.php
 * Add a new asset.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('asset.manage');

$errors = [];
$form = ['asset_code'=>'','asset_name'=>'','asset_type'=>'','registration_no'=>'','make_model'=>'',
         'purchase_date'=>'','purchase_cost'=>'','road_tax_expiry'=>'','insurance_expiry'=>'','remarks'=>'','status'=>'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($form) as $k) { $form[$k] = trim((string)input($k)); }
    $errors = validate_asset($pdo, $form);
    if (!$errors) {
        $id = save_asset($pdo, $form, null);
        log_activity($pdo, current_user_id(), 'create', 'assets', 'Created asset ' . $form['asset_code']);
        set_flash('success', 'Asset created.');
        redirect('modules/assets/view.php?id=' . $id);
    }
}

$page_title = 'Add Asset';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
