<?php
/**
 * modules/costing/export.php
 * CSV export of the block profitability table (opens in Excel).
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('report.export');

$from     = trim((string)input('date_from')) ?: date('Y-m-01');
$to       = trim((string)input('date_to'))   ?: date('Y-m-t');
if (!strtotime($from)) $from = date('Y-m-01');
if (!strtotime($to))   $to   = date('Y-m-t');
$estateId = (int)input('estate_id');

$c = compute_costing($pdo, $from, $to, $estateId);

$rows = [];
foreach ($c['blocks'] as $b) {
    $rows[] = [
        $b['block_code'], $b['division_name'],
        number_format($b['acreage'], 2, '.', ''),
        number_format($b['tonnes'], 2, '.', ''),
        number_format($b['revenue'], 2, '.', ''),
        number_format($b['cost'], 2, '.', ''),
        number_format($b['profit'], 2, '.', ''),
        $b['cost_per_tonne'] !== null ? number_format($b['cost_per_tonne'], 2, '.', '') : '',
        $b['cost_per_acre'] !== null ? number_format($b['cost_per_acre'], 2, '.', '') : '',
    ];
}
// Totals row.
$rows[] = ['TOTAL', '', number_format($c['acreage_total'], 2, '.', ''), number_format($c['ffb_tonnes_total'], 2, '.', ''),
    number_format($c['revenue_total'], 2, '.', ''), number_format($c['cost_total'], 2, '.', ''),
    number_format($c['profit_total'], 2, '.', ''),
    $c['cost_per_tonne'] !== null ? number_format($c['cost_per_tonne'], 2, '.', '') : '',
    $c['cost_per_acre'] !== null ? number_format($c['cost_per_acre'], 2, '.', '') : ''];

log_activity($pdo, current_user_id(), 'export', 'costing', "Costing CSV $from..$to");

send_csv(
    'costing_' . $from . '_to_' . $to,
    ['Block', 'Division', 'Acreage', 'Tonnes', 'Revenue (RM)', 'Cost (RM)', 'Profit (RM)', 'Cost/Tonne', 'Cost/Acre'],
    $rows
);
