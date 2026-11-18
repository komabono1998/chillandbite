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
<form class="panel" method="post" action="<?= url('admin/gallery') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="settings"><h2>Settings</h2>
  <label class="chk"><input type="checkbox" name="gallery_downloads" value="1"<?= $downloads ? ' checked' : '' ?>> Let visitors download photos (a download button on every photo, plus &ldquo;Download all&rdquo; per album)</label>
  <div class="field" style="max-width:260px;margin-top:10px"><label for="mx">Largest picture allowed (MB)</label><input id="mx" type="number" name="upload_max_mb" min="1" max="100" value="<?= (int)$maxmb ?>"></div>
  <p class="small muted" style="margin:0 0 8px">Applies to every picture upload in the admin. Your server currently accepts files up to <b><?= h($ini['upload']) ?></b> each (<b><?= h($ini['post']) ?></b> per request). <?= $ini['gd'] ? 'Fast small copies of each photo are made automatically, so the website grid stays quick.' : 'Tip: ask your host to enable the PHP GD extension so small copies of big photos are made automatically.' ?></p>
  <button class="btn btn-primary btn-sm">Save</button></form>
<form class="panel" id="upForm" method="post" action="<?= url('admin/gallery') ?>" enctype="multipart/form-data"><?= csrf_field() ?><h2>Upload photos</h2>
  <div class="form-grid"><?php field('album_id', 'Album', ['type' => 'select', 'options' => ['' => 'No album'] + array_column($albums, 'name', 'id'), 'value' => $filter > 0 ? (string)$filter : '']); field('caption', 'Caption (optional, applied to every photo in this upload)', ['value' => '']); ?></div>
  <div class="field"><label for="f_photos">Photos (JPG, PNG, WebP, GIF &middot; up to <?= (int)$maxmb ?>MB each &middot; choose as many as you like, 50 or more)</label><input id="f_photos" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" required></div>
  <div id="upBar" hidden style="margin:8px 0"><div style="height:10px;background:#e6e0d4;border-radius:99px;overflow:hidden"><div id="upFill" style="height:100%;width:0;background:var(--orange,#e8731a);transition:width .2s"></div></div><p id="upMsg" class="small" style="margin:6px 0 0"></p></div>
  <button class="btn btn-primary" id="upBtn">Upload</button>
  <p class="small muted" style="margin:8px 0 0">Photos are sent in small batches, so big uploads work on any host. They line up automatically in the website grid.</p></form>
<script>
(function () {
  var form = document.getElementById('upForm'), input = document.getElementById('f_photos');
  if (!window.fetch || !window.FormData) return;
  form.addEventListener('submit', function (e) {
    var files = Array.prototype.slice.call(input.files || []); if (!files.length) return;
    e.preventDefault();
    var maxBytes = <?= (int)$maxmb ?> * 1048576, ok = 0, errs = [], i = 0, done = 0;
    var bar = document.getElementById('upBar'), fill = document.getElementById('upFill'), msg = document.getElementById('upMsg'), btn = document.getElementById('upBtn');
    var queue = files.filter(function (f) { if (f.size > maxBytes) { errs.push(f.name + ': larger than <?= (int)$maxmb ?>MB'); return false; } return true; });
    var total = files.length; done = files.length - queue.length;
    bar.hidden = false; btn.disabled = true;
    function progress() { fill.style.width = Math.round(done / total * 100) + '%'; msg.textContent = 'Uploaded ' + ok + ' of ' + total + ' photos' + (errs.length ? ' · ' + errs.length + ' skipped' : '') + '...'; }
    function next() {
      if (i >= queue.length) return finish();
      var fd = new FormData(), size = 0, n = 0;
      ['_csrf', 'album_id', 'caption'].forEach(function (k) { var el = form.elements[k]; if (el) fd.append(k, el.value); });
      fd.append('action', 'upload'); fd.append('ajax', '1');
      while (i < queue.length && n < 5 && (n === 0 || size + queue[i].size < 30 * 1048576)) { fd.append('photos[]', queue[i]); size += queue[i].size; i++; n++; }
      fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        ok += j.ok || 0; (j.errors || []).forEach(function (m) { errs.push(m); }); done += n; progress(); next();
      }).catch(function () { errs.push('A batch of ' + n + ' photo(s) failed to upload'); done += n; progress(); next(); });
    }
    function finish() {
      fill.style.width = '100%';
      msg.innerHTML = '<b>' + ok + ' photo(s) uploaded.</b>' + (errs.length ? '<br>' + errs.map(function (m) { return m.replace(/</g, '&lt;'); }).join('<br>') : '') + ' Reloading...';
      setTimeout(function () { location.reload(); }, errs.length ? 4000 : 900);
    }
    progress(); next();
  });
})();
</script>
<form method="post" action="<?= url('admin/gallery') ?>" id="bulkForm"><?= csrf_field() ?><input type="hidden" name="action" value="bulk"><input type="hidden" name="filter" value="<?= (int)$filter ?>">
<div class="panel" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center"><b class="small">Selected photos:</b>
  <select name="bulk"><option value="hide">Hide from the website</option><option value="show">Show on the website</option><?php foreach ($albums as $a): ?><option value="move:<?= (int)$a['id'] ?>">Move to: <?= h($a['name']) ?></option><?php endforeach; ?><option value="move:0">Move to: no album</option><option value="delete">Delete</option></select>
  <button class="btn btn-ghost btn-sm" onclick="if(this.form.bulk.value==='delete')return confirm('Delete the selected photos?')">Apply</button>
  <label class="chk small" style="margin-left:auto"><input type="checkbox" onclick="document.querySelectorAll('.pick').forEach(c=>c.checked=this.checked)"> Select all</label></div>
