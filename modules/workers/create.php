<?php
/**
 * modules/workers/create.php
 * Add a new worker.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('worker.manage');

$errors = [];
$form = ['worker_code' => '', 'name' => '', 'phone' => '', 'nationality' => '', 'worker_type' => '',
         'ic_passport' => '', 'permit_expiry' => '', 'contract_expiry' => '', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string)input($key));
    }
    $errors = validate_worker($pdo, $form);

    if (!$errors) {
        $id = save_worker($pdo, $form, null);
        log_activity($pdo, current_user_id(), 'create', 'workers', 'Created worker ' . $form['worker_code']);
        set_flash('success', 'Worker created successfully.');
        redirect('modules/workers/view.php?id=' . $id);
    }
}

$page_title = 'Add Worker';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
