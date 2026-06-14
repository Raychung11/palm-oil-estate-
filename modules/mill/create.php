<?php
/**
 * modules/mill/create.php
 * Create a mill delivery trip.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('mill.manage');

$data = mill_form_data($pdo);
$mills = $data['mills']; $vehicles = $data['vehicles']; $drivers = $data['drivers']; $harvests = $data['harvests'];

$errors = [];
$form = ['delivery_date' => date('Y-m-d'), 'mill_id' => '', 'vehicle_asset_id' => '', 'driver_worker_id' => '',
         'time_out' => '', 'time_in' => '', 'gross_weight_kg' => '', 'tare_weight_kg' => '',
         'price_per_tonne' => '', 'oer' => '', 'remarks' => ''];
$linked = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (array_keys($form) as $k) { $form[$k] = trim((string)input($k)); }
    $linked = array_map('intval', (array)($_POST['harvest_ids'] ?? []));
    $errors = validate_delivery($form);

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $deliveryId = save_delivery($pdo, $form, null);
            save_delivery_items($pdo, $deliveryId, $linked);

            $stored = handle_upload($_FILES['ticket'] ?? [], 'tickets');
            if ($stored !== null || trim((string)input('ticket_no')) !== '') {
                $pdo->prepare('INSERT INTO weighbridge_tickets (mill_delivery_id, ticket_no, file_path, gross_weight_kg, tare_weight_kg, net_weight_kg, created_by, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
                    ->execute([$deliveryId, trim((string)input('ticket_no')) ?: null, $stored,
                        to_decimal_or_null($form['gross_weight_kg']), to_decimal_or_null($form['tare_weight_kg']),
                        max(0, (float)$form['gross_weight_kg'] - (float)$form['tare_weight_kg']), current_user_id()]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not save the delivery.';
        }
        if (!$errors) {
            log_activity($pdo, current_user_id(), 'create', 'mill', 'Created delivery #' . $deliveryId);
            set_flash('success', 'Delivery recorded.');
            redirect('modules/mill/view.php?id=' . $deliveryId);
        }
    }
}

$page_title  = 'New Mill Delivery';
$submitLabel = 'Save Delivery';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
