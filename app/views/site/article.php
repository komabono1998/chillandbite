<?php
$accredited = ($art['author_role'] ?? '') === 'contributor';
$byline = $art['author'] ? 'By ' . h($art['author']) . (!empty($art['author_org']) ? ' · ' . h($art['author_org']) : '') : '';
$newsLd = ['@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => $art['title'], 'datePublished' => date('c', strtotime($art['published_at'])),
    'dateModified' => date('c', strtotime($art['updated_at'] ?? $art['published_at'])), 'description' => $art['excerpt'] ?: excerpt($art['body'], 160),
    'mainEntityOfPage' => abs_url('news/' . $art['slug']),
    'publisher' => ['@type' => 'Organization', 'name' => 'The Gulu Chronicles', 'logo' => ['@type' => 'ImageObject', 'url' => abs_from(brand_logo())]]];
if ($art['author']) $newsLd['author'] = ['@type' => 'Person', 'name' => $art['author']];
if ($art['image']) $newsLd['image'] = [(str_starts_with($art['image'], 'img/') ? abs_url('assets/' . $art['image']) : abs_url('uploads/' . $art['image']))];
?>
<script type="application/ld+json"><?= json_encode($newsLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<article>
<section class="page-hero compact"><div class="wrap narrow-wrap">
  <p><span class="eyebrow gold"><?= h($art['category'] ?? 'The Gulu Chronicles') ?></span> <span class="tag tag-<?= h($art['label']) ?>"><?= h(NEWS_LABELS[$art['label']]) ?></span>
    <?php if ($accredited): ?><span class="tag tag-accredited">✓ Accredited contributor</span><?php endif; ?></p>
  <h1><?= h($art['title']) ?></h1>
  <p class="meta"><?= $byline ? $byline . ' · ' : '' ?><?= fmt_date($art['published_at'], 'j F Y') ?><?= !empty($art['source_name']) ? ' · via ' . h($art['source_name']) : '' ?></p>
</div></section>
<section class="section section-cream"><div class="wrap narrow-wrap">
  <?php if ($art['image']): ?><img class="article-hero" src="<?= h(upload_url($art['image'])) ?>" alt="<?= h($art['title']) ?>"><?php endif; ?>
  <?php if ($art['label'] === 'sponsored'): ?><p class="alert alert-warn"><strong>Sponsored content</strong><?= $art['sponsor_name'] ? ' by ' . h($art['sponsor_name']) : '' ?>. Paid promotion, not editorial reporting.</p><?php endif; ?>
  <?php if ($art['label'] === 'gossip' || $art['label'] === 'unconfirmed'): ?><p class="alert alert-warn">This is <?= $art['label'] === 'gossip' ? 'entertainment gossip and commentary' : 'an unconfirmed report' ?>, not verified news.</p><?php endif; ?>
  <?php if ($art['label'] === 'opinion'): ?><p class="alert alert-warn">Opinion: the views expressed are the author's own.</p><?php endif; ?>
  <div class="prose"><?= paragraphs($art['body']) ?></div>
  <?php if (!empty($art['source_name'])): ?>
    <aside class="gc-source"><span aria-hidden="true">🔗</span><div><b>Source</b>
      <p>This is a summary written by The Gulu Chronicles based on reporting by <strong><?= h($art['source_name']) ?></strong>.<?php if (!empty($art['source_url']) && preg_match('#^https?://#i', $art['source_url'])): ?> Read the full original story at <a href="<?= h($art['source_url']) ?>" target="_blank" rel="noopener nofollow"><?= h(parse_url($art['source_url'], PHP_URL_HOST) ?: 'the source') ?> ↗</a>.<?php endif; ?></p></div></aside>
  <?php endif; ?>
  <?php if ($art['correction']): ?><div class="alert alert-err"><strong>Correction:</strong> <?= h($art['correction']) ?></div><?php endif; ?>
  <?php share_buttons($art['title'], 'news/' . $art['slug']); ?>
  <aside class="gc-write"><div><b>Got a story or a blog?</b><span>Bloggers and media houses can apply to be accredited to publish on The Gulu Chronicles.</span></div><a class="btn btn-primary btn-sm" href="<?= url('accreditation') ?>">Get accredited</a></aside>
</div></section>
</article>
<?php if ($related): ?><section class="section"><div class="wrap"><h2>Related stories</h2><div class="grid grid-3"><?php foreach ($related as $a) article_card($a); ?></div></div></section><?php endif; ?>
