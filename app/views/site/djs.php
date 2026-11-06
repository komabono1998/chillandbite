<?php
$phone = trim((string)setting('contact_phone'));
$telHref = $phone ? 'tel:' . preg_replace('/\s+/', '', $phone) : '';
$waHref = $phone ? wa_link($phone, "Hello, I'd like to book a DJ for an event.") : '';
$trust = ctl('djs.hero.trust');
$selType = old('event_type');
$selDj = old('dj_id');
$sources = array_values(array_unique(array_column($mixes, 'source')));
$initial = fn(string $n) => h(mb_strtoupper(mb_substr(trim($n), 0, 1)));
?>
<section class="page-hero hero-dark dj-hero"><div class="wrap dj-hero-in">
  <div class="dj-hero-text">
    <span class="eyebrow gold"><?= cth('djs.hero.eyebrow') ?></span>
    <h1><?= cth('djs.hero.heading') ?></h1>
    <p class="lead"><?= cth('djs.hero.lead') ?></p>
    <div class="btn-row"><a class="btn btn-primary btn-lg" href="#book"><?= cth('djs.hero.btn_book') ?></a><a class="btn btn-outline btn-lg" href="#mixes"><?= cth('djs.hero.btn_mixes') ?></a></div>
    <div class="dj-eq" aria-hidden="true"><?php for ($i = 0; $i < 28; $i++): ?><i style="--d:<?= number_format(0.4 + (($i * 37) % 70) / 50, 2) ?>s;--h:<?= 22 + (($i * 53) % 60) ?>%"></i><?php endfor; ?></div>
  </div>
  <div class="dj-deck" aria-hidden="true">
    <div class="dj-vinyl"><span class="dj-label">Podium<br>DJs</span></div>
    <div class="dj-arm"></div>
    <span class="lw-badge lw-badge-1">🎧 Live DJ sets</span>
    <span class="lw-badge lw-badge-2">🔊 Sound &amp; lights</span>
  </div>
</div></section>

<?php if ($trust): $items = ''; foreach ($trust as $t) $items .= '<span>' . h($t) . '</span>'; ?>
<div class="lw-marquee" aria-label="Highlights"><div class="lw-marquee-track"><?= $items ?><span class="lw-dup" aria-hidden="true"><?= $items ?></span></div></div>
<?php endif; ?>

<section class="section section-dark dj-mixes" id="mixes"><div class="wrap">
  <div class="section-head"><span class="eyebrow gold"><?= cth('djs.mixes.eyebrow') ?></span><h2><?= cth('djs.mixes.heading') ?></h2><p><?= cth('djs.mixes.intro') ?></p></div>
  <?php if ($mixes): ?>
    <?php if (count($sources) > 1): ?>
      <nav class="lw-chips" aria-label="Filter mixes"><button type="button" class="on" data-src="all">All mixes</button><?php foreach ($sources as $s): ?><button type="button" data-src="<?= h($s) ?>"><?= h(DJ_SOURCES[$s] ?? $s) ?></button><?php endforeach; ?></nav>
    <?php endif; ?>
    <div class="dj-mix-grid">
      <?php foreach ($mixes as $m):
          $audio = $m['audio'] ? upload_url($m['audio']) : '';
          $emb = !$audio && $m['external_url'] ? mix_embed($m['external_url']) : null;
          $cover = $m['cover'] ? upload_url($m['cover']) : ''; ?>
        <article class="dj-mix" data-src="<?= h($m['source']) ?>"<?= $audio ? ' data-audio="' . h($audio) . '" data-title="' . h($m['title']) . '" data-dj="' . h($m['dj_name']) . '" data-cover="' . h($cover) . '"' : '' ?>>
          <div class="dj-mix-art<?= $cover ? '' : ' no-cover' ?>"<?= $cover ? ' style="background-image:url(\'' . h($cover) . '\')"' : '' ?>>
            <?php if (!$cover): ?><span class="dj-mini-vinyl" aria-hidden="true"></span><?php endif; ?>
            <?php if ($m['featured']): ?><span class="dj-feat">★ Featured</span><?php endif; ?>
            <?php if ($audio): ?><button type="button" class="dj-play" aria-label="Play <?= h($m['title']) ?>"><span aria-hidden="true">▶</span></button>
            <?php elseif ($emb): ?><button type="button" class="dj-play dj-embed-btn" data-embed="<?= h($emb[1]) ?>" aria-label="Play <?= h($m['title']) ?>"><span aria-hidden="true">▶</span></button>
            <?php elseif ($m['external_url']): ?><a class="dj-play" target="_blank" rel="noopener nofollow" href="<?= h($m['external_url']) ?>" aria-label="Listen to <?= h($m['title']) ?>"><span aria-hidden="true">↗</span></a><?php endif; ?>
          </div>
          <div class="dj-mix-body">
            <span class="dj-src dj-src-<?= h($m['source']) ?>"><?= h(DJ_SOURCES[$m['source']] ?? $m['source']) ?></span>
            <h3><?= h($m['title']) ?></h3>
            <p class="dj-by">by <b><?= h($m['dj_name']) ?></b><?= $m['genre'] ? ' · ' . h($m['genre']) : '' ?></p>
            <?php if ($m['description']): ?><p class="dj-desc"><?= h($m['description']) ?></p><?php endif; ?>
            <?php if ($audio && $m['allow_download']): ?><a class="dj-dl" href="<?= h($audio) ?>" download>⬇ Download</a><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="lw-empty"><span aria-hidden="true">🎧</span><p><?= cth('djs.mixes.empty') ?></p></div>
  <?php endif; ?>
