<?php
$due = (int)($sum['due'] ?? 0); $paid = (int)($sum['paid'] ?? 0);
$outstanding = max(0, $due - $paid);
$rate = $due > 0 ? min(100, (int)round($paid / $due * 100)) : 100;
$k = [
    ['🎟️', 'Bookings', (int)($sum['bookings'] ?? 0), ''], ['👥', 'Guests', (int)($sum['guests'] ?? 0), 'info'],
    ['✅', 'Confirmed', (int)($sum['n_confirmed'] ?? 0), 'ok'], ['⏳', 'Pending', (int)($sum['n_pending'] ?? 0), 'warn'],
    ['🚫', 'Cancelled / rejected', (int)($sum['n_lost'] ?? 0), ''], ['💰', 'Payments received', money($revenue), 'ok'],
    ['🧾', 'Outstanding', money($outstanding), $outstanding ? 'warn' : ''], ['📈', 'Collection rate', $rate . '%', 'info'],
    ['🚪', 'Guests checked in', (int)($sum['checked'] ?? 0), ''],
];
$days = array_reverse($daily);
$maxD = $days ? max(1, max(array_column($days, 'n'))) : 1;
$maxT = $by_type ? max(1, max(array_column($by_type, 'g'))) : 1;
$qs = $filters;
?>
<div class="adm-head">
  <div><h1>Reports &amp; exports</h1><p class="adm-sub">Bookings, attendance and money across your events. Filter, then download as PDF, Word, Excel or CSV.</p></div>
  <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()">🖨️ Print this page</button>
</div>

<form class="panel filters" method="get" action="<?= url('admin/reports') ?>">
  <label>Event <select name="event"><option value="">All events</option><?php foreach ($all_events as $x): ?><option value="<?= $x['id'] ?>"<?= (int)($filters['event'] ?? 0) === (int)$x['id'] ? ' selected' : '' ?>><?= h(excerpt($x['title'], 50)) ?> · <?= fmt_date($x['start_at'], 'j M Y') ?></option><?php endforeach; ?></select></label>
  <label>Status <select name="status"><option value="">Any</option><?php foreach (['pending', 'confirmed', 'rejected', 'cancelled'] as $s): ?><option value="<?= $s ?>"<?= ($filters['status'] ?? '') === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></label>
  <label>From <input type="date" name="from" value="<?= h($filters['from'] ?? '') ?>"></label>
  <label>To <input type="date" name="to" value="<?= h($filters['to'] ?? '') ?>"></label>
  <button class="btn btn-primary btn-sm">Apply filters</button>
  <?php if ($filters): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/reports') ?>">Clear</a><?php endif; ?>
</form>

<div class="kpis"><?php foreach ($k as [$ic, $label, $v, $tone]): ?>
  <div class="kpi<?= $tone ? ' kpi-' . $tone : '' ?>"><span class="kpi-ic" aria-hidden="true"><?= $ic ?></span><div><b><?= h($v) ?></b><span><?= h($label) ?></span></div></div>
<?php endforeach; ?></div>

<div class="panel">
  <div class="panel-head"><h2>⬇️ Download reports</h2><span class="muted small">Filters above apply where they make sense</span></div>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Report</th><th>What is inside</th><th>Download</th></tr></thead><tbody>
    <tr><td><b>Reservations</b></td><td class="muted">Every booking with guest, package, status, amount due, paid and balance</td><td><?= export_buttons('reservations', $qs) ?></td></tr>
    <tr><td><b>Payments ledger</b></td><td class="muted">Each payment and refund with method, who recorded it and when</td><td><?= export_buttons('payments', array_diff_key($qs, ['status' => 1])) ?></td></tr>
    <tr><td><b>Guest list</b></td><td class="muted">Confirmed guests for one event, A to Z with check-in column (uses the event filter, or the next event)</td><td><?= export_buttons('guestlist', array_intersect_key($qs, ['event' => 1])) ?></td></tr>
    <tr><td><b>Event summary</b></td><td class="muted">Capacity, bookings, attendance and revenue for every event</td><td><?= export_buttons('events', []) ?></td></tr>
  </tbody></table></div>
</div>

