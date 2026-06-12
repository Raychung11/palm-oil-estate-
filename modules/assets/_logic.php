<?php
/**
 * modules/assets/_logic.php
 * Shared helpers for the asset module.
 */

/**
 * Asset types (blueprint Module 9).
 *
 * @return string[]
 */
function asset_types(): array
{
    return ['Tractor', 'Lorry', 'Pickup', 'Excavator', 'Backhoe', 'Generator',
            'Water Pump', 'Sprayer Machine', 'Trailer', 'Motorcycle'];
}

/**
 * Validate an asset form payload.
 *
 * @param int|null $excludeId  Asset id excluded from the unique-code check (edit).
 * @return string[]
 */
function validate_asset(PDO $pdo, array $form, ?int $excludeId = null): array
{
    $errors = [];
    if (trim((string)$form['asset_code']) === '') $errors[] = 'Asset code is required.';
    if (trim((string)$form['asset_name']) === '') $errors[] = 'Asset name is required.';

    if (trim((string)$form['asset_code']) !== '') {
        $sql = 'SELECT 1 FROM assets WHERE asset_code = ?';
        $args = [trim((string)$form['asset_code'])];
        if ($excludeId !== null) { $sql .= ' AND id <> ?'; $args[] = $excludeId; }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        if ($stmt->fetchColumn()) $errors[] = 'An asset with this code already exists.';
    }
    return $errors;
}

/**
 * Insert (when $id is null) or update an asset. Returns the asset id.
 */
function save_asset(PDO $pdo, array $form, ?int $id): int
{
    $data = [
        'asset_code'       => trim((string)$form['asset_code']),
        'asset_name'       => trim((string)$form['asset_name']),
        'asset_type'       => trim((string)$form['asset_type']) ?: null,
        'registration_no'  => trim((string)$form['registration_no']) ?: null,
        'make_model'       => trim((string)$form['make_model']) ?: null,
        'purchase_date'    => trim((string)$form['purchase_date']) ?: null,
        'purchase_cost'    => to_decimal_or_null($form['purchase_cost']),
        'road_tax_expiry'  => trim((string)$form['road_tax_expiry']) ?: null,
        'insurance_expiry' => trim((string)$form['insurance_expiry']) ?: null,
        'remarks'          => trim((string)$form['remarks']) ?: null,
        'status'           => $form['status'] === 'inactive' ? 'inactive' : 'active',
    ];

    if ($id === null) {
        $data['created_by'] = current_user_id();
        $cols = implode(', ', array_keys($data));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $pdo->prepare("INSERT INTO assets ($cols, created_at) VALUES ($ph, NOW())")->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    }
    $data['updated_by'] = current_user_id();
    $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    $args = array_values($data); $args[] = $id;
    $pdo->prepare("UPDATE assets SET $set, updated_at = NOW() WHERE id = ?")->execute($args);
    return $id;
}
