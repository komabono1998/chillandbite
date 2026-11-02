<?php $act = fn(int $id) => url('admin/accreditation/' . $id); $total = array_sum($counts); ?>
<div class="adm-head"><div><h1>Press accreditation</h1><p class="adm-sub">Bloggers, journalists and media houses who applied to publish on The Gulu Chronicles. Approving creates a contributor login.</p></div>
  <a class="btn btn-ghost" target="_blank" href="<?= url('accreditation') ?>">View public form ↗</a></div>

<div class="panel">
  <nav class="pills" aria-label="Filter">
    <a href="<?= url('admin/accreditation') ?>"<?= !$status ? ' aria-current="true"' : '' ?>>All (<?= $total ?>)</a>
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Declined'] as $k => $l): ?>
      <a href="<?= url('admin/accreditation?status=' . $k) ?>"<?= $status === $k ? ' aria-current="true"' : '' ?>><?= $l ?> (<?= (int)($counts[$k] ?? 0) ?>)</a>
    <?php endforeach; ?>
  </nav>
  <?php if (!$list): ?><div class="empty">No applications here yet.</div><?php endif; ?>
  <?php foreach ($list as $a): ?>
  <div class="panel" style="margin:14px 0 0">
    <div class="panel-head"><h2><?= h($a['full_name']) ?><?= $a['organization'] ? ' · ' . h($a['organization']) : '' ?></h2>
      <span><?= status_badge($a['status']) ?> <span class="role-pill"><?= h(ACC_TYPES[$a['applicant_type']] ?? $a['applicant_type']) ?></span></span></div>
    <p class="small muted">Applied <?= fmt_date($a['created_at'], 'j M Y H:i') ?> · <a href="mailto:<?= h($a['email']) ?>"><?= h($a['email']) ?></a> · <?= h($a['phone']) ?><?= $a['location'] ? ' · ' . h($a['location']) : '' ?></p>
    <?php if ($a['coverage']): ?><p><b>Covers:</b> <?= h($a['coverage']) ?></p><?php endif; ?>
    <details><summary class="btn btn-ghost btn-sm" style="display:inline-block;cursor:pointer">Read application</summary>
      <div style="margin-top:10px">
        <?php if ($a['website']): ?><p><b>Website:</b> <a href="<?= h($a['website']) ?>" target="_blank" rel="noopener nofollow"><?= h($a['website']) ?></a></p><?php endif; ?>
        <?php if ($a['socials']): ?><p><b>Social:</b> <?= h($a['socials']) ?></p><?php endif; ?>
        <p><b>Published work</b></p><div class="prose"><?= paragraphs($a['samples']) ?></div>
        <p><b>About</b></p><div class="prose"><?= paragraphs($a['about']) ?></div>
        <?php if ($a['document']): ?><p><b>Document:</b> <a href="<?= h(upload_url($a['document'])) ?>" target="_blank" rel="noopener">View upload ↗</a></p><?php endif; ?>
        <?php if ($a['admin_note']): ?><p class="small muted"><b>Note:</b> <?= h($a['admin_note']) ?></p><?php endif; ?>
      </div></details>
    <?php if ($a['status'] === 'approved'): ?>
      <p class="small muted">Contributor login: <?= $a['user_active'] ? 'active' : 'switched off' ?><?= $a['last_login'] ? ' · last sign-in ' . fmt_date($a['last_login'], 'j M Y H:i') : ' · has not signed in yet' ?></p>
    <?php endif; ?>
    <form method="post" action="<?= $act((int)$a['id']) ?>" class="filters" style="margin-top:10px"><?= csrf_field() ?>
      <input type="hidden" name="back_status" value="<?= h((string)$status) ?>">
      <label>Note (optional) <input name="admin_note" maxlength="255" value="<?= h((string)$a['admin_note']) ?>"></label>
      <?php if ($a['status'] !== 'approved'): ?><button class="btn btn-primary btn-sm" name="action" value="approve"><?= $a['status'] === 'rejected' ? 'Approve instead' : 'Approve &amp; create login' ?></button><?php endif; ?>
      <?php if ($a['status'] !== 'rejected'): ?><button class="btn btn-ghost btn-sm" name="action" value="reject" onclick="return confirm('<?= $a['status'] === 'approved' ? 'Revoke accreditation and switch off this login?' : 'Decline this application?' ?>')"><?= $a['status'] === 'approved' ? 'Revoke access' : 'Decline' ?></button><?php endif; ?>
    </form>
  </div>
  <?php endforeach; ?>
</div>
