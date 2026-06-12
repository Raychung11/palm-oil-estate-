<?php
/**
 * modules/chemicals/_tabs.php
 * Sub-navigation tabs for the chemical module.
 */
$cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
$tabs = [
    'products.php' => 'Products',
    'stock.php'    => 'Stock',
    'spraying.php' => 'Spraying',
    'report.php'   => 'Report',
];
?>
<ul class="nav nav-pills mb-3 small">
    <?php foreach ($tabs as $file => $label): ?>
        <li class="nav-item">
            <a class="nav-link<?= $cur === $file ? ' active' : '' ?>" href="<?= e(url('modules/chemicals/' . $file)) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
