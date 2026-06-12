<?php
/**
 * modules/fuel/_tabs.php
 * Sub-navigation tabs for the fuel module.
 */
$cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
$tabs = ['purchases.php' => 'Purchases', 'issues.php' => 'Issues', 'report.php' => 'Report'];

// Current tank balance shown alongside the tabs.
$tank = 0.0;
try { $tank = (float)$pdo->query('SELECT current_litres FROM fuel_tank_balance WHERE id = 1')->fetchColumn(); } catch (Throwable $e) {}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <ul class="nav nav-pills small mb-0">
        <?php foreach ($tabs as $file => $label): ?>
            <li class="nav-item"><a class="nav-link<?= $cur === $file ? ' active' : '' ?>" href="<?= e(url('modules/fuel/' . $file)) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <span class="badge bg-dark fs-6">Tank: <?= num($tank) ?> L</span>
</div>
