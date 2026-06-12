<?php
/**
 * modules/ai/insights.php
 * Generate and review AI management insights (monthly summaries).
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_once dirname(__DIR__) . '/costing/_logic.php';
require_permission('ai.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = input('action');
    if ($action === 'generate') {
        $month = trim((string)input('month')) ?: date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) { $month = date('Y-m'); }
        $summary = ai_generate_monthly_summary($pdo, $month);
        $pdo->prepare('INSERT INTO ai_insights (insight_type, period, title, content, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute(['monthly_summary', $summary['period'], $summary['title'], $summary['content'], current_user_id()]);
        log_activity($pdo, current_user_id(), 'generate', 'ai', 'Generated insight for ' . $month);
        set_flash('success', 'Insight generated.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM ai_insights WHERE id = ?')->execute([(int)input('id')]);
        set_flash('success', 'Insight deleted.');
    }
    redirect('modules/ai/insights.php');
}

$insights = $pdo->query('SELECT i.*, u.name AS author FROM ai_insights i LEFT JOIN users u ON u.id = i.created_by ORDER BY i.id DESC LIMIT 50')->fetchAll();

$page_title = 'AI Insights';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">AI Insights</h1>
    <a href="<?= e(url('modules/ai/index.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Assistant</a>
</div>

<div class="card mb-3"><div class="card-body">
    <form method="post" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate">
        <div class="col-auto">
            <label class="form-label small mb-1">Month</label>
            <input type="month" name="month" class="form-control form-control-sm" value="<?= e(date('Y-m')) ?>" max="<?= e(date('Y-m')) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-success btn-sm"><i class="bi bi-stars me-1"></i> Generate Monthly Summary</button>
        </div>
    </form>
</div></div>

<?php if (empty($insights)): ?>
    <div class="card"><div class="card-body text-center text-muted py-5">No insights generated yet.</div></div>
<?php else: foreach ($insights as $i): ?>
    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><?= e($i['title']) ?></span>
            <span>
                <span class="text-muted small fw-normal me-2"><?= e(fmt_datetime($i['created_at'])) ?><?= $i['author'] ? ' · ' . e($i['author']) : '' ?></span>
                <form method="post" class="d-inline" data-confirm="Delete this insight?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>
            </span>
        </div>
        <div class="card-body">
            <?php foreach (explode("\n", (string)$i['content']) as $line): if (trim($line) === '') continue; ?>
                <p class="mb-2"><i class="bi bi-dot"></i> <?= e($line) ?></p>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; endif; ?>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
