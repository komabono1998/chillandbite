// Quick-edit helpers for signed-in staff: a pencil appears on any picture they can change.
(function () {
  var map = window.QE_MAP || {};
  var bar = document.getElementById('qeBar'), toggle = document.getElementById('qeToggle');
  var pencils = [], all = false;

  function key(img) {
    try { return new URL(img.currentSrc || img.src, location.href).pathname; } catch (e) { return ''; }
  }
  function build() {
    document.querySelectorAll('img').forEach(function (img) {
      if (img.closest('.qe-bar,.qe-pencil') || img.classList.contains('qe-seen')) return;
      var hit = map[key(img)];
      if (!hit) return;
      img.classList.add('qe-seen');
      var a = document.createElement('a');
      a.className = 'qe-pencil'; a.href = hit[1]; a.target = '_blank'; a.rel = 'noopener';
      a.title = hit[0]; a.setAttribute('aria-label', hit[0]); a.innerHTML = '<span aria-hidden="true">✏️</span><b>' + hit[0] + '</b>';
      document.body.appendChild(a);
      pencils.push({ img: img, a: a });
      img.addEventListener('mouseenter', function () { a.classList.add('on'); place(); });
      img.addEventListener('mouseleave', function () { setTimeout(function () { if (!a.matches(':hover') && !all) a.classList.remove('on'); }, 250); });
      a.addEventListener('mouseleave', function () { if (!all) a.classList.remove('on'); });
    });
  }
  function place() {
    var sx = window.scrollX, sy = window.scrollY;
    pencils.forEach(function (p) {
      var r = p.img.getBoundingClientRect();
      var visible = r.width > 40 && r.height > 40 && r.bottom > 0 && r.top < innerHeight && r.right > 0 && r.left < innerWidth && getComputedStyle(p.img).visibility !== 'hidden';
      p.a.style.display = visible ? '' : 'none';
      if (!visible) return;
      p.a.style.top = (r.top + sy + 8) + 'px';
      p.a.style.left = Math.max(sx + 4, Math.min(r.right + sx - p.a.offsetWidth - 8, sx + innerWidth - p.a.offsetWidth - 4)) + 'px';
    });
  }
  var queued = false;
  function schedule() { if (queued) return; queued = true; requestAnimationFrame(function () { queued = false; place(); }); }

  if (toggle) toggle.addEventListener('click', function () {
    all = !all; toggle.setAttribute('aria-pressed', all ? 'true' : 'false');
    toggle.textContent = all ? '✏️ Hide picture pencils' : '✏️ Show picture pencils';
    document.body.classList.toggle('qe-all', all);
    pencils.forEach(function (p) { p.a.classList.toggle('on', all); });
    build(); place();
  });
  window.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule);
  window.addEventListener('load', function () { build(); place(); });
  document.addEventListener('DOMContentLoaded', function () { build(); place(); });
  setInterval(function () { build(); place(); }, 1500);   // lazy-loaded and animated pictures
})();
