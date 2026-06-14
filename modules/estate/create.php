<?php
/**
 * modules/estate/create.php
 * Add a new estate.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('estate.manage');

$errors = [];
$form = ['estate_name' => '', 'estate_code' => '', 'location' => '',
         'total_acreage' => '', 'total_hectare' => '', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form['estate_name']   = trim((string)input('estate_name'));
    $form['estate_code']   = trim((string)input('estate_code'));
    $form['location']      = trim((string)input('location'));
    $form['total_acreage'] = trim((string)input('total_acreage'));
    $form['total_hectare'] = trim((string)input('total_hectare'));
    $form['status']        = input('status') === 'inactive' ? 'inactive' : 'active';

    if ($form['estate_name'] === '') {
        $errors[] = 'Estate name is required.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO estates (estate_name, estate_code, location, total_acreage, total_hectare,
                                  status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $form['estate_name'],
            $form['estate_code'] ?: null,
            $form['location'] ?: null,
            to_decimal_or_null($form['total_acreage']),
            to_decimal_or_null($form['total_hectare']),
            $form['status'],
            current_user_id(),
        ]);
        log_activity($pdo, current_user_id(), 'create', 'estate', 'Created estate ' . $form['estate_name']);
        set_flash('success', 'Estate created successfully.');
        redirect('modules/estate/index.php');
    }
}

$page_title = 'Add Estate';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
