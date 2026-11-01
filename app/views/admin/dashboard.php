<h1>Dashboard</h1>
<div class="stats"><?php foreach ($s as $l => $v): ?><div class="stat"><b><?= h($v) ?></b><span><?= h($l) ?></span></div><?php endforeach; ?></div>
<?php if (can('events')): ?><div class="panel"><h2>Upcoming events &amp; capacity</h2>
  <div class="tbl-wrap"><table class="tbl"><tr><th>Event</th><th>Date</th><th>Booked</th><th></th></tr>
  <?php foreach ($events as $e): ?><tr><td><?= h($e['title']) ?></td><td><?= fmt_date($e['start_at']) ?></td><td><?= (int)$e['booked'] ?><?= $e['capacity'] ? ' / ' . (int)$e['capacity'] : '' ?></td><td><a href="<?= url('admin/reservations?event=' . $e['id']) ?>">Guests</a></td></tr><?php endforeach; ?>
  <?php if (!$events): ?><tr><td colspan="4" class="muted">No upcoming published events.</td></tr><?php endif; ?></table></div></div><?php endif; ?>
<?php if (can('reservations')): ?><div class="panel"><h2>Latest reservations</h2><div class="tbl-wrap"><table class="tbl"><tr><th>Ref</th><th>Name</th><th>Event</th><th>Guests</th><th>Status</th></tr>
  <?php foreach ($recent as $r): ?><tr><td><a href="<?= url('admin/reservations/' . $r['id']) ?>"><?= h($r['reference']) ?></a></td><td><?= h($r['full_name']) ?></td><td><?= h($r['title']) ?></td><td><?= (int)$r['guests'] ?></td><td><?= status_badge($r['status']) ?></td></tr><?php endforeach; ?></table></div></div><?php endif; ?>
<div class="panel"><h2>Latest inquiries</h2><div class="tbl-wrap"><table class="tbl"><tr><th>Type</th><th>From</th><th>Subject</th><th>Status</th></tr>
  <?php foreach ($inq as $i): if (!can('inquiries')) break; ?><tr><td><?= h($i['type']) ?></td><td><?= h($i['name']) ?></td><td><?= h($i['subject']) ?></td><td><?= status_badge($i['status']) ?></td></tr><?php endforeach; ?></table></div></div>
