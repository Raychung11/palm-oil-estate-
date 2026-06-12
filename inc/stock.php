<?php
/**
 * inc/stock.php
 * Generic stock-movement helper shared by the fertilizer and chemical
 * modules (and reusable by inventory later). The product and movement
 * table names are passed in so one routine serves every stock domain.
 */

/**
 * Record a stock movement and adjust the product's current_stock.
 *
 *   type 'in'         -> current_stock += quantity
 *   type 'out'        -> current_stock -= quantity
 *   type 'adjustment' -> current_stock += quantity   (quantity may be negative)
 *
 * @param string $productTable   e.g. 'fertilizer_products'
 * @param string $movementTable  e.g. 'fertilizer_stock_movements'
 * @param array  $opts           unit_cost, reference_type, reference_id, remarks, movement_date
 */
function record_stock_movement(
    PDO $pdo,
    string $productTable,
    string $movementTable,
    int $productId,
    string $type,
    float $quantity,
    array $opts = []
): void {
    $type = in_array($type, ['in', 'out', 'adjustment'], true) ? $type : 'adjustment';
    $date = $opts['movement_date'] ?? date('Y-m-d');

    $pdo->prepare(
        "INSERT INTO $movementTable
            (product_id, movement_date, movement_type, quantity, unit_cost, reference_type, reference_id, remarks, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    )->execute([
        $productId,
        $date,
        $type,
        $quantity,
        $opts['unit_cost'] ?? null,
        $opts['reference_type'] ?? null,
        $opts['reference_id'] ?? null,
        $opts['remarks'] ?? null,
        current_user_id(),
    ]);

    // Signed delta applied to the running balance.
    $delta = $type === 'out' ? -abs($quantity) : ($type === 'in' ? abs($quantity) : $quantity);
    $pdo->prepare("UPDATE $productTable SET current_stock = current_stock + ?, updated_at = NOW() WHERE id = ?")
        ->execute([$delta, $productId]);
}
