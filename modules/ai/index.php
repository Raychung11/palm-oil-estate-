<?php
/**
 * modules/ai/index.php
 * AI estate assistant — ask a question, get a data-backed answer.
 */
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_logic.php';
require_permission('ai.use');

$question = trim((string)input('q'));
$answer = null;

if ($question !== '') {
    $answer = ai_answer($pdo, $question, can('worker.view'));
    $pdo->prepare('INSERT INTO ai_query_logs (user_id, question, intent_key, answered, created_at) VALUES (?, ?, ?, ?, NOW())')
        ->execute([current_user_id(), mb_substr($question, 0, 500), $answer['intent'], $answer['found'] ? 1 : 0]);
}

// Suggestion chips (seeded examples, falling back to the intent catalogue).
$suggestions = $pdo->query("SELECT question FROM ai_queries WHERE status = 'active' ORDER BY sort_order, id LIMIT 12")->fetchAll();
if (!$suggestions) {
    $suggestions = array_map(fn($m) => ['question' => $m['example']], ai_intents());
}

$recent = $pdo->prepare('SELECT question, answered, created_at FROM ai_query_logs WHERE user_id = ? ORDER BY id DESC LIMIT 8');
$recent->execute([current_user_id()]);
$recent = $recent->fetchAll();

$page_title = 'AI Estate Assistant';
require __DIR__ . '/../../inc/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0"><i class="bi bi-robot me-1"></i> AI Estate Assistant</h1>
    <?php if (can('ai.manage')): ?>
    <a href="<?= e(url('modules/ai/insights.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-lightbulb me-1"></i> Insights</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-body">
            <form method="get" class="d-flex gap-2">
                <input type="text" name="q" class="form-control" placeholder="Ask about yield, workers, fertilizer, fuel, stock…" value="<?= e($question) ?>" autofocus>
                <button class="btn btn-success"><i class="bi bi-send"></i></button>
            </form>
            <div class="mt-3 d-flex flex-wrap gap-2">
                <?php foreach ($suggestions as $s): ?>
                    <a href="<?= e(url('modules/ai/index.php?q=' . urlencode($s['question']))) ?>" class="badge bg-light text-dark text-decoration-none border py-2 px-2"><?= e($s['question']) ?></a>
                <?php endforeach; ?>
            </div>
        </div></div>

        <?php if ($answer !== null): ?>
        <div class="card mb-3 border-success">
            <div class="card-body">
                <div class="d-flex gap-2">
                    <i class="bi bi-robot fs-4 text-success"></i>
                    <div class="flex-grow-1">
                        <div class="text-muted small mb-1">You asked: <?= e($question) ?></div>
                        <p class="mb-2 fs-6"><?= e($answer['answer']) ?></p>
                        <?php if ($answer['source']): ?>
                        <div class="small text-muted">
                            <i class="bi bi-database"></i> Source: <?= e($answer['source']) ?>
                            <?php if ($answer['period']): ?> &middot; Period: <?= e($answer['period']) ?><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="alert alert-light border small text-muted">
            <i class="bi bi-shield-check me-1"></i> The assistant answers only from Estate BOS data. If data is unavailable it will say so, it never invents figures, and it will not expose worker data to users without permission.
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Your Recent Questions</div>
            <div class="list-group list-group-flush">
                <?php if (empty($recent)): ?>
                    <div class="list-group-item text-muted small">No questions yet.</div>
                <?php else: foreach ($recent as $r): ?>
                    <a href="<?= e(url('modules/ai/index.php?q=' . urlencode($r['question']))) ?>" class="list-group-item list-group-item-action small">
                        <?= e($r['question']) ?>
                        <span class="float-end"><?= $r['answered'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>' ?></span>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
