<div class="adm-head">
  <div><h1>Website pages</h1><p class="adm-sub">Pick a page to edit its text, buttons and photos, section by section. Changes go live as soon as you save.</p></div>
</div>
<div class="page-cards">
<?php foreach ($pages as $id => $pg):
    $fields = 0; foreach ($pg['sections'] as $s) $fields += count($s['fields']);
    $edited = $counts[$id] ?? 0; ?>
  <article class="page-card">
    <div class="pc-top"><span class="pc-ic" aria-hidden="true"><?= $pg['icon'] ?></span><div><h2><?= h($pg['label']) ?></h2><span class="small muted">/<?= h($pg['url']) ?></span></div></div>
    <p><?= h($pg['desc']) ?></p>
    <div class="pc-meta"><span><?= count($pg['sections']) ?> section<?= count($pg['sections']) === 1 ? '' : 's' ?></span><span><?= $fields ?> fields</span><?php if ($edited): ?><span class="edited"><?= $edited ?> edited</span><?php endif; ?></div>
    <div class="pc-meta"><?php foreach ($pg['sections'] as $sid => $sec): ?><span><a href="<?= url('admin/pages/' . $id . '#sec-' . $sid) ?>" style="color:inherit;text-decoration:none"><?= h($sec['label']) ?></a></span><?php endforeach; ?></div>
    <?php $rel = array_filter($pg['related'], fn($r) => can($r[2])); if ($rel): ?><div class="pc-links"><?php foreach ($rel as [$l, $path, $m]): ?><a href="<?= url($path) ?>"><?= h($l) ?> →</a><?php endforeach; ?></div><?php endif; ?>
    <div class="btn-row"><a class="btn btn-primary btn-sm" href="<?= url('admin/pages/' . $id) ?>">Edit content</a><a class="btn btn-ghost btn-sm" href="<?= url($pg['url']) ?>" target="_blank" rel="noopener">View page ↗</a></div>
  </article>
<?php endforeach; ?>
<?php if (can('settings')): ?>
  <article class="page-card">
    <div class="pc-top"><span class="pc-ic" aria-hidden="true">⚙️</span><div><h2>Site-wide details</h2><span class="small muted">header, footer &amp; contact</span></div></div>
    <p>Announcement bar, venue, phone, WhatsApp, email, social links and the WhatsApp group link shown across every page.</p>
    <div class="btn-row"><a class="btn btn-primary btn-sm" href="<?= url('admin/settings') ?>">Open settings</a></div>
  </article>
<?php endif; ?>
</div>
<?php if (!$pages): ?><div class="empty">Your role does not have any pages assigned. Ask a System Administrator.</div><?php endif; ?>
