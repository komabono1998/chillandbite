<h1>Comedy videos</h1>
<form class="panel" method="post" action="<?= url('admin/comedy') ?>"><?= csrf_field() ?><h2>Add a YouTube video</h2>
  <div class="form-grid"><?php field('title', 'Title', ['required' => true, 'value' => '']); field('url', 'YouTube link', ['required' => true, 'value' => '']);
  field('kind', 'Type', ['type' => 'select', 'options' => ['clip' => 'Short clip', 'full_show' => 'Full show', 'behind_scenes' => 'Behind the scenes', 'interview' => 'Interview', 'highlight' => 'Live highlight']]); field('description', 'Short note', ['value' => '']); ?></div>
  <label class="chk"><input type="checkbox" name="featured" value="1"> Feature on homepage</label><button class="btn btn-primary">Add video</button></form>
<div class="panel"><div class="tbl-wrap"><table class="tbl"><tr><th>Title</th><th>Type</th><th>Featured</th><th></th></tr>
<?php foreach ($videos as $v): ?><tr><td><a href="<?= h($v['url']) ?>" target="_blank" rel="noopener"><?= h($v['title']) ?></a></td><td><?= h($v['kind']) ?></td><td><?= $v['featured'] ? 'Yes' : '' ?></td>
<td><form class="inline" method="post" action="<?= url('admin/comedy') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="btn btn-ghost btn-sm" onclick="return confirm('Remove video?')">Remove</button></form></td></tr><?php endforeach; ?></table></div></div>
