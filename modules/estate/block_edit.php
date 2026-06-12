<?php
/**
 * modules/estate/block_edit.php
 * Edit an existing block.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('estate.manage');

$id = (int)input('id');
if ($id <= 0) {
    set_flash('danger', 'Invalid block.');
    redirect('modules/estate/blocks.php');
}

$stmt = $pdo->prepare('SELECT * FROM blocks WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$block = $stmt->fetch();
if (!$block) {
    set_flash('danger', 'Block not found.');
    redirect('modules/estate/blocks.php');
}

$estates      = $pdo->query('SELECT id, estate_name FROM estates ORDER BY estate_name')->fetchAll();
$allDivisions = $pdo->query('SELECT id, estate_id, division_name FROM divisions ORDER BY division_name')->fetchAll();

$fields = ['estate_id','division_id','block_code','block_name','acreage','hectare','palm_count',
           'planting_year','palm_age','soil_type','terrain_type','gps_lat','gps_lng','status'];

$errors = [];
$form = [];
foreach ($fields as $f) {
    $form[$f] = $block[$f];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($fields as $f) {
        $form[$f] = trim((string)input($f));
    }
    $form['estate_id']   = (int)$form['estate_id'];
    $form['division_id'] = (int)$form['division_id'];

    $errors = validate_block($pdo, $form, $id);

    if (!$errors) {
        save_block($pdo, $form, $id);
        log_activity($pdo, current_user_id(), 'update', 'estate', 'Updated block ' . $form['block_code']);
        set_flash('success', 'Block updated successfully.');
        redirect('modules/estate/blocks.php?estate_id=' . $form['estate_id']);
    }
}

$page_title = 'Edit Block';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_block_form.php';
require __DIR__ . '/../../inc/footer.php';
