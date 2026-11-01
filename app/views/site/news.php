<section class="page-hero"><div class="wrap"><span class="eyebrow gold">News &amp; Gossips</span><h1>Northern Buzz: What's happening up north?</h1>
  <p class="lead">Entertainment, music, lifestyle, business and community stories from Northern Uganda.</p></div></section>
<section class="section section-cream"><div class="wrap">
  <nav class="pills" aria-label="Categories">
    <a href="<?= url('news') ?>"<?= !$cat ? ' aria-current="true"' : '' ?>>All</a>
    <?php foreach ($categories as $c): ?><a href="<?= url('news?cat=' . h($c['slug'])) ?>"<?= $cat === $c['slug'] ? ' aria-current="true"' : '' ?>><?= h($c['name']) ?></a><?php endforeach; ?>
  </nav>
  <p class="small muted">Stories are labelled: <b>Verified News</b>, <b>Opinion</b>, <b>Entertainment Gossip</b>, <b>Unconfirmed Report</b> or <b>Sponsored</b>.</p>
  <?php if ($articles): ?><div class="grid grid-3"><?php foreach ($articles as $a) article_card($a); ?></div>
    <p class="center mt"><?php if ($pg > 1): ?><a class="btn btn-ghost-dark" href="<?= url('news?' . http_build_query(array_filter(['cat' => $cat, 'p' => $pg - 1]))) ?>">&larr; Newer</a><?php endif; ?>
      <?php if (count($articles) === 9): ?><a class="btn btn-outline-dark" href="<?= url('news?' . http_build_query(array_filter(['cat' => $cat, 'p' => $pg + 1]))) ?>">Older &rarr;</a><?php endif; ?></p>
  <?php else: ?><p class="muted">No stories here yet.</p><?php endif; ?>
</div></section>
