<div class="adm-head"><div><h1><?= $mine ? 'My stories' : 'News &amp; articles' ?></h1>
  <?php if ($mine): ?><p class="adm-sub">Write a story, save it as a draft, then send it to the editor. Approved stories are published with your byline.</p><?php endif; ?></div>
  <a class="btn btn-primary" href="<?= url('admin/news/new') ?>">+ New <?= $mine ? 'story' : 'article' ?></a></div>
<div class="panel"><div class="tbl-wrap"><table class="tbl"><tr><th>Headline</th><th>Label</th><th>Category</th><th>Status</th><th>Publish date</th><th></th></tr>
<?php if (!$list): ?><tr><td colspan="6" class="muted">Nothing here yet.</td></tr><?php endif; ?>
<?php foreach ($list as $a): $locked = $mine && $a['status'] === 'published'; ?><tr>
<td><?php if ($locked): ?><a target="_blank" href="<?= url('news/' . h($a['slug'])) ?>"><strong><?= h($a['title']) ?></strong></a><?php else: ?><a href="<?= url('admin/news/' . $a['id']) ?>"><strong><?= h($a['title']) ?></strong></a><?php endif; ?>
  <br><span class="small muted"><?= h($a['author']) ?><?= !empty($a['author_org']) ? ' · ' . h($a['author_org']) : '' ?><?= !empty($a['source_name']) ? ' · via ' . h($a['source_name']) : '' ?></span></td>
<td><span class="tag tag-<?= h($a['label']) ?>"><?= h(NEWS_LABELS[$a['label']]) ?></span></td><td><?= h($a['category']) ?></td>
<td><?= $a['status'] === 'pending' ? '<span class="badge badge-warn">awaiting review</span>' : status_badge($a['status']) ?></td><td><?= fmt_date($a['published_at'], 'j M Y H:i') ?></td>
<td><?php if (!$locked): ?><form class="inline" method="post" action="<?= url('admin/news/' . $a['id'] . '/delete') ?>" onsubmit="return confirm('Delete this article permanently?')"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div></div>
