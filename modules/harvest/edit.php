<?php
/**
 * modules/harvest/edit.php
 * Edit a harvest record (approved records are locked).
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('harvest.create');

$id = (int)input('id');
if ($id <= 0) {
    set_flash('danger', 'Invalid harvest record.');
    redirect('modules/harvest/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM harvest_records WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$record = $stmt->fetch();
if (!$record) {
    set_flash('danger', 'Harvest record not found.');
    redirect('modules/harvest/index.php');
}
if ($record['approval_status'] === 'approved') {
    set_flash('warning', 'Approved records cannot be edited.');
    redirect('modules/harvest/view.php?id=' . $id);
}

$loc          = harvest_location_data($pdo);
$estates      = $loc['estates'];
$allDivisions = $loc['divisions'];
$allBlocks    = $loc['blocks'];
$supervisors  = $pdo->query("SELECT id, name FROM users WHERE status = 'active' ORDER BY name")->fetchAll();
$workers      = $pdo->query("SELECT id, worker_code, name, worker_type FROM workers WHERE status = 'active' ORDER BY name")->fetchAll();

$fields = ['harvest_date','collection_time','supervisor_id','estate_id','division_id','block_id',
           'bunches_count','ffb_weight_kg','loose_fruit_kg','rejected_bunches','remarks'];

$errors = [];
$form = [];
foreach ($fields as $f) {
    $form[$f] = (string)($record[$f] ?? '');
}

// Existing team + photos for prefill / display.
$teamStmt = $pdo->prepare('SELECT worker_id, role_in_task, productivity_value FROM harvest_record_workers WHERE harvest_record_id = ?');
$teamStmt->execute([$id]);
$assigned = [];
foreach ($teamStmt->fetchAll() as $row) {
    $assigned[(int)$row['worker_id']] = $row;
}

$photoStmt = $pdo->prepare('SELECT file_path FROM harvest_photos WHERE harvest_record_id = ? ORDER BY id');
$photoStmt->execute([$id]);
$existingPhotos = $photoStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($fields as $f) {
        $form[$f] = trim((string)input($f));
    }

    $checked = (array)($_POST['worker_check'] ?? []);
    $roles   = (array)($_POST['role'] ?? []);
    $prod    = (array)($_POST['prod'] ?? []);
    $assigned = [];
    foreach ($checked as $wid) {
        $wid = (int)$wid;
        $assigned[$wid] = ['role_in_task' => $roles[$wid] ?? '', 'productivity_value' => $prod[$wid] ?? ''];
    }

    $errors = validate_harvest($pdo, $form);

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            save_harvest($pdo, $form, $id);
            save_harvest_workers($pdo, $id, $checked, $roles, $prod);

            $stored = handle_upload($_FILES['photo'] ?? [], 'harvest');
            if ($stored !== null) {
                $pdo->prepare('INSERT INTO harvest_photos (harvest_record_id, file_path, created_by, created_at)
                               VALUES (?, ?, ?, NOW())')
                    ->execute([$id, $stored, current_user_id()]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not update the harvest record.';
        }

        if (!$errors) {
            log_activity($pdo, current_user_id(), 'update', 'harvest', 'Updated harvest record #' . $id);
            set_flash('success', 'Harvest record updated.');
            redirect('modules/harvest/view.php?id=' . $id);
        }
    }
}

$page_title  = 'Edit Harvest Record';
$submitLabel = 'Update Harvest';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
