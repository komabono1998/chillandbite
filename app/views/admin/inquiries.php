<div class="adm-head"><div><h1>Inquiries</h1><p class="adm-sub">Messages, comedy bookings and advertising requests.</p></div><?= export_buttons('inquiries', $type ? ['type' => $type] : []) ?></div>
<nav class="pills"><a href="<?= url('admin/inquiries') ?>"<?= !$type ? ' aria-current="true"' : '' ?>>All</a><?php foreach ($types as $t): ?><a href="<?= url('admin/inquiries?type=' . $t) ?>"<?= $type === $t ? ' aria-current="true"' : '' ?>><?= h(['dj' => 'DJ bookings', 'performance' => 'Comedy bookings'][$t] ?? ucfirst($t)) ?></a><?php endforeach; ?></nav>
<?php foreach ($list as $i): $d = json_decode((string)$i['details'], true) ?: []; ?>
<div class="panel"><div class="adm-head"><h2><?= h($i['name']) ?><?= $i['organization'] ? ' · ' . h($i['organization']) : '' ?></h2><span><span class="tag"><?= h($i['type'] === 'dj' ? 'DJ' : $i['type']) ?></span> <?= status_badge($i['status']) ?> <span class="small muted"><?= fmt_date($i['created_at'], 'j M Y H:i') ?></span></span></div>
  <p class="small"><?= $i['phone'] ? '<a href="tel:' . h($i['phone']) . '">' . h($i['phone']) . '</a> · ' : '' ?><?= $i['email'] ? '<a href="mailto:' . h($i['email']) . '">' . h($i['email']) . '</a>' : '' ?></p>
  <?php if ($i['subject']): ?><p><strong><?= h($i['subject']) ?></strong></p><?php endif; ?>
  <?php foreach ($d as $k => $val) if ($val !== ''): ?><span class="small"><b><?= h(ucfirst(str_replace('_', ' ', $k))) ?>:</b> <?= h($val) ?> &nbsp;</span><?php endif; ?>
  <?php if ($i['message']): ?><p><?= nl2br(h($i['message'])) ?></p><?php endif; ?>
  <form method="post" action="<?= url('admin/inquiries/' . $i['id']) ?>" class="filters"><?= csrf_field() ?><input type="hidden" name="back_type" value="<?= h($type) ?>">
    <label>Status <select name="status"><?php foreach (['new', 'in_progress', 'won', 'closed'] as $s): ?><option value="<?= $s ?>"<?= $i['status'] === $s ? ' selected' : '' ?>><?= str_replace('_', ' ', $s) ?></option><?php endforeach; ?></select></label>
    <label>Follow-up action <input name="follow_up" value="<?= h($i['follow_up']) ?>" maxlength="255"></label>
    <?php if ($i['type'] === 'advertising'): ?><label>Agreed package <input name="agreed_package" value="<?= h($i['agreed_package']) ?>" maxlength="255"></label><?php endif; ?>
    <button class="btn btn-primary btn-sm">Update</button></form></div>
<?php endforeach; if (!$list): ?><p class="muted">No inquiries yet.</p><?php endif; ?>
