<?php
$kinds = ['all' => 'Everything', 'booking' => 'Reservations made', 'payment' => 'Payments received', 'attempt' => 'Payment attempts', 'admin' => 'Admin actions'];
$kindStyle = ['booking' => ['🎟️', '#2563eb'], 'payment' => ['💰', '#1f8a4c'], 'attempt' => ['📲', '#7a5cc7'], 'admin' => ['🛠️', '#8a8378']];
$stColor = ['confirmed' => '#2f9e56', 'pending' => '#d99a1c', 'rejected' => '#d9534f', 'cancelled' => '#8a8378', 'paid' => '#1f8a4c', 'refund' => '#c0392b', 'success' => '#2f9e56', 'failed' => '#d9534f', 'unconfirmed' => '#7a5cc7', 'admin' => '#8a8378'];
$qs = ['tab' => 'trail', 'event' => $f['event'] ?: null, 'kind' => $f['kind'] !== 'all' ? $f['kind'] : null, 'q' => $f['q'] ?: null, 'from' => $f['from'] ?: null, 'to' => $f['to'] ?: null];
$link = fn(array $over) => url('admin?' . http_build_query(array_filter(array_merge($qs, $over), fn($v) => $v !== null && $v !== '')));
?>
<form class="panel filters dash-filter" method="get" action="<?= url('admin') ?>"><input type="hidden" name="tab" value="trail">
  <label>Event <select name="event"><option value="0">All events</option><?php foreach ($all_events as $e): ?><option value="<?= (int)$e['id'] ?>"<?= (int)$e['id'] === (int)$f['event'] ? ' selected' : '' ?>><?= h(excerpt($e['title'], 44)) ?> · <?= h(fmt_date($e['start_at'], 'j M')) ?></option><?php endforeach; ?></select></label>
  <label>Show <select name="kind"><?php foreach ($kinds as $k => $l): ?><option value="<?= $k ?>"<?= $f['kind'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
  <label>From <input type="date" name="from" value="<?= h((string)$f['from']) ?>"></label><label>To <input type="date" name="to" value="<?= h((string)$f['to']) ?>"></label>
  <label>Search <input type="search" name="q" value="<?= h($f['q']) ?>" placeholder="reference, name or phone" style="min-width:190px"></label>
  <button class="btn btn-primary btn-sm">Show</button><a class="btn btn-ghost btn-sm" href="<?= url('admin?tab=trail') ?>">Clear</a>
</form>
<p class="small muted" style="margin:0 0 10px">One time-ordered record of every reservation made, every payment received or refunded, every online payment attempt (with when it started and finished) and the admin actions on bookings and money. Newest first.</p>
<div class="panel trail">
<?php if (!$trail['rows']): ?><div class="empty">Nothing matches these filters.</div><?php endif; ?>
<ol class="tl"><?php $day = ''; foreach ($trail['rows'] as $r): [$ic, $col] = $kindStyle[$r['kind']]; $d = date('Y-m-d', strtotime($r['ts'])); if ($d !== $day): $day = $d; ?><li class="tl-day"><?= h(fmt_date($d, 'l, j F Y')) ?></li><?php endif; ?>
  <li class="tl-row"><span class="tl-ic" style="background:<?= $col ?>"><?= $ic ?></span>
    <div class="tl-main"><div class="tl-top"><b><?= h($r['title']) ?></b>
        <span class="pill" style="background:<?= $stColor[$r['status']] ?? '#999' ?>;color:#fff"><?= h(ucfirst($r['status'])) ?></span>
        <?php if ($r['amount'] !== null): ?><b class="tl-amt" style="color:<?= $r['amount'] < 0 ? '#c0392b' : 'inherit' ?>"><?= h(money($r['amount'])) ?></b><?php endif; ?></div>
      <div class="tl-sub"><?php if ($r['ref']): ?><a href="<?= $r['rid'] ? url('admin/reservations/' . $r['rid']) : '#' ?>"><?= h($r['ref']) ?></a> · <?php endif; ?><?= $r['who'] ? h($r['who']) : '' ?><?= $r['phone'] ? ' · ' . h($r['phone']) : '' ?><?= $r['ev'] ? ' · <span class="muted">' . h(excerpt($r['ev'], 50)) . '</span>' : '' ?></div>
      <?php if ($r['detail']): ?><div class="tl-det"><?= h(excerpt($r['detail'], 160)) ?></div><?php endif; ?>
      <div class="tl-by">by <?= h($r['by']) ?></div></div>
    <div class="tl-time"><b><?= h(date('g:i:s A', strtotime($r['ts']))) ?></b><?php if (!empty($r['end'])): ?><small>finished <?= h(date('g:i:s A', strtotime($r['end']))) ?> (<?= max(0, strtotime($r['end']) - strtotime($r['ts'])) ?> sec)</small><?php endif; ?></div></li>
<?php endforeach; ?></ol>
<?php if ($trail['page'] > 1 || $trail['more']): ?><p class="center" style="margin-top:14px"><?php if ($trail['page'] > 1): ?><a class="btn btn-ghost btn-sm" href="<?= h($link(['page' => $trail['page'] - 1])) ?>">← Newer</a> <?php endif; ?><span class="small muted">Page <?= (int)$trail['page'] ?></span> <?php if ($trail['more']): ?><a class="btn btn-ghost btn-sm" href="<?= h($link(['page' => $trail['page'] + 1])) ?>">Older →</a><?php endif; ?></p><?php endif; ?>
</div>
