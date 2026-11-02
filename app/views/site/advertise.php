<section class="page-hero hero-dark"><div class="wrap"><span class="eyebrow gold">Brands &amp; advertising</span><h1>Your brand deserves to be seen.</h1>
  <p class="lead">Connect your business with professionals, entrepreneurs, creatives, and entertainment lovers through Chill &amp; Bite MEATINGS and our digital media platforms.</p>
  <a class="btn btn-primary btn-lg" href="#proposal">Request a proposal</a> <a class="btn btn-outline btn-lg" href="<?= url('partner') ?>">Become a partner</a></div></section>
<?php if ($sponsors): ?><section class="section section-dark"><div class="wrap center"><h2>Our partners</h2><ul class="sponsor-row"><?php foreach ($sponsors as $s) sponsor_item($s); ?></ul></div></section><?php endif; ?>
<section class="section section-cream"><div class="wrap">
  <div class="section-head"><span class="eyebrow">Opportunities</span><h2>Ways to partner with us</h2></div>
  <div class="grid grid-4">
    <?php foreach ([
      'Event Sponsorship' => 'Sponsor a monthly event or a specific activity.',
      'Website Banner Advertising' => 'Advertise on selected website pages.',
      'Sponsored Articles' => 'Clearly labelled promotional articles.',
      'Social Media Promotion' => 'Campaigns across our social channels.',
      'Brand Activations' => 'Engage attendees with approved activities.',
      'Product Showcases' => 'Put products and services in front of guests.',
      'Comedy Show Sponsorship' => 'Sponsor Laugh with Owakabi performances.',
      'Annual Partnerships' => 'Longer-term promotional partnerships.'] as $t => $d): ?>
      <div class="card card-pad"><h3><?= h($t) ?></h3><p><?= h($d) ?></p></div>
    <?php endforeach; ?>
  </div>
  <p class="small muted mt">All paid promotions are clearly identified as sponsored content.</p>
</div></section>
<section class="section" id="proposal"><div class="wrap narrow-wrap">
  <div class="section-head"><span class="eyebrow">Advertising inquiry</span><h2>Request a proposal</h2></div>
  <form class="form-card" method="post" action="<?= url('advertise') ?>"><?= csrf_field() ?><?php honeypot(); ?>
    <div class="form-grid">
      <?php field('organization', 'Business / organization', ['required' => true]); field('name', 'Contact person', ['required' => true]); field('phone', 'Telephone', ['required' => true, 'type' => 'tel']); field('email', 'Email', ['required' => true, 'type' => 'email']);
      field('industry', 'Industry');
      field('service', 'Service required', ['required' => true, 'type' => 'select', 'options' => ['' => 'Choose...', 'Event Sponsorship' => 'Event Sponsorship', 'Website Banner Advertising' => 'Website Banner Advertising', 'Sponsored Articles' => 'Sponsored Articles', 'Social Media Promotion' => 'Social Media Promotion', 'Brand Activations' => 'Brand Activations', 'Product Showcases' => 'Product Showcases', 'Comedy Show Sponsorship' => 'Comedy Show Sponsorship', 'Annual Partnerships' => 'Annual Partnerships']]);
      field('dates', 'Preferred campaign dates'); field('budget', 'Estimated budget (optional)'); ?>
    </div>
    <?php field('message', 'Campaign objectives', ['type' => 'textarea']); ?>
    <button class="btn btn-primary btn-lg">Send inquiry</button>
  </form>
</div></section>
