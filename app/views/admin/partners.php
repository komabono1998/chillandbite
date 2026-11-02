<div class="adm-head"><h1>Partner applications</h1></div>
<nav class="pills"><a href="<?= url('admin/partners') ?>"<?= !$status ? ' aria-current="true"' : '' ?>>All</a>
  <?php foreach (['pending', 'approved', 'rejected'] as $s): ?><a href="<?= url('admin/partners?status=' . $s) ?>"<?= $status === $s ? ' aria-current="true"' : '' ?>><?= ucfirst($s) ?></a><?php endforeach; ?></nav>
<?php foreach ($list as $p): $act = url('admin/partners/' . $p['id']); ?>
<div class="panel partner-app">
  <div class="pa-logo"><a href="<?= h(upload_url($p['logo'])) ?>" target="_blank"><img src="<?= h(upload_url($p['logo'])) ?>" alt="Logo of <?= h($p['company']) ?>"></a></div>
  <div class="pa-body">
    <div class="adm-head"><h2><?= h($p['company']) ?></h2><span><?= status_badge($p['status']) ?> <span class="small muted"><?= fmt_date($p['created_at'], 'j M Y H:i') ?></span></span></div>
    <p class="small"><strong><?= h($p['contact_name']) ?></strong> · <a href="tel:<?= h($p['phone']) ?>"><?= h($p['phone']) ?></a> · <a href="mailto:<?= h($p['email']) ?>"><?= h($p['email']) ?></a>
      <?php if ($p['website']): ?> · <a href="<?= h($p['website']) ?>" target="_blank" rel="noopener nofollow"><?= h($p['website']) ?></a><?php endif; ?></p>
    <p class="small"><b>Type:</b> <?= h($p['partnership_type']) ?><?= $p['industry'] ? ' · <b>Industry:</b> ' . h($p['industry']) : '' ?></p>
    <p><b>About:</b> <?= nl2br(h($p['about'])) ?></p>
    <?php if ($p['proposal']): ?><p><b>Proposal:</b> <?= nl2br(h($p['proposal'])) ?></p><?php endif; ?>
    <?php if ($p['status'] === 'approved'): ?><p class="small"><?= $p['sponsor_active'] ? '<span class="badge badge-ok">Logo live on website</span> (' . h($p['sponsor_tier']) . ')' : '<span class="badge badge-muted">Logo hidden</span>' ?></p><?php endif; ?>
    <form method="post" action="<?= $act ?>" class="filters"><?= csrf_field() ?><input type="hidden" name="back_status" value="<?= h($status) ?>">
      <label>Tier <select name="tier"><?php foreach (['partner' => 'Partner', 'silver' => 'Silver', 'gold' => 'Gold', 'title' => 'Title'] as $k => $l): ?><option value="<?= $k ?>"<?= ($p['sponsor_tier'] ?? 'partner') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label>Note <input name="admin_note" maxlength="255" value="<?= h($p['admin_note']) ?>"></label>
      <?php if ($p['status'] !== 'approved'): ?><button class="btn btn-primary btn-sm" name="action" value="approve">Approve &amp; publish logo</button><?php else: ?><button class="btn btn-ghost btn-sm" name="action" value="approve">Update tier</button><?php endif; ?>
      <?php if ($p['status'] !== 'rejected'): ?><button class="btn btn-ghost btn-sm" name="action" value="reject" onclick="return confirm('<?= $p['status'] === 'approved' ? 'Reject and remove this logo from the website?' : 'Reject this application?' ?>')"><?= $p['status'] === 'approved' ? 'Revoke & remove logo' : 'Reject' ?></button><?php endif; ?>
    </form>
  </div>
</div>
<?php endforeach; if (!$list): ?><p class="muted">No applications here yet.</p><?php endif; ?>