</div></section>

<section class="section section-cream"><div class="wrap">
  <div class="section-head center"><span class="eyebrow"><?= cth('djs.roster.eyebrow') ?></span><h2><?= cth('djs.roster.heading') ?></h2><p><?= cth('djs.roster.intro') ?></p></div>
  <?php if ($djs): ?>
    <div class="dj-roster">
      <?php foreach ($djs as $d): ?>
        <article class="dj-card" data-reveal>
          <div class="dj-photo"><?php if ($d['photo']): ?><img src="<?= h(upload_url($d['photo'])) ?>" alt="<?= h($d['name']) ?>" loading="lazy" decoding="async"><?php else: ?><span class="dj-initial" aria-hidden="true"><?= $initial($d['name']) ?></span><?php endif; ?></div>
          <div class="dj-info">
            <h3><?= h($d['name']) ?></h3>
            <?php if ($d['tagline']): ?><p class="dj-tag"><?= h($d['tagline']) ?></p><?php endif; ?>
            <?php if ($d['genres']): ?><p class="dj-genres"><?php foreach (array_filter(array_map('trim', explode(',', $d['genres']))) as $g): ?><span><?= h($g) ?></span><?php endforeach; ?></p><?php endif; ?>
            <?php if ($d['bio']): ?><p class="dj-bio"><?= h(excerpt($d['bio'], 160)) ?></p><?php endif; ?>
            <div class="dj-actions"><button type="button" class="btn btn-primary btn-sm dj-pick" data-dj="<?= (int)$d['id'] ?>">Book <?= h($d['name']) ?></button>
              <?php if ($d['instagram']): ?><a class="btn btn-ghost-dark btn-sm" target="_blank" rel="noopener nofollow" href="<?= h($d['instagram']) ?>">Instagram ↗</a><?php endif; ?></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="lw-empty lw-empty-light"><span aria-hidden="true">🎚️</span><p><?= cth('djs.roster.empty') ?></p><a class="btn btn-primary" href="#book">Request a DJ</a></div>
  <?php endif; ?>
</div></section>

<section class="section section-dark lw-services"><div class="wrap">
  <div class="section-head center"><span class="eyebrow gold"><?= cth('djs.services.eyebrow') ?></span><h2><?= cth('djs.services.heading') ?></h2><p><?= cth('djs.services.intro') ?></p></div>
  <div class="lw-svc-grid">
    <?php foreach (ctrows('djs.services.cards', 4) as [$ic, $t, $dsc, $val]): ?>
      <button type="button" class="lw-svc" data-type="<?= h($val) ?>" data-reveal><span class="lw-svc-ic" aria-hidden="true"><?= h($ic) ?></span><b><?= h($t) ?></b><span><?= h($dsc) ?></span><em>Request this →</em></button>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section section-cream"><div class="wrap">
  <div class="section-head center"><span class="eyebrow">Simple process</span><h2><?= cth('djs.process.heading') ?></h2></div>
  <div class="steps acc-steps">
    <?php foreach (ctrows('djs.process.steps') as $i => [$t, $dsc]): ?><div data-reveal><b><?= $i + 1 ?></b><h4><?= h($t) ?></h4><p><?= h($dsc) ?></p></div><?php endforeach; ?>
  </div>
</div></section>

