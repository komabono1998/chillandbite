<?php
$first = trim(explode(' ', trim($u['name']))[0]);
$hr = (int)date('G');
$hello = $hr < 12 ? 'Good morning' : ($hr < 17 ? 'Good afternoon' : 'Good evening');
$isDoorOnly = !can_any(['reservations', 'reports', 'events', 'pages', 'news', 'advertising', 'inquiries']);

// KPI cards, built from what this role is allowed to see: [icon, label, value, link, tone]
$k = [];
if (isset($next)) $k[] = ['📅', 'Upcoming events', count($events), url('admin/events'), ''];
if (isset($stat_pending)) $k[] = ['⏳', 'Pending approvals', $stat_pending, url('admin/reservations?status=pending'), $stat_pending ? 'warn' : ''];
if (isset($stat_guests)) $k[] = ['🎟️', 'Confirmed guests (upcoming)', $stat_guests, url('admin/reservations?status=confirmed'), 'ok'];
if (isset($stat_today)) $k[] = ['🆕', 'Bookings today', $stat_today, url('admin/reservations'), 'info'];
if (isset($revenue)) $k[] = ['💰', 'Payments received', money($revenue), url('admin/reports'), 'ok'];
if (isset($outstanding)) $k[] = ['🧾', 'Outstanding balances', money($outstanding), url('admin/reports'), $outstanding ? 'warn' : ''];
if (isset($stat_inquiries)) $k[] = ['📬', 'New inquiries', $stat_inquiries, url('admin/inquiries'), $stat_inquiries ? 'warn' : ''];
if (isset($stat_partners)) $k[] = ['🤝', 'Partner applications', $stat_partners, url('admin/partners?status=pending'), $stat_partners ? 'warn' : ''];
if (isset($stat_sponsors))$k[] = ['📣', 'Live sponsors', $stat_sponsors, url('admin/advertising'), 'info'];

// Donut (reservation status mix)
$statusColors = ['confirmed' => '#2f9e56', 'pending' => '#f1b84a', 'cancelled' => '#c8c1b4', 'rejected' => '#d9534f'];
$donut = ''; $totalRes = 0;
if (isset($by_status)) {
    $totalRes = array_sum($by_status); $acc = 0; $stops = [];
    foreach ($statusColors as $s => $c) {
        $n = (int)($by_status[$s] ?? 0); if (!$n) continue;
        $from = $acc / max(1, $totalRes) * 100; $acc += $n; $to = $acc / max(1, $totalRes) * 100;
        $stops[] = "$c " . round($from, 2) . '% ' . round($to, 2) . '%';
    }
    $donut = $stops ? 'conic-gradient(' . implode(',', $stops) . ')' : 'conic-gradient(#efe9dd 0 100%)';
}
$maxDay = isset($daily) ? max(1, max(array_column($daily, 'n'))) : 1;
$maxType = isset($by_type) && $by_type ? max(array_column($by_type, 'g')) : 1;
$maxRev = isset($rev_events) && $rev_events ? max(1, max(array_column($rev_events, 'revenue'))) : 1;
?>
<div class="welcome">
  <div><h1><?= h($hello) ?>, <?= h($first) ?> 👋</h1><p>Here is what is happening at Chill &amp; Bite today · <?= date('l, j F Y') ?></p></div>
  <div class="btn-row">
    <?php if (can('checkin')): ?><a class="btn btn-primary" href="<?= url('admin/checkin') ?>">✅ Open check-in desk</a><?php endif; ?>
    <?php if (can('reports')): ?><a class="btn btn-ghost" href="<?= url('admin/reports') ?>">📊 Reports &amp; exports</a><?php endif; ?>
    <a class="btn btn-ghost" href="<?= url() ?>" target="_blank" rel="noopener">View website ↗</a>
  </div>
</div>

<?php if ($k): ?><div class="kpis">
  <?php foreach ($k as [$ic, $label, $v, $href, $tone]): ?>
    <a class="kpi<?= $tone ? ' kpi-' . $tone : '' ?>" href="<?= h($href) ?>"><span class="kpi-ic" aria-hidden="true"><?= $ic ?></span><div><b><?= h($v) ?></b><span><?= h($label) ?></span></div></a>
  <?php endforeach; ?>
</div><?php endif; ?>

