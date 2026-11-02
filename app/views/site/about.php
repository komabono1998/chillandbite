<section class="page-hero"><div class="wrap"><span class="eyebrow gold"><?= cth('about.hero.eyebrow') ?></span><h1><?= cth('about.hero.heading') ?></h1><p class="lead"><?= cth('about.hero.lead') ?></p></div></section>
<section class="section section-cream"><div class="wrap prose">
  <h2><?= cth('about.story.heading') ?></h2>
  <p><?= cth('about.story.p1') ?></p>
  <p><?= cth('about.story.p2') ?></p>
  <h2><?= cth('about.expect.heading') ?></h2>
  <ul class="ticks"><?php foreach (ctl('about.expect.list') as $li): ?><li><?= h($li) ?></li><?php endforeach; ?></ul>
  <h2><?= cth('about.team.heading') ?></h2>
  <p><?= cth('about.team.text') ?></p>
  <p><a class="btn btn-primary" href="<?= url('events') ?>"><?= cth('about.team.button') ?></a></p>
</div></section>
