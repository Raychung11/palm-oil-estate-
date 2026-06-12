<?php
/**
 * modules/fertilizer/_tabs.php
 * Sub-navigation tabs for the fertilizer module.
 */
$cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
$tabs = [
    'products.php'     => 'Products',
    'stock.php'        => 'Stock',
    'applications.php' => 'Applications',
    'schedules.php'    => 'Schedule',
    'report.php'       => 'Report',
];
?>
<ul class="nav nav-pills mb-3 small">
    <?php foreach ($tabs as $file => $label): ?>
        <li class="nav-item">
            <a class="nav-link<?= $cur === $file ? ' active' : '' ?>" href="<?= e(url('modules/fertilizer/' . $file)) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
