<?php
$chColor = ['daraza_mtn' => '#f5c400', 'daraza_airtel' => '#e0282e', 'cash' => '#2f9e56', 'mobile_money' => '#7a5cc7', 'bank' => '#2563eb', 'pesapal' => '#0ea5e9', 'daraza_refund' => '#8a8378', 'refund' => '#8a8378'];
$statusPill = ['confirmed' => '#2f9e56', 'pending' => '#d99a1c', 'rejected' => '#d9534f', 'cancelled' => '#8a8378'];
$grand = ['net' => 0, 'paid' => 0, 'refunded' => 0, 'outstanding' => 0];
foreach ($totals as $e) { $grand['net'] += $e['net']; $grand['paid'] += $e['paid']; $grand['refunded'] += $e['refunded']; $grand['outstanding'] += $e['outstanding']; }
?>
<form class="panel filters dash-filter" method="get" action="<?= url('admin') ?>"><input type="hidden" name="tab" value="events">
  <label>Event <select name="event" onchange="this.form.submit()"><option value="0">All events (separate totals for each)</option><?php foreach ($all_events as $e): ?><option value="<?= (int)$e['id'] ?>"<?= (int)$e['id'] === $event_pick ? ' selected' : '' ?>><?= h(excerpt($e['title'], 54)) ?> · <?= h(fmt_date($e['start_at'], 'j M Y')) ?></option><?php endforeach; ?></select></label>
  <?php if ($event_pick): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin?tab=events') ?>">← All events</a><a class="btn btn-primary btn-sm" href="<?= url('admin?tab=events&event=' . $event_pick . '&export=csv') ?>">⬇ Download this event's records (CSV)</a><?php endif; ?>
  <noscript><button class="btn btn-ghost btn-sm">Show</button></noscript>
</form>

<?php if (!$event_pick): ?>
<div class="kpis">
  <div class="kpi kpi-ok"><span class="kpi-ic">💰</span><div><b><?= h(money($grand['net'])) ?></b><span>Collected, all events (net)</span></div></div>
  <div class="kpi"><span class="kpi-ic">⬇️</span><div><b><?= h(money($grand['paid'])) ?></b><span>Money in</span></div></div>
  <div class="kpi kpi-info"><span class="kpi-ic">↩️</span><div><b><?= h(money($grand['refunded'])) ?></b><span>Refunded</span></div></div>
  <div class="kpi kpi-warn"><span class="kpi-ic">🧾</span><div><b><?= h(money($grand['outstanding'])) ?></b><span>Still owing</span></div></div>
</div>
<?php endif; ?>

