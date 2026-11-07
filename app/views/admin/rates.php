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
