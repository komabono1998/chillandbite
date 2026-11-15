<section class="page-hero"><div class="wrap"><span class="eyebrow gold"><?= cth('gallery.hero.eyebrow') ?></span><h1><?= cth('gallery.hero.heading') ?></h1></div></section>
<section class="section section-cream"><div class="wrap">
  <nav class="pills" aria-label="Albums"><a href="<?= url('gallery') ?>"<?= !$album ? ' aria-current="true"' : '' ?>>All</a>
    <?php foreach ($albums as $a): if (!(int)$a['n']) continue; ?><a href="<?= url('gallery?album=' . h($a['slug'])) ?>"<?= $album === $a['slug'] ? ' aria-current="true"' : '' ?>><?= h($a['name']) ?> <small>(<?= (int)$a['n'] ?>)</small></a><?php endforeach; ?></nav>
  <?php if ($images): ?>
    <div class="gal-bar"><span class="small muted"><?= count($images) ?> photo<?= count($images) === 1 ? '' : 's' ?><?= $downloads ? ' · tap a photo to enlarge, or use the download button' : ' · tap a photo to enlarge' ?></span>
      <?php if ($downloads && $zip): ?><a class="btn btn-outline btn-sm" href="<?= url('gallery/zip' . ($album ? '?album=' . h($album) : '')) ?>">Download all (ZIP)</a><?php endif; ?></div>
    <div class="gallery-grid"><?php foreach ($images as $i): $src = h(upload_url($i['file'])); ?><figure><a href="<?= $src ?>" data-lightbox><img src="<?= $src ?>" alt="<?= h($i['caption'] ?: 'Chill & Bite event photo') ?>" loading="lazy" decoding="async"></a>
      <?php if ($downloads): ?><a class="gal-dl" href="<?= url('gallery/download/' . (int)$i['id']) ?>" title="Download this photo" aria-label="Download this photo">Download</a><?php endif; ?>
      <?php if ($i['caption']): ?><figcaption><?= h($i['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div>
    <p class="small muted" style="margin-top:1.2rem">Free to download and share. Please tag us <b>@chillandbitemeatings</b>. Spot yourself and want a photo taken down? <a href="<?= url('contact') ?>">Contact us</a> and we will remove it.</p>
  <?php else: ?><p class="muted"><?= cth('gallery.misc.empty') ?></p><?php endif; ?>
</div></section>
