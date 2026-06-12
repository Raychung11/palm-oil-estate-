<?php
/**
 * modules/estate/_logic.php
 * Shared validation + persistence helpers for blocks.
 */

/**
 * Validate a block form payload. Returns a list of error messages.
 *
 * @param array    $form
 * @param int|null $excludeId  Block id to exclude from the unique-code check (edit).
 * @return string[]
 */
function validate_block(PDO $pdo, array $form, ?int $excludeId = null): array
{
    $errors = [];

    if ($form['estate_id'] <= 0)   $errors[] = 'Please select an estate.';
    if ($form['division_id'] <= 0) $errors[] = 'Please select a division.';
    if (trim((string)$form['block_code']) === '') $errors[] = 'Block code is required.';

    // Division must belong to the chosen estate.
    if ($form['estate_id'] > 0 && $form['division_id'] > 0) {
        $stmt = $pdo->prepare('SELECT 1 FROM divisions WHERE id = ? AND estate_id = ? LIMIT 1');
        $stmt->execute([$form['division_id'], $form['estate_id']]);
        if (!$stmt->fetchColumn()) {
            $errors[] = 'The selected division does not belong to the selected estate.';
        }
    }

    // Unique block code within the estate.
    if (!$errors) {
        $sql = 'SELECT 1 FROM blocks WHERE estate_id = ? AND block_code = ?';
        $args = [$form['estate_id'], $form['block_code']];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $args[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        if ($stmt->fetchColumn()) {
            $errors[] = 'A block with this code already exists in the estate.';
        }
    }

    return $errors;
}

/**
 * Insert (when $id is null) or update a block from a validated form payload.
 */
function save_block(PDO $pdo, array $form, ?int $id): void
{
    // Derive palm age from planting year when left blank.
    $palmAge = to_int_or_null($form['palm_age']);
    $plantingYear = to_int_or_null($form['planting_year']);
    if ($palmAge === null && $plantingYear !== null) {
        $palmAge = max(0, (int)date('Y') - $plantingYear);
    }

    $data = [
        'estate_id'     => $form['estate_id'],
        'division_id'   => $form['division_id'],
        'block_code'    => $form['block_code'],
        'block_name'    => $form['block_name'] !== '' ? $form['block_name'] : null,
        'acreage'       => to_decimal_or_null($form['acreage']),
        'hectare'       => to_decimal_or_null($form['hectare']),
        'palm_count'    => to_int_or_null($form['palm_count']),
        'planting_year' => $plantingYear,
        'palm_age'      => $palmAge,
        'soil_type'     => $form['soil_type'] !== '' ? $form['soil_type'] : null,
        'terrain_type'  => $form['terrain_type'] !== '' ? $form['terrain_type'] : null,
        'gps_lat'       => to_decimal_or_null($form['gps_lat']),
        'gps_lng'       => to_decimal_or_null($form['gps_lng']),
        'status'        => $form['status'] !== '' ? $form['status'] : 'active',
    ];

    if ($id === null) {
        $data['created_by'] = current_user_id();
        $cols = implode(', ', array_keys($data));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $pdo->prepare("INSERT INTO blocks ($cols, created_at) VALUES ($ph, NOW())");
        $stmt->execute(array_values($data));
    } else {
        $data['updated_by'] = current_user_id();
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
        $args = array_values($data);
        $args[] = $id;
        $stmt = $pdo->prepare("UPDATE blocks SET $set, updated_at = NOW() WHERE id = ?");
        $stmt->execute($args);
    }
}
