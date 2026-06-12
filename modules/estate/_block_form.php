<?php
/**
 * modules/estate/_block_form.php
 * Shared block create/edit form body.
 * Expects: $form, $errors, $estates (all), $allDivisions (all), $page_title.
 * Division options carry data-estate so they can be filtered client-side.
 */
$blockStatuses = [
    'active'    => 'Active (Normal)',
    'attention' => 'Attention',
    'critical'  => 'Critical',
    'scheduled' => 'Scheduled',
    'inactive'  => 'Inactive',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($page_title) ?></h1>
    <a href="<?= e(url('modules/estate/blocks.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-md-4">
                <label class="form-label" for="estate_id">Estate <span class="text-danger">*</span></label>
                <select id="estate_id" name="estate_id" class="form-select" required>
                    <option value="">— Select estate —</option>
                    <?php foreach ($estates as $es): ?>
                        <option value="<?= (int)$es['id'] ?>" <?= (int)$form['estate_id'] === (int)$es['id'] ? 'selected' : '' ?>>
                            <?= e($es['estate_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="division_id">Division <span class="text-danger">*</span></label>
                <select id="division_id" name="division_id" class="form-select" required>
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
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <?php foreach ($blockStatuses as $val => $label): ?>
                        <option value="<?= e($val) ?>" <?= $form['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="block_code">Block Code <span class="text-danger">*</span></label>
                <input type="text" id="block_code" name="block_code" class="form-control" value="<?= e($form['block_code']) ?>" required>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="block_name">Block Name</label>
                <input type="text" id="block_name" name="block_name" class="form-control" value="<?= e($form['block_name']) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="acreage">Acreage</label>
                <input type="number" step="0.01" min="0" id="acreage" name="acreage" class="form-control" value="<?= e($form['acreage']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="hectare">Hectare</label>
                <input type="number" step="0.01" min="0" id="hectare" name="hectare" class="form-control" value="<?= e($form['hectare']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="palm_count">Palm Count</label>
                <input type="number" min="0" id="palm_count" name="palm_count" class="form-control" value="<?= e($form['palm_count']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="planting_year">Planting Year</label>
                <input type="number" min="1900" max="2100" id="planting_year" name="planting_year" class="form-control" value="<?= e($form['planting_year']) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="palm_age">Palm Age (years)</label>
                <input type="number" min="0" id="palm_age" name="palm_age" class="form-control" value="<?= e($form['palm_age']) ?>">
                <div class="form-text">Leave blank to derive from planting year.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="soil_type">Soil Type</label>
                <input type="text" id="soil_type" name="soil_type" class="form-control" value="<?= e($form['soil_type']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="terrain_type">Terrain Type</label>
                <input type="text" id="terrain_type" name="terrain_type" class="form-control" value="<?= e($form['terrain_type']) ?>">
            </div>

            <div class="col-12"><hr class="text-muted my-1"><small class="text-muted">GPS centre point (for the map module)</small></div>
            <div class="col-md-3">
                <label class="form-label" for="gps_lat">Latitude</label>
                <input type="number" step="0.0000001" id="gps_lat" name="gps_lat" class="form-control" value="<?= e($form['gps_lat']) ?>" placeholder="e.g. 3.1234567">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="gps_lng">Longitude</label>
                <input type="number" step="0.0000001" id="gps_lng" name="gps_lng" class="form-control" value="<?= e($form['gps_lng']) ?>" placeholder="e.g. 101.1234567">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Save Block</button>
            </div>
        </form>
    </div>
</div>

<script>
// Filter the division dropdown to match the chosen estate.
(function () {
    var estateSel = document.getElementById('estate_id');
    var divSel = document.getElementById('division_id');
    if (!estateSel || !divSel) return;

    function filterDivisions(keepSelected) {
        var estateId = estateSel.value;
        var current = divSel.value;
        Array.prototype.forEach.call(divSel.options, function (opt) {
            if (!opt.value) return; // skip placeholder
            var match = opt.getAttribute('data-estate') === estateId;
            opt.hidden = !match;
            opt.disabled = !match;
        });
        // Reset selection if the chosen division no longer belongs to the estate.
        if (!keepSelected) {
            var sel = divSel.options[divSel.selectedIndex];
            if (sel && sel.value && sel.getAttribute('data-estate') !== estateId) {
                divSel.value = '';
            }
        }
    }

    estateSel.addEventListener('change', function () { filterDivisions(false); });
    filterDivisions(true); // initial load keeps any pre-selected value
})();
</script>
