<?php
/**
 * modules/costing/_logic.php
 * Estate costing computation.
 *
 * Costs are derived live from the operational modules and merged with the
 * manual cost/revenue entries:
 *   - Fertilizer  : fertilizer_applications.total_cost  (per block)
 *   - Chemical    : spraying_records.total_cost         (per block)
 *   - Fuel        : fuel_issues.total_cost              (per block)
 *   - Maintenance : asset_maintenance_logs.cost         (estate-wide)
 *   - Manual      : estate_cost_entries                 (per block or estate-wide)
 *   - Revenue     : mill_deliveries.total_value + estate_revenue_entries
 *
 * Estate-wide costs and revenue are allocated to blocks by their FFB share.
 */

/**
 * Suggested manual cost categories.
 *
 * @return string[]
 */
function cost_categories(): array
{
    return ['Labour', 'Maintenance', 'Overhead', 'Transport', 'Utilities', 'General', 'Other'];
}

/**
 * Sum a per-block numeric column for a table over a date range, keyed by block_id.
 *
 * @return array<int,float>
 */
function sum_by_block(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int)$row['block_id']] = (float)$row['total'];
    }
    return $out;
}

/**
 * Compute the full costing picture for a period (and optional estate).
 *
 * @return array
 */
function compute_costing(PDO $pdo, string $from, string $to, int $estateId = 0): array
{
    // --- Block universe (optionally filtered to one estate) ------------
    $blkSql = 'SELECT b.id, b.block_code, b.acreage, b.hectare, b.division_id,
                      d.division_name, b.estate_id
               FROM blocks b LEFT JOIN divisions d ON d.id = b.division_id';
    $blkParams = [];
    if ($estateId > 0) { $blkSql .= ' WHERE b.estate_id = ?'; $blkParams[] = $estateId; }
    $blkStmt = $pdo->prepare($blkSql);
    $blkStmt->execute($blkParams);
    $blocks = [];
    foreach ($blkStmt->fetchAll() as $b) { $blocks[(int)$b['id']] = $b; }

    $range = [$from, $to];

    // --- FFB tonnes per block (from harvest) ---------------------------
    $ffb = sum_by_block($pdo,
        'SELECT block_id, SUM(ffb_weight_kg + loose_fruit_kg)/1000 AS total
         FROM harvest_records WHERE harvest_date BETWEEN ? AND ? GROUP BY block_id', $range);

    // --- Per-block direct costs ---------------------------------------
    $fert = sum_by_block($pdo,
        'SELECT block_id, SUM(total_cost) AS total FROM fertilizer_applications
         WHERE application_date BETWEEN ? AND ? AND block_id IS NOT NULL GROUP BY block_id', $range);
    $chem = sum_by_block($pdo,
        'SELECT block_id, SUM(total_cost) AS total FROM spraying_records
         WHERE spray_date BETWEEN ? AND ? AND block_id IS NOT NULL GROUP BY block_id', $range);
    $fuel = sum_by_block($pdo,
        'SELECT block_id, SUM(total_cost) AS total FROM fuel_issues
         WHERE issue_date BETWEEN ? AND ? AND block_id IS NOT NULL GROUP BY block_id', $range);
    $manualBlock = sum_by_block($pdo,
        'SELECT block_id, SUM(amount) AS total FROM estate_cost_entries
         WHERE entry_date BETWEEN ? AND ? AND block_id IS NOT NULL GROUP BY block_id', $range);

    // --- Manual cost entries by category (block-filtered) -------------
    $catStmt = $pdo->prepare(
        'SELECT category, block_id, amount FROM estate_cost_entries WHERE entry_date BETWEEN ? AND ?');
    $catStmt->execute($range);
    $manualByCat = [];
    foreach ($catStmt->fetchAll() as $row) {
        $bid = $row['block_id'] !== null ? (int)$row['block_id'] : null;
        if ($bid !== null && !isset($blocks[$bid])) { continue; } // outside estate filter
        $manualByCat[$row['category']] = ($manualByCat[$row['category']] ?? 0) + (float)$row['amount'];
    }

    // --- Estate-wide costs --------------------------------------------
    $maintStmt = $pdo->prepare('SELECT COALESCE(SUM(cost),0) FROM asset_maintenance_logs WHERE service_date BETWEEN ? AND ?');
    $maintStmt->execute($range);
    $maintenance = (float)$maintStmt->fetchColumn();

    // --- Revenue -------------------------------------------------------
    $millStmt = $pdo->prepare('SELECT COALESCE(SUM(total_value),0) FROM mill_deliveries WHERE delivery_date BETWEEN ? AND ?');
    $millStmt->execute($range);
    $millRevenue = (float)$millStmt->fetchColumn();

    $revEntryStmt = $pdo->prepare('SELECT block_id, amount FROM estate_revenue_entries WHERE entry_date BETWEEN ? AND ?');
    $revEntryStmt->execute($range);
    $manualRevenue = 0.0;
    foreach ($revEntryStmt->fetchAll() as $row) {
        $bid = $row['block_id'] !== null ? (int)$row['block_id'] : null;
        if ($bid !== null && !isset($blocks[$bid])) { continue; }
        $manualRevenue += (float)$row['amount'];
    }
    $revenueTotal = $millRevenue + $manualRevenue;

    // --- Totals & estate-wide allocation base -------------------------
    $totalTonnes = 0.0;
    foreach ($blocks as $bid => $b) { $totalTonnes += $ffb[$bid] ?? 0; }

    $fertAll = $chemAll = $fuelAll = 0.0;
    foreach ($blocks as $bid => $b) {
        $fertAll += $fert[$bid] ?? 0;
        $chemAll += $chem[$bid] ?? 0;
        $fuelAll += $fuel[$bid] ?? 0;
    }
    $manualAll = array_sum($manualByCat);

    // Estate-wide manual = manual entries with no block_id.
    $meStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM estate_cost_entries WHERE entry_date BETWEEN ? AND ? AND block_id IS NULL');
    $meStmt->execute($range);
    $manualEstate = (float)$meStmt->fetchColumn();
    $estateWideCost = $maintenance + $manualEstate;

    $costTotal = $fertAll + $chemAll + $fuelAll + $maintenance + $manualAll;

    // --- Per-block rows ------------------------------------------------
    $blockRows = [];
    foreach ($blocks as $bid => $b) {
        $tonnes = $ffb[$bid] ?? 0;
        $share  = $totalTonnes > 0 ? $tonnes / $totalTonnes : 0;
        $direct = ($fert[$bid] ?? 0) + ($chem[$bid] ?? 0) + ($fuel[$bid] ?? 0) + ($manualBlock[$bid] ?? 0);
        $allocated = $estateWideCost * $share;
        $cost = $direct + $allocated;
        $revenue = $revenueTotal * $share;
        // Skip blocks with no activity at all.
        if ($tonnes == 0 && $direct == 0 && $revenue == 0) { continue; }
        $acreage = (float)($b['acreage'] ?? 0);
        $blockRows[] = [
            'block_code'     => $b['block_code'],
            'division_name'  => $b['division_name'] ?? '—',
            'division_id'    => (int)$b['division_id'],
            'acreage'        => $acreage,
            'tonnes'         => $tonnes,
            'revenue'        => $revenue,
            'cost'           => $cost,
            'profit'         => $revenue - $cost,
            'cost_per_tonne' => $tonnes > 0 ? $cost / $tonnes : null,
            'cost_per_acre'  => $acreage > 0 ? $cost / $acreage : null,
        ];
    }
    usort($blockRows, fn($a, $b) => $b['profit'] <=> $a['profit']);

    // --- Division aggregation -----------------------------------------
    $divAgg = [];
    foreach ($blockRows as $r) {
        $key = $r['division_name'];
        if (!isset($divAgg[$key])) { $divAgg[$key] = ['division_name' => $key, 'tonnes' => 0, 'revenue' => 0, 'cost' => 0, 'profit' => 0]; }
        $divAgg[$key]['tonnes']  += $r['tonnes'];
        $divAgg[$key]['revenue'] += $r['revenue'];
        $divAgg[$key]['cost']    += $r['cost'];
        $divAgg[$key]['profit']  += $r['profit'];
    }
    $divisions = array_values($divAgg);
    usort($divisions, fn($a, $b) => $b['profit'] <=> $a['profit']);

    // --- Cost breakdown ------------------------------------------------
    $breakdown = ['Fertilizer' => $fertAll, 'Chemical' => $chemAll, 'Fuel' => $fuelAll, 'Maintenance' => $maintenance];
    foreach ($manualByCat as $cat => $amt) {
        $breakdown[$cat] = ($breakdown[$cat] ?? 0) + $amt;
    }
    arsort($breakdown);

    $acreageTotal = 0.0;
    foreach ($blocks as $b) { $acreageTotal += (float)($b['acreage'] ?? 0); }

    return [
        'from' => $from, 'to' => $to,
        'revenue_total' => $revenueTotal, 'mill_revenue' => $millRevenue, 'manual_revenue' => $manualRevenue,
        'cost_total' => $costTotal, 'profit_total' => $revenueTotal - $costTotal,
        'ffb_tonnes_total' => $totalTonnes, 'acreage_total' => $acreageTotal,
        'cost_per_tonne' => $totalTonnes > 0 ? $costTotal / $totalTonnes : null,
        'cost_per_acre'  => $acreageTotal > 0 ? $costTotal / $acreageTotal : null,
        'breakdown' => $breakdown,
        'blocks' => $blockRows,
        'divisions' => $divisions,
    ];
}
