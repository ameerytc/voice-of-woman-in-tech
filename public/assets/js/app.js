(function () {
  var d = document;
  d.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toggle]');
    if (t) { var el = d.querySelector(t.getAttribute('data-toggle')); if (el) { var o = el.classList.toggle('open'); t.setAttribute('aria-expanded', o ? 'true' : 'false'); } }
    if (e.target.closest('[data-print]')) window.print();
    var th = e.target.closest('[data-theme-toggle]');
    if (th) { var cur = d.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'; d.documentElement.setAttribute('data-theme', cur); try { localStorage.setItem('vw-theme', cur); } catch (x) {} }
    var tab = e.target.closest('[role=tab]');
    if (tab) { var list = tab.closest('[role=tablist]'); list.querySelectorAll('[role=tab]').forEach(function (b) { var on = b === tab; b.setAttribute('aria-selected', on ? 'true' : 'false'); var p = d.getElementById(b.getAttribute('aria-controls')); if (p) p.hidden = !on; }); }
    var dd = d.querySelectorAll('details.dd[open]'); dd.forEach(function (x) { if (!x.contains(e.target)) x.removeAttribute('open'); });
  });
  // confirmation dialogs: forms/buttons with data-confirm, and selects with data-confirm-values="closed,archived"
  d.addEventListener('submit', function (e) {
    var f = e.target, m = f.getAttribute('data-confirm') || (e.submitter && e.submitter.getAttribute('data-confirm'));
    if (!m) { var s = f.querySelector('select[data-confirm-values]'); if (s && s.getAttribute('data-confirm-values').split(',').indexOf(s.value) > -1) m = 'Change this quiz to "' + s.value + '"? Participants will be affected.'; }
    if (m && !window.confirm(m)) e.preventDefault();
  });
  // fade-in on scroll (CSS-only fallback when IntersectionObserver is missing)
  var rv = d.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) { var io = new IntersectionObserver(function (es) { es.forEach(function (x) { if (x.isIntersecting) { x.target.classList.add('in'); io.unobserve(x.target); } }); }, { threshold: .08 }); rv.forEach(function (n) { io.observe(n); }); }
  else rv.forEach(function (n) { n.classList.add('in'); });
  // live filter for tables/lists: <input data-filter="#id"> filters rows[data-s]
  d.querySelectorAll('input[data-filter]').forEach(function (inp) {
    var sel = d.querySelector(inp.getAttribute('data-filter')); if (!sel) return;
    var sel2 = d.querySelector(inp.getAttribute('data-filter-select') || '#none');
    function run() { var q = inp.value.toLowerCase().trim(), g = sel2 ? sel2.value : ''; sel.querySelectorAll('[data-s]').forEach(function (r) { var ok = (!q || r.getAttribute('data-s').indexOf(q) > -1) && (!g || r.getAttribute('data-g') === g); r.hidden = !ok; }); }
    inp.addEventListener('input', run); if (sel2) sel2.addEventListener('change', run);
  });
  // profile picture: instant preview, and auto-upload from the dashboard camera button
  d.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0]; if (!f) return;
      if (f.size > 3145728) { alert('Please choose a picture smaller than 3 MB.'); inp.value = ''; return; }
      var img = d.querySelector(inp.getAttribute('data-preview'));
      if (img && img.tagName === 'IMG') img.src = URL.createObjectURL(f);
      else if (img) { var n = d.createElement('img'); n.className = img.className.replace('avatar-ini', ''); n.width = img.offsetWidth; n.height = img.offsetHeight; n.alt = ''; n.src = URL.createObjectURL(f); img.replaceWith(n); n.id = img.id; }
      if (inp.hasAttribute('data-autosubmit')) inp.form.submit();
    });
  });
})();
