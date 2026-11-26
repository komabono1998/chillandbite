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

  // Announcement bar timer (every page)
  var bt = document.getElementById('barTimer');
  if (bt && bt.dataset.target) {
    var bTarget = new Date(bt.dataset.target).getTime(), bc = {}, bp = function (n) { return n < 10 ? '0' + n : '' + n; };
    bt.querySelectorAll('[data-u]').forEach(function (el) { bc[el.dataset.u] = el; });
    var bTick = function () {
      var diff = bTarget - Date.now();
      if (diff <= 0) { bt.classList.add('is-live'); bt.textContent = 'Happening now'; return false; }
      bc.d.textContent = bp(Math.floor(diff / 864e5)); bc.h.textContent = bp(Math.floor(diff % 864e5 / 36e5));
      bc.m.textContent = bp(Math.floor(diff % 36e5 / 6e4)); bc.s.textContent = bp(Math.floor(diff % 6e4 / 1e3));
      return true;
    };
    if (bTick()) { var bTimer = setInterval(function () { if (!bTick()) clearInterval(bTimer); }, 1000); }
  }

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
      var gField = guests.closest('.field'), isPkg = !!pkgBox && sel.value === 'table';
      var needCount = !isPkg && price > 0 && min < max;       // the guest number is only shown when it sets the price
      if (gField) gField.style.display = needCount ? 'block' : 'none';
      guests.required = needCount;
      if (!needCount) guests.value = min;
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
    var scope = a.closest('.strip, .mosaic, .gallery-grid, .flyer, .wall, .cf-viewport') || document;
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

// A picture that cannot load (for example a file lost from the server) is hidden, never shown as a broken box
document.addEventListener('error', function (e) {
  var t = e.target;
  if (!t || t.tagName !== 'IMG' || t.dataset.broken) return;
  t.dataset.broken = '1';
  var box = t.closest('figure, .wall-item, .mosaic-item');
  if (box && box.tagName !== 'ARTICLE') box.style.display = 'none'; else t.style.visibility = 'hidden';
}, true);
window.addEventListener('load', function () { document.querySelectorAll('img').forEach(function (i) { if (i.complete && i.naturalWidth === 0 && i.getAttribute('src')) i.dispatchEvent(new Event('error')); }); });

// Running headline advert: 3D flyer carousel (auto-play, swipe, arrows, keyboard, name chips, share and download)
(function () {
  'use strict';
  var root = document.querySelector('[data-cf]'); if (!root) return;
  var vp = root.querySelector('.cf-viewport'), slides = [].slice.call(vp.querySelectorAll('.cf-slide'));
  var chips = [].slice.call(document.querySelectorAll('.cf-chips button')), n = slides.length; if (!n) return;
  var title = document.querySelector('.cf-title'), sub = document.querySelector('.cf-sub'), bar = document.querySelector('.cf-progress i');
  var share = document.querySelector('.cf-share'), dl = document.querySelector('.cf-dl');
  var active = 0, timer = null, paused = false, interval = parseInt(root.dataset.interval, 10) || 4500;
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  function layout() {
    var narrow = vp.clientWidth < 640, step = narrow ? 0.52 : 0.68;
    slides.forEach(function (s, i) {
      var o = i - active; if (o > n / 2) o -= n; if (o < -n / 2) o += n;
      var a = Math.abs(o), hide = a > 2;
      s.style.transform = 'translateX(calc(-50% + ' + (o * step * 100) + '%)) translateZ(' + (-a * 90) + 'px) rotateY(' + (-o * 24) + 'deg) scale(' + (1 - a * 0.14) + ')';
      s.style.zIndex = String(10 - a); s.style.opacity = hide ? '0' : String(1 - a * 0.28); s.style.pointerEvents = hide ? 'none' : 'auto';
      s.classList.toggle('is-active', o === 0); s.tabIndex = o === 0 ? 0 : -1;
    });
    chips.forEach(function (c, i) { c.classList.toggle('on', i === active); c.setAttribute('aria-selected', i === active ? 'true' : 'false'); });
    var cur = slides[active];
    title.textContent = cur.dataset.name || ''; sub.textContent = cur.dataset.sub || '';
    var txt = 'Chill & Bite MEATINGS: ' + (cur.dataset.name || '') + '. Sunday 29 November, Lavington Park, Lira, 12 PM. ' + (share ? share.dataset.shareUrl : '');
    if (share) share.href = 'https://wa.me/?text=' + encodeURIComponent(txt);
    if (dl) dl.href = dl.dataset.dl + cur.dataset.id;
    var strip = chips[active]; if (strip && strip.parentNode.scrollTo) strip.parentNode.scrollTo({ left: strip.offsetLeft - strip.parentNode.clientWidth / 2 + strip.clientWidth / 2, behavior: reduce ? 'auto' : 'smooth' });
  }
  function go(i) { active = (i + n) % n; layout(); restart(); }
  function restart() {
    clearTimeout(timer); if (bar) { bar.style.transition = 'none'; bar.style.width = '0'; }
    if (reduce || paused) return;
    requestAnimationFrame(function () { requestAnimationFrame(function () { if (bar) { bar.style.transition = 'width ' + interval + 'ms linear'; bar.style.width = '100%'; } }); });
    timer = setTimeout(function () { go(active + 1); }, interval);
  }
  function pause(p) { paused = p; if (p) { clearTimeout(timer); if (bar) { bar.style.transition = 'none'; bar.style.width = '0'; } } else restart(); }
  root.querySelector('.cf-prev').addEventListener('click', function () { go(active - 1); });
  root.querySelector('.cf-next').addEventListener('click', function () { go(active + 1); });
  chips.forEach(function (c, i) { c.addEventListener('click', function () { go(i); }); });
  slides.forEach(function (s, i) { s.addEventListener('click', function (e) { if (dragged) { e.preventDefault(); e.stopPropagation(); return; } if (i !== active) { e.preventDefault(); e.stopPropagation(); go(i); } }); });
  vp.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') { go(active + 1); e.preventDefault(); } else if (e.key === 'ArrowLeft') { go(active - 1); e.preventDefault(); } });
  // swipe / drag
  var x0 = null, dragged = false;
  vp.addEventListener('pointerdown', function (e) { x0 = e.clientX; dragged = false; pause(true); });
  vp.addEventListener('pointermove', function (e) { if (x0 !== null && Math.abs(e.clientX - x0) > 12) dragged = true; });
  function end(e) { if (x0 === null) return; var dx = e.clientX - x0; x0 = null; if (Math.abs(dx) > 40) go(active + (dx < 0 ? 1 : -1)); setTimeout(function () { dragged = false; }, 50); pause(false); }
  vp.addEventListener('pointerup', end); vp.addEventListener('pointercancel', function () { x0 = null; pause(false); });
  root.addEventListener('mouseenter', function () { pause(true); }); root.addEventListener('mouseleave', function () { pause(false); });
  root.addEventListener('focusin', function () { pause(true); }); root.addEventListener('focusout', function () { pause(false); });
  document.addEventListener('visibilitychange', function () { pause(document.hidden); });
  window.addEventListener('resize', layout);
  layout(); restart();
})();
