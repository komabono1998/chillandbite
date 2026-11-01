<section class="page-hero"><div class="wrap"><span class="eyebrow gold">Gallery</span><h1>Moments from Chill &amp; Bite</h1></div></section>
<section class="section section-cream"><div class="wrap">
  <nav class="pills" aria-label="Albums"><a href="<?= url('gallery') ?>"<?= !$album ? ' aria-current="true"' : '' ?>>All</a>
    <?php foreach ($albums as $a): ?><a href="<?= url('gallery?album=' . h($a['slug'])) ?>"<?= $album === $a['slug'] ? ' aria-current="true"' : '' ?>><?= h($a['name']) ?></a><?php endforeach; ?></nav>
  <?php if ($images): ?><div class="gallery-grid"><?php foreach ($images as $i): ?><figure><img src="<?= h(upload_url($i['file'])) ?>" alt="<?= h($i['caption'] ?: 'Chill & Bite event photo') ?>" loading="lazy"><?php if ($i['caption']): ?><figcaption><?= h($i['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div>
  <?php else: ?><p class="muted">Photos from our events will appear here.</p><?php endif; ?>
</div></section>
