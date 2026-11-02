<?php
$firstPage = $pg === 1 && !$cat && $q === '';
$lead = $firstPage ? ($articles[0] ?? null) : null;
$side = $lead ? array_slice($articles, 1, 2) : [];
$rest = $lead ? array_slice($articles, 3) : $articles;
$qs = fn(array $o) => url('news' . (($t = http_build_query(array_filter(array_merge(['cat' => $cat, 'q' => $q], $o)))) ? '?' . $t : ''));
$tickerItems = '';
foreach ($ticker ?? [] as $t) $tickerItems .= '<a href="' . h(url('news/' . $t['slug'])) . '">' . h($t['title']) . '</a>';
?>
<section class="page-hero gc-masthead"><div class="wrap">
  <span class="eyebrow gold"><?= cth('news.hero.eyebrow') ?></span>
  <h1><?= cth('news.hero.heading') ?></h1>
  <p class="lead"><?= cth('news.hero.lead') ?></p>
  <div class="gc-actions">
    <form class="gc-search" method="get" action="<?= url('news') ?>" role="search">
      <?php if ($cat): ?><input type="hidden" name="cat" value="<?= h($cat) ?>"><?php endif; ?>
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search stories, people, places" aria-label="Search stories" maxlength="80">
      <button class="btn btn-primary">Search</button>
    </form>
    <a class="btn btn-outline" href="<?= url('accreditation') ?>">Get accredited to publish →</a>
  </div>
</div></section>

<?php if ($tickerItems): ?>
<div class="gc-ticker" aria-label="Latest headlines"><span class="gc-ticker-label">Latest</span>
  <div class="gc-ticker-view"><div class="gc-ticker-track"><?= $tickerItems ?><span class="gc-dup" aria-hidden="true"><?= $tickerItems ?></span></div></div>
</div>
<?php endif; ?>

<section class="section section-cream" id="stories"><div class="wrap">
  <nav class="pills" aria-label="Categories">
    <a href="<?= $qs(['cat' => null]) ?>"<?= !$cat ? ' aria-current="true"' : '' ?>>All</a>
    <?php foreach ($categories as $c): ?><a href="<?= $qs(['cat' => $c['slug']]) ?>"<?= $cat === $c['slug'] ? ' aria-current="true"' : '' ?>><?= h($c['name']) ?></a><?php endforeach; ?>
  </nav>
  <?php if ($q !== ''): ?><p class="gc-found">Results for <b>“<?= h($q) ?>”</b> · <a href="<?= $qs(['q' => null]) ?>">clear search</a></p><?php endif; ?>

  <?php if ($articles): ?>
    <?php if ($lead): ?>
    <div class="gc-lead-grid">
      <a class="gc-lead" href="<?= url('news/' . h($lead['slug'])) ?>">
        <span class="gc-lead-img"><?php article_visual($lead, 'gc-img'); ?></span>
        <span class="gc-lead-body">
          <span class="gc-kicker"><?= h($lead['category'] ?? 'News') ?> <span class="tag tag-<?= h($lead['label']) ?>"><?= h(NEWS_LABELS[$lead['label']] ?? '') ?></span></span>
          <b><?= h($lead['title']) ?></b>
          <span><?= h(excerpt($lead['excerpt'] ?: $lead['body'], 190)) ?></span>
          <small><?= fmt_date($lead['published_at'], 'j M Y') ?><?= !empty($lead['source_name']) ? ' · via ' . h($lead['source_name']) : (!empty($lead['author']) ? ' · ' . h($lead['author']) : '') ?></small>
        </span>
      </a>
      <?php if ($side): ?><div class="gc-side">
        <?php foreach ($side as $a): ?>
          <a class="gc-side-item" href="<?= url('news/' . h($a['slug'])) ?>">
            <span class="gc-side-img"><?php article_visual($a, 'gc-img'); ?></span>
            <span><span class="gc-kicker"><?= h($a['category'] ?? 'News') ?></span><b><?= h($a['title']) ?></b><small><?= fmt_date($a['published_at'], 'j M Y') ?><?= !empty($a['source_name']) ? ' · via ' . h($a['source_name']) : '' ?></small></span>
          </a>
        <?php endforeach; ?>
      </div><?php endif; ?>
    </div>
    <?php if ($rest): ?><h2 class="gc-more">More stories</h2><?php endif; ?>
    <?php endif; ?>
    <?php if ($rest): ?><div class="grid grid-3"><?php foreach ($rest as $a) article_card($a); ?></div><?php endif; ?>

    <p class="center mt"><?php if ($pg > 1): ?><a class="btn btn-ghost-dark" href="<?= $qs(['p' => $pg - 1 > 1 ? $pg - 1 : null]) ?>">&larr; Newer</a><?php endif; ?>
      <?php if (count($articles) === $per_page): ?><a class="btn btn-outline-dark" href="<?= $qs(['p' => $pg + 1]) ?>">Older &rarr;</a><?php endif; ?></p>
  <?php else: ?>
    <div class="empty"><?= $q !== '' ? 'No stories match your search.' : cth('news.misc.empty') ?> <?php if ($q !== '' || $cat): ?><a href="<?= url('news') ?>">See all stories</a><?php endif; ?></div>
  <?php endif; ?>
  <p class="small muted gc-labels">Stories are labelled: <b>Verified News</b>, <b>Opinion</b>, <b>Entertainment Gossip</b>, <b>Unconfirmed Report</b> or <b>Sponsored</b>. Stories that summarise other outlets say so and link to the original.</p>
</div></section>

<section class="gc-cta"><div class="wrap">
  <div class="gc-cta-text">
    <span class="eyebrow gold">For bloggers &amp; media houses</span>
    <h2>Write for The Gulu Chronicles</h2>
    <p>Get accredited to publish news and blogs on a platform built for Gulu and Northern Uganda. Every story gets your byline, and our editors review it before it goes live.</p>
    <div class="btn-row"><a class="btn btn-primary btn-lg" href="<?= url('accreditation') ?>">Apply for accreditation</a><a class="btn btn-outline btn-lg" href="<?= url('contact') ?>">Send us a tip</a></div>
  </div>
  <ul class="gc-cta-points">
    <li><b>Byline &amp; badge</b><span>Your name and organisation on every story, with an Accredited badge.</span></li>
    <li><b>Editorial support</b><span>An editor reviews each story before publication.</span></li>
    <li><b>Real audience</b><span>Readers from our events and community, in Gulu and beyond.</span></li>
  </ul>
</div></section>
