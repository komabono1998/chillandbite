<?php
$reserve = !empty($next) ? url('events/' . $next['slug'] . '/reserve') : url('events');
$facts = ctrows('about.stats.items');
$pillars = ctrows('about.pillars.cards', 5);
$values = ctrows('about.mission.values');
$faqs = ctrows('about.faq.items');
$socials = social_links();
$hostName = 'MC Kash Owakabi';
$safeLink = fn(string $l) => preg_match('#^[A-Za-z0-9/_\-\#?=&]*$#', $l) ? url($l) : url();

// Structured data so search engines understand the organisation and the FAQ
$org = [
    '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => setting('site_name', 'Chill & Bite MEATINGS'),
    'url' => abs_url(''), 'logo' => abs_url('assets/img/logo.png'), 'description' => setting('tagline'),
    'address' => ['@type' => 'PostalAddress', 'streetAddress' => setting('venue'), 'addressLocality' => 'Gulu City', 'addressCountry' => 'UG'],
];
if (setting('contact_phone')) $org['contactPoint'] = [['@type' => 'ContactPoint', 'telephone' => setting('contact_phone'), 'contactType' => 'customer service', 'areaServed' => 'UG', 'availableLanguage' => ['English']]];
if ($socials) $org['sameAs'] = array_values($socials);
$faqLd = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faqs)];
$ld = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;
?>
<script type="application/ld+json"><?= json_encode($org, $ld) ?></script>
<?php if ($faqs): ?><script type="application/ld+json"><?= json_encode($faqLd, $ld) ?></script><?php endif; ?>

<section class="page-hero ab-hero"><div class="wrap">
  <span class="eyebrow gold"><?= cth('about.hero.eyebrow') ?></span>
  <h1><?= cth('about.hero.heading') ?></h1>
  <p class="lead"><?= cth('about.hero.lead') ?></p>
  <div class="btn-row"><a class="btn btn-primary btn-lg" href="<?= h($reserve) ?>"><?= cth('about.hero.btn_primary') ?></a><a class="btn btn-outline btn-lg" href="<?= url('contact') ?>"><?= cth('about.hero.btn_secondary') ?></a></div>
</div></section>

<?php if ($facts): ?>
<section class="ab-facts" aria-label="Key facts"><div class="wrap"><ul>
  <?php foreach ($facts as [$val, $lab]): ?>
    <li><b<?= ctype_digit($val) ? ' data-count="' . h($val) . '"' : '' ?>><?= h($val) ?></b><span><?= h($lab) ?></span></li>
  <?php endforeach; ?>
</ul></div></section>
<?php endif; ?>

<nav class="ab-subnav" aria-label="On this page"><div class="wrap">
  <?php foreach (['story' => 'Our story', 'what-we-do' => 'What we do', 'mission' => 'Mission & values', 'expect' => 'Experience', 'team' => 'Team', 'partners' => 'Partners', 'faq' => 'FAQ'] as $id => $label): ?>
    <a href="#<?= $id ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</div></nav>

<section class="section section-cream" id="story"><div class="wrap ab-story">
  <div data-reveal>
    <span class="eyebrow"><?= cth('about.story.eyebrow') ?></span>
    <h2><?= cth('about.story.heading') ?></h2>
    <p class="ab-p"><?= cth('about.story.p1') ?></p>
    <p class="ab-p"><?= cth('about.story.p2') ?></p>
    <blockquote class="ab-quote"><?= cth('about.story.quote') ?></blockquote>
  </div>
  <div class="ab-collage" data-reveal>
    <img class="c1" src="<?= h(cti('about.story.photo')) ?>" alt="Guests enjoying Chill &amp; Bite MEATINGS" loading="lazy" decoding="async">
    <img class="c2" src="<?= h(cti('about.story.photo2')) ?>" alt="The stage and crowd at night" loading="lazy" decoding="async">
    <img class="c3" src="<?= h(cti('about.story.photo3')) ?>" alt="Food from the grill" loading="lazy" decoding="async">
  </div>
</div></section>

<section class="section section-dark" id="what-we-do"><div class="wrap">
  <div class="section-head center"><span class="eyebrow gold"><?= cth('about.pillars.eyebrow') ?></span><h2><?= cth('about.pillars.heading') ?></h2><p class="lead narrow"><?= cth('about.pillars.intro') ?></p></div>
  <div class="ab-pillars">
    <?php foreach ($pillars as $i => [$ic, $t, $d, $link, $ph]): ?>
      <a class="ab-pillar" data-reveal href="<?= h($safeLink($link)) ?>" style="--i:<?= $i ?>">
        <span class="ab-pillar-img"><img src="<?= h($ph !== '' ? upload_url($ph) : photo_from('crowd', $i + 3)) ?>" alt="" loading="lazy" decoding="async"><span class="ab-pillar-ic" aria-hidden="true"><?= h($ic) ?></span></span>
        <span class="ab-pillar-body"><b><?= h($t) ?></b><span><?= h($d) ?></span><em>Explore →</em></span>
      </a>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section section-cream" id="mission"><div class="wrap">
  <div class="ab-mv">
    <article data-reveal><span class="ab-mv-ic" aria-hidden="true">🎯</span><h3><?= cth('about.mission.mission_title') ?></h3><p><?= cth('about.mission.mission') ?></p></article>
    <article data-reveal><span class="ab-mv-ic" aria-hidden="true">🔭</span><h3><?= cth('about.mission.vision_title') ?></h3><p><?= cth('about.mission.vision') ?></p></article>
  </div>
  <?php if ($values): ?>
  <h3 class="center ab-values-h"><?= cth('about.mission.values_heading') ?></h3>
  <div class="ab-values">
    <?php foreach ($values as $i => [$t, $d]): ?><div data-reveal><span><?= sprintf('%02d', $i + 1) ?></span><b><?= h($t) ?></b><p><?= h($d) ?></p></div><?php endforeach; ?>
  </div>
  <?php endif; ?>
