<?php
/**
 * modules/harvest/_location_picker.php
 * Reusable Estate -> Division -> Block cascade selects.
 *
 * Expects:
 *   $estates      [{id, estate_name}]
 *   $allDivisions [{id, estate_id, division_name}]
 *   $allBlocks    [{id, estate_id, division_id, block_code, block_name}]
 *   $form         with estate_id / division_id / block_id
 *   $required     (bool, default true)
 */
$required = $required ?? true;
$req = $required ? 'required' : '';
?>
<div class="col-md-4">
    <label class="form-label" for="estate_id">Estate <?= $required ? '<span class="text-danger">*</span>' : '' ?></label>
    <select id="estate_id" name="estate_id" class="form-select" <?= $req ?>>
        <option value="">— Select estate —</option>
        <?php foreach ($estates as $es): ?>
            <option value="<?= (int)$es['id'] ?>" <?= (int)$form['estate_id'] === (int)$es['id'] ? 'selected' : '' ?>>
                <?= e($es['estate_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label" for="division_id">Division <?= $required ? '<span class="text-danger">*</span>' : '' ?></label>
    <select id="division_id" name="division_id" class="form-select" <?= $req ?>>
        <option value="">— Select division —</option>
        <?php foreach ($allDivisions as $dv): ?>
            <option value="<?= (int)$dv['id'] ?>" data-estate="<?= (int)$dv['estate_id'] ?>"
                <?= (int)$form['division_id'] === (int)$dv['id'] ? 'selected' : '' ?>>
                <?= e($dv['division_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label" for="block_id">Block <?= $required ? '<span class="text-danger">*</span>' : '' ?></label>
    <select id="block_id" name="block_id" class="form-select" <?= $req ?>>
        <option value="">— Select block —</option>
        <?php foreach ($allBlocks as $bk): ?>
            <option value="<?= (int)$bk['id'] ?>" data-estate="<?= (int)$bk['estate_id'] ?>" data-division="<?= (int)$bk['division_id'] ?>"
                <?= (int)$form['block_id'] === (int)$bk['id'] ? 'selected' : '' ?>>
                <?= e($bk['block_code']) ?><?= $bk['block_name'] ? ' — ' . e($bk['block_name']) : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<script>
// Cascade: estate filters divisions, division filters blocks.
(function () {
    var estate = document.getElementById('estate_id');
    var division = document.getElementById('division_id');
    var block = document.getElementById('block_id');
    if (!estate || !division || !block) return;

    function applyFilter(sel, attr, value, resetWhenHidden) {
        Array.prototype.forEach.call(sel.options, function (opt) {
            if (!opt.value) return;
            var match = opt.getAttribute(attr) === value;
            opt.hidden = !match;
            opt.disabled = !match;
        });
        if (resetWhenHidden) {
            var cur = sel.options[sel.selectedIndex];
            if (cur && cur.value && cur.getAttribute(attr) !== value) sel.value = '';
        }
    }

    function refreshDivisions(reset) { applyFilter(division, 'data-estate', estate.value, reset); }
    function refreshBlocks(reset)    { applyFilter(block, 'data-division', division.value, reset); }

    estate.addEventListener('change', function () { refreshDivisions(true); refreshBlocks(true); });
    division.addEventListener('change', function () { refreshBlocks(true); });

    // Initial load keeps any pre-selected values.
    refreshDivisions(false);
    refreshBlocks(false);
})();
</script>
