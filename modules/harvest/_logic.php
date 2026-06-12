<?php
/**
 * modules/harvest/_logic.php
 * Shared helpers for the harvest module.
 */

/**
 * Load the dropdown datasets used by the harvest location picker.
 *
 * @return array{estates: array, divisions: array, blocks: array}
 */
function harvest_location_data(PDO $pdo): array
{
    return [
        'estates'   => $pdo->query('SELECT id, estate_name FROM estates ORDER BY estate_name')->fetchAll(),
        'divisions' => $pdo->query('SELECT id, estate_id, division_name FROM divisions ORDER BY division_name')->fetchAll(),
        'blocks'    => $pdo->query('SELECT id, estate_id, division_id, block_code, block_name FROM blocks ORDER BY block_code')->fetchAll(),
    ];
}

/**
 * Validate a harvest form payload. Returns a list of error messages.
 *
 * @return string[]
 */
function validate_harvest(PDO $pdo, array $form): array
{
    $errors = [];

    $date = trim((string)$form['harvest_date']);
    if ($date === '' || !strtotime($date)) {
        $errors[] = 'A valid harvest date is required.';
    } elseif (strtotime($date) > strtotime('today')) {
        $errors[] = 'Harvest date cannot be in the future.';
    }

    if ((int)$form['estate_id'] <= 0)   $errors[] = 'Please select an estate.';
    if ((int)$form['division_id'] <= 0) $errors[] = 'Please select a division.';
    if ((int)$form['block_id'] <= 0)    $errors[] = 'Please select a block.';

    // The block must belong to the chosen division and estate.
    if ((int)$form['block_id'] > 0) {
        $stmt = $pdo->prepare('SELECT 1 FROM blocks WHERE id = ? AND division_id = ? AND estate_id = ? LIMIT 1');
        $stmt->execute([(int)$form['block_id'], (int)$form['division_id'], (int)$form['estate_id']]);
        if (!$stmt->fetchColumn()) {
            $errors[] = 'The selected block does not belong to the chosen estate/division.';
        }
    }

    foreach (['bunches_count' => 'Bunches', 'ffb_weight_kg' => 'FFB weight',
              'loose_fruit_kg' => 'Loose fruit', 'rejected_bunches' => 'Rejected bunches'] as $key => $label) {
        if ($form[$key] !== '' && (float)$form[$key] < 0) {
            $errors[] = $label . ' cannot be negative.';
        }
    }

    return $errors;
}

/**
 * Insert (when $id is null) or update a harvest record.
 * Returns the record id.
 */
function save_harvest(PDO $pdo, array $form, ?int $id): int
{
    $data = [
        'harvest_date'     => $form['harvest_date'],
        'estate_id'        => (int)$form['estate_id'],
        'division_id'      => (int)$form['division_id'],
        'block_id'         => (int)$form['block_id'],
        'supervisor_id'    => to_int_or_null($form['supervisor_id'] ?? ''),
        'bunches_count'    => (int)($form['bunches_count'] !== '' ? $form['bunches_count'] : 0),
        'ffb_weight_kg'    => (float)($form['ffb_weight_kg'] !== '' ? $form['ffb_weight_kg'] : 0),
        'loose_fruit_kg'   => (float)($form['loose_fruit_kg'] !== '' ? $form['loose_fruit_kg'] : 0),
        'rejected_bunches' => (int)($form['rejected_bunches'] !== '' ? $form['rejected_bunches'] : 0),
        'collection_time'  => trim((string)$form['collection_time']) !== '' ? $form['collection_time'] : null,
        'remarks'          => trim((string)$form['remarks']) !== '' ? $form['remarks'] : null,
    ];

    if ($id === null) {
        $data['created_by'] = current_user_id();
        $cols = implode(', ', array_keys($data));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $pdo->prepare("INSERT INTO harvest_records ($cols, created_at) VALUES ($ph, NOW())")
            ->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    }

    $data['updated_by'] = current_user_id();
    $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    $args = array_values($data);
    $args[] = $id;
    $pdo->prepare("UPDATE harvest_records SET $set, updated_at = NOW() WHERE id = ?")->execute($args);
    return $id;
}

/**
 * Replace the harvest team for a record.
 *
 * @param int[]    $checkedIds   Worker ids selected for the team
 * @param string[] $roles        role_in_task keyed by worker id
 * @param string[] $productivity productivity_value keyed by worker id
 */
function save_harvest_workers(PDO $pdo, int $recordId, array $checkedIds, array $roles, array $productivity): void
{
    $pdo->prepare('DELETE FROM harvest_record_workers WHERE harvest_record_id = ?')->execute([$recordId]);
    $ins = $pdo->prepare(
        'INSERT INTO harvest_record_workers (harvest_record_id, worker_id, role_in_task, productivity_value, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    foreach ($checkedIds as $wid) {
        $wid = (int)$wid;
        if ($wid <= 0) {
            continue;
        }
        $role = isset($roles[$wid]) && trim((string)$roles[$wid]) !== '' ? trim((string)$roles[$wid]) : null;
        $prod = isset($productivity[$wid]) && $productivity[$wid] !== '' ? (float)$productivity[$wid] : 0;
        $ins->execute([$recordId, $wid, $role, $prod]);
    }
}
