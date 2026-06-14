<?php
/**
 * modules/costing/index.php
 * Estate costing dashboard — revenue, cost, profit and profitability by block.
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
$estates = $pdo->query('SELECT id, estate_name FROM estates ORDER BY estate_name')->fetchAll();
$qs = http_build_query(['date_from' => $from, 'date_to' => $to, 'estate_id' => $estateId ?: '']);

$page_title = 'Costing Dashboard';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Costing Dashboard</h1>
    <div class="d-flex gap-2">
        <?php if (can('costing.manage')): ?>
        <a href="<?= e(url('modules/costing/entries.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square me-1"></i> Cost / Revenue Entries</a>
        <?php endif; ?>
        <?php if (can('report.export')): ?>
        <a href="<?= e(url('modules/costing/export.php?' . $qs)) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i> Excel</a>
        <?php endif; ?>
        <a href="<?= e(url('modules/costing/print.php?' . $qs)) ?>" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i> PDF</a>
    </div>
</div>

<div class="card mb-3"><div class="card-body">
    <form class="row g-2" method="get">
        <div class="col-6 col-md-3"><label class="form-label small mb-1">From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">Estate</label>
            <select name="estate_id" class="form-select form-select-sm"><option value="">All estates</option>
            <?php foreach ($estates as $es): ?><option value="<?= (int)$es['id'] ?>" <?= $estateId === (int)$es['id'] ? 'selected' : '' ?>><?= e($es['estate_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> View</button></div>
    </form>
</div></div>

<!-- Headline KPIs -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value text-success"><?= num($c['revenue_total']) ?></div><div class="kpi-label">Revenue (RM)</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value text-danger"><?= num($c['cost_total']) ?></div><div class="kpi-label">Total Cost (RM)</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value <?= $c['profit_total'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= num($c['profit_total']) ?></div><div class="kpi-label">Profit (RM)</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= $c['cost_per_tonne'] !== null ? num($c['cost_per_tonne']) : '—' ?></div><div class="kpi-label">Cost / Tonne</div></div></div></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= num($c['ffb_tonnes_total']) ?></div><div class="kpi-label">FFB (tonnes)</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= $c['cost_per_acre'] !== null ? num($c['cost_per_acre']) : '—' ?></div><div class="kpi-label">Cost / Acre</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= $c['revenue_total'] > 0 ? num(($c['profit_total'] / $c['revenue_total']) * 100) . '%' : '—' ?></div><div class="kpi-label">Profit Margin</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body"><div class="kpi-value"><?= num($c['acreage_total']) ?></div><div class="kpi-label">Acreage</div></div></div></div>
</div>

<div class="row g-3">
    <!-- Cost breakdown -->
    <div class="col-md-4"><div class="card h-100">
        <div class="card-header bg-white fw-semibold">Cost Breakdown</div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Category</th><th class="text-end">RM</th><th class="text-end">%</th></tr></thead>
            <tbody>
            <?php foreach ($c['breakdown'] as $cat => $amt): if ($amt == 0) continue; ?>
                <tr><td><?= e($cat) ?></td><td class="text-end"><?= num($amt) ?></td><td class="text-end small text-muted"><?= $c['cost_total'] > 0 ? num(($amt / $c['cost_total']) * 100) : '0' ?>%</td></tr>
            <?php endforeach; ?>
            <?php if ($c['cost_total'] == 0): ?><tr><td colspan="3" class="text-center text-muted py-3">No costs in this period.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div></div>

    <!-- Profitability by division -->
    <div class="col-md-8"><div class="card h-100">
        <div class="card-header bg-white fw-semibold">Profitability by Division</div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Division</th><th class="text-end">Tonnes</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Profit</th></tr></thead>
            <tbody>
            <?php if (empty($c['divisions'])): ?><tr><td colspan="5" class="text-center text-muted py-3">No data.</td></tr>
            <?php else: foreach ($c['divisions'] as $d): ?>
                <tr><td><?= e($d['division_name']) ?></td><td class="text-end"><?= num($d['tonnes']) ?></td>
                    <td class="text-end"><?= num($d['revenue']) ?></td><td class="text-end"><?= num($d['cost']) ?></td>
                    <td class="text-end <?= $d['profit'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= num($d['profit']) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>
</div>

<!-- Profitability by block -->
<div class="card mt-3">
    <div class="card-header bg-white fw-semibold">Profitability by Block</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Block</th><th>Division</th><th class="text-end">Acreage</th><th class="text-end">Tonnes</th>
            <th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Profit</th>
            <th class="text-end">Cost/t</th><th class="text-end">Cost/ac</th></tr></thead>
        <tbody>
        <?php if (empty($c['blocks'])): ?><tr><td colspan="9" class="text-center text-muted py-4">No activity in this period.</td></tr>
        <?php else: foreach ($c['blocks'] as $b): ?>
            <tr>
                <td class="fw-semibold"><?= e($b['block_code']) ?></td>
                <td class="small text-muted"><?= e($b['division_name']) ?></td>
                <td class="text-end"><?= num($b['acreage']) ?></td>
                <td class="text-end"><?= num($b['tonnes']) ?></td>
                <td class="text-end"><?= num($b['revenue']) ?></td>
                <td class="text-end"><?= num($b['cost']) ?></td>
                <td class="text-end <?= $b['profit'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= num($b['profit']) ?></td>
                <td class="text-end"><?= $b['cost_per_tonne'] !== null ? num($b['cost_per_tonne']) : '—' ?></td>
                <td class="text-end"><?= $b['cost_per_acre'] !== null ? num($b['cost_per_acre']) : '—' ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
