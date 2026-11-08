<?php
$left = max(0, $stats['expected'] - $stats['in']);
$pct = $stats['expected'] ? min(100, (int)round($stats['in'] / $stats['expected'] * 100)) : 0;
$showPhone = can('reservations');
$back = 'admin/checkin?' . http_build_query(array_filter(['event' => $ev['id'] ?? '', 'q' => $q, 'package' => $pkg ?? '']));
?>
<div class="adm-head">
  <div><h1>Check-in desk</h1><p class="adm-sub">Search a guest by name, phone or reference, or scan their QR ticket with your phone camera.</p></div>
  <?php if ($ev && can_any(['reservations', 'checkin', 'reports'])): ?><?= export_buttons('guestlist', ['event' => $ev['id']]) ?><?php endif; ?>
</div>

<form class="panel filters" method="get" action="<?= url('admin/checkin') ?>">
  <label>Event <select name="event" onchange="this.form.submit()"><?php foreach ($events as $x): ?><option value="<?= $x['id'] ?>"<?= $ev && (int)$ev['id'] === (int)$x['id'] ? ' selected' : '' ?>><?= h(excerpt($x['title'], 56)) ?> · <?= fmt_date($x['start_at'], 'j M Y') ?></option><?php endforeach; ?></select></label>
  <label>Table package <?= package_select($package_opts ?? [], $pkg ?? '', true) ?></label>
  <label style="flex:1;min-width:220px">Find a guest <input type="search" name="q" value="<?= h($q) ?>" placeholder="Name, phone or reference" autofocus></label>
  <button class="btn btn-primary btn-sm">Search</button>
  <?php if ($q !== '' || ($pkg ?? '') !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/checkin?event=' . (int)($ev['id'] ?? 0)) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$ev): ?><div class="empty">There are no events to check guests into yet.</div><?php else: ?>
<div class="stat-row">
  <div class="mini"><b><?= $stats['in'] ?> / <?= $stats['expected'] ?></b><span>Guests arrived</span></div>
  <div class="mini"><b><?= $left ?></b><span>Still expected</span></div>
  <div class="mini"><b><?= $stats['bookings'] ?></b><span>Confirmed bookings</span></div>
  <div class="mini"><b><?= $stats['pending'] ?></b><span>Pending (not admitted)</span></div>
</div>
<div class="cap-row"><div class="top"><span><b><?= h($ev['title']) ?></b></span><span><?= $pct ?>% arrived</span></div><div class="cap"><i class="c-ok" style="width:<?= $pct ?>%"></i></div></div>

<div class="panel">
  <?php if (!$list): ?><div class="empty"><?= $q !== '' ? 'No confirmed guest matches “' . h($q) . '”.' : 'No confirmed bookings for this event yet.' ?></div><?php endif; ?>
  <?php foreach ($list as $r): $bal = max(0, (float)$r['amount_due'] - (float)$r['paid']); $in = $r['checked_in_at'] !== null; ?>
    <div class="desk-row<?= $in ? ' done' : '' ?>">
      <div class="who"><b><?= h($r['full_name']) ?></b>
        <small><?= h($r['reference']) ?> · <?= h(str_replace(' Reservation', '', RES_TYPES[$r['type']])) ?><?= $r['package'] ? ' · ' . h($r['package']) : '' ?><?= $showPhone ? ' · ' . h($r['phone']) : '' ?></small></div>
      <div><span class="badge badge-muted"><?= (int)$r['guests'] ?> guest<?= (int)$r['guests'] === 1 ? '' : 's' ?></span>
        <?php if ($bal > 0): ?><span class="badge badge-err">Owes <?= h(money($bal)) ?></span><?php elseif ($r['amount_due'] > 0): ?><span class="badge badge-ok">Paid</span><?php endif; ?></div>
      <form class="inline" method="post" action="<?= url('admin/reservations/' . $r['id'] . '/action') ?>"><?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= h($back) ?>">
        <?php if ($in): ?><input type="hidden" name="action" value="undo_checkin"><span class="small muted">In at <?= fmt_time($r['checked_in_at']) ?></span> <button class="btn btn-ghost btn-sm">Undo</button>
        <?php else: ?><input type="hidden" name="action" value="checkin"><button class="btn btn-primary">Check in</button><?php endif; ?></form>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