<?php if (can('pages')): $qePages = array_filter(site_pages(), fn($pg, $pid) => can_edit_page($pid), ARRAY_FILTER_USE_BOTH); ?>
<div class="panel qe-panel">
  <div class="panel-head"><h2>✏️ Quick edit</h2><span class="muted small">Jump straight to the text or pictures of any page</span></div>
  <div class="qe-grid">
    <?php foreach ($qePages as $pid => $pg): ?>
      <div class="qe-item"><b><?= h($pg['icon']) ?> <?= h($pg['label']) ?></b>
        <span><a href="<?= url('admin/pages/' . $pid) ?>">Text</a><a href="<?= url('admin/pictures#page-' . $pid) ?>">Pictures</a><a target="_blank" rel="noopener" href="<?= url($pg['url'] === 'home' ? '' : $pg['url']) ?>">View ↗</a></span></div>
    <?php endforeach; ?>
    <div class="qe-item qe-item-wide"><b>🖼️ All pictures</b><span><a href="<?= url('admin/pictures#photos') ?>">Photo library</a><a href="<?= url('admin/pictures#brand') ?>">Logo &amp; icon</a><?php if (can('gallery')): ?><a href="<?= url('admin/gallery') ?>">Gallery</a><?php endif; ?><?php if (can('events')): ?><a href="<?= url('admin/events') ?>">Event posters</a><?php endif; ?><?php if (can('djs')): ?><a href="<?= url('admin/djs') ?>">DJs &amp; mixes</a><?php endif; ?></span></div>
  </div>
</div>
<?php endif; ?>

<div class="dash-grid">
<?php if (!empty($next)): $n = $next; $days = max(0, (int)ceil((strtotime($n['start_at']) - time()) / 86400)); ?>
  <div class="col-7"><div class="next-card">
    <span class="eyebrow">Next event</span>
    <h2><?= h($n['title']) ?></h2>
    <div class="next-meta"><span>📅 <?= fmt_date($n['start_at'], 'l, j F Y') ?> · <?= fmt_time($n['start_at']) ?></span><span>📍 <?= h($n['venue']) ?></span></div>
    <div style="display:flex;gap:26px;align-items:flex-end;flex-wrap:wrap;position:relative;z-index:1">
      <div class="next-days"><?= $days ?><small>day<?= $days === 1 ? '' : 's' ?> to go</small></div>
      <div style="flex:1;min-width:240px">
        <div class="cap" title="Confirmed / pending / checked in"><i class="c-in" style="width:<?= $n['capacity'] ? min(100, round($n['checked'] / $n['capacity'] * 100, 1)) : 0 ?>%"></i><i class="c-ok" style="width:<?= $n['capacity'] ? min(100, round(max(0, $n['confirmed'] - $n['checked']) / $n['capacity'] * 100, 1)) : 0 ?>%"></i><i class="c-wait" style="width:<?= $n['capacity'] ? min(100, round($n['pending'] / $n['capacity'] * 100, 1)) : 0 ?>%"></i></div>
        <p style="margin:.4rem 0 0;font-size:.82rem;color:#e8dfd1"><?= (int)$n['booked'] ?><?= $n['capacity'] ? ' of ' . (int)$n['capacity'] : '' ?> places booked · <?= (int)$n['confirmed'] ?> confirmed · <?= (int)$n['pending'] ?> pending · <?= (int)$n['checked'] ?> checked in</p>
      </div>
    </div>
    <div class="btn-row" style="margin-top:14px">
      <?php if (can('reservations')): ?><a class="btn btn-primary btn-sm" href="<?= url('admin/reservations?event=' . $n['id']) ?>">Guest list</a><?php endif; ?>
      <?php if (can('checkin')): ?><a class="btn btn-outline btn-sm" style="border-color:var(--gold);color:var(--gold)" href="<?= url('admin/checkin?event=' . $n['id']) ?>">Check-in</a><?php endif; ?>
      <?php if (can('events')): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/events/' . $n['id']) ?>">Edit event</a><?php endif; ?>
    </div>
  </div></div>
  <div class="col-5"><div class="panel">
    <div class="panel-head"><h2>Capacity by event</h2></div>
    <?php foreach ($events as $e): $cap = (int)$e['capacity']; ?>
      <div class="cap-row"><div class="top"><span><b><?= h(excerpt($e['title'], 38)) ?></b> <span class="muted">· <?= fmt_date($e['start_at'], 'j M') ?></span></span><span><?= (int)$e['booked'] ?><?= $cap ? ' / ' . $cap : '' ?></span></div>
        <div class="cap"><i class="c-in" style="width:<?= $cap ? min(100, round($e['checked'] / $cap * 100, 1)) : 0 ?>%"></i><i class="c-ok" style="width:<?= $cap ? min(100, round(max(0, $e['confirmed'] - $e['checked']) / $cap * 100, 1)) : 0 ?>%"></i><i class="c-wait" style="width:<?= $cap ? min(100, round($e['pending'] / $cap * 100, 1)) : 0 ?>%"></i></div></div>
    <?php endforeach; ?>
    <div class="cap-key"><span><i style="background:#171717"></i>Checked in</span><span><i style="background:#2f9e56"></i>Confirmed</span><span><i style="background:#f1b84a"></i>Pending</span></div>
  </div></div>
