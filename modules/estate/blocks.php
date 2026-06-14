<?php
/**
 * modules/estate/blocks.php
 * Block listing — searchable, paginated, filterable by estate and division.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('estate.view');

$estateId   = (int)input('estate_id');
$divisionId = (int)input('division_id');
$search     = trim((string)input('q'));
$page       = current_page();
$offset     = ($page - 1) * PER_PAGE;

// Build filter clause with bound params.
$conds  = [];
$params = [];
if ($estateId > 0)   { $conds[] = 'b.estate_id = ?';   $params[] = $estateId; }
if ($divisionId > 0) { $conds[] = 'b.division_id = ?'; $params[] = $divisionId; }
if ($search !== '')  {
    $conds[] = '(b.block_code LIKE ? OR b.block_name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like;
}
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM blocks b $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT b.*, e.estate_name, d.division_name
        FROM blocks b
        JOIN estates e   ON e.id = b.estate_id
        JOIN divisions d ON d.id = b.division_id
        $where
        ORDER BY e.estate_name, d.division_name, b.block_code
        LIMIT $offset, " . PER_PAGE;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$blocks = $stmt->fetchAll();

// Dropdown data for filters.
$estates = $pdo->query('SELECT id, estate_name FROM estates ORDER BY estate_name')->fetchAll();
$divisions = [];
if ($estateId > 0) {
    $dStmt = $pdo->prepare('SELECT id, division_name FROM divisions WHERE estate_id = ? ORDER BY division_name');
    $dStmt->execute([$estateId]);
    $divisions = $dStmt->fetchAll();
}

$createUrl = 'modules/estate/block_create.php' . ($estateId ? '?estate_id=' . $estateId : '');

$page_title = 'Blocks';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Blocks</h1>
    <?php if (can('estate.manage')): ?>
    <a href="<?= e(url($createUrl)) ?>" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Add Block
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-4 col-md-3">
                <select name="estate_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All estates</option>
                    <?php foreach ($estates as $es): ?>
                        <option value="<?= (int)$es['id'] ?>" <?= $estateId === (int)$es['id'] ? 'selected' : '' ?>>
                            <?= e($es['estate_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <select name="division_id" class="form-select form-select-sm" onchange="this.form.submit()" <?= $divisions ? '' : 'disabled' ?>>
                    <option value="">All divisions</option>
                    <?php foreach ($divisions as $dv): ?>
                        <option value="<?= (int)$dv['id'] ?>" <?= $divisionId === (int)$dv['id'] ? 'selected' : '' ?>>
                            <?= e($dv['division_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-md-4">
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Search block code or name…" value="<?= e($search) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
                <a href="<?= e(url('modules/estate/blocks.php')) ?>" class="btn btn-link btn-sm">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Block</th><th>Estate / Division</th>
                        <th class="text-end">Acreage</th><th class="text-end">Palms</th>
                        <th class="text-center">Age</th><th>Status</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($blocks)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No blocks found.</td></tr>
                <?php else: foreach ($blocks as $b): ?>
                    <tr>
                        <td class="fw-semibold">
                            <?= e($b['block_code']) ?>
                            <?php if ($b['block_name']): ?><div class="text-muted small"><?= e($b['block_name']) ?></div><?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e($b['estate_name']) ?> / <?= e($b['division_name']) ?></td>
                        <td class="text-end"><?= num($b['acreage']) ?></td>
                        <td class="text-end"><?= num($b['palm_count'], 0) ?></td>
                        <td class="text-center"><?= $b['palm_age'] !== null ? (int)$b['palm_age'] : '—' ?></td>
                        <td><?= status_badge($b['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('modules/estate/plots.php?block_id=' . (int)$b['id'])) ?>"
                               class="btn btn-outline-secondary btn-sm" title="Plots"><i class="bi bi-grid-3x3-gap"></i></a>
                            <?php if (can('estate.manage')): ?>
                            <a href="<?= e(url('modules/estate/block_edit.php?id=' . (int)$b['id'])) ?>"
                               class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($total, PER_PAGE, $page, 'modules/estate/blocks.php') ?>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
