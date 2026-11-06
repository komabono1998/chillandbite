<?php
$act = url('admin/pictures');
$byPage = [];
foreach ($slots as $s) $byPage[$s['page']][] = $s;
$pages = site_pages();
$replaceForm = function (array $hidden, string $btn = 'Replace picture') use ($act) { ?>
  <form class="pic-form" method="post" action="<?= $act ?>" enctype="multipart/form-data"><?= csrf_field() ?>
    <?php foreach ($hidden as $k => $v): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h((string)$v) ?>"><?php endforeach; ?>
    <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required aria-label="Choose a new picture">
    <button class="btn btn-primary btn-sm"><?= h($btn) ?></button>
  </form>
  <?php $pick = $hidden; $pick['action'] = str_replace('_replace', '_pick', (string)$hidden['action']); $lab = $hidden['key'] ?? (isset($hidden['n']) ? 'photo ' . $hidden['n'] : ($hidden['which'] ?? 'picture')); ?>
  <span class="pic-or">or</span>
  <button type="button" class="btn btn-ghost btn-sm pic-choose" data-pick="<?= h(json_encode($pick)) ?>" data-label="<?= h((string)$lab) ?>">🖼️ Choose from gallery</button>
<?php };
$resetForm = function (array $hidden) use ($act) { ?>
  <form class="pic-reset" method="post" action="<?= $act ?>" onsubmit="return confirm('Go back to the original picture?')"><?= csrf_field() ?>
    <?php foreach ($hidden as $k => $v): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h((string)$v) ?>"><?php endforeach; ?>
    <button class="btn btn-ghost btn-sm">↩ Use original</button>
  </form>
<?php };
?>
<div class="adm-head"><div><h1>Pictures</h1><p class="adm-sub">Change any picture on the website. Pick a new image and it goes live straight away. You can always go back to the original.</p></div>
  <a class="btn btn-ghost" target="_blank" href="<?= url() ?>">View website ↗</a></div>

<nav class="pills pic-tabs" aria-label="Sections">
  <a href="#pages">Page pictures</a><a href="#photos">Photo library</a><?php if ($brandOk): ?><a href="#brand">Logo &amp; icon</a><?php endif; ?><a href="#elsewhere">Other pictures</a>
</nav>

<div class="panel" id="pages">
  <div class="panel-head"><h2>Page pictures</h2><span class="muted small">Header photos, host portrait, About photos and more, grouped by page</span></div>
  <?php if (!$byPage): ?><p class="muted">No picture slots are available for your role.</p><?php endif; ?>
  <?php foreach ($byPage as $pid => $list): ?>
    <h3 class="pic-page" id="page-<?= h($pid) ?>"><?= h($pages[$pid]['icon'] ?? '') ?> <?= h($pages[$pid]['label'] ?? $pid) ?>
      <a class="small" href="<?= url('admin/pages/' . $pid) ?>">Edit page text</a> <a class="small" target="_blank" href="<?= url($pages[$pid]['url'] === 'home' ? '' : $pages[$pid]['url']) ?>">View page ↗</a></h3>
    <div class="pic-grid">
      <?php foreach ($list as $s): ?>
        <article class="pic-card" id="slot-<?= h(str_replace('.', '-', $s['key'])) ?>">
          <div class="pic-thumb"><?php if ($s['url']): ?><img src="<?= h($s['url']) ?>" alt="" loading="lazy"><?php else: ?><span>Built-in photo</span><?php endif; ?></div>
          <div class="pic-body">
            <b><?= h($s['label']) ?></b>
            <small class="muted"><?= h($s['section']) ?><?= $s['overridden'] ? ' · <span class="pic-flag">changed</span>' : '' ?></small>
            <?php if ($s['help']): ?><small class="muted"><?= h($s['help']) ?></small><?php endif; ?>
            <?php $replaceForm(['action' => 'slot_replace', 'key' => $s['key']]); ?>
            <?php if ($s['overridden']) $resetForm(['action' => 'slot_reset', 'key' => $s['key']]); ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="panel" id="photos">
  <div class="panel-head"><h2>Photo library</h2><span class="muted small"><?= count($lib) ?> photos used in slideshows, strips, the photo wall and event cards. Replacing one changes it everywhere it appears.</span></div>
  <div class="pic-grid pic-grid-sm">
    <?php foreach ($lib as $p): ?>
      <article class="pic-card" id="photo-<?= (int)$p['n'] ?>">
        <div class="pic-thumb"><img src="<?= h($p['url']) ?>" alt="Photo <?= (int)$p['n'] ?>" loading="lazy"></div>
        <div class="pic-body">
          <b>Photo <?= (int)$p['n'] ?><?= $p['overridden'] ? ' <span class="pic-flag">changed</span>' : '' ?></b>
          <?php if ($p['used']): ?><small class="muted"><?= h(implode(' · ', $p['used'])) ?></small><?php endif; ?>
          <?php $replaceForm(['action' => 'photo_replace', 'n' => $p['n']], 'Replace'); ?>
          <?php if ($p['overridden']) $resetForm(['action' => 'photo_reset', 'n' => $p['n']]); ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($brandOk): ?>
