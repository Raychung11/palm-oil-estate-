<?php
/**
 * modules/costing/print.php
 * Print-friendly costing report. Use the browser's "Save as PDF" to export
 * (no Dompdf / Composer dependency required on shared hosting).
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('costing.view');

$from     = trim((string)input('date_from')) ?: date('Y-m-01');
$to       = trim((string)input('date_to'))   ?: date('Y-m-t');
if (!strtotime($from)) $from = date('Y-m-01');
if (!strtotime($to))   $to   = date('Y-m-t');
$estateId = (int)input('estate_id');

$c = compute_costing($pdo, $from, $to, $estateId);
$estateName = 'All Estates';
if ($estateId > 0) {
    $s = $pdo->prepare('SELECT estate_name FROM estates WHERE id = ?');
    $s->execute([$estateId]);
    $estateName = (string)($s->fetchColumn() ?: 'Estate');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Costing Report</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #222; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
        th { background: #f0f3f5; }
        td.num, th.num { text-align: right; }
        .kpis { margin-top: 12px; }
        .kpis span { display: inline-block; margin-right: 24px; }
        .kpis strong { font-size: 15px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="noprint" style="margin-bottom:12px;">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    <h1>Estate Costing Report</h1>
    <div class="muted"><?= e($estateName) ?> &middot; <?= e(date('d M Y', strtotime($from))) ?> – <?= e(date('d M Y', strtotime($to))) ?></div>

    <div class="kpis">
        <span>Revenue: <strong>RM <?= num($c['revenue_total']) ?></strong></span>
        <span>Cost: <strong>RM <?= num($c['cost_total']) ?></strong></span>
        <span>Profit: <strong>RM <?= num($c['profit_total']) ?></strong></span>
        <span>FFB: <strong><?= num($c['ffb_tonnes_total']) ?> t</strong></span>
        <span>Cost/t: <strong><?= $c['cost_per_tonne'] !== null ? num($c['cost_per_tonne']) : '—' ?></strong></span>
        <span>Cost/acre: <strong><?= $c['cost_per_acre'] !== null ? num($c['cost_per_acre']) : '—' ?></strong></span>
    </div>

    <h3>Profitability by Block</h3>
    <table>
        <thead><tr><th>Block</th><th>Division</th><th class="num">Acreage</th><th class="num">Tonnes</th>
            <th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Cost/t</th></tr></thead>
        <tbody>
        <?php foreach ($c['blocks'] as $b): ?>
            <tr>
                <td><?= e($b['block_code']) ?></td><td><?= e($b['division_name']) ?></td>
                <td class="num"><?= num($b['acreage']) ?></td><td class="num"><?= num($b['tonnes']) ?></td>
                <td class="num"><?= num($b['revenue']) ?></td><td class="num"><?= num($b['cost']) ?></td>
                <td class="num"><?= num($b['profit']) ?></td>
                <td class="num"><?= $b['cost_per_tonne'] !== null ? num($b['cost_per_tonne']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($c['blocks'])): ?><tr><td colspan="8" style="text-align:center;">No activity in this period.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><th>TOTAL</th><th></th><th class="num"><?= num($c['acreage_total']) ?></th><th class="num"><?= num($c['ffb_tonnes_total']) ?></th>
            <th class="num"><?= num($c['revenue_total']) ?></th><th class="num"><?= num($c['cost_total']) ?></th>
            <th class="num"><?= num($c['profit_total']) ?></th><th class="num"></th></tr></tfoot>
    </table>

    <h3>Cost Breakdown</h3>
    <table>
        <thead><tr><th>Category</th><th class="num">RM</th></tr></thead>
        <tbody>
        <?php foreach ($c['breakdown'] as $cat => $amt): if ($amt == 0) continue; ?>
            <tr><td><?= e($cat) ?></td><td class="num"><?= num($amt) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p class="muted" style="margin-top:18px;">Generated <?= e(date('d M Y H:i')) ?> by Estate BOS.</p>
</body>
</html>
