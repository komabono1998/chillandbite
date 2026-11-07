<?php
$v = fn($k, $d = '') => $old[$k] ?? ($e[$k] ?? $d);
$dtv = fn($k) => isset($old[$k]) ? $old[$k] : dt_out($e[$k] ?? null);
?>
<div class="adm-head"><h1><?= $e ? 'Edit event' : 'New event' ?></h1><a href="<?= url('admin/events') ?>">&larr; All events</a></div>
<form class="panel" method="post" action="<?= url('admin/events/save') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
  <div class="form-grid">
    <?php field('title', 'Title', ['required' => true, 'value' => $v('title')]);
    field('category_id', 'Category', ['type' => 'select', 'value' => $v('category_id'), 'options' => ['' => '—'] + array_column($cats, 'name', 'id')]);
    field('start_at', 'Starts', ['type' => 'datetime-local', 'required' => true, 'value' => $dtv('start_at')]);
    field('end_at', 'Ends', ['type' => 'datetime-local', 'value' => $dtv('end_at')]);
    field('venue', 'Venue', ['value' => $v('venue', setting('venue'))]); field('location', 'Location', ['value' => $v('location', setting('location'))]);
    field('capacity', 'Maximum guests (blank = unlimited)', ['type' => 'number', 'value' => $v('capacity'), 'attrs' => 'min="0"']);
    field('status', 'Status', ['type' => 'select', 'value' => $v('status', 'draft'), 'options' => ['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled', 'archived' => 'Archived']]);
    field('reservation_opens', 'Reservations open', ['type' => 'datetime-local', 'value' => $dtv('reservation_opens')]);
    field('reservation_closes', 'Reservations close', ['type' => 'datetime-local', 'value' => $dtv('reservation_closes')]);
    field('booking_mode', 'Free bookings are', ['type' => 'select', 'value' => $v('booking_mode', 'instant'), 'options' => ['instant' => 'Confirmed instantly', 'approval' => 'Held for admin approval'], 'help' => 'Paid bookings always stay pending until payment is recorded.']);
    field('video_url', 'YouTube video (optional)', ['value' => $v('video_url')]); ?>
  </div>
  <?php field('short_desc', 'Short description', ['value' => $v('short_desc'), 'attrs' => 'maxlength="300"']); field('description', 'Full description', ['type' => 'textarea', 'rows' => 7, 'value' => $v('description')]); ?>
  <div class="field"><label for="f_poster">Poster image</label><input id="f_poster" type="file" name="poster" accept="image/jpeg,image/png,image/webp,image/gif">
    <?php if (!empty($e['poster'])): ?><img src="<?= h(upload_url($e['poster'])) ?>" alt="" style="max-height:90px;margin-top:8px"><?php endif; ?></div>
  <label class="chk"><input type="checkbox" name="featured" value="1"<?= $v('featured') ? ' checked' : '' ?>> Featured</label>
  <h2>Reservation options</h2>
  <div class="tbl-wrap"><table class="tbl"><tr><th>Enable</th><th>Type</th><th>Price / guest (UGX, 0 = free)</th><th>Min guests</th><th>Max guests</th></tr>
  <?php foreach (RES_TYPES as $t => $lab): $o = $opts[$t] ?? null; $on = $old ? !empty($old['opt'][$t]['on']) : ($e ? (bool)$o : ($tplOn !== null ? isset($tplOn[$t]) : in_array($t, ['individual', 'couple', 'group', 'corporate']))); ?>
    <tr><td><input type="checkbox" name="opt[<?= $t ?>][on]" value="1"<?= $on ? ' checked' : '' ?> aria-label="Enable <?= h($lab) ?>"></td><td><?= h($lab) ?></td>
    <td><input type="number" min="0" name="opt[<?= $t ?>][price]" value="<?= (int)($o['price_per_guest'] ?? $old['opt'][$t]['price'] ?? 0) ?>"></td>
    <td><input type="number" min="1" name="opt[<?= $t ?>][min]" value="<?= (int)($o['min_guests'] ?? $old['opt'][$t]['min'] ?? ($t === 'couple' ? 2 : ($t === 'group' || $t === 'corporate' ? 3 : 1))) ?>"></td>
    <td><input type="number" min="1" name="opt[<?= $t ?>][max]" value="<?= (int)($o['max_guests'] ?? $old['opt'][$t]['max'] ?? ($t === 'individual' ? 1 : ($t === 'couple' ? 2 : 20))) ?>"></td></tr>
  <?php endforeach; ?></table></div>
  <h2>Table packages</h2>
  <?php field('packages', 'One package per line as: Name | price in UGX', ['type' => 'textarea', 'rows' => 6, 'value' => $old['packages'] ?? $pkgText,
      'help' => 'Shown when guests choose "Table Reservation" (enable it above). Price is per table, not per guest. Example: Silver | 200000']); ?>
  <p><button class="btn btn-primary">Save event</button> <?php if ($e): ?><a class="btn btn-ghost" href="<?= url('events/' . h($e['slug'])) ?>" target="_blank">View public page</a><?php endif; ?></p>
</form>
