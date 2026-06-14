<?php
/**
 * modules/estate/block_create.php
 * Add a new block.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('estate.manage');

$estates     = $pdo->query('SELECT id, estate_name FROM estates ORDER BY estate_name')->fetchAll();
$allDivisions = $pdo->query('SELECT id, estate_id, division_name FROM divisions ORDER BY division_name')->fetchAll();

$errors = [];
$form = [
    'estate_id' => (int)input('estate_id'), 'division_id' => '', 'block_code' => '', 'block_name' => '',
    'acreage' => '', 'hectare' => '', 'palm_count' => '', 'planting_year' => '', 'palm_age' => '',
    'soil_type' => '', 'terrain_type' => '', 'gps_lat' => '', 'gps_lng' => '', 'status' => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string)input($key));
    }
    $form['estate_id']   = (int)$form['estate_id'];
    $form['division_id'] = (int)$form['division_id'];

    $errors = validate_block($pdo, $form);

    if (!$errors) {
        save_block($pdo, $form, null);
        log_activity($pdo, current_user_id(), 'create', 'estate', 'Created block ' . $form['block_code']);
        set_flash('success', 'Block created successfully.');
        redirect('modules/estate/blocks.php?estate_id=' . $form['estate_id']);
    }
}

$page_title = 'Add Block';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_block_form.php';
require __DIR__ . '/../../inc/footer.php';
