<?php
/**
 * modules/tasks/edit.php
 * Edit a task (approved tasks are locked).
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_once dirname(__DIR__) . '/harvest/_logic.php';
require_permission('task.create');

$id = (int)input('id');
if ($id <= 0) {
    set_flash('danger', 'Invalid task.');
    redirect('modules/tasks/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM field_tasks WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$task = $stmt->fetch();
if (!$task) {
    set_flash('danger', 'Task not found.');
    redirect('modules/tasks/index.php');
}
if ($task['status'] === 'approved') {
    set_flash('warning', 'Approved tasks cannot be edited.');
    redirect('modules/tasks/view.php?id=' . $id);
}

$loc          = harvest_location_data($pdo);
$estates      = $loc['estates'];
$allDivisions = $loc['divisions'];
$allBlocks    = $loc['blocks'];
$supervisors  = $pdo->query("SELECT id, name FROM users WHERE status = 'active' ORDER BY name")->fetchAll();
$workers      = $pdo->query("SELECT id, worker_code, name, worker_type FROM workers WHERE status = 'active' ORDER BY name")->fetchAll();

$fields = ['task_date','task_type','priority','title','description','assigned_to','estate_id','division_id','block_id','remarks'];
$errors = [];
$form = [];
foreach ($fields as $f) {
    $form[$f] = (string)($task[$f] ?? '');
}

$teamStmt = $pdo->prepare('SELECT worker_id, role_in_task FROM field_task_workers WHERE field_task_id = ?');
$teamStmt->execute([$id]);
$assigned = [];
foreach ($teamStmt->fetchAll() as $row) {
    $assigned[(int)$row['worker_id']] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($fields as $f) {
        $form[$f] = trim((string)input($f));
    }
    $checked = (array)($_POST['worker_check'] ?? []);
    $roles   = (array)($_POST['role'] ?? []);
    $assigned = [];
    foreach ($checked as $wid) {
        $assigned[(int)$wid] = ['role_in_task' => $roles[(int)$wid] ?? ''];
    }

    $errors = validate_task($pdo, $form);

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            save_task($pdo, $form, $id);
            save_task_workers($pdo, $id, $checked, $roles);
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Could not update the task.';
        }
        if (!$errors) {
            log_activity($pdo, current_user_id(), 'update', 'tasks', 'Updated task #' . $id);
            set_flash('success', 'Task updated.');
            redirect('modules/tasks/view.php?id=' . $id);
        }
    }
}

$page_title  = 'Edit Task';
$submitLabel = 'Update Task';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
