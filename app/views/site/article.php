<article>
<section class="page-hero compact"><div class="wrap narrow-wrap">
  <p><span class="eyebrow gold"><?= h($art['category'] ?? 'Northern Buzz') ?></span> <span class="tag tag-<?= h($art['label']) ?>"><?= h(NEWS_LABELS[$art['label']]) ?></span></p>
  <h1><?= h($art['title']) ?></h1>
  <p class="meta"><?= $art['author'] ? 'By ' . h($art['author']) . ' · ' : '' ?><?= fmt_date($art['published_at'], 'j F Y') ?></p>
</div></section>
<section class="section section-cream"><div class="wrap narrow-wrap">
  <?php if ($art['image']): ?><img class="article-hero" src="<?= h(upload_url($art['image'])) ?>" alt="<?= h($art['title']) ?>"><?php endif; ?>
  <?php if ($art['label'] === 'sponsored'): ?><p class="alert alert-warn"><strong>Sponsored content</strong><?= $art['sponsor_name'] ? ' by ' . h($art['sponsor_name']) : '' ?>. Paid promotion, not editorial reporting.</p><?php endif; ?>
  <?php if ($art['label'] === 'gossip' || $art['label'] === 'unconfirmed'): ?><p class="alert alert-warn">This is <?= $art['label'] === 'gossip' ? 'entertainment gossip and commentary' : 'an unconfirmed report' ?>, not verified news.</p><?php endif; ?>
  <?php if ($art['label'] === 'opinion'): ?><p class="alert alert-warn">Opinion: the views expressed are the author's own.</p><?php endif; ?>
  <div class="prose"><?= paragraphs($art['body']) ?></div>
  <?php if ($art['correction']): ?><div class="alert alert-err"><strong>Correction:</strong> <?= h($art['correction']) ?></div><?php endif; ?>
  <?php share_buttons($art['title'], 'news/' . $art['slug']); ?>
</div></section>
</article>
<?php if ($related): ?><section class="section"><div class="wrap"><h2>Related stories</h2><div class="grid grid-3"><?php foreach ($related as $a) article_card($a); ?></div></div></section><?php endif; ?>
