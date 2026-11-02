<div class="adm-head"><div><h1>Reservations</h1><p class="adm-sub">Search, approve and track every booking. Exports follow the filters below.</p></div><?= export_buttons('reservations', $_GET) ?></div>
<?php if ($ev): ?><div class="stats">
  <div class="stat"><b><?= (int)$booked ?><?= $ev['capacity'] ? ' / ' . (int)$ev['capacity'] : '' ?></b><span>Guests booked (pending + confirmed)</span></div>
  <div class="stat"><b><?= $ev['capacity'] ? max(0, (int)$ev['capacity'] - (int)$booked) : '∞' ?></b><span>Available capacity</span></div>
  <div class="stat"><b><?= (int)$checked ?></b><span>Guests checked in</span></div></div><?php endif; ?>
<form class="filters panel" method="get">
  <label>Event <select name="event"><option value="">All</option><?php foreach ($events as $x): ?><option value="<?= $x['id'] ?>"<?= (int)($_GET['event'] ?? 0) === (int)$x['id'] ? ' selected' : '' ?>><?= h($x['title']) ?></option><?php endforeach; ?></select></label>
  <label>Status <select name="status"><option value="">All</option><?php foreach (['pending', 'confirmed', 'rejected', 'cancelled'] as $s): ?><option<?= ($_GET['status'] ?? '') === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></label>
  <label>Booked on <input type="date" name="date" value="<?= h($_GET['date'] ?? '') ?>"></label>
  <label>Search <input type="search" name="q" placeholder="Name, phone, ref" value="<?= h($_GET['q'] ?? '') ?>"></label>
  <button class="btn btn-primary btn-sm">Filter</button></form>
<div class="panel"><div class="tbl-wrap"><table class="tbl"><tr><th>Ref</th><th>Guest</th><th>Event</th><th>Type</th><th>Guests</th><th>Balance</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($list as $r): $bal = max(0, $r['amount_due'] - $r['paid']); ?><tr>
  <td><a href="<?= url('admin/reservations/' . $r['id']) ?>"><?= h($r['reference']) ?></a></td>
  <td><strong><?= h($r['full_name']) ?></strong><br><span class="small muted"><?= h($r['phone']) ?></span></td>
  <td><?= h($r['event_title']) ?></td><td><?= h(RES_TYPES[$r['type']]) ?><?= $r['package'] ? '<br><span class="small muted">' . h($r['package']) . '</span>' : '' ?></td><td><?= (int)$r['guests'] ?></td>
  <td><?= $r['amount_due'] > 0 ? money($bal) : '—' ?></td>
  <td><?= status_badge($r['status']) ?><?= $r['checked_in_at'] ? '<br><span class="badge badge-ok">In</span>' : '' ?></td>
  <td><div class="actions"><?php foreach (array_filter([
      $r['status'] === 'pending' && can('reservations_manage') ? ['approve', 'Approve'] : null, $r['status'] === 'pending' && can('reservations_manage') ? ['reject', 'Reject'] : null,
      $r['status'] === 'confirmed' && !$r['checked_in_at'] && can('checkin') ? ['checkin', 'Check in'] : null] ) as [$act, $lab]): ?>
    <form class="inline" method="post" action="<?= url('admin/reservations/' . $r['id'] . '/action') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $act ?>"><input type="hidden" name="back" value="admin/reservations?<?= h(http_build_query($_GET)) ?>"><button class="btn btn-ghost btn-sm"><?= $lab ?></button></form>
  <?php endforeach; ?></div></td></tr><?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="8" class="muted">No reservations match.</td></tr><?php endif; ?></table></div></div>
