<section class="section section-cream ticket-wrap"><div class="narrow-wrap">
<?php if (!$valid): ?>
  <div class="form-card center">
    <h1 style="font-size:1.8rem">Ticket not available</h1>
    <p>Booking <strong><?= h($r['reference']) ?></strong> is currently <strong><?= h($r['status']) ?></strong>, so no ticket is active.
      <?= $r['status'] === 'pending' ? 'You will receive your ticket as soon as the event team approves it.' : 'Please contact us if you think this is a mistake.' ?></p>
    <p><a class="btn btn-primary" href="<?= url('booking/' . h($r['reference'])) ?>">View booking status</a></p>
  </div>
<?php else: ?>
  <article class="ticket" aria-label="Event ticket">
    <header class="ticket-head">
      <img class="ticket-logo" src="<?= h(asset('img/logo.png')) ?>" alt="Chill &amp; Bite MEATINGS" width="320" height="253">
      <div><p class="eyebrow gold">Admit <?= (int)$r['guests'] ?> guest<?= (int)$r['guests'] === 1 ? '' : 's' ?></p><h1><?= h($r['title']) ?></h1></div>
    </header>
    <div class="ticket-body">
      <dl class="ticket-info">
        <div><dt>Date</dt><dd><?= fmt_date($r['start_at'], 'l, j F Y') ?></dd></div>
        <div><dt>Gates open</dt><dd><?= fmt_time($r['start_at']) ?></dd></div>
        <div><dt>Venue</dt><dd><?= h($r['venue']) ?>, <?= h($r['location']) ?></dd></div>
        <div><dt>Guest</dt><dd><?= h($r['full_name']) ?><?= $r['company'] ? ' · ' . h($r['company']) : '' ?></dd></div>
        <div><dt>Reservation</dt><dd><?= h(RES_TYPES[$r['type']]) ?><?= $r['package'] ? ' · ' . h($r['package']) . ' package' : '' ?></dd></div>
        <div><dt>Reference</dt><dd class="ticket-ref"><?= h($r['reference']) ?></dd></div>
      </dl>
      <div class="ticket-qr">
        <div id="qr" data-url="<?= h($checkin_url) ?>" role="img" aria-label="QR code for entry"></div>
        <?= $r['checked_in_at'] ? '<span class="badge badge-warn">Checked in ' . h(fmt_time($r['checked_in_at'])) . '</span>' : '<span class="badge badge-ok">Valid</span>' ?>
      </div>
    </div>
    <div class="ticket-tear" aria-hidden="true"></div>
    <footer class="ticket-foot">
      <p>Show this QR code at the entrance. One ticket admits the number of guests shown above. Please arrive with your reference <strong><?= h($r['reference']) ?></strong> if the code cannot be scanned.</p>
      <p class="small">Chill &amp; Bite MEATINGS · Organized by Owakabi &amp; The Geng</p>
    </footer>
  </article>
  <div class="btn-row center-row">
    <button class="btn btn-primary" type="button" id="dl-ticket">⬇ Download ticket (image)</button>
    <button class="btn btn-outline-dark" type="button" onclick="window.print()">Print / Save as PDF</button>
    <a class="btn btn-ghost-dark" target="_blank" rel="noopener" href="<?= h(gcal_link($r)) ?>">Add to calendar</a>
    <a class="btn btn-ghost-dark" href="<?= url('booking/' . h($r['reference'])) ?>">My booking</a>
  </div>
  <p class="center small muted">Tip: save the image to your phone so you can show it at the gate even without internet.</p>
  <script type="application/json" id="ticket-data"><?= json_encode([
      'ref' => $r['reference'], 'title' => $r['title'], 'guests' => (int)$r['guests'],
      'date' => fmt_date($r['start_at'], 'l, j F Y'), 'time' => fmt_time($r['start_at']),
      'venue' => $r['venue'] . ', ' . $r['location'], 'name' => $r['full_name'] . ($r['company'] ? ' · ' . $r['company'] : ''),
      'type' => RES_TYPES[$r['type']] . ($r['package'] ? ' · ' . $r['package'] . ' package' : ''),
      'logo' => asset('img/logo.png'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script src="<?= h(asset('js/vendor/qrcode.min.js')) ?>"></script>
  <script>
  (function () {
    var el = document.getElementById('qr');
    if (!el || !window.QRCode) return;
    var d = JSON.parse(document.getElementById('ticket-data').textContent);
    new QRCode(el, { text: el.dataset.url, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });

    // Draw the ticket onto a canvas so it can be saved as a PNG (works offline once downloaded)
    var logo = new Image(); logo.src = d.logo;
    var logoReady = new Promise(function (res) { logo.onload = res; logo.onerror = res; });
    function roundRect(c, x, y, w, h, r) { c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath(); }
    function wrap(c, text, x, y, maxW, lh, maxLines) {
      var words = String(text).split(/\s+/), line = '', lines = [];
      words.forEach(function (w) { var t = line ? line + ' ' + w : w; if (c.measureText(t).width > maxW && line) { lines.push(line); line = w; } else line = t; });
      if (line) lines.push(line);
      lines.slice(0, maxLines).forEach(function (l, i) { c.fillText(l, x, y + i * lh); });
      return y + Math.min(lines.length, maxLines) * lh;
    }
    function build() {
      var holder = document.createElement('div');
      new QRCode(holder, { text: el.dataset.url, width: 560, height: 560, correctLevel: QRCode.CorrectLevel.M });
      var qr = holder.querySelector('canvas');
      var fonts = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
      return Promise.all([logoReady, fonts]).then(function () {
        var W = 1080, H = 1660, cv = document.createElement('canvas'); cv.width = W; cv.height = H;
        var c = cv.getContext('2d'), F = 'Poppins, "Segoe UI", Arial, sans-serif';
        c.fillStyle = '#ffffff'; c.fillRect(0, 0, W, H);
        var g = c.createLinearGradient(0, 0, W, 300); g.addColorStop(0, '#171717'); g.addColorStop(1, '#3a2210');
        c.fillStyle = g; c.fillRect(0, 0, W, 300); c.fillStyle = '#D96B27'; c.fillRect(0, 300, W, 10);
        if (logo.naturalWidth) { var lh = 210, lw = lh * logo.naturalWidth / logo.naturalHeight; c.drawImage(logo, 48, 45, lw, lh); }
        c.textBaseline = 'alphabetic';
        c.fillStyle = '#E6B65C'; c.font = '700 26px ' + F;
        c.fillText('ADMIT ' + d.guests + (d.guests === 1 ? ' GUEST' : ' GUESTS'), 360, 100);
        c.fillStyle = '#FFF7EB'; c.font = '700 40px ' + F; wrap(c, d.title, 360, 158, 670, 52, 3);
        function field(label, value, x, y, maxW, lines) {
          c.fillStyle = '#8a8378'; c.font = '600 22px ' + F; c.fillText(label.toUpperCase(), x, y);
          c.fillStyle = '#1d1b19'; c.font = '700 34px ' + F; return wrap(c, value, x, y + 44, maxW, 44, lines || 1);
        }
        field('Date', d.date, 60, 400, 470); field('Gates open', d.time, 580, 400, 440);
        field('Venue', d.venue, 60, 520, 960, 2);
        field('Guest', d.name, 60, 660, 470, 2); field('Reservation', d.type, 580, 660, 440, 2);
        c.strokeStyle = '#d8cfc0'; c.lineWidth = 4; c.setLineDash([14, 12]); c.beginPath(); c.moveTo(40, 800); c.lineTo(W - 40, 800); c.stroke(); c.setLineDash([]);
        roundRect(c, 236, 840, 608, 608, 28); c.fillStyle = '#fff'; c.fill(); c.lineWidth = 6; c.strokeStyle = '#171717'; c.stroke();
        c.imageSmoothingEnabled = false; c.drawImage(qr, 260, 864, 560, 560); c.imageSmoothingEnabled = true;
        c.textAlign = 'center'; c.fillStyle = '#8a8378'; c.font = '600 24px ' + F; c.fillText('REFERENCE', W / 2, 1500);
        c.fillStyle = '#b9561b'; c.font = '700 64px "Courier New", monospace'; c.fillText(d.ref, W / 2, 1572);
        c.fillStyle = '#6b6258'; c.font = '500 22px ' + F; c.fillText('Show this QR code at the entrance · Chill & Bite MEATINGS', W / 2, 1626);
        return cv;
      });
    }
    function download() {
      build().then(function (cv) {
        cv.toBlob(function (b) {
          var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = 'ticket-' + d.ref + '.png';
          document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
        }, 'image/png');
      });
    }
    var btn = document.getElementById('dl-ticket'); if (btn) btn.addEventListener('click', download);
    if (/[?&]download=1/.test(location.search)) setTimeout(download, 700);   // used by the "Download" link on the booking page
  })();
  </script>
<?php endif; ?>
</div></section>
