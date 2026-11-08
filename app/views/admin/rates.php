<?php
if (!function_exists('rates_fields')) {
    /** The editable rates: ticket types with price, and table packages. */
    function rates_fields(array $r): void { ?>
      <div class="tbl-wrap"><table class="tbl"><tr><th>On</th><th>Ticket type</th><th>Price per guest (UGX, 0 = free)</th><th>Min</th><th>Max</th></tr>
      <?php foreach (RES_TYPES as $t => $lab): $o = $r['opt'][$t] ?? null; $d = rates_type_defaults($t); ?>
        <tr><td><input type="checkbox" name="opt[<?= $t ?>][on]" value="1"<?= $o ? ' checked' : '' ?> aria-label="Enable <?= h($lab) ?>"></td><td><?= h($lab) ?></td>
          <td><input type="text" inputmode="numeric" name="opt[<?= $t ?>][price]" value="<?= (int)($o['price'] ?? 0) ?>"></td>
          <td><input type="number" min="1" name="opt[<?= $t ?>][min]" value="<?= (int)($o['min'] ?? $d['min']) ?>" style="width:70px"></td>
          <td><input type="number" min="1" name="opt[<?= $t ?>][max]" value="<?= (int)($o['max'] ?? $d['max']) ?>" style="width:70px"></td></tr>
      <?php endforeach; ?></table></div>
      <label class="rates-pk">Table packages (one per line: Name | price in UGX). Shown when &ldquo;Table Reservation&rdquo; is on.
        <textarea name="packages" rows="5" placeholder="Silver | 200000&#10;Gold Pro | 300000"><?= h($r['packages']) ?></textarea></label>
    <?php }
}
$pastQ = $past ? '?past=1' : '';
?>
<style>
.rates-ev{padding:0;overflow:hidden}.rates-ev>summary{list-style:none;cursor:pointer;display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center;padding:14px 18px}
.rates-ev>summary::-webkit-details-marker{display:none}.rates-ev[open]>summary{background:#fbf4e8;border-bottom:1px solid var(--line)}
.rates-ev .rv-title{font-weight:800}.rates-ev .rv-date{color:var(--muted);font-size:.82rem}.rates-ev .rv-sum{flex-basis:100%;color:#5a5249;font-size:.82rem}
.rates-body{padding:16px 18px}.rates-pk{display:block;font-weight:600;font-size:.88rem;margin-top:12px}.rates-pk textarea{width:100%;margin-top:6px}
.rates-copy{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:14px;padding-top:12px;border-top:1px dashed var(--line)}
.tag-soft{display:inline-block;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:99px;background:#efe9dd;color:#6b6258}
</style>
<div class="adm-head">
  <div><h1>Ticket rates</h1><p class="adm-sub">Set prices for every event in one place, including events you create later. Prices change on the website straight away.</p></div>
  <a class="btn btn-ghost btn-sm" href="<?= url('admin/rates' . ($past ? '' : '?past=1')) ?>"><?= $past ? 'Show upcoming events' : 'Show past events' ?></a>
</div>

<form class="panel" id="size" method="post" action="<?= url('admin/rates/package-size') ?>" onsubmit="return confirm('Update how many people this package admits?')"><?= csrf_field() ?>
  <h2 style="margin:0 0 6px;font-size:1.05rem">👥 People per table (package size)</h2>
  <p class="small muted" style="margin:0 0 10px">Say how many people one table of a package admits, for example <b>Silver admits 4</b>. New bookings use it, it shows on the reserve page and on the ticket, and you can update the tickets that were already booked.</p>
  <div class="form-grid">
    <div class="field"><label for="ps_pkg">Package</label><select id="ps_pkg" name="package" required><option value="">Choose a package</option><?php foreach ($pkg_names as $pn): ?><option value="<?= h($pn) ?>"><?= h($pn) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="ps_n">People it admits</label><input id="ps_n" type="number" name="admits" min="1" max="50" value="4" required></div>
    <div class="field"><label for="ps_ev">Events</label><select id="ps_ev" name="events"><option value="all">All events</option><option value="upcoming">Upcoming events only</option><?php foreach ($others as $o): ?><option value="<?= (int)$o['id'] ?>"><?= h(excerpt($o['title'], 46)) ?> · <?= fmt_date($o['start_at'], 'j M Y') ?></option><?php endforeach; ?></select></div>
  </div>
  <fieldset style="border:0;padding:0;margin:10px 0 0"><legend class="small" style="font-weight:700;margin-bottom:4px">Tickets already booked on this package</legend>
    <label class="chk"><input type="radio" name="scope" value="package"> Leave them as they are (only the package changes)</label>
    <label class="chk"><input type="radio" name="scope" value="approved" checked> Update all <b>approved</b> tickets</label>
    <label class="chk"><input type="radio" name="scope" value="all"> Update <b>all</b> tickets: approved ones and bookings still awaiting approval</label>
  </fieldset>
  <p style="margin-top:12px"><button class="btn btn-primary btn-sm">Update package and tickets</button> <span class="small muted">You can run this again at any time.</span></p>
</form>

<form class="panel" method="post" action="<?= url('admin/rates/apply-all') ?>" onsubmit="return confirm('Replace the table packages on all <?= (int)$up_n ?> upcoming events with these?')"><?= csrf_field() ?>
  <h2 style="margin:0 0 6px;font-size:1.05rem">⚡ Set table packages on all upcoming events</h2>
  <p class="small muted" style="margin:0 0 10px">One package per line as <b>Name | price in UGX | people it admits</b> (the last part is optional, for example <b>Silver | 200000 | 4</b>). This replaces the table packages on all <b><?= (int)$up_n ?></b> upcoming events and saves them as the default for new events. Other ticket types are not touched.</p>
  <textarea name="packages" rows="6" style="width:100%" placeholder="Silver | 200000&#10;Gold Pro | 300000"><?= h($cur_packages) ?></textarea>
  <p style="margin-top:10px"><button class="btn btn-primary btn-sm">Apply to all upcoming events</button></p>
</form>

<details class="panel rates-ev" id="e0"<?= $has_template ? '' : ' open' ?>>
  <summary><span class="rv-title">⭐ Default rates for new events</span><span class="tag-soft"><?= $has_template ? 'saved' : 'not set' ?></span><span class="rv-sum">New events start with these prices, so you only adjust what is different. <?= $has_template ? h(rates_summary($template)) : '' ?></span></summary>
  <form class="rates-body" method="post" action="<?= url('admin/rates/0') ?>"><?= csrf_field() ?>
    <?php rates_fields($template); ?>
    <p style="margin-top:14px"><button class="btn btn-primary btn-sm">Save default rates</button></p>
  </form>
</details>

<?php if (!$events): ?><div class="panel"><div class="empty"><?= $past ? 'No past events.' : 'No upcoming events yet. Create one under Events, then set its rates here.' ?> <a href="<?= url('admin/events/new') ?>">New event</a></div></div><?php endif; ?>
<?php foreach ($events as $i => $e): ?>
<details class="panel rates-ev" id="e<?= (int)$e['id'] ?>"<?= ($i === 0 && !$past) ? ' open' : '' ?>>
  <summary><span class="rv-title"><?= h($e['title']) ?></span><span class="rv-date"><?= fmt_date($e['start_at'], 'D j M Y') ?></span><span class="tag-soft"><?= h(ucfirst($e['status'])) ?></span>
    <span class="rv-sum"><?= h(rates_summary($e['rates'])) ?></span></summary>
  <div class="rates-body">
    <form method="post" action="<?= url('admin/rates/' . (int)$e['id']) ?>"><?= csrf_field() ?><?php if ($past): ?><input type="hidden" name="past" value="1"><?php endif; ?>
      <?php rates_fields($e['rates']); ?>
      <p style="margin-top:14px"><button class="btn btn-primary btn-sm">Save rates for this event</button> <a class="btn btn-ghost btn-sm" href="<?= url('admin/events/' . (int)$e['id']) ?>">Open full event</a></p>
    </form>
    <form class="rates-copy" method="post" action="<?= url('admin/rates/' . (int)$e['id'] . '/copy') ?>" onsubmit="return confirm('Replace this event\'s rates with the copied ones?')"><?= csrf_field() ?><?php if ($past): ?><input type="hidden" name="past" value="1"><?php endif; ?>
      <label style="font-size:.85rem;font-weight:600">Copy rates from
        <select name="from"><?php if ($has_template): ?><option value="template">⭐ Default rates</option><?php endif; ?>
          <?php foreach ($others as $o): if ((int)$o['id'] === (int)$e['id']) continue; ?><option value="<?= (int)$o['id'] ?>"><?= h(excerpt($o['title'], 46)) ?> · <?= fmt_date($o['start_at'], 'j M Y') ?></option><?php endforeach; ?></select></label>
      <button class="btn btn-ghost btn-sm">Copy</button>
    </form>
  </div>
</details>
<?php endforeach; ?>