<section class="section section-dark lw-process lw-book dj-book" id="book"><div class="wrap lw-book-in">
  <div>
    <div class="section-head"><span class="eyebrow gold"><?= cth('djs.booking.eyebrow') ?></span><h2><?= cth('djs.booking.heading') ?></h2><p><?= cth('djs.booking.intro') ?></p></div>
    <form class="form-card" id="djForm" method="post" action="<?= url('djs') ?>"><?= csrf_field() ?><?php honeypot(); ?>
      <fieldset class="lw-types"><legend>What kind of event is it? *</legend>
        <?php foreach (DJ_EVENT_TYPES as $k => $l): ?><label><input type="radio" name="event_type" value="<?= h($k) ?>"<?= $selType === $k ? ' checked' : '' ?>><span><?= h($l) ?></span></label><?php endforeach; ?>
      </fieldset>
      <div class="form-grid">
        <?php field('dj_id', 'Preferred DJ', ['type' => 'select', 'value' => $selDj, 'options' => ['' => 'Any DJ (match me)'] + array_column($djs, 'name', 'id')]);
        field('event_date', 'Event date', ['type' => 'date', 'required' => true, 'attrs' => 'min="' . date('Y-m-d') . '"']);
        field('start_time', 'Start time', ['type' => 'time']);
        field('hours', 'Hours needed', ['type' => 'number', 'attrs' => 'min="1" max="24" placeholder="e.g. 5"']);
        field('location', 'Venue / location', ['required' => true]);
        field('audience', 'Expected guests', ['type' => 'number', 'attrs' => 'min="1"']);
        field('name', 'Your name', ['required' => true]); field('organization', 'Organization (optional)');
        field('phone', 'Telephone', ['required' => true, 'type' => 'tel']); field('email', 'Email', ['type' => 'email']); ?>
      </div>
      <fieldset class="lw-types"><legend>Sound equipment</legend>
        <label><input type="radio" name="sound" value="yes"<?= old('sound') === 'yes' ? ' checked' : '' ?>><span>🔊 I need sound system &amp; lights</span></label>
        <label><input type="radio" name="sound" value="no"<?= old('sound') === 'no' ? ' checked' : '' ?>><span>🏛️ Venue has its own sound</span></label>
      </fieldset>
      <?php field('message', 'Music style, must-play songs, other requirements', ['type' => 'textarea']); ?>
      <button class="btn btn-primary btn-lg"><?= cth('djs.booking.button') ?></button>
    </form>
  </div>
  <aside class="lw-side" data-reveal>
    <h3><?= cth('djs.booking.side_title') ?></h3>
    <p><?= cth('djs.booking.side_text') ?></p>
    <?php if ($telHref): ?><a class="lw-contact" href="<?= h($telHref) ?>"><span aria-hidden="true">📞</span><span><small>Call</small><b><?= h($phone) ?></b></span></a><?php endif; ?>
    <?php if ($waHref): ?><a class="lw-contact lw-wa" target="_blank" rel="noopener" href="<?= h($waHref) ?>"><span aria-hidden="true">💬</span><span><small>WhatsApp</small><b>Chat with us</b></span></a><?php endif; ?>
    <ul class="ticks"><li>Professional DJs for any occasion</li><li>Sound system and lights available</li><li>Gulu-based, available across Uganda</li></ul>
  </aside>
</div></section>

<section class="section section-cream"><div class="narrow-wrap">
  <div class="section-head center"><span class="eyebrow">FAQ</span><h2><?= cth('djs.faq.heading') ?></h2></div>
  <div class="ab-faq">
    <?php foreach (ctrows('djs.faq.items') as [$qq, $aa]): ?><details><summary><?= h($qq) ?></summary><p><?= h($aa) ?></p></details><?php endforeach; ?>
  </div>
</div></section>

<div class="dj-player" id="djPlayer" hidden role="region" aria-label="Mix player">
  <span class="dj-pl-art" id="djPlArt" aria-hidden="true"></span>
  <span class="dj-pl-meta"><b id="djPlTitle"></b><small id="djPlDj"></small></span>
  <button type="button" class="dj-pl-btn" id="djPrev" aria-label="Previous mix">⏮</button>
  <button type="button" class="dj-pl-btn dj-pl-main" id="djToggle" aria-label="Play or pause">▶</button>
  <button type="button" class="dj-pl-btn" id="djNext" aria-label="Next mix">⏭</button>
  <span class="dj-pl-time" id="djCur">0:00</span>
  <input type="range" id="djSeek" min="0" max="1000" value="0" aria-label="Seek">
  <span class="dj-pl-time" id="djDur">0:00</span>
  <button type="button" class="dj-pl-btn dj-pl-close" id="djClose" aria-label="Close player">✕</button>
  <audio id="djAudio" preload="none"></audio>
