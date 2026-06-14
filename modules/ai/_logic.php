<?php
/**
 * modules/ai/_logic.php
 * Rule-based AI estate assistant (Phase 1).
 *
 * Safety rules (blueprint Module 16) enforced here:
 *   - Every answer is produced from a live database query; nothing is invented.
 *   - When no data exists, the assistant says so explicitly.
 *   - Each answer cites its source table(s) and the reporting period.
 *   - Worker-level answers require the caller to hold worker.view; otherwise
 *     the assistant refuses to expose private worker data.
 *
 * An optional LLM provider (Phase 2) can be layered on later via the
 * AI_API_* config flags, but it must be constrained to the same data.
 */

/**
 * The intents the assistant understands, with their trigger keywords and
 * example questions (also seeded into ai_queries for the suggestion chips).
 *
 * @return array<string,array{label:string,keywords:string[],example:string,worker:bool}>
 */
function ai_intents(): array
{
    return [
        'lowest_yield'      => ['label' => 'Lowest-yield block',     'keywords' => ['lowest', 'worst', 'yield', 'ffb', 'block'], 'example' => 'Which block has the lowest yield this month?', 'worker' => false],
        'highest_yield'     => ['label' => 'Highest-yield block',    'keywords' => ['highest', 'best', 'top', 'yield', 'block'], 'example' => 'Which block has the highest yield this month?', 'worker' => false],
        'productive_worker' => ['label' => 'Most productive worker', 'keywords' => ['most', 'productive', 'worker', 'best'], 'example' => 'Which worker is most productive?', 'worker' => true],
        'fertilizer_block'  => ['label' => 'Fertilizer used in block','keywords' => ['fertilizer', 'fertiliser', 'used', 'usage', 'applied'], 'example' => 'How much fertilizer was used in a block?', 'worker' => false],
        'abnormal_fuel'     => ['label' => 'Abnormal fuel usage',    'keywords' => ['abnormal', 'fuel', 'vehicle', 'consumption', 'leak'], 'example' => 'Which vehicle has abnormal fuel usage?', 'worker' => false],
        'not_harvested'     => ['label' => 'Blocks not harvested',   'keywords' => ['not', 'harvested', 'recently', 'idle', 'overdue'], 'example' => 'Which blocks have not been harvested recently?', 'worker' => false],
        'estimated_harvest' => ['label' => 'Estimated harvest',      'keywords' => ['estimate', 'estimated', 'forecast', 'projected', 'expected'], 'example' => 'What is the estimated harvest this month?', 'worker' => false],
        'total_ffb'         => ['label' => 'Total FFB this month',   'keywords' => ['total', 'ffb', 'production', 'how much', 'harvest'], 'example' => 'What is the total FFB this month?', 'worker' => false],
        'pending_approvals' => ['label' => 'Pending approvals',      'keywords' => ['pending', 'approval', 'approve', 'waiting'], 'example' => 'How many harvest records are pending approval?', 'worker' => false],
        'low_stock'         => ['label' => 'Low stock items',        'keywords' => ['low', 'stock', 'reorder', 'inventory', 'out of'], 'example' => 'Which items are low in stock?', 'worker' => false],
    ];
}

/**
 * Match a free-text question to an intent by keyword overlap.
 *
 * @return string|null  intent key or null when nothing matches
 */
function ai_match_intent(string $question): ?string
{
    $q = ' ' . strtolower($question) . ' ';
    $best = null; $bestScore = 0;
    foreach (ai_intents() as $key => $meta) {
        $score = 0;
        foreach ($meta['keywords'] as $kw) {
            if (strpos($q, ' ' . $kw) !== false || strpos($q, $kw) !== false) {
                $score++;
            }
        }
        if ($score > $bestScore) { $bestScore = $score; $best = $key; }
    }
    return $bestScore > 0 ? $best : null;
}

/**
 * Find a block id/code mentioned in the question (matched against real codes).
 *
 * @return array{0:?int,1:?string}  [block_id, block_code] or [null, null]
 */
function ai_find_block(PDO $pdo, string $question): array
{
    $q = strtolower($question);
    $rows = $pdo->query('SELECT id, block_code FROM blocks')->fetchAll();
    foreach ($rows as $r) {
        $code = strtolower(trim((string)$r['block_code']));
        if ($code !== '' && strpos($q, $code) !== false) {
            return [(int)$r['id'], $r['block_code']];
        }
    }
    return [null, null];
}

/**
 * Answer a question. Returns a structured result for rendering + logging.
 *
 * @return array{intent:?string,answer:string,source:?string,period:?string,found:bool}
 */
