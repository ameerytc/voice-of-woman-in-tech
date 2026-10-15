(function () {
  var s = document.currentScript, url = s && s.getAttribute('data-pulse');
  // question editor: show the right answer inputs
  var type = document.getElementById('type');
  if (type) {
    var sync = function () { var tf = type.value === 'tf'; document.getElementById('mcq-box').style.display = tf ? 'none' : 'block'; document.getElementById('tf-box').style.display = tf ? 'block' : 'none'; };
    type.addEventListener('change', sync); sync();
  }
  // live badge: new submissions and pending reviews, refreshed every 30 s
  if (!url) return;
  function setBadge(id, n, parent) {
    var el = document.getElementById(id);
    if (!el && n > 0) { var a = document.querySelector('a[href$="' + parent + '"]'); if (a) { el = document.createElement('span'); el.id = id; el.className = 'count' + (id === 'pulse-pending' ? ' warn' : ''); a.appendChild(el); } }
    if (el) { el.textContent = n; el.style.display = n > 0 ? '' : 'none'; }
  }
  setInterval(function () {
    if (document.hidden) return;
    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d) return; setBadge('pulse-new', d.new, '/admin/submissions'); setBadge('pulse-pending', d.pending, '/admin/verification');
    }).catch(function () {});
  }, 30000);
})();
