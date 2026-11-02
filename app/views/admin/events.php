<div class="adm-head"><div><h1>Events</h1><p class="adm-sub">Create, publish, reschedule and cancel events.</p></div><div class="btn-row"><?= export_buttons('events', []) ?><a class="btn btn-primary" href="<?= url('admin/events/new') ?>">+ New event</a></div></div>
<div class="panel"><div class="tbl-wrap"><table class="tbl"><tr><th>Event</th><th>When</th><th>Status</th><th>Booked</th><th>Actions</th></tr>
<?php foreach ($events as $e): ?><tr>
  <td><a href="<?= url('admin/events/' . $e['id']) ?>"><strong><?= h($e['title']) ?></strong></a><br><span class="muted small"><?= h($e['category']) ?></span></td>
  <td><?= fmt_date($e['start_at']) ?><br><span class="small muted"><?= fmt_time($e['start_at']) ?></span></td>
  <td><?= status_badge($e['status']) ?></td>
  <td><?= (int)$e['booked'] ?><?= $e['capacity'] ? ' / ' . (int)$e['capacity'] : '' ?></td>
  <td><div class="actions">
    <a class="btn btn-ghost btn-sm" href="<?= url('admin/reservations?event=' . $e['id']) ?>">Guests</a>
    <?php foreach (['published' => 'Publish', 'cancelled' => 'Cancel', 'archived' => 'Archive', 'draft' => 'Unpublish'] as $st => $lab) if ($st !== $e['status']): ?>
      <form class="inline" method="post" action="<?= url('admin/events/' . $e['id'] . '/status') ?>"><?= csrf_field() ?><input type="hidden" name="status" value="<?= $st ?>"><button class="btn btn-ghost btn-sm"<?= $st === 'cancelled' ? ' onclick="return confirm(\'Cancel this event?\')"' : '' ?>><?= $lab ?></button></form>
    <?php endif; ?></div></td></tr><?php endforeach; ?></table></div></div>
<div class="panel"><h2>Recurring monthly event</h2>
  <p class="muted">Create the next first-Sunday editions from an existing event. They are created as drafts and each date can be changed afterwards (for example when an event is rescheduled).</p>
  <form method="post" action="<?= url('admin/events/recurring') ?>" class="filters"><?= csrf_field() ?>
    <label>Template <select name="template_id"><?php foreach ($events as $e): ?><option value="<?= $e['id'] ?>"><?= h($e['title']) ?></option><?php endforeach; ?></select></label>
    <label>Months <input type="number" name="months" value="3" min="1" max="12"></label>
    <button class="btn btn-primary btn-sm">Generate</button></form></div>
