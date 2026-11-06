<section class="page-hero hero-dark"><div class="wrap"><span class="eyebrow gold"><?= cth('advertise.hero.eyebrow') ?></span><h1><?= cth('advertise.hero.heading') ?></h1>
  <p class="lead"><?= cth('advertise.hero.lead') ?></p>
  <a class="btn btn-primary btn-lg" href="#proposal"><?= cth('advertise.hero.btn_proposal') ?></a> <a class="btn btn-outline btn-lg" href="<?= url('partner') ?>"><?= cth('advertise.hero.btn_partner') ?></a></div></section>
<?php if ($sponsors): ?><section class="section section-dark"><div class="wrap center"><h2><?= cth('advertise.partners.heading') ?></h2><ul class="sponsor-row"><?php foreach ($sponsors as $s) sponsor_item($s); ?></ul></div></section><?php endif; ?>
<?php
$groups = [
    'ads' => ['icon' => '📣', 'cls' => 'g-ads', 'icons' => ['🖥️', '📰', '📱', '🛍️']],
    'spons' => ['icon' => '🤝', 'cls' => 'g-spons', 'icons' => ['🎪', '✨', '🎤', '📅']],
];
?>
<section class="section section-cream" id="ways"><div class="wrap">
  <div class="section-head"><span class="eyebrow"><?= cth('advertise.opportunities.eyebrow') ?></span><h2><?= cth('advertise.opportunities.heading') ?></h2></div>
  <div class="way-tabs" role="tablist" aria-label="Choose how to partner">
    <?php $first = true; foreach ($groups as $g => $m): ?>
      <button type="button" class="way-tab <?= $m['cls'] ?><?= $first ? ' on' : '' ?>" role="tab" aria-selected="<?= $first ? 'true' : 'false' ?>" data-way="<?= $g ?>">
        <span class="way-ic" aria-hidden="true"><?= $m['icon'] ?></span><span><b><?= cth('advertise.opportunities.' . $g . '_title') ?></b><small><?= cth('advertise.opportunities.' . $g . '_tag') ?></small></span>
      </button>
    <?php $first = false; endforeach; ?>
  </div>
  <?php $first = true; foreach ($groups as $g => $m): ?>
  <div class="way-pane <?= $m['cls'] ?><?= $first ? ' on' : '' ?>" data-pane="<?= $g ?>" role="tabpanel">
    <h3 class="way-pane-title"><span aria-hidden="true"><?= $m['icon'] ?></span> <?= cth('advertise.opportunities.' . $g . '_title') ?></h3>
    <div class="way-grid">
      <?php foreach (ctrows('advertise.opportunities.' . $g . '_cards') as $i => [$t, $d]): ?>
        <a class="way-card" href="#proposal" data-service="<?= h($t) ?>">
          <span class="way-card-ic" aria-hidden="true"><?= $m['icons'][$i % 4] ?></span>
          <h4><?= h($t) ?></h4><p><?= h($d) ?></p>
          <span class="way-go">Request a proposal <i aria-hidden="true">→</i></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php $first = false; endforeach; ?>
  <p class="small muted mt"><?= cth('advertise.opportunities.note') ?></p>
</div></section>
<script>
(function () {
  var root = document.getElementById('ways'); if (!root) return;
  root.classList.add('is-tabs');
  var tabs = root.querySelectorAll('.way-tab'), panes = root.querySelectorAll('.way-pane');
  function show(w) {
    tabs.forEach(function (t) { var on = t.dataset.way === w; t.classList.toggle('on', on); t.setAttribute('aria-selected', on); });
    panes.forEach(function (p) { p.classList.toggle('on', p.dataset.pane === w); });
  }
  tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.way); }); });
  root.querySelectorAll('.way-card').forEach(function (c) {
    c.addEventListener('click', function () {
      var sel = document.querySelector('select[name="service"]'); if (!sel) return;
      for (var i = 0; i < sel.options.length; i++) if (sel.options[i].value === c.dataset.service) sel.selectedIndex = i;
    });
  });
})();
</script>
<section class="section" id="proposal"><div class="wrap narrow-wrap">
  <div class="section-head"><span class="eyebrow"><?= cth('advertise.proposal.eyebrow') ?></span><h2><?= cth('advertise.proposal.heading') ?></h2></div>
  <form class="form-card" method="post" action="<?= url('advertise') ?>"><?= csrf_field() ?><?php honeypot(); ?>
    <div class="form-grid">
      <?php field('organization', 'Business / organization', ['required' => true]); field('name', 'Contact person', ['required' => true]); field('phone', 'Telephone', ['required' => true, 'type' => 'tel']); field('email', 'Email', ['required' => true, 'type' => 'email']);
      field('industry', 'Industry');
      field('service', 'Service required', ['required' => true, 'type' => 'select', 'options' => ['' => 'Choose...', 'Event Sponsorship' => 'Event Sponsorship', 'Website Banner Advertising' => 'Website Banner Advertising', 'Sponsored Articles' => 'Sponsored Articles', 'Social Media Promotion' => 'Social Media Promotion', 'Brand Activations' => 'Brand Activations', 'Product Showcases' => 'Product Showcases', 'Comedy Show Sponsorship' => 'Comedy Show Sponsorship', 'Annual Partnerships' => 'Annual Partnerships']]);
      field('dates', 'Preferred campaign dates'); field('budget', 'Estimated budget (optional)'); ?>
    </div>
    <?php field('message', 'Campaign objectives', ['type' => 'textarea']); ?>
    <button class="btn btn-primary btn-lg"><?= cth('advertise.proposal.button') ?></button>
  </form>
</div></section>
