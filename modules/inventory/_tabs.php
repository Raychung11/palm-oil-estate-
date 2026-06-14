<?php
/**
 * modules/inventory/_tabs.php
 * Sub-navigation tabs for the inventory module.
 */
$cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
$tabs = ['items.php' => 'Items', 'stock.php' => 'Stock Movements', 'report.php' => 'Valuation & Low Stock'];
?>
<ul class="nav nav-pills mb-3 small">
    <?php foreach ($tabs as $file => $label): ?>
        <li class="nav-item"><a class="nav-link<?= $cur === $file ? ' active' : '' ?>" href="<?= e(url('modules/inventory/' . $file)) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>