function ai_answer(PDO $pdo, string $question, bool $canViewWorkers): array
{
    $intent = ai_match_intent($question);
    $monthStart = date('Y-m-01');
    $monthEnd   = date('Y-m-t');
    $today      = date('Y-m-d');
    $periodLabel = date('1 M Y', strtotime($monthStart)) . ' to ' . date('t M Y', strtotime($monthEnd));

    $result = ['intent' => $intent, 'answer' => '', 'source' => null, 'period' => null, 'found' => false];

    if ($intent === null) {
        $result['answer'] = "I can only answer questions about the estate's recorded data. Try asking about block yield, worker productivity, fertilizer usage, fuel, pending approvals or low stock.";
        return $result;
    }

    // Guard private worker data.
    if (ai_intents()[$intent]['worker'] && !$canViewWorkers) {
        $result['answer'] = 'You do not have permission to view worker data, so I cannot answer that.';
        return $result;
    }

    switch ($intent) {
        case 'lowest_yield':
        case 'highest_yield':
            $dir = $intent === 'lowest_yield' ? 'ASC' : 'DESC';
            $stmt = $pdo->prepare(
                "SELECT b.block_code, b.acreage, SUM(h.ffb_weight_kg + h.loose_fruit_kg) AS kg
                 FROM harvest_records h JOIN blocks b ON b.id = h.block_id
                 WHERE h.harvest_date BETWEEN ? AND ?
                 GROUP BY h.block_id ORDER BY kg $dir LIMIT 1"
            );
            $stmt->execute([$monthStart, $monthEnd]);
            $row = $stmt->fetch();
            if (!$row) {
                $result['answer'] = 'No harvest has been recorded this month, so yield data is not available.';
            } else {
                $t = (float)$row['kg'] / 1000;
                $perAcre = ((float)$row['acreage'] > 0) ? ' (' . num($t / (float)$row['acreage']) . ' t/acre)' : '';
                $word = $intent === 'lowest_yield' ? 'lowest' : 'highest';
                $result['answer'] = "Block {$row['block_code']} has the {$word} yield this month with " . num($t) . " tonnes FFB{$perAcre}.";
                $result['found'] = true;
            }
            $result['source'] = 'harvest_records'; $result['period'] = $periodLabel;
            break;

        case 'productive_worker':
            $stmt = $pdo->prepare(
                "SELECT w.name, w.worker_code, SUM(hrw.productivity_value) AS p
                 FROM harvest_record_workers hrw
                 JOIN harvest_records h ON h.id = hrw.harvest_record_id
                 JOIN workers w ON w.id = hrw.worker_id
                 WHERE h.harvest_date BETWEEN ? AND ?
                 GROUP BY hrw.worker_id ORDER BY p DESC LIMIT 1"
            );
            $stmt->execute([$monthStart, $monthEnd]);
            $row = $stmt->fetch();
            if (!$row || (float)$row['p'] <= 0) {
                $result['answer'] = 'No worker productivity has been recorded this month.';
            } else {
                $result['answer'] = "{$row['name']} ({$row['worker_code']}) is the most productive worker this month with a recorded productivity of " . num($row['p']) . '.';
                $result['found'] = true;
            }
            $result['source'] = 'harvest_record_workers'; $result['period'] = $periodLabel;
            break;

        case 'fertilizer_block':
            [$blockId, $blockCode] = ai_find_block($pdo, $question);
            if ($blockId === null) {
                $result['answer'] = 'Please mention a block code (e.g. "fertilizer used in A12") so I can look it up.';
                $result['source'] = 'fertilizer_applications';
                break;
            }
            $stmt = $pdo->prepare(
                'SELECT SUM(quantity) AS qty, SUM(total_cost) AS cost FROM fertilizer_applications
                 WHERE block_id = ? AND application_date BETWEEN ? AND ?'
            );
            $stmt->execute([$blockId, $monthStart, $monthEnd]);
            $row = $stmt->fetch();
            if (!$row || $row['qty'] === null) {
                $result['answer'] = "No fertilizer application is recorded for block {$blockCode} this month.";
            } else {
                $result['answer'] = "Block {$blockCode} used " . num($row['qty']) . ' units of fertilizer this month, costing RM ' . num($row['cost']) . '.';
                $result['found'] = true;
            }
            $result['source'] = 'fertilizer_applications'; $result['period'] = $periodLabel;
            break;

        case 'abnormal_fuel':
            $stmt = $pdo->prepare(
                "SELECT a.asset_name, a.asset_code, SUM(i.fuel_litre) AS litres,
                        MIN(i.mileage_reading) AS min_km, MAX(i.mileage_reading) AS max_km
                 FROM fuel_issues i JOIN assets a ON a.id = i.asset_id
                 WHERE i.issue_date BETWEEN ? AND ? GROUP BY i.asset_id"
            );
            $stmt->execute([$monthStart, $monthEnd]);
            $rows = $stmt->fetchAll();
            $rates = [];
            foreach ($rows as $r) {
                $km = ($r['max_km'] !== null && $r['min_km'] !== null) ? (float)$r['max_km'] - (float)$r['min_km'] : 0;
                if ($km > 0 && (float)$r['litres'] > 0) { $rates[] = ['name' => $r['asset_name'], 'code' => $r['asset_code'], 'rate' => $km / (float)$r['litres']]; }
            }
            if (count($rates) < 2) {
                $result['answer'] = 'Not enough fuel and mileage data this month to assess abnormal usage.';
            } else {
                $avg = array_sum(array_column($rates, 'rate')) / count($rates);
                usort($rates, fn($a, $b) => $a['rate'] <=> $b['rate']);
                $worst = $rates[0];
                if ($worst['rate'] < $avg * 0.6) {
                    $result['answer'] = "{$worst['name']} ({$worst['code']}) shows abnormal fuel usage at " . num($worst['rate']) . ' km/L, well below the fleet average of ' . num($avg) . ' km/L.';
                } else {
                    $result['answer'] = 'No vehicle is significantly below the fleet average fuel efficiency (' . num($avg) . ' km/L) this month.';
                }
                $result['found'] = true;
            }
            $result['source'] = 'fuel_issues, assets'; $result['period'] = $periodLabel;
            break;

        case 'not_harvested':
            $stmt = $pdo->prepare(
                "SELECT b.block_code FROM blocks b
                 WHERE b.status = 'active'
                   AND NOT EXISTS (SELECT 1 FROM harvest_records h WHERE h.block_id = b.id AND h.harvest_date >= ?)
                 ORDER BY b.block_code LIMIT 10"
            );
            $cutoff = date('Y-m-d', strtotime('-14 days'));
            $stmt->execute([$cutoff]);
            $codes = array_column($stmt->fetchAll(), 'block_code');
            if (!$codes) {
                $result['answer'] = 'All active blocks have been harvested within the last 14 days.';
            } else {
                $result['answer'] = 'These blocks have not been harvested in the last 14 days: ' . implode(', ', $codes) . '.';
                $result['found'] = true;
            }
            $result['source'] = 'harvest_records, blocks'; $result['period'] = 'since ' . date('d M Y', strtotime($cutoff));
            break;

        case 'estimated_harvest':
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(ffb_weight_kg + loose_fruit_kg),0) FROM harvest_records WHERE harvest_date BETWEEN ? AND ?');
            $stmt->execute([$monthStart, $today]);
            $mtdKg = (float)$stmt->fetchColumn();
            $dayOfMonth = (int)date('j');
            $daysInMonth = (int)date('t');
            $projected = $dayOfMonth > 0 ? ($mtdKg / $dayOfMonth) * $daysInMonth : 0;
            if ($mtdKg <= 0) {
                $result['answer'] = 'No harvest has been recorded yet this month, so an estimate is not available.';
            } else {
                $result['answer'] = 'Month-to-date harvest is ' . kg_to_tonnes($mtdKg) . ' tonnes. At the current daily rate the full month is projected at about ' . kg_to_tonnes($projected) . ' tonnes.';
                $result['found'] = true;
            }
            $result['source'] = 'harvest_records'; $result['period'] = $periodLabel;
            break;

        case 'total_ffb':
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(ffb_weight_kg),0) AS ffb, COALESCE(SUM(loose_fruit_kg),0) AS loose, COUNT(*) AS n FROM harvest_records WHERE harvest_date BETWEEN ? AND ?');
            $stmt->execute([$monthStart, $monthEnd]);
            $row = $stmt->fetch();
            if ((int)$row['n'] === 0) {
                $result['answer'] = 'No harvest has been recorded this month.';
            } else {
                $result['answer'] = 'Total FFB this month is ' . kg_to_tonnes($row['ffb']) . ' tonnes (plus ' . kg_to_tonnes($row['loose']) . ' tonnes loose fruit) across ' . (int)$row['n'] . ' harvest entries.';
                $result['found'] = true;
            }
            $result['source'] = 'harvest_records'; $result['period'] = $periodLabel;
            break;

        case 'pending_approvals':
            $n = (int)$pdo->query("SELECT COUNT(*) FROM harvest_records WHERE approval_status = 'pending'")->fetchColumn();
            $result['answer'] = $n === 0 ? 'There are no harvest records pending approval.' : "There are {$n} harvest record(s) pending approval.";
            $result['found'] = true;
            $result['source'] = 'harvest_records'; $result['period'] = 'current';
            break;

        case 'low_stock':
            $items = [];
            foreach ([['inventory_items', 'item_name'], ['fertilizer_products', 'product_name'], ['chemical_products', 'product_name']] as [$tbl, $col]) {
                try {
                    $stmt = $pdo->query("SELECT $col AS name FROM $tbl WHERE status = 'active' AND current_stock <= minimum_stock ORDER BY name LIMIT 10");
                    foreach ($stmt->fetchAll() as $r) { $items[] = $r['name']; }
                } catch (Throwable $e) { /* table may not exist yet */ }
            }
            if (!$items) {
                $result['answer'] = 'No items are currently at or below their minimum stock level.';
            } else {
                $result['answer'] = 'Items at or below minimum stock: ' . implode(', ', array_slice($items, 0, 15)) . '.';
                $result['found'] = true;
            }
            $result['source'] = 'inventory_items, fertilizer_products, chemical_products'; $result['period'] = 'current';
            break;
    }

    return $result;
}

