<?php
/**
 * modules/workers/edit.php
 * Edit an existing worker.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('worker.manage');

$id = (int)input('id');
if ($id <= 0) {
    set_flash('danger', 'Invalid worker.');
    redirect('modules/workers/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM workers WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$worker = $stmt->fetch();
if (!$worker) {
    set_flash('danger', 'Worker not found.');
    redirect('modules/workers/index.php');
}

$fields = ['worker_code','name','phone','nationality','worker_type','ic_passport',
           'permit_expiry','contract_expiry','status'];
$errors = [];
$form = [];
foreach ($fields as $f) {
    $form[$f] = (string)($worker[$f] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($fields as $f) {
        $form[$f] = trim((string)input($f));
    }
    $errors = validate_worker($pdo, $form, $id);

    if (!$errors) {
        save_worker($pdo, $form, $id);
        log_activity($pdo, current_user_id(), 'update', 'workers', 'Updated worker ' . $form['worker_code']);
        set_flash('success', 'Worker updated successfully.');
        redirect('modules/workers/view.php?id=' . $id);
    }
}

$page_title = 'Edit Worker';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
