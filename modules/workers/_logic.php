<?php
/**
 * modules/workers/_logic.php
 * Shared helpers for the worker module.
 */

/**
 * Worker categories (blueprint Module 4).
 *
 * @return string[]
 */
function worker_categories(): array
{
    return [
        'Harvester', 'Loose Fruit Collector', 'Sprayer', 'Pruner',
        'Fertilizer Team', 'General Worker', 'Driver', 'Mechanic', 'Supervisor',
    ];
}

/**
 * Validate a worker form payload.
 *
 * @param int|null $excludeId  Worker id excluded from the unique-code check (edit).
 * @return string[]
 */
function validate_worker(PDO $pdo, array $form, ?int $excludeId = null): array
{
    $errors = [];

    if (trim((string)$form['worker_code']) === '') $errors[] = 'Worker code is required.';
    if (trim((string)$form['name']) === '')        $errors[] = 'Name is required.';

    foreach (['permit_expiry' => 'Permit expiry', 'contract_expiry' => 'Contract expiry'] as $key => $label) {
        if (trim((string)$form[$key]) !== '' && !strtotime((string)$form[$key])) {
            $errors[] = $label . ' must be a valid date.';
        }
    }

    if (trim((string)$form['worker_code']) !== '') {
        $sql  = 'SELECT 1 FROM workers WHERE worker_code = ?';
        $args = [trim((string)$form['worker_code'])];
        if ($excludeId !== null) { $sql .= ' AND id <> ?'; $args[] = $excludeId; }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        if ($stmt->fetchColumn()) {
            $errors[] = 'A worker with this code already exists.';
        }
    }

    return $errors;
}

/**
 * Insert (when $id is null) or update a worker from a validated payload.
 * Returns the worker id.
 */
function save_worker(PDO $pdo, array $form, ?int $id): int
{
    $data = [
        'worker_code'     => trim((string)$form['worker_code']),
        'name'            => trim((string)$form['name']),
        'phone'           => trim((string)$form['phone']) !== '' ? trim((string)$form['phone']) : null,
        'nationality'     => trim((string)$form['nationality']) !== '' ? trim((string)$form['nationality']) : null,
        'worker_type'     => trim((string)$form['worker_type']) !== '' ? trim((string)$form['worker_type']) : null,
        'ic_passport'     => trim((string)$form['ic_passport']) !== '' ? trim((string)$form['ic_passport']) : null,
        'permit_expiry'   => trim((string)$form['permit_expiry']) !== '' ? $form['permit_expiry'] : null,
        'contract_expiry' => trim((string)$form['contract_expiry']) !== '' ? $form['contract_expiry'] : null,
        'status'          => $form['status'] === 'inactive' ? 'inactive' : 'active',
    ];

    if ($id === null) {
        $data['created_by'] = current_user_id();
        $cols = implode(', ', array_keys($data));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $pdo->prepare("INSERT INTO workers ($cols, created_at) VALUES ($ph, NOW())")->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    }

    $data['updated_by'] = current_user_id();
    $set  = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    $args = array_values($data);
    $args[] = $id;
    $pdo->prepare("UPDATE workers SET $set, updated_at = NOW() WHERE id = ?")->execute($args);
    return $id;
}