<?php if (!$totals): ?><div class="empty">No events found.</div><?php endif; ?>
<div class="ev-list">
<?php foreach ($totals as $e): $booked = (int)($e['res']['confirmed']['n'] ?? 0) + (int)($e['res']['pending']['n'] ?? 0); $pc = $e['due'] > 0 ? min(100, round($e['net'] / $e['due'] * 100)) : 0; ?>
  <section class="panel ev-block" id="event-<?= (int)$e['id'] ?>">
    <div class="ev-head">
      <div><span class="ev-date">📅 <?= h(fmt_date($e['start_at'], 'l, j F Y')) ?> · <?= h(fmt_time($e['start_at'])) ?> · <?= h($e['venue']) ?><?= $e['location'] ? ', ' . h($e['location']) : '' ?></span><h2><?= h($e['title']) ?></h2></div>
      <div class="ev-total"><b><?= h(money($e['net'])) ?></b><small>collected<?= $e['refunded'] ? ' (' . h(money($e['paid'])) . ' in, ' . h(money($e['refunded'])) . ' refunded)' : '' ?></small></div>
    </div>
    <div class="ev-bar ev-bar-lg"><i style="width:<?= $pc ?>%"></i></div>
    <div class="ev-facts">
      <span><b><?= $booked ?></b> bookings</span><span><b><?= (int)$e['guests'] ?></b> guests</span>
      <span><b><?= (int)($e['res']['confirmed']['n'] ?? 0) ?></b> confirmed</span><span><b><?= (int)($e['res']['pending']['n'] ?? 0) ?></b> pending</span>
      <span><b><?= h(money($e['due'])) ?></b> expected</span><span class="<?= $e['outstanding'] ? 'ev-owe' : 'ev-ok' ?>"><b><?= $e['outstanding'] ? h(money($e['outstanding'])) : 'Paid up' ?></b><?= $e['outstanding'] ? ' owing' : '' ?></span>
      <span><b><?= (int)$e['att']['success'] ?></b> online payments ok · <b><?= (int)$e['att']['failed'] ?></b> failed<?= $e['att']['pending'] + $e['att']['unconfirmed'] ? ' · <b>' . ($e['att']['pending'] + $e['att']['unconfirmed']) . '</b> waiting' : '' ?></span>
    </div>
    <?php if ($e['by_ch']): ?><div class="ev-ch"><?php foreach ($e['by_ch'] as $c => $v): ?><span class="ev-chip"><i style="background:<?= $chColor[$c] ?? '#999' ?>"></i><?= h(dash_channel($c)) ?> <b><?= h(money($v['amt'])) ?></b> <small>(<?= (int)$v['n'] ?>)</small></span><?php endforeach; ?></div><?php endif; ?>
    <?php if ($e['pkg']): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Package / type</th><th>Bookings</th><th>Guests</th><th>Expected</th><th>Collected</th><th>Owing</th></tr></thead><tbody>
      <?php foreach ($e['pkg'] as $p): $owe = max(0, (int)$p['due'] - (int)$p['paid']); ?><tr><td><b><?= h($p['pkg']) ?></b></td><td><?= (int)$p['n'] ?></td><td><?= (int)$p['g'] ?></td><td><?= h(money($p['due'])) ?></td><td><?= h(money($p['paid'])) ?></td><td><?= $owe ? h(money($owe)) : '—' ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    <?php if (!$event_pick): ?><p style="margin:10px 0 0"><a class="btn btn-ghost btn-sm" href="<?= url('admin?tab=events&event=' . (int)$e['id']) ?>">Open all records for this event →</a> <a class="btn btn-ghost btn-sm" href="<?= url('admin?tab=trail&event=' . (int)$e['id']) ?>">Activity trail →</a> <a class="btn btn-ghost btn-sm" href="<?= url('admin/reservations?event=' . (int)$e['id']) ?>">Guest list →</a></p><?php endif; ?>

    <?php if ($event_pick): ?>
      <h3 class="ev-sub">Every payment <small class="muted">(<?= count($pay_rows) ?> record<?= count($pay_rows) === 1 ? '' : 's' ?>, newest first)</small></h3>
      <?php if ($pay_rows): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Date &amp; time</th><th>Reference</th><th>Guest</th><th>Package</th><th>Route</th><th>Amount</th><th>Recorded by</th></tr></thead><tbody>
        <?php foreach ($pay_rows as $p): ?><tr><td><?= h(fmt_date($p['created_at'], 'D j M Y')) ?><br><span class="small muted"><?= h(date('g:i:s A', strtotime($p['created_at']))) ?></span></td>
          <td><a href="<?= url('admin/reservations/' . (int)$p['reservation_id']) ?>"><?= h($p['reference']) ?></a></td><td><?= h($p['full_name']) ?><br><span class="small muted"><?= h($p['phone']) ?></span></td><td><?= h((string)$p['package']) ?></td>
          <td><span class="ev-chip"><i style="background:<?= $chColor[$p['ch']] ?? '#999' ?>"></i><?= h(dash_channel($p['ch'])) ?></span><?= $p['note'] ? '<br><span class="small muted">' . h(excerpt($p['note'], 60)) . '</span>' : '' ?></td>
          <td><b style="color:<?= $p['amount'] < 0 ? '#c0392b' : '#1f8a4c' ?>"><?= h(money($p['amount'])) ?></b></td><td><?= h($p['by_name'] ?: ($p['channel'] ? 'Online (automatic)' : 'Staff')) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php else: ?><div class="empty">No payments recorded for this event yet.</div><?php endif; ?>

      <h3 class="ev-sub">Every payment attempt <small class="muted">(start and finish time of each online try)</small></h3>
      <?php if ($att_rows): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Started</th><th>Finished</th><th>Reference</th><th>Guest / phone</th><th>Amount</th><th>Result</th></tr></thead><tbody>
        <?php $aCol = ['success' => '#2f9e56', 'failed' => '#d9534f', 'pending' => '#d99a1c', 'unconfirmed' => '#7a5cc7']; foreach ($att_rows as $a): ?><tr>
          <td><?= h(fmt_date($a['created_at'], 'D j M')) ?><br><span class="small muted"><?= h(date('g:i:s A', strtotime($a['created_at']))) ?></span></td>
          <td><?= $a['completed_at'] ? h(date('g:i:s A', strtotime($a['completed_at']))) . '<br><span class="small muted">' . max(0, strtotime($a['completed_at']) - strtotime($a['created_at'])) . ' sec</span>' : '<span class="muted">—</span>' ?></td>
          <td><a href="<?= url('admin/reservations/' . (int)$a['reservation_id']) ?>"><?= h($a['reference']) ?></a></td><td><?= h($a['full_name']) ?><br><span class="small muted"><?= h($a['phone']) ?> · <?= (int)$a['method'] === 2 ? 'Airtel' : 'MTN' ?></span></td>
          <td><?= h(money($a['amount'])) ?><?= $a['direction'] === 'refund' ? ' <span class="small muted">(refund)</span>' : '' ?></td>
          <td><span class="pill" style="background:<?= $aCol[$a['status']] ?? '#999' ?>;color:#fff"><?= h(ucfirst($a['status'])) ?></span><?= $a['message'] ? '<br><span class="small muted">' . h(excerpt((string)$a['message'], 70)) . '</span>' : '' ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php else: ?><div class="empty">No online payment attempts for this event.</div><?php endif; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
