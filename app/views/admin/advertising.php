<h1>Sponsors &amp; advertising</h1>
<form class="panel" method="post" action="<?= url('admin/advertising') ?>" enctype="multipart/form-data"><?= csrf_field() ?><h2>Add sponsor</h2>
  <div class="form-grid"><?php field('name', 'Name', ['required' => true, 'value' => '']); field('website', 'Website (https://…)', ['value' => '']); field('tier', 'Tier', ['type' => 'select', 'options' => ['title' => 'Title', 'gold' => 'Gold', 'silver' => 'Silver', 'partner' => 'Partner']]); ?></div>
  <div class="field"><label for="f_logo">Logo</label><input id="f_logo" type="file" name="logo" accept="image/*"></div><button class="btn btn-primary">Add sponsor</button></form>
<div class="panel"><div class="tbl-wrap"><table class="tbl"><tr><th>Sponsor</th><th>Tier</th><th>Visible</th><th></th></tr>
<?php foreach ($sponsors as $s): ?><tr><td><?= h($s['name']) ?></td><td><?= h($s['tier']) ?></td><td><?= $s['active'] ? 'Yes' : 'Hidden' ?></td><td class="actions">
  <?php foreach (['toggle' => 'Show/hide', 'delete' => 'Delete'] as $a => $l): ?><form class="inline" method="post" action="<?= url('admin/advertising') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-ghost btn-sm"><?= $l ?></button></form><?php endforeach; ?></td></tr><?php endforeach; ?></table></div></div>
<div class="panel"><h2>Advertising inquiries</h2><p><a href="<?= url('admin/inquiries?type=advertising') ?>">Manage all advertising inquiries &rarr;</a> (<?= count($inq) ?> on record)</p></div>
