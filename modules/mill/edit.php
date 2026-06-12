<?php
/**
 * modules/mill/edit.php
 * Edit a delivery (reconciled deliveries are locked).
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('mill.manage');

$id = (int)input('id');
if ($id <= 0) { set_flash('danger', 'Invalid delivery.'); redirect('modules/mill/index.php'); }

$stmt = $pdo->prepare('SELECT * FROM mill_deliveries WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$delivery = $stmt->fetch();
if (!$delivery) { set_flash('danger', 'Delivery not found.'); redirect('modules/mill/index.php'); }
if ($delivery['status'] === 'reconciled') {
    set_flash('warning', 'Reconciled deliveries cannot be edited.');
    redirect('modules/mill/view.php?id=' . $id);
}

$data = mill_form_data($pdo);
$mills = $data['mills']; $vehicles = $data['vehicles']; $drivers = $data['drivers']; $harvests = $data['harvests'];

// Include already-linked harvest records even if older than 30 days.
$linkStmt = $pdo->prepare(
    'SELECT h.id, h.harvest_date, h.ffb_weight_kg, b.block_code
     FROM mill_delivery_items i JOIN harvest_records h ON h.id = i.harvest_record_id
     JOIN blocks b ON b.id = h.block_id WHERE i.mill_delivery_id = ?'
);
$linkStmt->execute([$id]);
$linkedRows = $linkStmt->fetchAll();
$linked = array_map(fn($r) => (int)$r['id'], $linkedRows);
$existingIds = array_map(fn($r) => (int)$r['id'], $harvests);
foreach ($linkedRows as $r) {
    if (!in_array((int)$r['id'], $existingIds, true)) { $harvests[] = $r; }
}

$ticketStmt = $pdo->prepare('SELECT * FROM weighbridge_tickets WHERE mill_delivery_id = ? ORDER BY id');
$ticketStmt->execute([$id]);
$existingTickets = $ticketStmt->fetchAll();

$fields = ['delivery_date','mill_id','vehicle_asset_id','driver_worker_id','time_out','time_in',
           'gross_weight_kg','tare_weight_kg','price_per_tonne','oer','remarks'];
$errors = [];
$form = [];
foreach ($fields as $f) { $form[$f] = (string)($delivery[$f] ?? ''); }
// Trim seconds from TIME columns for the input fields.
foreach (['time_out', 'time_in'] as $tf) { if ($form[$tf] !== '') { $form[$tf] = substr($form[$tf], 0, 5); } }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($fields as $f) { $form[$f] = trim((string)input($f)); }
    $linked = array_map('intval', (array)($_POST['harvest_ids'] ?? []));
    $errors = validate_delivery($form);

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            save_delivery($pdo, $form, $id);
            save_delivery_items($pdo, $id, $linked);

            $stored = handle_upload($_FILES['ticket'] ?? [], 'tickets');
            if ($stored !== null || trim((string)input('ticket_no')) !== '') {
                $pdo->prepare('INSERT INTO weighbridge_tickets (mill_delivery_id, ticket_no, file_path, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')
                    ->execute([$id, trim((string)input('ticket_no')) ?: null, $stored, current_user_id()]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not update the delivery.';
        }
        if (!$errors) {
            log_activity($pdo, current_user_id(), 'update', 'mill', 'Updated delivery #' . $id);
            set_flash('success', 'Delivery updated.');
            redirect('modules/mill/view.php?id=' . $id);
        }
    }
}

$page_title  = 'Edit Mill Delivery';
$submitLabel = 'Update Delivery';
require __DIR__ . '/../../inc/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../inc/footer.php';