<?php elseif (isset($events) && !$events && can_any(['events', 'reservations'])): ?>
  <div class="col-12"><div class="empty">No upcoming published events. <?php if (can('events')): ?><a href="<?= url('admin/events/new') ?>">Create one</a>.<?php endif; ?></div></div>
<?php endif; ?>

<?php if (isset($daily)): ?>
  <div class="col-7"><div class="panel">
    <div class="panel-head"><h2>Bookings, last 14 days</h2><span class="muted small"><?= array_sum(array_column($daily, 'n')) ?> bookings · <?= array_sum(array_column($daily, 'g')) ?> guests</span></div>
    <div class="bars" role="img" aria-label="Bookings per day for the last 14 days">
      <?php foreach ($daily as $day => $v): ?>
        <div class="col" title="<?= h(fmt_date($day, 'D j M')) ?>: <?= $v['n'] ?> booking(s), <?= $v['g'] ?> guest(s)">
          <span class="val"><?= $v['n'] ?: '' ?></span><div class="bar" style="height:<?= $v['n'] ? max(4, round($v['n'] / $maxDay * 100)) : 2 ?>%<?= $v['n'] ? '' : ';background:#e9e3d8' ?>"></div><span class="lab"><?= date('j/n', strtotime($day)) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div></div>
  <div class="col-5"><div class="panel">
    <div class="panel-head"><h2>Booking status</h2><a class="small" href="<?= url('admin/reservations') ?>">All bookings</a></div>
    <?php if ($totalRes): ?>
      <div class="donut-wrap">
        <div class="donut" style="background:<?= h($donut) ?>"><div class="donut-center"><div><b><?= $totalRes ?></b><br><small>bookings</small></div></div></div>
        <ul class="legend"><?php foreach ($statusColors as $s => $c): ?><li><i style="background:<?= $c ?>"></i><?= ucfirst($s) ?><b><?= (int)($by_status[$s] ?? 0) ?></b></li><?php endforeach; ?></ul>
      </div>
    <?php else: ?><div class="empty">No bookings yet.</div><?php endif; ?>
  </div></div>

  <div class="col-6"><div class="panel">
    <div class="panel-head"><h2>Guests by reservation type</h2></div>
    <?php if ($by_type): ?><div class="hbars"><?php foreach ($by_type as $t): ?>
      <div class="row"><span><?= h(str_replace(' Reservation', '', RES_TYPES[$t['type']])) ?></span><span class="track"><i class="fill" style="width:<?= round($t['g'] / $maxType * 100) ?>%"></i></span><span class="num"><?= (int)$t['g'] ?></span></div>
    <?php endforeach; ?></div><?php else: ?><div class="empty">No active bookings.</div><?php endif; ?>
  </div></div>
<?php endif; ?>

<?php if (isset($rev_events)): ?>
  <div class="col-6"><div class="panel">
    <div class="panel-head"><h2>Revenue by event</h2><a class="small" href="<?= url('admin/reports') ?>">Reports</a></div>
    <?php $any = false; foreach ($rev_events as $e) if ($e['revenue'] > 0 || $e['outstanding'] > 0) { $any = true; ?>
      <div class="hbars"><div class="row" style="grid-template-columns:150px 1fr 110px"><span title="<?= h($e['title']) ?>"><?= h(excerpt($e['title'], 22)) ?></span><span class="track"><i class="fill" style="width:<?= round($e['revenue'] / $maxRev * 100) ?>%"></i></span><span class="num"><?= h(money($e['revenue'])) ?></span></div></div>
    <?php } if (!$any): ?><div class="empty">No payments recorded yet.</div><?php endif; ?>
  </div></div>
<?php endif; ?>

