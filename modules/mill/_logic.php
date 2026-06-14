<?php
/**
 * modules/mill/_logic.php
 * Shared helpers for the mill-delivery module.
 */

/**
 * Load the dropdown / selection datasets used by the delivery form.
 *
 * @return array{mills: array, vehicles: array, drivers: array, harvests: array}
 */
function mill_form_data(PDO $pdo): array
{
    return [
        'mills'    => $pdo->query("SELECT id, mill_name, default_price_per_tonne FROM mills WHERE status = 'active' ORDER BY mill_name")->fetchAll(),
        'vehicles' => $pdo->query("SELECT id, asset_name FROM assets WHERE status = 'active' ORDER BY asset_name")->fetchAll(),
        'drivers'  => $pdo->query("SELECT id, name FROM workers WHERE status = 'active' ORDER BY name")->fetchAll(),
        // Approved harvest records from the last 30 days for linking.
        'harvests' => $pdo->query(
            "SELECT h.id, h.harvest_date, h.ffb_weight_kg, b.block_code
             FROM harvest_records h JOIN blocks b ON b.id = h.block_id
             WHERE h.approval_status = 'approved' AND h.harvest_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             ORDER BY h.harvest_date DESC"
        )->fetchAll(),
    ];
}

/**
 * Validate a delivery form payload.
 *
 * @return string[]
 */
function validate_delivery(array $form): array
{
    $errors = [];
    if (trim((string)$form['delivery_date']) === '' || !strtotime((string)$form['delivery_date'])) {
        $errors[] = 'A valid delivery date is required.';
    }
    $gross = (float)$form['gross_weight_kg'];
    $tare  = (float)$form['tare_weight_kg'];
    if ($gross < 0 || $tare < 0)       $errors[] = 'Weights cannot be negative.';
    if ($gross > 0 && $tare > $gross)  $errors[] = 'Tare weight cannot exceed gross weight.';
    return $errors;
}

/**
 * Insert (when $id is null) or update a delivery. Computes net weight and
 * total value from gross/tare and price-per-tonne. Returns the delivery id.
 */
function save_delivery(PDO $pdo, array $form, ?int $id): int
{
    $gross = (float)($form['gross_weight_kg'] !== '' ? $form['gross_weight_kg'] : 0);
    $tare  = (float)($form['tare_weight_kg'] !== '' ? $form['tare_weight_kg'] : 0);
    $net   = max(0, $gross - $tare);
    $price = (float)($form['price_per_tonne'] !== '' ? $form['price_per_tonne'] : 0);
    $value = ($net / 1000) * $price;

    $data = [
        'delivery_date'    => $form['delivery_date'],
        'mill_id'          => to_int_or_null($form['mill_id']),
        'vehicle_asset_id' => to_int_or_null($form['vehicle_asset_id']),
        'driver_worker_id' => to_int_or_null($form['driver_worker_id']),
        'time_out'         => trim((string)$form['time_out']) !== '' ? $form['time_out'] : null,
        'time_in'          => trim((string)$form['time_in']) !== '' ? $form['time_in'] : null,
        'gross_weight_kg'  => $gross,
        'tare_weight_kg'   => $tare,
        'net_weight_kg'    => $net,
        'price_per_tonne'  => $price,
        'total_value'      => $value,
        'oer'              => to_decimal_or_null($form['oer']),
        'remarks'          => trim((string)$form['remarks']) !== '' ? $form['remarks'] : null,
    ];

    if ($id === null) {
        $data['created_by'] = current_user_id();
        $cols = implode(', ', array_keys($data));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $pdo->prepare("INSERT INTO mill_deliveries ($cols, created_at) VALUES ($ph, NOW())")->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    }
    $data['updated_by'] = current_user_id();
    $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    $args = array_values($data); $args[] = $id;
    $pdo->prepare("UPDATE mill_deliveries SET $set, updated_at = NOW() WHERE id = ?")->execute($args);
    return $id;
}

/**
 * Replace the harvest records linked to a delivery, copying each record's
 * FFB weight as the consolidated line weight.
 *
 * @param int[] $harvestIds
 */
function save_delivery_items(PDO $pdo, int $deliveryId, array $harvestIds): void
{
    $pdo->prepare('DELETE FROM mill_delivery_items WHERE mill_delivery_id = ?')->execute([$deliveryId]);
    if (!$harvestIds) {
        return;
    }
    $ins = $pdo->prepare('INSERT INTO mill_delivery_items (mill_delivery_id, harvest_record_id, ffb_weight_kg, created_at) VALUES (?, ?, ?, NOW())');
    $get = $pdo->prepare('SELECT ffb_weight_kg FROM harvest_records WHERE id = ?');
    foreach ($harvestIds as $hid) {
        $hid = (int)$hid;
        if ($hid <= 0) {
            continue;
        }
        $get->execute([$hid]);
        $w = (float)($get->fetchColumn() ?: 0);
        $ins->execute([$deliveryId, $hid, $w]);
    }
}
