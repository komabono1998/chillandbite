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
    var scope = a.closest('.strip, .mosaic, .gallery-grid, .flyer, .wall') || document;
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

// Staggered entrance for tile groups marked data-stagger (food mosaic)
(function () {
  'use strict';
  if (!('IntersectionObserver' in window)) return;
  document.querySelectorAll('[data-stagger]').forEach(function (group) {
    group.classList.add('will');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting) { group.classList.add('in'); io.disconnect(); } });
    }, { threshold: 0.2 });
    io.observe(group);
  });
})();

// Company profile effects: count-up numbers, scroll-spy sub menu, generic reveal-on-scroll for [data-reveal]
(function () {
  'use strict';
  if (!('IntersectionObserver' in window)) return;

  document.querySelectorAll('[data-count]').forEach(function (el) {
    var end = parseInt(el.dataset.count, 10), final = el.textContent;
    if (isNaN(end)) return;
    el.textContent = '0';
    var io = new IntersectionObserver(function (entries) {
      if (!entries[0].isIntersecting) return;
      io.disconnect();
      var t0 = null, dur = 1400;
      (function tick(t) {
        if (t0 === null) t0 = t;
        var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
        el.textContent = p < 1 ? Math.round(end * e) : final;
        if (p < 1) requestAnimationFrame(tick);
      })(performance.now());
    }, { threshold: 0.6 });
    io.observe(el);
  });

  var links = [].slice.call(document.querySelectorAll('.ab-subnav a[href^="#"]'));
  if (links.length) {
    var byId = {};
    links.forEach(function (a) { var s = document.querySelector(a.getAttribute('href')); if (s) byId[s.id] = a; });
    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        links.forEach(function (l) { l.classList.remove('on'); });
        byId[e.target.id].classList.add('on');
        var bar = byId[e.target.id].closest('.ab-subnav');                  // keep the active chip visible on narrow screens
        if (bar && bar.scrollWidth > bar.clientWidth) bar.scrollTo({ left: byId[e.target.id].offsetLeft - 20, behavior: 'smooth' });
      });
    }, { rootMargin: '-40% 0px -55% 0px' });
    Object.keys(byId).forEach(function (id) { spy.observe(document.getElementById(id)); });
  }

  var rv = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); rv.unobserve(e.target); } });
  }, { threshold: 0.12 });
  document.querySelectorAll('[data-reveal]').forEach(function (el) { el.classList.add('reveal'); rv.observe(el); });
})();
