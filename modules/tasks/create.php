<?php
/**
 * modules/tasks/create.php
 * Create a daily field task.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_once dirname(__DIR__) . '/harvest/_logic.php';
require_permission('task.create');

$loc          = harvest_location_data($pdo);
$estates      = $loc['estates'];
$allDivisions = $loc['divisions'];
$allBlocks    = $loc['blocks'];
$supervisors  = $pdo->query("SELECT id, name FROM users WHERE status = 'active' ORDER BY name")->fetchAll();
$workers      = $pdo->query("SELECT id, worker_code, name, worker_type FROM workers WHERE status = 'active' ORDER BY name")->fetchAll();

$errors = [];
$form = [
    'task_date' => date('Y-m-d'), 'task_type' => '', 'priority' => 'normal', 'title' => '', 'description' => '',
    'assigned_to' => '', 'estate_id' => '', 'division_id' => '', 'block_id' => '', 'remarks' => '',
];
$assigned = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string)input($key));
    }
    $checked = (array)($_POST['worker_check'] ?? []);
    $roles   = (array)($_POST['role'] ?? []);
    foreach ($checked as $wid) {
        $assigned[(int)$wid] = ['role_in_task' => $roles[(int)$wid] ?? ''];
    }

    $errors = validate_task($pdo, $form);

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $taskId = save_task($pdo, $form, null);
            save_task_workers($pdo, $taskId, $checked, $roles);
            log_task_status($pdo, $taskId, null, 'planned', 'Task created');
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Could not save the task.';
        }
        if (!$errors) {
            log_activity($pdo, current_user_id(), 'create', 'tasks', 'Created task: ' . $form['title']);
            set_flash('success', 'Task created.');
            redirect('modules/tasks/view.php?id=' . $taskId);
        }
    }
}

$page_title  = 'Create Task';
$submitLabel = 'Create Task';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