<?php $missingN = count(array_filter($images, fn($i) => !empty($i['missing']))); if ($missingN): ?><div class="panel" style="border-left:4px solid #c0392b"><b><?= $missingN ?> photo(s) are missing their file on the server</b> and are hidden from the website. Restore your <code>uploads</code> folder from a hosting backup to bring them back, or remove the empty records.
  <button class="btn btn-ghost btn-sm" formaction="<?= url('admin/gallery') ?>" name="action" value="purge_missing" onclick="return confirm('Remove all photo records whose file is missing?')">Remove records with missing files</button></div><?php endif; ?>
<p class="small muted">&#9733; Featured photos show first on the homepage photo wall. &ldquo;Slideshow&rdquo; photos (up to 5, newest first) play as the homepage banner background. With none chosen, the standard banner photos are used.</p>
<div class="gallery-grid"><?php foreach ($images as $i): ?>
  <figure class="panel" style="padding:8px<?= $i['hidden'] ? ';opacity:.55' : '' ?>"><label style="display:block;position:relative"><input type="checkbox" class="pick" name="ids[]" value="<?= (int)$i['id'] ?>" style="position:absolute;left:6px;top:6px;z-index:2;width:18px;height:18px"><img src="<?= h(upload_url(gallery_thumb($i['file']))) ?>" alt="<?= h($i['caption']) ?>" loading="lazy"></label>
    <figcaption><?= h($i['album'] ?: 'No album') ?> <?= $i['featured'] ? '&#9733;' : '' ?> <?= $i['hero'] ? '&#127916;' : '' ?> <?= $i['hidden'] ? '(hidden)' : '' ?><?= !empty($i['missing']) ? ' <b style="color:#c0392b">(file missing)</b>' : '' ?><br><span class="muted"><?= h($i['caption']) ?></span></figcaption>
    <div class="actions"><button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('ed<?= (int)$i['id'] ?>').hidden=false;this.hidden=true">Edit</button>
      <?php foreach (['feature' => $i['featured'] ? 'Unfeature' : 'Feature', 'hero' => $i['hero'] ? 'Remove from slideshow' : 'Slideshow', 'hidden' => $i['hidden'] ? 'Show' : 'Hide', 'delete' => 'Delete'] as $a => $l): ?>
        <button class="btn btn-ghost btn-sm" formaction="<?= url('admin/gallery') ?>" name="one" value="<?= $a ?>:<?= (int)$i['id'] ?>" formnovalidate<?= $a === 'delete' ? ' onclick="return confirm(\'Delete photo?\')"' : '' ?>><?= $l ?></button><?php endforeach; ?></div>
    <div id="ed<?= (int)$i['id'] ?>" hidden style="margin-top:6px"><input form="f_e<?= (int)$i['id'] ?>" name="caption" value="<?= h($i['caption']) ?>" placeholder="Caption" maxlength="190" style="width:100%;margin-bottom:4px">
      <select form="f_e<?= (int)$i['id'] ?>" name="album_id" style="width:100%;margin-bottom:4px"><option value="">No album</option><?php foreach ($albums as $a): ?><option value="<?= (int)$a['id'] ?>"<?= (int)$i['album_id'] === (int)$a['id'] ? ' selected' : '' ?>><?= h($a['name']) ?></option><?php endforeach; ?></select>
      <button form="f_e<?= (int)$i['id'] ?>" class="btn btn-primary btn-sm">Save</button></div>
  </figure><?php endforeach; ?></div>
</form>
<?php foreach ($images as $i): ?><form id="f_e<?= (int)$i['id'] ?>" method="post" action="<?= url('admin/gallery') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><input type="hidden" name="filter" value="<?= (int)$filter ?>"></form><?php endforeach; ?>
