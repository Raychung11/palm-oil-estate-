<?php
/**
 * modules/tasks/_logic.php
 * Shared helpers for the field-task module.
 */

/**
 * Task types (blueprint Module 5).
 *
 * @return string[]
 */
function task_types(): array
{
    return [
        'Harvesting', 'Loose Fruit Collection', 'Fertilizer Application', 'Chemical Spraying',
        'Pruning', 'Weeding', 'Road Maintenance', 'Drainage Maintenance', 'Replanting',
        'Pest Inspection', 'Disease Inspection', 'General Work',
    ];
}

/**
 * Workflow statuses mapped to badge colours.
 *
 * @return array<string,string>
 */
function task_statuses(): array
{
    return [
        'planned'     => 'secondary',
        'in_progress' => 'primary',
        'completed'   => 'info',
        'approved'    => 'success',
        'rejected'    => 'danger',
    ];
}

/**
 * Render a task status badge.
 */
function task_status_badge(string $status): string
{
    $colors = task_statuses();
    $color = $colors[$status] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge bg-' . $color . '">' . e($label) . '</span>';
}

/**
 * Validate a task form payload.
 *
 * @return string[]
 */
function validate_task(PDO $pdo, array $form): array
{
    $errors = [];

    if (trim((string)$form['task_date']) === '' || !strtotime((string)$form['task_date'])) {
        $errors[] = 'A valid task date is required.';
    }
    if (trim((string)$form['title']) === '')      $errors[] = 'Task title is required.';
    if (trim((string)$form['task_type']) === '')  $errors[] = 'Please select a task type.';

    // If a block is chosen it must match the estate/division (when those are set).
    $estate = (int)$form['estate_id'];
    $division = (int)$form['division_id'];
    $block = (int)$form['block_id'];
    if ($block > 0) {
        $stmt = $pdo->prepare('SELECT estate_id, division_id FROM blocks WHERE id = ? LIMIT 1');
        $stmt->execute([$block]);
        $row = $stmt->fetch();
        if (!$row) {
            $errors[] = 'The selected block does not exist.';
        } else {
            if ($estate > 0 && (int)$row['estate_id'] !== $estate) {
                $errors[] = 'The selected block does not belong to the chosen estate.';
            }
            if ($division > 0 && (int)$row['division_id'] !== $division) {
                $errors[] = 'The selected block does not belong to the chosen division.';
            }
        }
    }

    return $errors;
}

/**
 * Insert (when $id is null) or update a task. Returns the task id.
 */
function save_task(PDO $pdo, array $form, ?int $id): int
{
    $data = [
        'task_date'   => $form['task_date'],
        'estate_id'   => to_int_or_null($form['estate_id']),
        'division_id' => to_int_or_null($form['division_id']),
        'block_id'    => to_int_or_null($form['block_id']),
        'task_type'   => trim((string)$form['task_type']),
        'title'       => trim((string)$form['title']),
        'description' => trim((string)$form['description']) !== '' ? $form['description'] : null,
        'assigned_to' => to_int_or_null($form['assigned_to']),
        'priority'    => in_array($form['priority'], ['low', 'normal', 'high'], true) ? $form['priority'] : 'normal',
        'remarks'     => trim((string)$form['remarks']) !== '' ? $form['remarks'] : null,
    ];

    if ($id === null) {
        $data['status']     = 'planned';
        $data['created_by'] = current_user_id();
        $cols = implode(', ', array_keys($data));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $pdo->prepare("INSERT INTO field_tasks ($cols, created_at) VALUES ($ph, NOW())")->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    }

    $data['updated_by'] = current_user_id();
    $set  = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    $args = array_values($data);
    $args[] = $id;
    $pdo->prepare("UPDATE field_tasks SET $set, updated_at = NOW() WHERE id = ?")->execute($args);
    return $id;
}

/**
 * Replace the worker team for a task.
 *
 * @param int[]    $checkedIds
 * @param string[] $roles  role_in_task keyed by worker id
 */
function save_task_workers(PDO $pdo, int $taskId, array $checkedIds, array $roles): void
{
    $pdo->prepare('DELETE FROM field_task_workers WHERE field_task_id = ?')->execute([$taskId]);
    $ins = $pdo->prepare('INSERT INTO field_task_workers (field_task_id, worker_id, role_in_task, created_at) VALUES (?, ?, ?, NOW())');
    foreach ($checkedIds as $wid) {
        $wid = (int)$wid;
        if ($wid <= 0) {
            continue;
        }
        $role = isset($roles[$wid]) && trim((string)$roles[$wid]) !== '' ? trim((string)$roles[$wid]) : null;
        $ins->execute([$taskId, $wid, $role]);
    }
}

/**
 * Record a status transition in the log.
 */
function log_task_status(PDO $pdo, int $taskId, ?string $from, string $to, ?string $remarks = null): void
{
    $pdo->prepare('INSERT INTO field_task_status_logs (field_task_id, from_status, to_status, remarks, changed_by, created_at)
                   VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$taskId, $from, $to, $remarks, current_user_id()]);
}