<?php if (!empty($pending_list)): ?>
  <div class="col-7"><div class="panel">
    <div class="panel-head"><h2>⏳ Needs approval</h2><a class="small" href="<?= url('admin/reservations?status=pending') ?>">See all pending</a></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Guest</th><th>Booking</th><th>Amount</th><th></th></tr></thead><tbody>
    <?php foreach ($pending_list as $r): ?><tr>
      <td><a href="<?= url('admin/reservations/' . $r['id']) ?>"><b><?= h($r['full_name']) ?></b></a><br><span class="small muted"><?= h($r['reference']) ?> · <?= h(excerpt($r['title'], 30)) ?></span></td>
      <td><?= h(RES_TYPES[$r['type']]) ?><?= $r['package'] ? '<br><span class="small muted">' . h($r['package']) . '</span>' : '' ?><br><span class="small muted"><?= (int)$r['guests'] ?> guest(s)</span></td>
      <td><?= $r['amount_due'] > 0 ? h(money($r['amount_due'])) : 'Free' ?></td>
      <td><?php if (can('reservations_manage')): ?><div class="actions"><?php foreach (['approve' => ['Approve', 'btn-primary'], 'reject' => ['Reject', 'btn-ghost']] as $act => [$lab, $cls]): ?>
        <form class="inline" method="post" action="<?= url('admin/reservations/' . $r['id'] . '/action') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $act ?>"><input type="hidden" name="back" value="admin/reservations?status=pending"><button class="btn <?= $cls ?> btn-sm"><?= $lab ?></button></form>
      <?php endforeach; ?></div><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div></div>
<?php endif; ?>

<?php if (!empty($inq_list) || !empty($partner_list)): ?>
  <div class="col-5"><div class="panel">
    <div class="panel-head"><h2>📬 Waiting for a reply</h2></div>
    <ul class="feed">
      <?php foreach ($inq_list ?? [] as $i): ?><li><span class="dot"></span><div><a href="<?= url('admin/inquiries?type=' . $i['type']) ?>"><b><?= h($i['name']) ?></b></a> <span class="role-pill"><?= h($i['type']) ?></span><small><?= h(excerpt($i['subject'] ?: $i['message'] ?? '', 60)) ?> · <?= fmt_date($i['created_at'], 'j M') ?></small></div></li><?php endforeach; ?>
      <?php foreach ($partner_list ?? [] as $p): ?><li><span class="dot" style="background:#2f9e56"></span><div><a href="<?= url('admin/partners?status=pending') ?>"><b><?= h($p['company']) ?></b></a> <span class="role-pill">partner</span><small><?= h($p['partnership_type']) ?> · <?= fmt_date($p['created_at'], 'j M') ?></small></div></li><?php endforeach; ?>
    </ul>
  </div></div>
<?php endif; ?>

<?php if (!empty($recent)): ?>
  <div class="col-7"><div class="panel">
    <div class="panel-head"><h2>Latest reservations</h2><a class="small" href="<?= url('admin/reservations') ?>">View all</a></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Ref</th><th>Guest</th><th>Event</th><th>Guests</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($recent as $r): ?><tr><td><a href="<?= url('admin/reservations/' . $r['id']) ?>"><?= h($r['reference']) ?></a></td><td><?= h($r['full_name']) ?></td><td><?= h(excerpt($r['title'], 34)) ?></td><td><?= (int)$r['guests'] ?></td><td><?= status_badge($r['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div></div>
<?php endif; ?>

<?php if (!empty($activity)): ?>
  <div class="col-5"><div class="panel">
    <div class="panel-head"><h2>Recent activity</h2><a class="small" href="<?= url('admin/audit') ?>">Audit log</a></div>
    <ul class="feed"><?php foreach ($activity as $a): ?><li><span class="dot"></span><div><b><?= h($a['name'] ?? 'System') ?></b> · <?= h($a['action']) ?><small><?= h(excerpt($a['detail'], 56)) ?> · <?= fmt_date($a['created_at'], 'j M, H:i') ?></small></div></li><?php endforeach; ?></ul>
  </div></div>
<?php endif; ?>

  <div class="col-12"><div class="panel">
    <div class="panel-head"><h2>Quick actions</h2></div>
    <div class="quick">
      <?php foreach ([
          ['pages', '✏️', 'Edit website pages', 'admin/pages'], ['events', '➕', 'New event', 'admin/events/new'],
          ['gallery', '📷', 'Upload photos', 'admin/gallery'], ['checkin', '✅', 'Check-in desk', 'admin/checkin'], ['reservations', '🎟️', 'Reservations', 'admin/reservations'],
          ['reports', '📊', 'Reports & exports', 'admin/reports'], ['advertising', '🤝', 'Partner applications', 'admin/partners'], ['users', '👥', 'Users & roles', 'admin/users'],
      ] as [$mod, $ic, $label, $path]) if (can($mod)): ?><a href="<?= url($path) ?>"><span><?= $ic ?></span><?= h($label) ?></a><?php endif; ?>
    </div>
  </div></div>
</div>
