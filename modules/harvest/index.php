<?php
/**
 * modules/harvest/index.php
 * Harvest records list — filter by date range, estate, status; paginated.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('harvest.view');

$dateFrom = trim((string)input('date_from'));
$dateTo   = trim((string)input('date_to'));
$estateId = (int)input('estate_id');
$status   = trim((string)input('status'));
$page     = current_page();
$offset   = ($page - 1) * PER_PAGE;

$conds = [];
$params = [];
if ($dateFrom !== '' && strtotime($dateFrom)) { $conds[] = 'h.harvest_date >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '' && strtotime($dateTo))     { $conds[] = 'h.harvest_date <= ?'; $params[] = $dateTo; }
if ($estateId > 0)                            { $conds[] = 'h.estate_id = ?';    $params[] = $estateId; }
if (in_array($status, ['pending','approved','rejected'], true)) { $conds[] = 'h.approval_status = ?'; $params[] = $status; }
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM harvest_records h $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT h.*, e.estate_name, d.division_name, b.block_code
        FROM harvest_records h
        JOIN estates e   ON e.id = h.estate_id
        JOIN divisions d ON d.id = h.division_id
        JOIN blocks b    ON b.id = h.block_id
        $where
        ORDER BY h.harvest_date DESC, h.id DESC
        LIMIT $offset, " . PER_PAGE;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$estates = $pdo->query('SELECT id, estate_name FROM estates ORDER BY estate_name')->fetchAll();

$page_title = 'Harvest Records';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Harvest Records</h1>
    <div class="d-flex gap-2">
        <a href="<?= e(url('modules/harvest/daily_report.php')) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-calendar-day me-1"></i> Daily
        </a>
        <a href="<?= e(url('modules/harvest/monthly_report.php')) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-calendar-month me-1"></i> Monthly
        </a>
        <?php if (can('harvest.create')): ?>
        <a href="<?= e(url('modules/harvest/create.php')) ?>" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg me-1"></i> New Entry
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Estate</label>
                <select name="estate_id" class="form-select form-select-sm">
                    <option value="">All estates</option>
                    <?php foreach ($estates as $es): ?>
                        <option value="<?= (int)$es['id'] ?>" <?= $estateId === (int)$es['id'] ? 'selected' : '' ?>>
                            <?= e($es['estate_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['pending','approved','rejected'] as $st): ?>
                        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
                <a href="<?= e(url('modules/harvest/index.php')) ?>" class="btn btn-link btn-sm">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Date</th><th>Block</th><th>Estate / Division</th>
                        <th class="text-end">Bunches</th><th class="text-end">FFB (kg)</th>
                        <th>Status</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No harvest records found.</td></tr>
                <?php else: foreach ($records as $r): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fmt_datetime($r['harvest_date'], 'd M Y')) ?></td>
                        <td class="fw-semibold"><?= e($r['block_code']) ?></td>
                        <td class="small text-muted"><?= e($r['estate_name']) ?> / <?= e($r['division_name']) ?></td>
                        <td class="text-end"><?= num($r['bunches_count'], 0) ?></td>
                        <td class="text-end"><?= num($r['ffb_weight_kg']) ?></td>
                        <td><?= status_badge($r['approval_status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('modules/harvest/view.php?id=' . (int)$r['id'])) ?>"
                               class="btn btn-outline-secondary btn-sm" title="View"><i class="bi bi-eye"></i></a>
                            <?php if (can('harvest.create') && $r['approval_status'] !== 'approved'): ?>
                            <a href="<?= e(url('modules/harvest/edit.php?id=' . (int)$r['id'])) ?>"
                               class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($total, PER_PAGE, $page, 'modules/harvest/index.php') ?>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
