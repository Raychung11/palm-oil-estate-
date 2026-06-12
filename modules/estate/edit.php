<?php
/**
 * modules/estate/edit.php
 * Edit an existing estate.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('estate.manage');

$id = (int)input('id');
if ($id <= 0) {
    set_flash('danger', 'Invalid estate.');
    redirect('modules/estate/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM estates WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$estate = $stmt->fetch();
if (!$estate) {
    set_flash('danger', 'Estate not found.');
    redirect('modules/estate/index.php');
}

$errors = [];
$form = [
    'estate_name'   => $estate['estate_name'],
    'estate_code'   => $estate['estate_code'],
    'location'      => $estate['location'],
    'total_acreage' => $estate['total_acreage'],
    'total_hectare' => $estate['total_hectare'],
    'status'        => $estate['status'],
];

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
            'UPDATE estates SET estate_name=?, estate_code=?, location=?, total_acreage=?,
                    total_hectare=?, status=?, updated_by=?, updated_at=NOW() WHERE id=?'
        );
        $stmt->execute([
            $form['estate_name'],
            $form['estate_code'] ?: null,
            $form['location'] ?: null,
            to_decimal_or_null($form['total_acreage']),
            to_decimal_or_null($form['total_hectare']),
            $form['status'],
            current_user_id(),
            $id,
        ]);
        log_activity($pdo, current_user_id(), 'update', 'estate', 'Updated estate ' . $form['estate_name']);
        set_flash('success', 'Estate updated successfully.');
        redirect('modules/estate/index.php');
    }
}

$page_title = 'Edit Estate';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