/**
 * Build a monthly management summary (rule-based insight generation).
 *
 * @return array{title:string,period:string,content:string}
 */
function ai_generate_monthly_summary(PDO $pdo, string $month): array
{
    $from = $month . '-01';
    $to   = date('Y-m-t', strtotime($from));
    $lines = [];

    // Top / bottom blocks by yield.
    $blocks = $pdo->prepare(
        "SELECT b.block_code, SUM(h.ffb_weight_kg + h.loose_fruit_kg)/1000 AS t
         FROM harvest_records h JOIN blocks b ON b.id = h.block_id
         WHERE h.harvest_date BETWEEN ? AND ? GROUP BY h.block_id ORDER BY t DESC"
    );
    $blocks->execute([$from, $to]);
    $rows = $blocks->fetchAll();

    if ($rows) {
        $top = array_slice($rows, 0, 5);
        $bottom = array_slice(array_reverse($rows), 0, 5);
        $lines[] = 'Top performing blocks: ' . implode(', ', array_map(fn($r) => $r['block_code'] . ' (' . num($r['t']) . ' t)', $top)) . '.';
        $lines[] = 'Lowest performing blocks: ' . implode(', ', array_map(fn($r) => $r['block_code'] . ' (' . num($r['t']) . ' t)', $bottom)) . '.';
        $totalT = array_sum(array_column($rows, 't'));
        $lines[] = 'Total FFB harvested: ' . num($totalT) . ' tonnes across ' . count($rows) . ' blocks.';
    } else {
        $lines[] = 'No harvest was recorded for this period.';
    }

    // Costing snapshot (reuses the costing engine if available).
    if (function_exists('compute_costing')) {
        $c = compute_costing($pdo, $from, $to, 0);
        $lines[] = 'Financials: revenue RM ' . num($c['revenue_total']) . ', cost RM ' . num($c['cost_total']) . ', profit RM ' . num($c['profit_total']) . '.';
        if ($c['cost_per_tonne'] !== null) {
            $lines[] = 'Cost per tonne: RM ' . num($c['cost_per_tonne']) . '.';
        }
    }

    // Top worker.
    $w = $pdo->prepare(
        "SELECT wk.name, SUM(hrw.productivity_value) AS p FROM harvest_record_workers hrw
         JOIN harvest_records h ON h.id = hrw.harvest_record_id JOIN workers wk ON wk.id = hrw.worker_id
         WHERE h.harvest_date BETWEEN ? AND ? GROUP BY hrw.worker_id ORDER BY p DESC LIMIT 1"
    );
    $w->execute([$from, $to]);
    if ($wr = $w->fetch()) {
        if ((float)$wr['p'] > 0) { $lines[] = 'Most productive worker: ' . $wr['name'] . ' (' . num($wr['p']) . ').'; }
    }

    // Harvest-delay flag.
    $idle = $pdo->prepare(
        "SELECT COUNT(*) FROM blocks b WHERE b.status = 'active'
         AND NOT EXISTS (SELECT 1 FROM harvest_records h WHERE h.block_id = b.id AND h.harvest_date >= ?)"
    );
    $idle->execute([date('Y-m-d', strtotime($to . ' -14 days'))]);
    $idleCount = (int)$idle->fetchColumn();
    if ($idleCount > 0) {
        $lines[] = "Attention: {$idleCount} active block(s) had no harvest in the final two weeks of the period.";
    }

    // Simple recommendation.
    $lines[] = 'Recommendation: review the lowest-yield blocks for replanting or input adjustment, and investigate any harvest delays flagged above.';

    return [
        'title'   => 'Monthly Management Summary — ' . date('F Y', strtotime($from)),
        'period'  => $month,
        'content' => implode("\n", $lines),
    ];
}
