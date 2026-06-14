<?php
/**
 * modules/estate/index.php
 * Estate master — list of estates with division/block roll-ups.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_permission('estate.view');

$search = trim((string)input('q'));
$page   = current_page();
$offset = ($page - 1) * PER_PAGE;

$where  = '';
$params = [];
if ($search !== '') {
    $where  = 'WHERE e.estate_name LIKE ? OR e.location LIKE ? OR e.estate_code LIKE ?';
    $like   = '%' . $search . '%';
    $params = [$like, $like, $like];
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM estates e $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT e.*,
               (SELECT COUNT(*) FROM divisions d WHERE d.estate_id = e.id) AS division_count,
               (SELECT COUNT(*) FROM blocks b WHERE b.estate_id = e.id)    AS block_count
        FROM estates e
        $where
        ORDER BY e.estate_name ASC
        LIMIT $offset, " . PER_PAGE;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$estates = $stmt->fetchAll();

$page_title = 'Estates';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Estates</h1>
    <?php if (can('estate.manage')): ?>
    <a href="<?= e(url('modules/estate/create.php')) ?>" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Add Estate
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-sm-8 col-md-5">
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Search name, code or location…" value="<?= e($search) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i> Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= e(url('modules/estate/index.php')) ?>" class="btn btn-link btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Estate</th><th>Location</th>
                        <th class="text-end">Acreage</th><th class="text-end">Hectare</th>
                        <th class="text-center">Divisions</th><th class="text-center">Blocks</th>
                        <th>Status</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($estates)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No estates yet.</td></tr>
                <?php else: foreach ($estates as $row): ?>
                    <tr>
                        <td class="fw-semibold">
                            <?= e($row['estate_name']) ?>
                            <?php if ($row['estate_code']): ?><div class="text-muted small"><?= e($row['estate_code']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e($row['location'] ?? '—') ?></td>
                        <td class="text-end"><?= num($row['total_acreage']) ?></td>
                        <td class="text-end"><?= num($row['total_hectare']) ?></td>
                        <td class="text-center">
                            <a href="<?= e(url('modules/estate/divisions.php?estate_id=' . (int)$row['id'])) ?>">
                                <?= (int)$row['division_count'] ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="<?= e(url('modules/estate/blocks.php?estate_id=' . (int)$row['id'])) ?>">
                                <?= (int)$row['block_count'] ?>
                            </a>
                        </td>
                        <td><?= status_badge($row['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('modules/estate/divisions.php?estate_id=' . (int)$row['id'])) ?>"
                               class="btn btn-outline-secondary btn-sm" title="Divisions"><i class="bi bi-diagram-3"></i></a>
                            <?php if (can('estate.manage')): ?>
                            <a href="<?= e(url('modules/estate/edit.php?id=' . (int)$row['id'])) ?>"
                               class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($total, PER_PAGE, $page, 'modules/estate/index.php') ?>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
