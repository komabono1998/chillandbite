<h1>Gallery <small class="muted" style="font-size:.9rem;font-weight:500">(<?= (int)$total ?> photos)</small></h1>
<div class="panel"><h2>Albums</h2>
  <p class="small muted" style="margin:0 0 10px">Group photos by event or edition. Visitors can filter the public Gallery page by album.</p>
  <p style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px"><a class="btn btn-sm <?= $filter === 0 ? 'btn-primary' : 'btn-ghost' ?>" href="<?= url('admin/gallery') ?>">All</a><?php foreach ($albums as $a): ?><a class="btn btn-sm <?= $filter === (int)$a['id'] ? 'btn-primary' : 'btn-ghost' ?>" href="<?= url('admin/gallery?album=' . (int)$a['id']) ?>"><?= h($a['name']) ?> (<?= (int)$a['n'] ?>)</a><?php endforeach; ?><a class="btn btn-sm <?= $filter === -1 ? 'btn-primary' : 'btn-ghost' ?>" href="<?= url('admin/gallery?album=-1') ?>">No album</a></p>
  <form class="inline" method="post" action="<?= url('admin/gallery') ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end"><?= csrf_field() ?><input type="hidden" name="action" value="album_add"><div class="field" style="margin:0"><label for="al_new">New album</label><input id="al_new" name="name" maxlength="120" placeholder="e.g. December 2026 edition" required></div><button class="btn btn-primary btn-sm">Create album</button></form>
  <?php if ($filter > 0): foreach ($albums as $a) if ((int)$a['id'] === $filter): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:end"><form class="inline" method="post" action="<?= url('admin/gallery') ?>" style="display:flex;gap:8px;align-items:end"><?= csrf_field() ?><input type="hidden" name="action" value="album_rename"><input type="hidden" name="id" value="<?= $filter ?>"><input type="hidden" name="filter" value="<?= $filter ?>"><div class="field" style="margin:0"><label>Rename this album</label><input name="name" value="<?= h($a['name']) ?>" maxlength="120"></div><button class="btn btn-ghost btn-sm">Rename</button></form>
    <form class="inline" method="post" action="<?= url('admin/gallery') ?>" onsubmit="return confirm('Delete this album? Its photos are kept without an album.')"><?= csrf_field() ?><input type="hidden" name="action" value="album_delete"><input type="hidden" name="id" value="<?= $filter ?>"><button class="btn btn-ghost btn-sm">Delete album</button></form></div>
  <?php endif; endif; ?>
</div>
<form class="panel" method="post" action="<?= url('admin/gallery') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="settings"><h2>Public downloads</h2>
  <label class="chk"><input type="checkbox" name="gallery_downloads" value="1"<?= $downloads ? ' checked' : '' ?>> Let visitors download photos (a download button on every photo, plus &ldquo;Download all&rdquo; per album)</label>
  <button class="btn btn-primary btn-sm" style="margin-top:8px">Save</button></form>
<form class="panel" method="post" action="<?= url('admin/gallery') ?>" enctype="multipart/form-data"><?= csrf_field() ?><h2>Upload photos</h2>
  <div class="form-grid"><?php field('album_id', 'Album', ['type' => 'select', 'options' => ['' => 'No album'] + array_column($albums, 'name', 'id'), 'value' => $filter > 0 ? (string)$filter : '']); field('caption', 'Caption (optional)', ['value' => '']); ?></div>
  <div class="field"><label for="f_photos">Photos (JPG, PNG, WebP, GIF &middot; max 5MB each)</label><input id="f_photos" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" required></div>
  <button class="btn btn-primary">Upload</button></form>

<form method="post" action="<?= url('admin/gallery') ?>" id="bulkForm"><?= csrf_field() ?><input type="hidden" name="action" value="bulk"><input type="hidden" name="filter" value="<?= (int)$filter ?>">
<div class="panel" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center"><b class="small">Selected photos:</b>
  <select name="bulk"><option value="hide">Hide from the website</option><option value="show">Show on the website</option><?php foreach ($albums as $a): ?><option value="move:<?= (int)$a['id'] ?>">Move to: <?= h($a['name']) ?></option><?php endforeach; ?><option value="move:0">Move to: no album</option><option value="delete">Delete</option></select>
  <button class="btn btn-ghost btn-sm" onclick="if(this.form.bulk.value==='delete')return confirm('Delete the selected photos?')">Apply</button>
  <label class="chk small" style="margin-left:auto"><input type="checkbox" onclick="document.querySelectorAll('.pick').forEach(c=>c.checked=this.checked)"> Select all</label></div>
<p class="small muted">&#9733; Featured photos show first on the homepage photo wall. &ldquo;Slideshow&rdquo; photos (up to 5, newest first) play as the homepage banner background. With none chosen, the standard banner photos are used.</p>
<div class="gallery-grid"><?php foreach ($images as $i): ?>
  <figure class="panel" style="padding:8px<?= $i['hidden'] ? ';opacity:.55' : '' ?>"><label style="display:block;position:relative"><input type="checkbox" class="pick" name="ids[]" value="<?= (int)$i['id'] ?>" style="position:absolute;left:6px;top:6px;z-index:2;width:18px;height:18px"><img src="<?= h(upload_url($i['file'])) ?>" alt="<?= h($i['caption']) ?>" loading="lazy"></label>
    <figcaption><?= h($i['album'] ?: 'No album') ?> <?= $i['featured'] ? '&#9733;' : '' ?> <?= $i['hero'] ? '&#127916;' : '' ?> <?= $i['hidden'] ? '(hidden)' : '' ?><br><span class="muted"><?= h($i['caption']) ?></span></figcaption>
    <div class="actions"><button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('ed<?= (int)$i['id'] ?>').hidden=false;this.hidden=true">Edit</button>
      <?php foreach (['feature' => $i['featured'] ? 'Unfeature' : 'Feature', 'hero' => $i['hero'] ? 'Remove from slideshow' : 'Slideshow', 'hidden' => $i['hidden'] ? 'Show' : 'Hide', 'delete' => 'Delete'] as $a => $l): ?>
        <button class="btn btn-ghost btn-sm" formaction="<?= url('admin/gallery') ?>" name="one" value="<?= $a ?>:<?= (int)$i['id'] ?>" formnovalidate<?= $a === 'delete' ? ' onclick="return confirm(\'Delete photo?\')"' : '' ?>><?= $l ?></button><?php endforeach; ?></div>
    <div id="ed<?= (int)$i['id'] ?>" hidden style="margin-top:6px"><input form="f_e<?= (int)$i['id'] ?>" name="caption" value="<?= h($i['caption']) ?>" placeholder="Caption" maxlength="190" style="width:100%;margin-bottom:4px">
      <select form="f_e<?= (int)$i['id'] ?>" name="album_id" style="width:100%;margin-bottom:4px"><option value="">No album</option><?php foreach ($albums as $a): ?><option value="<?= (int)$a['id'] ?>"<?= (int)$i['album_id'] === (int)$a['id'] ? ' selected' : '' ?>><?= h($a['name']) ?></option><?php endforeach; ?></select>
      <button form="f_e<?= (int)$i['id'] ?>" class="btn btn-primary btn-sm">Save</button></div>
  </figure><?php endforeach; ?></div>
</form>
<?php foreach ($images as $i): ?><form id="f_e<?= (int)$i['id'] ?>" method="post" action="<?= url('admin/gallery') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><input type="hidden" name="filter" value="<?= (int)$filter ?>"></form><?php endforeach; ?>
