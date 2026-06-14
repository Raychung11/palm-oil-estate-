<?php
/**
 * modules/mill/_form.php
 * Shared mill-delivery create/edit form.
 *
 * Expects: $form, $errors, $page_title, $mills, $vehicles, $drivers,
 *          $harvests (selectable approved harvest records),
 *          $linked (array of selected harvest ids), $existingTickets, $submitLabel
 */
$linked = $linked ?? [];
$existingTickets = $existingTickets ?? [];
$submitLabel = $submitLabel ?? 'Save Delivery';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= e($page_title) ?></h1>
    <a href="<?= e(url('modules/mill/index.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Trip</div>
        <div class="card-body row g-3">
            <div class="col-md-3"><label class="form-label" for="delivery_date">Delivery Date <span class="text-danger">*</span></label>
                <input type="date" id="delivery_date" name="delivery_date" class="form-control" value="<?= e($form['delivery_date']) ?>" required></div>
            <div class="col-md-3"><label class="form-label" for="mill_id">Mill</label>
                <select id="mill_id" name="mill_id" class="form-select" data-prices>
                    <option value="">— Select —</option>
                    <?php foreach ($mills as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" data-price="<?= e($m['default_price_per_tonne']) ?>" <?= (int)$form['mill_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['mill_name']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label" for="vehicle_asset_id">Lorry</label>
                <select id="vehicle_asset_id" name="vehicle_asset_id" class="form-select">
                    <option value="">— Select —</option>
                    <?php foreach ($vehicles as $v): ?><option value="<?= (int)$v['id'] ?>" <?= (int)$form['vehicle_asset_id'] === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['asset_name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label" for="driver_worker_id">Driver</label>
                <select id="driver_worker_id" name="driver_worker_id" class="form-select">
                    <option value="">— Select —</option>
                    <?php foreach ($drivers as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (int)$form['driver_worker_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label" for="time_out">Time Out</label><input type="time" id="time_out" name="time_out" class="form-control" value="<?= e($form['time_out']) ?>"></div>
            <div class="col-md-3"><label class="form-label" for="time_in">Time In</label><input type="time" id="time_in" name="time_in" class="form-control" value="<?= e($form['time_in']) ?>"></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Weighbridge &amp; Price</div>
        <div class="card-body row g-3">
            <div class="col-md-3"><label class="form-label" for="gross_weight_kg">Gross (kg)</label><input type="number" step="0.01" min="0" id="gross_weight_kg" name="gross_weight_kg" class="form-control" value="<?= e($form['gross_weight_kg']) ?>"></div>
            <div class="col-md-3"><label class="form-label" for="tare_weight_kg">Tare (kg)</label><input type="number" step="0.01" min="0" id="tare_weight_kg" name="tare_weight_kg" class="form-control" value="<?= e($form['tare_weight_kg']) ?>"></div>
            <div class="col-md-3"><label class="form-label">Net (kg)</label><input type="text" id="net_preview" class="form-control bg-light" value="" readonly></div>
            <div class="col-md-3"><label class="form-label" for="oer">OER (%)</label><input type="number" step="0.01" min="0" id="oer" name="oer" class="form-control" value="<?= e($form['oer']) ?>"></div>
            <div class="col-md-3"><label class="form-label" for="price_per_tonne">Price / Tonne</label><input type="number" step="0.01" min="0" id="price_per_tonne" name="price_per_tonne" class="form-control" value="<?= e($form['price_per_tonne']) ?>"></div>
            <div class="col-md-3"><label class="form-label">Est. Value</label><input type="text" id="value_preview" class="form-control bg-light" value="" readonly></div>
            <div class="col-12"><label class="form-label" for="remarks">Remarks</label><input type="text" id="remarks" name="remarks" class="form-control" value="<?= e($form['remarks']) ?>"></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Linked Harvest Records <small class="text-muted fw-normal">(approved, last 30 days)</small></div>
        <div class="card-body">
            <?php if (empty($harvests)): ?>
                <p class="text-muted mb-0">No approved harvest records available to link.</p>
            <?php else: ?>
            <div class="table-responsive" style="max-height:280px;overflow:auto;">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th style="width:40px;"></th><th>Date</th><th>Block</th><th class="text-end">FFB (kg)</th></tr></thead>
                    <tbody>
                    <?php foreach ($harvests as $h): ?>
                        <tr>
                            <td><input class="form-check-input" type="checkbox" name="harvest_ids[]" value="<?= (int)$h['id'] ?>" <?= in_array((int)$h['id'], $linked, true) ? 'checked' : '' ?>></td>
                            <td class="text-nowrap small"><?= e(fmt_datetime($h['harvest_date'], 'd M Y')) ?></td>
                            <td><?= e($h['block_code']) ?></td>
                            <td class="text-end"><?= num($h['ffb_weight_kg']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Weighbridge Ticket</div>
        <div class="card-body">
            <?php if (!empty($existingTickets)): ?>
                <div class="mb-2 small">
                    <?php foreach ($existingTickets as $t): ?>
                        <span class="me-3"><i class="bi bi-receipt"></i> <?= e($t['ticket_no'] ?? 'Ticket') ?>
                            <?php if ($t['file_path']): ?><a href="<?= e(UPLOAD_URL . '/' . $t['file_path']) ?>" target="_blank">view</a><?php endif; ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Ticket No.</label><input type="text" name="ticket_no" class="form-control"></div>
                <div class="col-md-8"><label class="form-label">Ticket File</label><input type="file" name="ticket" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> <?= e($submitLabel) ?></button>
</form>

<script>
// Live net-weight and value preview; auto-fill price from the chosen mill.
(function () {
    var gross = document.getElementById('gross_weight_kg');
    var tare = document.getElementById('tare_weight_kg');
    var price = document.getElementById('price_per_tonne');
    var net = document.getElementById('net_preview');
    var val = document.getElementById('value_preview');
    var mill = document.getElementById('mill_id');

    function recalc() {
        var n = Math.max(0, (parseFloat(gross.value) || 0) - (parseFloat(tare.value) || 0));
        net.value = n.toFixed(2);
        val.value = ((n / 1000) * (parseFloat(price.value) || 0)).toFixed(2);
    }
    [gross, tare, price].forEach(function (el) { el && el.addEventListener('input', recalc); });
    if (mill) {
        mill.addEventListener('change', function () {
            var opt = mill.options[mill.selectedIndex];
            var p = opt && opt.getAttribute('data-price');
            if (p && !price.value) { price.value = p; }
            recalc();
        });
    }
    recalc();
})();
</script>
