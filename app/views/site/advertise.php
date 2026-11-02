<section class="page-hero hero-dark"><div class="wrap"><span class="eyebrow gold"><?= cth('advertise.hero.eyebrow') ?></span><h1><?= cth('advertise.hero.heading') ?></h1>
  <p class="lead"><?= cth('advertise.hero.lead') ?></p>
  <a class="btn btn-primary btn-lg" href="#proposal"><?= cth('advertise.hero.btn_proposal') ?></a> <a class="btn btn-outline btn-lg" href="<?= url('partner') ?>"><?= cth('advertise.hero.btn_partner') ?></a></div></section>
<?php if ($sponsors): ?><section class="section section-dark"><div class="wrap center"><h2><?= cth('advertise.partners.heading') ?></h2><ul class="sponsor-row"><?php foreach ($sponsors as $s) sponsor_item($s); ?></ul></div></section><?php endif; ?>
<section class="section section-cream"><div class="wrap">
  <div class="section-head"><span class="eyebrow"><?= cth('advertise.opportunities.eyebrow') ?></span><h2><?= cth('advertise.opportunities.heading') ?></h2></div>
  <div class="grid grid-4">
    <?php foreach (ctrows('advertise.opportunities.cards') as [$t, $d]): ?>
      <div class="card card-pad"><h3><?= h($t) ?></h3><p><?= h($d) ?></p></div>
    <?php endforeach; ?>
  </div>
  <p class="small muted mt"><?= cth('advertise.opportunities.note') ?></p>
</div></section>
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
