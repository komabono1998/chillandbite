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
    var base = cd.textContent;
    var tick = function () {
      var diff = target - Date.now();
      if (diff <= 0) { cd.textContent = base; return; }
      var d = Math.floor(diff / 864e5), h = Math.floor(diff % 864e5 / 36e5), m = Math.floor(diff % 36e5 / 6e4);
      cd.textContent = base + ' · ' + d + 'd ' + h + 'h ' + m + 'm';
    };
    tick(); setInterval(tick, 60000);
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
      total.textContent = price > 0 ? 'Total: UGX ' + (price * guests.value).toLocaleString() : 'Free reservation';
    };
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