<div class="dash-grid">
  <div class="col-7"><div class="panel">
    <div class="panel-head"><h2>Bookings per day</h2><span class="muted small">latest <?= count($days) ?> day(s) with bookings</span></div>
    <?php if ($days): ?><div class="bars" role="img" aria-label="Bookings per day">
      <?php foreach ($days as $dd): ?><div class="col" title="<?= h(fmt_date($dd['d'], 'D j M Y')) ?>: <?= (int)$dd['n'] ?> booking(s), <?= (int)$dd['g'] ?> guest(s)">
        <span class="val"><?= (int)$dd['n'] ?></span><div class="bar" style="height:<?= max(5, round($dd['n'] / $maxD * 100)) ?>%"></div><span class="lab"><?= date('j/n', strtotime($dd['d'])) ?></span></div><?php endforeach; ?>
    </div><?php else: ?><div class="empty">No bookings match these filters.</div><?php endif; ?>
  </div></div>
  <div class="col-5"><div class="panel">
    <div class="panel-head"><h2>Payments by method</h2></div>
    <?php if ($methods): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Method</th><th>Payments</th><th>Amount</th></tr></thead><tbody>
      <?php foreach ($methods as $m): ?><tr><td><?= h(ucwords(str_replace('_', ' ', $m['method']))) ?></td><td><?= (int)$m['n'] ?></td><td><b><?= h(money($m['amt'])) ?></b></td></tr><?php endforeach; ?>
      <tr><td><b>Total</b></td><td></td><td><b><?= h(money($revenue)) ?></b></td></tr></tbody></table></div>
    <?php else: ?><div class="empty">No payments recorded for this selection.</div><?php endif; ?>
  </div></div>

  <div class="col-12"><div class="panel">
    <div class="panel-head"><h2>By reservation type</h2></div>
    <?php if ($by_type): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Type</th><th>Bookings</th><th>Guests</th><th></th><th>Amount due</th><th>Paid</th></tr></thead><tbody>
      <?php foreach ($by_type as $t): ?><tr><td><b><?= h(RES_TYPES[$t['type']]) ?></b></td><td><?= (int)$t['n'] ?></td><td><?= (int)$t['g'] ?></td>
        <td style="min-width:160px"><div class="hbars"><span class="track"><i class="fill" style="width:<?= round($t['g'] / $maxT * 100) ?>%"></i></span></div></td>
        <td><?= h(money($t['due'])) ?></td><td><?= h(money($t['paid'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><div class="empty">Nothing to show.</div><?php endif; ?>
  </div></div>

  <div class="col-12"><div class="panel">
    <div class="panel-head"><h2>Events</h2></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Event</th><th>Date</th><th>Status</th><th>Capacity</th><th>Booked</th><th>Confirmed</th><th>Pending</th><th>Checked in</th><th>Revenue</th><th>Outstanding</th></tr></thead><tbody>
      <?php foreach ($events_stats as $e): ?><tr><td><b><?= h(excerpt($e['title'], 44)) ?></b></td><td><?= fmt_date($e['start_at'], 'j M Y') ?></td><td><?= status_badge($e['status']) ?></td>
        <td><?= $e['capacity'] === null ? '∞' : (int)$e['capacity'] ?></td><td><?= (int)$e['booked'] ?></td><td><?= (int)$e['confirmed'] ?></td><td><?= (int)$e['pending'] ?></td><td><?= (int)$e['checked'] ?></td>
        <td><?= h(money($e['revenue'])) ?></td><td><?= h(money($e['outstanding'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div></div>

  <div class="col-12"><div class="panel">
    <div class="panel-head"><h2>Latest matching reservations</h2><span class="muted small">showing up to 60 · export for the full list</span></div>
    <?php if ($preview): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Ref</th><th>Guest</th><th>Event</th><th>Type</th><th>Guests</th><th>Status</th><th>Due</th><th>Paid</th></tr></thead><tbody>
      <?php foreach ($preview as $r): ?><tr><td><a href="<?= url('admin/reservations/' . $r['id']) ?>"><?= h($r['reference']) ?></a></td><td><?= h($r['full_name']) ?></td><td><?= h(excerpt($r['event_title'], 30)) ?></td>
        <td><?= h(str_replace(' Reservation', '', RES_TYPES[$r['type']])) ?><?= $r['package'] ? ' · ' . h($r['package']) : '' ?></td><td><?= (int)$r['guests'] ?></td><td><?= status_badge($r['status']) ?></td>
        <td><?= h(money($r['amount_due'])) ?></td><td><?= h(money($r['paid'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php else: ?><div class="empty">No reservations match these filters.</div><?php endif; ?>
  </div></div>
</div>
