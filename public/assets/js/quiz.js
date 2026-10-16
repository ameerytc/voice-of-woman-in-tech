(function () {
  var D = JSON.parse(document.getElementById('quiz-data').textContent);
  var Q = D.questions, A = D.answers || {}, idx = 0, ended = false;
  // server-synchronised clock: remaining = deadline - (server "now" at load + time elapsed since load)
  var t0 = performance.now();
  function remaining() { return Math.max(0, D.deadline - (D.now + (performance.now() - t0) / 1000)); }
  var $ = function (id) { return document.getElementById(id); };
  var timer = $('timer'), left = D.left || 0;

  function api(path, body) {
    return fetch(D.base + '/attempt/' + D.attemptId + '/' + path, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': D.csrf },
      body: JSON.stringify(body || {})
    }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
  }
  function go(url) { ended = true; window.location.href = url; }
  function handle(r) { if (r && r.redirect) go(r.redirect); return r; }

  function render() {
    var q = Q[idx];
    $('qcount').textContent = 'Question ' + (idx + 1) + ' of ' + Q.length;
    var answered = Object.keys(A).length;
    $('pfill').style.width = Math.round(answered / Q.length * 100) + '%';
    $('qtext').textContent = q.body;
    $('qmarks').textContent = q.marks + (q.marks === 1 ? ' mark' : ' marks');
    var box = $('opts'); box.innerHTML = '';
    var locked = !D.allowChange && A[q.id];
    q.options.forEach(function (o) {
      var l = document.createElement('label'); l.className = 'opt';
      var i = document.createElement('input'); i.type = 'radio'; i.name = 'o'; i.value = o.id; i.checked = A[q.id] === o.id; i.disabled = !!locked && A[q.id] !== o.id;
      i.addEventListener('change', function () { choose(q.id, o.id); });
      var s = document.createElement('span'); s.textContent = o.body;
      l.appendChild(i); l.appendChild(s); box.appendChild(l);
    });
    $('prev').style.display = D.allowBack ? '' : 'none';
    $('prev').disabled = idx === 0;
    $('next').disabled = idx === Q.length - 1;
    $('qmsg').textContent = '';
  }
  function choose(qid, oid) {
    var prev = A[qid]; A[qid] = oid; $('pfill').style.width = Math.round(Object.keys(A).length / Q.length * 100) + '%';
    api('answer', { question_id: qid, option_id: oid }).then(function (r) {
      if (r.ended) return handle(r);
      if (!r.ok) { if (prev === undefined) delete A[qid]; else A[qid] = prev; $('qmsg').textContent = r.error || 'Could not save your answer. Check your connection and try again.'; render(); }
    }).catch(function () { $('qmsg').textContent = 'Could not save your answer. Check your connection and try again.'; });
  }
  $('prev').addEventListener('click', function () { if (idx > 0) { idx--; render(); $('qtext').focus(); } });
  $('next').addEventListener('click', function () { if (idx < Q.length - 1) { idx++; render(); $('qtext').focus(); } });

  function dialog(title, text, okLabel, onOk, showCancel) {
    $('ov-t').textContent = title; $('ov-p').textContent = text; $('ov-ok').textContent = okLabel; $('ov-no').hidden = !showCancel; $('overlay').hidden = false;
    $('ov-ok').onclick = function () { $('overlay').hidden = true; if (onOk) onOk(); }; $('ov-no').onclick = function () { $('overlay').hidden = true; };
    $('ov-ok').focus();
  }
  function submit(auto) {
    if (ended) return; ended = true;
    api('submit', { auto: !!auto }).then(function (r) { if (r.redirect) window.location.href = r.redirect; else { ended = false; dialog('Could not submit', 'Please check your connection and try again.', 'OK'); } })
      .catch(function () { ended = false; dialog('Could not submit', 'Please check your connection and try again.', 'OK'); });
  }
  $('submit').addEventListener('click', function () {
    var un = Q.length - Object.keys(A).length;
    dialog('Submit quiz?', un > 0 ? 'You have ' + un + ' unanswered question(s). You cannot change anything after submitting.' : 'You cannot change anything after submitting.', 'Submit now', function () { submit(false); }, true);
  });

  function tick() {
    var r = Math.ceil(remaining());
    timer.textContent = String(Math.floor(r / 60)).padStart(2, '0') + ':' + String(r % 60).padStart(2, '0');
    timer.classList.toggle('low', r <= 60);
    if (r <= 0 && !ended) submit(true);
  }
  setInterval(tick, 500); tick();

  // transparent integrity signals: one event per absence (visibility + blur are de-duplicated)
  var away = false;
  function leave(detail) {
    if (away || ended) return; away = true;
    api('event', { type: 'left_page', detail: detail }).then(function (r) {
      if (r.redirect) return handle(r);
      if (r.ok) {
        left = r.left || left + 1; var w = $('warn');
        var msg = 'You left the quiz page (' + left + (D.policy === 'warn' ? ' time(s)). This is recorded.' : ' of ' + D.threshold + ' allowed). This is recorded' + (D.policy === 'disqualify' ? ' and reaching the limit disqualifies the attempt.' : ' and reaching the limit flags the attempt for review.'));
        w.textContent = msg; w.hidden = false;
      }
    }).catch(function () {});
  }
  function back() { if (!away) return; away = false; if (!ended) api('event', { type: 'returned' }).catch(function () {}); }
  document.addEventListener('visibilitychange', function () { document.hidden ? leave('tab hidden or window minimised') : back(); });
  window.addEventListener('blur', function () { setTimeout(function () { if (!document.hasFocus()) leave('window lost focus'); }, 150); });
  window.addEventListener('focus', back);
  window.addEventListener('beforeunload', function (e) { if (!ended) { e.preventDefault(); e.returnValue = ''; } });

  render();
})();
