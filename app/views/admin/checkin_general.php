<?php
$cat = ['prefix' => $g['prefix']];
$serial = gen_serial($cat, (int)$g['number']);
if ($g['status'] === 'void') { $state = 'bad'; $headline = 'TICKET VOID'; $note = 'Do not admit. This ticket was cancelled.'; }
elseif ($g['status'] === 'used') { $state = 'warn'; $headline = 'ALREADY USED'; $note = 'Checked in at ' . fmt_date($g['used_at'], 'j M Y H:i') . '. Do not admit again without checking with the event manager.'; }
else { $state = 'ok'; $headline = 'VALID TICKET'; $note = 'Admit ' . (int)$g['admits'] . ' guest' . ((int)$g['admits'] === 1 ? '' : 's') . '.'; }
?>
<div class="adm-head"><h1>Door check-in</h1><a href="<?= url('admin/gen-tickets/batch/' . (int)$g['batch_id']) ?>">&larr; Ticket batch</a></div>
<div class="panel checkin checkin-<?= $state ?>">
  <h2><?= h($headline) ?></h2>
  <p class="checkin-note"><?= h($note) ?></p>
  <dl class="summary">
    <dt>Ticket</dt><dd><strong><?= h($serial) ?></strong> · <?= h($g['cat_name']) ?></dd>
    <dt>Event</dt><dd><?= h($g['event_title']) ?></dd>
    <dt>Admits</dt><dd><strong><?= (int)$g['admits'] ?> guest<?= (int)$g['admits'] === 1 ? '' : 's' ?></strong></dd>
  </dl>
  <div class="ck-secure"><div><small>Verify code</small><b class="mono"><?= h(gen_verify((string)$g['token'])) ?></b><p class="small muted">Must match the &ldquo;Verify&rdquo; code printed under the QR code.</p></div></div>
  <?php if (!empty($dups)): ?><p class="alert alert-err"><b>⚠ Duplicate scan.</b> This ticket has been scanned <?= (int)$dups ?> time(s) after it was used. Do not admit.</p><?php endif; ?>
  <?php if ($g['status'] === 'valid'): ?>
    <form method="post" action="<?= url('admin/gen-tickets/ticket/' . (int)$g['id'] . '/checkin') ?>"><?= csrf_field() ?>
      <input type="hidden" name="back" value="admin/checkin/<?= h($token) ?>"><button class="btn btn-primary btn-lg btn-block">Check in <?= (int)$g['admits'] ?> guest<?= (int)$g['admits'] === 1 ? '' : 's' ?></button></form>
  <?php endif; ?>
</div>
