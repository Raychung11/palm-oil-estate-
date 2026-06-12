<?php
/**
 * cron/monthly_summary.php
 * Snapshot the previous month's estate costing into monthly_estate_summary.
 *
 * Run from CLI or a Hostinger cron job, e.g. on the 1st of each month:
 *   php /home/USER/public_html/estate-bos/cron/monthly_summary.php
 *
 * Optionally pass a month argument (YYYY-MM); defaults to last month.
 */

// CLI-only guard — never expose this over the web.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('This script runs from the command line only.');
}

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/inc/functions.php';
require_once dirname(__DIR__) . '/modules/costing/_logic.php';

// current_user_id() is referenced by some shared helpers; stub it for CLI.
if (!function_exists('current_user_id')) {
    function current_user_id(): ?int { return null; }
}

$month = $argv[1] ?? date('Y-m', strtotime('first day of last month'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    fwrite(STDERR, "Invalid month '$month' (expected YYYY-MM).\n");
    exit(1);
}
$from = $month . '-01';
$to   = date('Y-m-t', strtotime($from));

$c = compute_costing($pdo, $from, $to, 0);

$pdo->prepare(
    'INSERT INTO monthly_estate_summary (summary_month, ffb_tonnes, revenue, total_cost, profit, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE ffb_tonnes = VALUES(ffb_tonnes), revenue = VALUES(revenue),
        total_cost = VALUES(total_cost), profit = VALUES(profit), created_at = NOW()'
)->execute([$month, $c['ffb_tonnes_total'], $c['revenue_total'], $c['cost_total'], $c['profit_total']]);

fwrite(STDOUT, sprintf(
    "Monthly summary for %s: FFB %.2ft, revenue RM%.2f, cost RM%.2f, profit RM%.2f\n",
    $month, $c['ffb_tonnes_total'], $c['revenue_total'], $c['cost_total'], $c['profit_total']
));
exit(0);