<div class="panel" id="brand">
  <div class="panel-head"><h2>Logo &amp; icon</h2><span class="muted small">Shown in the header, footer, tickets, admin and browser tab</span></div>
  <div class="pic-grid">
    <?php foreach ([['logo', 'Logo', $brand['logo'], $brand['logo_ov'], 'A transparent PNG works best.'], ['favicon', 'Browser tab icon', $brand['favicon'], $brand['fav_ov'], 'A square PNG, at least 128 × 128.']] as [$w, $lab, $url, $ov, $help]): ?>
      <article class="pic-card">
        <div class="pic-thumb pic-thumb-dark"><img src="<?= h($url) ?>" alt=""></div>
        <div class="pic-body"><b><?= $lab ?><?= $ov ? ' <span class="pic-flag">changed</span>' : '' ?></b><small class="muted"><?= $help ?></small>
          <?php $replaceForm(['action' => 'brand_replace', 'which' => $w]); ?>
          <?php if ($ov) $resetForm(['action' => 'brand_reset', 'which' => $w]); ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="panel" id="elsewhere">
  <div class="panel-head"><h2>Other pictures</h2><span class="muted small">These are managed together with their own content</span></div>
  <div class="pic-links">
    <?php foreach ($elsewhere as [$label, $path, $mod, $icon, $count, $noun]): if (!can($mod)) continue; ?>
      <a class="pic-link" href="<?= url($path) ?>"><span class="pic-link-ic" aria-hidden="true"><?= $icon ?></span><span><b><?= h($label) ?></b><small><?= (int)$count ?> <?= h($noun) ?> · edit →</small></span></a>
    <?php endforeach; ?>
  </div>
</div>

<dialog class="pic-picker" id="picPicker" aria-labelledby="picPickerTitle">
  <div class="pp-head">
    <h2 id="picPickerTitle">Choose a picture</h2>
    <nav class="pp-tabs" role="tablist"><button type="button" class="on" data-tab="gallery" role="tab">Gallery (<?= count($pickGallery) ?>)</button><button type="button" data-tab="lib" role="tab">Photo library (<?= count($pickLib) ?>)</button></nav>
    <button type="button" class="pp-close" id="ppClose" aria-label="Close">✕</button>
  </div>
  <p class="pp-hint small muted">Click a picture to use it. The original stays where it is, so nothing is lost.</p>
  <div class="pp-body">
    <div class="pp-grid" data-pane="gallery">
      <?php foreach ($pickGallery as $p): ?><button type="button" class="pp-item" data-source="<?= h($p['id']) ?>" title="<?= h($p['label']) ?>"><img data-src="<?= h($p['url']) ?>" alt="<?= h($p['label']) ?>"></button><?php endforeach; ?>
      <?php if (!$pickGallery): ?><p class="muted">The gallery is empty. Upload photos under Gallery, or use the Photo library tab.</p><?php endif; ?>
    </div>
    <div class="pp-grid" data-pane="lib" hidden>
      <?php foreach ($pickLib as $p): ?><button type="button" class="pp-item" data-source="<?= h($p['id']) ?>" title="<?= h($p['label']) ?>"><img data-src="<?= h($p['url']) ?>" alt="<?= h($p['label']) ?>"></button><?php endforeach; ?>
    </div>
  </div>
  <form id="ppForm" method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="source" id="ppSource"></form>
</dialog>
<script>
(function () {
  var dlg = document.getElementById('picPicker'), form = document.getElementById('ppForm'), target = null, loaded = false;
  if (!dlg || !dlg.showModal) { document.querySelectorAll('.pic-choose').forEach(function (b) { b.hidden = true; }); return; }
  function loadThumbs(pane) {
    dlg.querySelectorAll('[data-pane="' + pane + '"] img[data-src]').forEach(function (i) { i.src = i.dataset.src; i.removeAttribute('data-src'); i.loading = 'lazy'; });
  }
  document.addEventListener('click', function (e) {
    var open = e.target.closest('.pic-choose');
    if (open) {
      target = JSON.parse(open.dataset.pick);
      document.getElementById('picPickerTitle').textContent = 'Choose a picture for: ' + ((open.closest('.pic-body') && open.closest('.pic-body').querySelector('b') ? open.closest('.pic-body').querySelector('b').textContent.replace(/changed$/, '').trim() : '') || open.dataset.label || 'this picture');
      dlg.showModal(); loadThumbs(dlg.querySelector('.pp-tabs .on').dataset.tab); return;
    }
    if (e.target.closest('#ppClose') || e.target === dlg) { dlg.close(); return; }
    var tab = e.target.closest('.pp-tabs button');
    if (tab) {
      dlg.querySelectorAll('.pp-tabs button').forEach(function (b) { b.classList.toggle('on', b === tab); });
      dlg.querySelectorAll('.pp-grid').forEach(function (g) { g.hidden = g.dataset.pane !== tab.dataset.tab; });
      loadThumbs(tab.dataset.tab); return;
    }
    var item = e.target.closest('.pp-item');
    if (item && target) {
      Object.keys(target).forEach(function (k) {
        var el = form.querySelector('[name="' + k + '"]');
        if (!el) { el = document.createElement('input'); el.type = 'hidden'; el.name = k; form.appendChild(el); }
        el.value = target[k];
      });
      document.getElementById('ppSource').value = item.dataset.source;
      form.submit();
    }
  });
})();
</script>
