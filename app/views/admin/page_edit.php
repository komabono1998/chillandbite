<div class="adm-head">
  <div><h1><?= $pg['icon'] ?> <?= h($pg['label']) ?></h1><p class="adm-sub"><?= h($pg['desc']) ?></p></div>
  <div class="btn-row"><a class="btn btn-ghost btn-sm" href="<?= url('admin/pages') ?>">← All pages</a><a class="btn btn-outline btn-sm" href="<?= url($pg['url']) ?>" target="_blank" rel="noopener">View live page ↗</a></div>
</div>
<div class="pe-layout">
  <nav class="pe-toc" aria-label="Sections">
    <h3>Sections</h3>
    <?php foreach ($pg['sections'] as $sid => $sec): ?><a href="#sec-<?= h($sid) ?>"><?= h($sec['label']) ?></a><?php endforeach; ?>
    <?php $rel = array_filter($pg['related'], fn($r) => can($r[2])); if ($rel): ?>
      <h3 style="margin-top:14px">Also manage</h3>
      <?php foreach ($rel as [$l, $path, $m]): ?><a href="<?= url($path) ?>"><?= h($l) ?> →</a><?php endforeach; ?>
    <?php endif; ?>
  </nav>
  <form method="post" action="<?= url('admin/pages/' . h($id)) ?>" enctype="multipart/form-data" id="page-form">
    <?= csrf_field() ?>
    <?php $first = true; foreach ($pg['sections'] as $sid => $sec):
        $editedHere = 0; foreach ($sec['fields'] as $fd) if (array_key_exists("$id.$sid.{$fd['key']}", $saved)) $editedHere++; ?>
      <details class="sec" id="sec-<?= h($sid) ?>"<?= $first ? ' open' : '' ?>>
        <summary><span><?= h($sec['label']) ?><span class="sec-count"><?= count($sec['fields']) ?> field<?= count($sec['fields']) === 1 ? '' : 's' ?><?= $editedHere ? ' · ' . $editedHere . ' edited' : '' ?></span></span></summary>
        <div class="sec-body">
          <?php foreach ($sec['fields'] as $fd):
              $key = "$id.$sid.{$fd['key']}"; $name = 'f_' . str_replace('.', '_', $key);
              $isEdited = array_key_exists($key, $saved); $cur = $saved[$key] ?? $fd['default']; ?>
            <div class="fld">
              <label class="l" for="<?= h($name) ?>"><span><?= h($fd['label']) ?></span><span class="state<?= $isEdited ? ' on' : '' ?>"><?= $isEdited ? 'Edited' : 'Default' ?></span></label>
              <?php if ($fd['type'] === 'text'): ?>
                <input type="text" id="<?= h($name) ?>" name="<?= h($name) ?>" value="<?= h($cur) ?>" maxlength="500">
              <?php elseif ($fd['type'] === 'image'): $src = $cur !== '' ? upload_url($cur) : ''; ?>
                <?php if ($src): ?><img class="img-prev" src="<?= h($src) ?>" alt="Current image"><?php else: ?><p class="small muted">Using the built-in photo.</p><?php endif; ?>
                <input type="file" id="<?= h($name) ?>" name="<?= h($name) ?>" accept="image/jpeg,image/png,image/webp,image/gif">
              <?php else: ?>
                <textarea id="<?= h($name) ?>" name="<?= h($name) ?>" rows="<?= $fd['type'] === 'lines' ? 6 : 4 ?>"><?= h($cur) ?></textarea>
              <?php endif; ?>
              <?php if (!empty($fd['help'])): ?><small><?= h($fd['help']) ?></small><?php endif; ?>
              <?php if ($isEdited): ?><label class="reset"><input type="checkbox" name="reset[<?= h($key) ?>]" value="1"> Reset this field to the original text</label><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </details>
    <?php $first = false; endforeach; ?>
    <div class="save-bar"><span class="small muted">Leave a field empty, or tick “Reset”, to go back to the original text.</span><button class="btn btn-primary btn-lg">Save changes</button></div>
  </form>
</div>
<script>
// Open the section named in the URL (#sec-...) so links from the page index land on it
(function () { var h = location.hash; if (h && document.querySelector(h)) { var d = document.querySelector(h); if (d.tagName === 'DETAILS') d.open = true; } })();
// Warn before leaving with unsaved edits
(function () { var f = document.getElementById('page-form'), dirty = false;
  f.addEventListener('input', function () { dirty = true; }); f.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } }); })();
</script>
