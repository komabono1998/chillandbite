<?php
$cat = ['prefix' => $c['prefix']];
$back = 'admin/gen-tickets/batch/' . (int)$b['id'] . ($status ? '?status=' . $status : '');
$pages = max(1, (int)ceil($total / $per));
$base = 'admin/gen-tickets/download?batch[]=' . (int)$b['id'];
?>
<div class="adm-head">
  <div><h1><span class="chip" style="--c:<?= h($c['color']) ?>;font-size:inherit"><?= h($c['name']) ?></span> tickets</h1>
    <p class="adm-sub"><?= h($b['event_title']) ?> · <?= fmt_date($b['start_at'], 'j M Y') ?> · <b><?= h(gen_serial($cat, (int)$b['from_no'])) ?></b> to <b><?= h(gen_serial($cat, (int)$b['to_no'])) ?></b></p></div>
  <div class="btn-row"><a class="btn btn-primary btn-sm" href="<?= url('admin/gen-tickets/batch/' . (int)$b['id'] . '/edit') ?>">✏️ Edit &amp; preview details</a><a class="btn btn-ghost btn-sm" href="<?= url('admin/gen-tickets') ?>">&larr; All batches</a></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Download or print</h2><span class="muted small"><?= (int)($counts['valid'] ?? 0) ?> unused · <?= (int)($counts['used'] ?? 0) ?> used · <?= (int)($counts['void'] ?? 0) ?> void</span></div>
  <form method="get" action="<?= url('admin/gen-tickets/download') ?>" class="filters">
    <input type="hidden" name="batch[]" value="<?= (int)$b['id'] ?>">
    <label>Page <select name="layout"><option value="a4">A4, 3 landscape tickets per page</option><option value="a5">A5 landscape, 1 per page</option></select></label>
    <label>From number <input type="number" name="from" min="<?= (int)$b['from_no'] ?>" max="<?= (int)$b['to_no'] ?>" placeholder="<?= (int)$b['from_no'] ?>" style="width:110px"></label>
    <label>To number <input type="number" name="to" min="<?= (int)$b['from_no'] ?>" max="<?= (int)$b['to_no'] ?>" placeholder="<?= (int)$b['to_no'] ?>" style="width:110px"></label>
    <label class="small"><input type="checkbox" name="only_valid" value="1"> Unused only</label>
    <button class="btn btn-primary btn-sm" name="mode" value="pdf">⬇ Download PDF</button>
    <button class="btn btn-ghost btn-sm" name="mode" value="print" formtarget="_blank">🖨️ Print</button>
  </form>
  <p class="small muted">Leave From and To empty for the whole batch. The PDF is A4 portrait sheets with three landscape tickets and cut lines, ready for any printer.</p>
</div>

<?php if ($sample): ?>
<div class="panel"><div class="panel-head"><h2>Preview</h2><span class="muted small">First ticket on this page</span></div>
  <style><?= ticket_css() ?>.gt-prev{overflow-x:auto;padding-bottom:6px}.gt-prev .gt{margin:0 auto;box-shadow:0 6px 20px rgba(0,0,0,.18)}</style>
  <div class="gt-prev"><?= ticket_html($sample) ?></div></div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head"><h2>Tickets</h2>
    <nav class="pills" style="margin:0"><?php foreach ([''=>'All', 'valid'=>'Unused', 'used'=>'Used', 'void'=>'Void'] as $k => $l): ?><a href="<?= url('admin/gen-tickets/batch/' . (int)$b['id'] . ($k ? '?status=' . $k : '')) ?>"<?= $status === $k ? ' aria-current="true"' : '' ?>><?= $l ?></a><?php endforeach; ?></nav></div>
  <?php if (!$tickets): ?><div class="empty">No tickets here.</div><?php else: ?>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Ticket</th><th>Status</th><th>Used at</th><th></th></tr></thead><tbody>
  <?php foreach ($tickets as $t): $st = ['valid' => ['Unused', 'badge-ok'], 'used' => ['Used', 'badge-warn'], 'void' => ['Void', 'badge-err']][$t['status']]; ?>
    <tr><td><b><?= h(gen_serial($cat, (int)$t['number'])) ?></b> <span class="muted small mono">verify <?= h(gen_verify((string)$t['token'])) ?></span></td>
      <td><span class="badge <?= $st[1] ?>"><?= $st[0] ?></span></td><td><?= $t['used_at'] ? fmt_date($t['used_at'], 'j M H:i') : '' ?></td>
      <td style="white-space:nowrap"><a class="btn btn-ghost btn-sm" href="<?= url($base . '&from=' . (int)$t['number'] . '&to=' . (int)$t['number']) ?>">PDF</a>
        <a class="btn btn-ghost btn-sm" href="<?= url($base . '&mode=print&from=' . (int)$t['number'] . '&to=' . (int)$t['number']) ?>" target="_blank" rel="noopener">Print</a>
        <?php foreach (($t['status'] === 'valid' ? [['checkin', 'Check in'], ['void', 'Void']] : ($t['status'] === 'used' ? [['undo', 'Undo check-in']] : [['restore', 'Restore']])) as [$act, $lab]): ?>
          <form class="inline" method="post" action="<?= url('admin/gen-tickets/ticket/' . (int)$t['id'] . '/' . $act) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-ghost btn-sm"<?= $act === 'void' ? ' onclick="return confirm(\'Void this ticket? It will be rejected at the gate.\')"' : '' ?>><?= $lab ?></button></form>
        <?php endforeach; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php if ($pages > 1): ?><p class="small" style="margin-top:12px">Page <?= $page ?> of <?= $pages ?>
    <?php if ($page > 1): ?> · <a href="<?= url('admin/gen-tickets/batch/' . (int)$b['id'] . '?page=' . ($page - 1) . ($status ? '&status=' . $status : '')) ?>">&larr; Previous</a><?php endif; ?>
    <?php if ($page < $pages): ?> · <a href="<?= url('admin/gen-tickets/batch/' . (int)$b['id'] . '?page=' . ($page + 1) . ($status ? '&status=' . $status : '')) ?>">Next &rarr;</a><?php endif; ?></p><?php endif; ?>
  <?php endif; ?>
</div>
