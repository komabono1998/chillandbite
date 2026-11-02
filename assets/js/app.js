(function () {
  'use strict';

  // Mobile navigation
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Cookie notice (essential cookies only; remembers dismissal locally)
  var cookie = document.getElementById('cookie');
  try {
    if (cookie && !localStorage.getItem('cb_cookie')) cookie.hidden = false;
    var ok = document.getElementById('cookie-ok');
    if (ok) ok.addEventListener('click', function () { localStorage.setItem('cb_cookie', '1'); cookie.hidden = true; });
  } catch (e) { if (cookie) cookie.hidden = true; }

  // Click-to-load YouTube (privacy friendly: nothing loads until played)
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.video-play');
    if (!btn) return;
    var f = document.createElement('iframe');
    f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(btn.dataset.yt) + '?autoplay=1&rel=0';
    f.allow = 'autoplay; encrypted-media; picture-in-picture';
    f.allowFullscreen = true;
    f.title = btn.getAttribute('aria-label') || 'Video';
    btn.replaceWith(f);
  });

  // Countdown to the next event
  var cd = document.getElementById('countdown');
  if (cd && cd.dataset.target) {
    var target = new Date(cd.dataset.target).getTime();
    var cells = {};
    cd.querySelectorAll('[data-u]').forEach(function (el) { cells[el.dataset.u] = el; });
    var note = document.getElementById('live-note');
    var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
    var tick = function () {
      var diff = target - Date.now();
      if (diff <= 0) {
        cd.hidden = true;
        if (note) note.hidden = false;
        return false;
      }
      cells.d.textContent = pad(Math.floor(diff / 864e5));
      cells.h.textContent = pad(Math.floor(diff % 864e5 / 36e5));
      cells.m.textContent = pad(Math.floor(diff % 36e5 / 6e4));
      cells.s.textContent = pad(Math.floor(diff % 6e4 / 1e3));
      return true;
    };
    if (tick()) { var timer = setInterval(function () { if (!tick()) clearInterval(timer); }, 1000); }
  }

  // Reservation form: guest limits, corporate field, live total
  var form = document.getElementById('reserve-form');
  if (form) {
    var guests = form.querySelector('[name=guests]');
    var total = document.getElementById('total');
    var corp = form.querySelector('.corp');
    var update = function () {
      var sel = form.querySelector('[name=type]:checked');
      if (corp) corp.style.display = sel && sel.value === 'corporate' ? '' : 'none';
      if (!sel) { total.textContent = ''; return; }
      var min = +sel.dataset.min, max = +sel.dataset.max, price = +sel.dataset.price;
      guests.min = min; guests.max = max;
      if (+guests.value < min) guests.value = min;
      if (+guests.value > max) guests.value = max;
      if (min === max) guests.value = min;
      guests.readOnly = (min === max);
      var pkgBox = document.getElementById('pkg-box');
      if (pkgBox) {
        var isTable = sel.value === 'table';
        pkgBox.classList.toggle('dim', !isTable);
        if (isTable) {
          var pkg = pkgBox.querySelector('[name=package]:checked');
          total.textContent = pkg ? 'Table package total: UGX ' + (+pkg.dataset.price).toLocaleString() : 'Select a table package';
          return;
        }
      }
      total.textContent = price > 0 ? 'Total: UGX ' + (price * guests.value).toLocaleString() : 'Free reservation';
    };
    // Picking a package selects the Table Reservation type; picking another type clears the package
    form.addEventListener('change', function (ev) {
      var t = ev.target;
      if (t.name === 'package') {
        var tbl = form.querySelector('[name=type][value=table]');
        if (tbl) tbl.checked = true;
      } else if (t.name === 'type' && t.value !== 'table') {
        form.querySelectorAll('[name=package]').forEach(function (r) { r.checked = false; });
      }
    });
    form.addEventListener('change', update); form.addEventListener('input', update); update();
  }

  // Scroll reveal
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { threshold: .12 });
    document.querySelectorAll('.card, .featured-event, .host, .info-panel').forEach(function (el) { el.classList.add('reveal'); io.observe(el); });
  }
})();

// Photo lightbox for any link marked data-lightbox (strips, mosaic, galleries)
(function () {
  'use strict';
  var items = [], idx = 0, box;
  function show() {
    box.querySelector('img').src = items[idx].href;
  }
  function close() { if (box) { box.remove(); box = null; document.removeEventListener('keydown', key); } }
  function step(d) { idx = (idx + d + items.length) % items.length; show(); }
  function key(e) { if (e.key === 'Escape') close(); else if (e.key === 'ArrowRight') step(1); else if (e.key === 'ArrowLeft') step(-1); }
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('a[data-lightbox]');
    if (!a) return;
    ev.preventDefault();
    var scope = a.closest('.strip, .mosaic, .gallery-grid, .flyer') || document;
    items = Array.prototype.slice.call(scope.querySelectorAll('a[data-lightbox]'));
    idx = items.indexOf(a);
    box = document.createElement('div');
    box.className = 'lb'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', 'Photo viewer');
    box.innerHTML = '<img alt=""><button class="x" aria-label="Close">&times;</button><button class="p" aria-label="Previous">&#8249;</button><button class="n" aria-label="Next">&#8250;</button>';
    box.addEventListener('click', function (e) {
      if (e.target.classList.contains('p')) step(-1); else if (e.target.classList.contains('n')) step(1);
      else if (e.target.tagName !== 'IMG') close();
    });
    document.body.appendChild(box); show();
    document.addEventListener('keydown', key);
  });
})();