</div>

<script>
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var form = $('#djForm');
  function toForm(sel) { var el = form && form.querySelector(sel); return el; }
  function jump() { var b = $('#book'); if (b) b.scrollIntoView({ behavior: 'smooth' }); var n = toForm('input[name="event_date"]'); if (n) setTimeout(function () { n.focus({ preventScroll: true }); }, 600); }
  $$('.lw-svc').forEach(function (c) { c.addEventListener('click', function () { var r = toForm('input[name="event_type"][value="' + c.dataset.type + '"]'); if (r) r.checked = true; jump(); }); });
  $$('.dj-pick').forEach(function (c) { c.addEventListener('click', function () { var s = toForm('select[name="dj_id"]'); if (s) s.value = c.dataset.dj; jump(); }); });

  // Filter mixes
  var chips = $$('.lw-chips button');
  chips.forEach(function (b) { b.addEventListener('click', function () {
    chips.forEach(function (x) { x.classList.toggle('on', x === b); });
    $$('.dj-mix').forEach(function (m) { m.hidden = b.dataset.src !== 'all' && m.dataset.src !== b.dataset.src; });
  }); });

  // Embedded mixes (SoundCloud, Mixcloud, YouTube) load only when played
  $$('.dj-embed-btn').forEach(function (b) { b.addEventListener('click', function () {
    var art = b.parentNode, f = document.createElement('iframe');
    f.src = b.dataset.embed; f.allow = 'autoplay; encrypted-media; picture-in-picture'; f.loading = 'lazy'; f.title = b.getAttribute('aria-label');
    art.classList.add('is-embed'); art.appendChild(f); b.remove();
    if (a && !a.paused) a.pause();
  }); });

  // Audio player
  var a = $('#djAudio'), bar = $('#djPlayer'), seek = $('#djSeek'), tg = $('#djToggle'), cur = null;
  if (!a) return;
  var fmt = function (s) { if (!isFinite(s)) return '0:00'; var m = Math.floor(s / 60), x = Math.floor(s % 60); return m + ':' + (x < 10 ? '0' : '') + x; };
  var list = function () { return $$('.dj-mix[data-audio]').filter(function (m) { return !m.hidden; }); };
  function mark() { $$('.dj-mix').forEach(function (m) { m.classList.toggle('is-playing', m === cur && !a.paused); }); tg.textContent = a.paused ? '▶' : '❚❚'; }
  function load(m, play) {
    cur = m; a.src = m.dataset.audio; $('#djPlTitle').textContent = m.dataset.title; $('#djPlDj').textContent = 'by ' + m.dataset.dj;
    var art = $('#djPlArt'); art.style.backgroundImage = m.dataset.cover ? 'url("' + m.dataset.cover + '")' : ''; art.classList.toggle('no-cover', !m.dataset.cover);
    bar.hidden = false; document.body.classList.add('has-player');
    if (play) a.play().catch(function () {}); mark();
  }
  function step(d) { var l = list(); if (!l.length) return; var i = l.indexOf(cur); load(l[(i + d + l.length) % l.length], true); }
  $$('.dj-mix[data-audio] .dj-play').forEach(function (b) { b.addEventListener('click', function () {
    var m = b.closest('.dj-mix'); if (m === cur) { a.paused ? a.play() : a.pause(); } else load(m, true);
  }); });
  tg.addEventListener('click', function () { a.paused ? a.play() : a.pause(); });
  $('#djPrev').addEventListener('click', function () { step(-1); }); $('#djNext').addEventListener('click', function () { step(1); });
  $('#djClose').addEventListener('click', function () { a.pause(); bar.hidden = true; document.body.classList.remove('has-player'); mark(); });
  a.addEventListener('play', mark); a.addEventListener('pause', mark); a.addEventListener('ended', function () { step(1); });
  a.addEventListener('loadedmetadata', function () { $('#djDur').textContent = fmt(a.duration); });
  a.addEventListener('timeupdate', function () { $('#djCur').textContent = fmt(a.currentTime); if (a.duration) seek.value = Math.round(a.currentTime / a.duration * 1000); });
  seek.addEventListener('input', function () { if (a.duration) a.currentTime = seek.value / 1000 * a.duration; });
  document.addEventListener('keydown', function (e) { if (e.code === 'Space' && !bar.hidden && !/INPUT|TEXTAREA|SELECT|BUTTON|SUMMARY/.test((document.activeElement || {}).tagName || '')) { e.preventDefault(); a.paused ? a.play() : a.pause(); } });
})();
</script>
