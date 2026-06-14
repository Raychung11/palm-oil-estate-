<?php
/**
 * modules/harvest/create.php
 * Daily harvest entry.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('harvest.create');

$loc          = harvest_location_data($pdo);
$estates      = $loc['estates'];
$allDivisions = $loc['divisions'];
$allBlocks    = $loc['blocks'];
$supervisors  = $pdo->query("SELECT id, name FROM users WHERE status = 'active' ORDER BY name")->fetchAll();
$workers      = $pdo->query("SELECT id, worker_code, name, worker_type FROM workers WHERE status = 'active' ORDER BY name")->fetchAll();

$errors = [];
$form = [
    'harvest_date' => date('Y-m-d'), 'collection_time' => '', 'supervisor_id' => '',
    'estate_id' => '', 'division_id' => '', 'block_id' => '',
    'bunches_count' => '', 'ffb_weight_kg' => '', 'loose_fruit_kg' => '', 'rejected_bunches' => '',
    'remarks' => '',
];
$assigned = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string)input($key));
    }

    // Rebuild the assigned-team map so the form repopulates on error.
    $checked = (array)($_POST['worker_check'] ?? []);
    $roles   = (array)($_POST['role'] ?? []);
    $prod    = (array)($_POST['prod'] ?? []);
    foreach ($checked as $wid) {
        $wid = (int)$wid;
        $assigned[$wid] = [
            'role_in_task'       => $roles[$wid] ?? '',
            'productivity_value' => $prod[$wid] ?? '',
        ];
    }

    $errors = validate_harvest($pdo, $form);

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $recordId = save_harvest($pdo, $form, null);
            save_harvest_workers($pdo, $recordId, $checked, $roles, $prod);

            $stored = handle_upload($_FILES['photo'] ?? [], 'harvest');
            if ($stored !== null) {
                $pdo->prepare('INSERT INTO harvest_photos (harvest_record_id, file_path, created_by, created_at)
                               VALUES (?, ?, ?, NOW())')
                    ->execute([$recordId, $stored, current_user_id()]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not save the harvest record.';
        }

        if (!$errors) {
            log_activity($pdo, current_user_id(), 'create', 'harvest', 'Recorded harvest for block #' . (int)$form['block_id']);
            set_flash('success', 'Harvest record saved.');
            redirect('modules/harvest/view.php?id=' . $recordId);
        }
    }
}

$page_title  = 'Daily Harvest Entry';
$submitLabel = 'Save Harvest';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