</div></section>

<section class="section section-dark" id="expect"><div class="wrap ab-expect">
  <div data-reveal>
    <span class="eyebrow gold"><?= cth('about.expect.eyebrow') ?></span>
    <h2><?= cth('about.expect.heading') ?></h2>
    <ul class="ab-checks"><?php foreach (ctl('about.expect.list') as $li): ?><li><?= h($li) ?></li><?php endforeach; ?></ul>
    <a class="btn btn-primary btn-lg" href="<?= h($reserve) ?>"><?= cth('about.hero.btn_primary') ?></a>
  </div>
  <div class="ab-mosaic" data-reveal>
    <img class="m1" src="<?= h(photo(41)) ?>" alt="DJ and crowd under the lights" loading="lazy" decoding="async">
    <img class="m2" src="<?= h(photo(11)) ?>" alt="Guests playing games and networking" loading="lazy" decoding="async">
    <img class="m3" src="<?= h(photo(30)) ?>" alt="Glazed chicken wings" loading="lazy" decoding="async">
  </div>
</div></section>

<section class="section section-cream" id="team"><div class="wrap">
  <div class="section-head"><span class="eyebrow"><?= cth('about.team.eyebrow') ?></span><h2><?= cth('about.team.heading') ?></h2></div>
  <div class="ab-team">
    <article class="ab-person" data-reveal>
      <img src="<?= h(cti('home.host.photo')) ?>" alt="<?= h($hostName) ?>" loading="lazy" decoding="async">
      <div>
        <span class="eyebrow"><?= cth('about.team.host_role') ?></span>
        <h3><?= h($hostName) ?></h3>
        <p><?= cth('about.team.host_bio') ?></p>
        <div class="btn-row"><a class="btn btn-primary btn-sm" href="<?= url('laugh') ?>">Laugh with Owakabi</a>
          <?php foreach ($socials as $n => $u): ?><a class="btn btn-ghost-dark btn-sm" href="<?= h($u) ?>" target="_blank" rel="noopener"><?= h($n) ?></a><?php endforeach; ?></div>
      </div>
    </article>
    <article class="ab-org" data-reveal>
      <img class="ab-org-logo" src="<?= h(asset('img/logo.png')) ?>" alt="Chill &amp; Bite MEATINGS" width="320" height="253" loading="lazy">
      <div>
        <h3><?= cth('about.team.heading') ?></h3>
        <p><?= cth('about.team.text') ?></p>
        <ul class="ab-contact">
          <?php if (setting('contact_phone')): ?><li>📞 <a href="tel:<?= h(preg_replace('/\s+/', '', setting('contact_phone'))) ?>"><?= h(setting('contact_phone')) ?></a></li><?php endif; ?>
          <?php if (setting('contact_email')): ?><li>✉️ <a href="mailto:<?= h(setting('contact_email')) ?>"><?= h(setting('contact_email')) ?></a></li><?php endif; ?>
          <li>📍 <a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode(setting('venue') . ' ' . setting('location')) ?>"><?= h(setting('venue')) ?>, <?= h(setting('location')) ?></a></li>
        </ul>
        <a class="btn btn-outline-dark btn-sm" href="<?= url('events') ?>"><?= cth('about.team.button') ?></a>
      </div>
    </article>
  </div>
</div></section>

<section class="section section-dark" id="partners"><div class="wrap center">
  <span class="eyebrow gold"><?= cth('about.partners.eyebrow') ?></span>
  <h2><?= cth('about.partners.heading') ?></h2>
  <p class="lead narrow"><?= cth('about.partners.intro') ?></p>
  <?php if ($sponsors): ?><ul class="sponsor-row"><?php foreach ($sponsors as $s) sponsor_item($s); ?></ul>
  <?php else: ?><p class="muted"><?= cth('about.partners.empty') ?></p><?php endif; ?>
  <div class="btn-row" style="justify-content:center"><a class="btn btn-primary btn-lg" href="<?= url('partner') ?>"><?= cth('about.partners.button') ?></a><a class="btn btn-outline btn-lg" href="<?= url('advertise') ?>">Advertising options</a></div>
</div></section>

<?php if ($faqs): ?>
<section class="section section-cream" id="faq"><div class="narrow-wrap">
  <div class="section-head center"><span class="eyebrow"><?= cth('about.faq.eyebrow') ?></span><h2><?= cth('about.faq.heading') ?></h2></div>
  <div class="ab-faq">
    <?php foreach ($faqs as $i => [$q, $a]): ?>
      <details data-reveal<?= $i === 0 ? ' open' : '' ?>><summary><?= h($q) ?></summary><p><?= h($a) ?></p></details>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<section class="cta-final" style="--cta-photo:url('<?= h(photo(41)) ?>')">
  <div class="wrap center"><div class="cta-card">
    <?php if (!empty($next)): ?><p class="cta-date"><span aria-hidden="true">📅</span> <?= fmt_date($next['start_at'], 'l, j F Y') ?> · <?= fmt_time($next['start_at']) ?></p><?php endif; ?>
    <h2><?= cth('about.cta.heading') ?></h2>
    <p class="cta-text"><?= cth('about.cta.text') ?></p>
    <div class="btn-row" style="justify-content:center"><a class="btn btn-primary btn-lg" href="<?= h($reserve) ?>"><?= cth('about.cta.btn1') ?></a><a class="btn btn-outline btn-lg" href="<?= url('partner') ?>"><?= cth('about.cta.btn2') ?></a></div>
  </div></div>
</section>
