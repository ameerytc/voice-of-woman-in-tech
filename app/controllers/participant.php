<?php
declare(strict_types=1);

function quiz_card_state(array $q, int $uid): array {
    $win = quiz_window_state($q);
    $used = (int)$q['my_attempts']; $allowed = attempts_allowed($q, $uid);
    if ($q['my_open']) return ['resume', 'In progress', true];
    if ((int)$q['published_results'] === 1 && $used > 0) return ['done', 'Completed', false];
    if ($win === 'upcoming') return ['upcoming', 'Upcoming', false];
    if ($used >= $allowed && $used > 0) return ['done', 'Attempt completed', false];
    if ($win === 'closed') return ['closed', 'Closed', false];
    return ['open', 'Open', true];
}

route('GET', '/dashboard', function () {
    $u = require_login();
    finalize_expired();
    $uid = (int)$u['id'];
    // lazy "closing soon" notifications (de-duplicated per quiz)
    foreach (db_all("SELECT id,title,closes_at FROM quizzes WHERE status IN ('scheduled','published') AND opens_at<=? AND closes_at>? AND closes_at<=?", [now(), now(), gmdate('Y-m-d H:i:s', time() + 86400)]) as $c)
        if (!db_val("SELECT 1 FROM quiz_attempts WHERE quiz_id=? AND user_id=? AND status<>'in_progress'", [$c['id'], $uid]))
            notify_user($uid, 'Closing soon: ' . $c['title'], 'Closes ' . fmt_dt($c['closes_at']), '/quiz/' . $c['id'], 'closing:' . $c['id']);
    $quizzes = db_all("SELECT z.*, (SELECT COUNT(*) FROM questions WHERE quiz_id=z.id) qcount,
            (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=z.id AND user_id=:u) my_attempts,
            (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=z.id AND user_id=:u AND status='in_progress') my_open,
            CASE WHEN EXISTS (SELECT 1 FROM official_results r WHERE r.quiz_id=z.id AND r.status='official' AND r.published_at IS NOT NULL) THEN 1 ELSE 0 END published_results
        FROM quizzes z WHERE z.status IN ('scheduled','published') AND z.closes_at > :cut ORDER BY z.opens_at", ['u' => $uid, 'cut' => gmdate('Y-m-d H:i:s', time() - 86400 * 30)]);
    $stats = ['available' => 0, 'upcoming' => 0];
    foreach ($quizzes as &$q) { [$q['k'], $q['label'], $q['can']] = quiz_card_state($q, $uid); if ($q['k'] === 'open' || $q['k'] === 'resume') $stats['available']++; if ($q['k'] === 'upcoming') $stats['upcoming']++; }
    unset($q);
    $hist = db_all("SELECT a.*, z.title, z.score_visibility, z.answers_visibility, z.status qstatus, z.closes_at, z.id zid FROM quiz_attempts a JOIN quizzes z ON z.id=a.quiz_id WHERE a.user_id=? ORDER BY a.id DESC", [$uid]);
    foreach ($hist as &$h) $h['show'] = $h['status'] !== 'in_progress' && score_visible(['id' => $h['zid'], 'score_visibility' => $h['score_visibility'], 'status' => $h['qstatus'], 'closes_at' => $h['closes_at']]);
    unset($h);
    $stats['completed'] = (int)db_val("SELECT COUNT(DISTINCT quiz_id) FROM quiz_attempts WHERE user_id=? AND status<>'in_progress'", [$uid]);
    $stats['recent'] = count(array_filter($hist, fn($h) => $h['show'] && ts($h['submitted_at']) > time() - 86400 * 30));
    $notes = db_all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 5', [$uid]);
    $age = db_val('SELECT label FROM age_groups WHERE id=?', [$u['age_group_id']]);
    $card = ['u' => $u, 'age' => $age, 'has_avatar' => has_avatar($uid), 'avatar_v' => ts($u['updated_at'] ?? ''), 'unread' => (int)db_val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL', [$uid])];
    view('participant/dashboard', compact('u', 'quizzes', 'stats', 'hist', 'notes', 'card'));
});

route('GET', '/notifications', function () {
    $u = require_login();
    $notes = db_all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100', [$u['id']]);
    view('participant/notifications', compact('u', 'notes'));
});
route('POST', '/notifications/read', function () { $u = require_login(); db_exec('UPDATE notifications SET read_at=? WHERE user_id=? AND read_at IS NULL', [now(), $u['id']]); redirect('/notifications'); });
route('GET', '/n/{id}', function ($p) {
    $u = require_login();
    $n = db_one('SELECT * FROM notifications WHERE id=? AND user_id=?', [(int)$p['id'], $u['id']]);
    if (!$n) abort(404);
    db_exec('UPDATE notifications SET read_at=COALESCE(read_at,?) WHERE id=?', [now(), $n['id']]);
    redirect($n['link'] && $n['link'][0] === '/' ? $n['link'] : '/notifications');
});

route('GET', '/profile', function () {
    $u = require_login();
    $reqs = db_all('SELECT * FROM data_requests WHERE user_id=? ORDER BY id DESC LIMIT 10', [$u['id']]);
    view('participant/profile', ['u' => $u, 'ages' => active_age_groups(), 'reqs' => $reqs, 'ageLabel' => db_val('SELECT label FROM age_groups WHERE id=?', [$u['age_group_id']])]);
});
route('POST', '/profile', function () {
    $u = require_login();
    $name = post('full_name'); $phone = normalize_phone(post('phone')); $age = (int)post('age_group_id'); $state = post('state'); $gender = post('gender');
    $err = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $err[] = 'Enter your full name.';
    if (!$phone) $err[] = 'Enter a valid phone number.';
    if (!db_val('SELECT 1 FROM age_groups WHERE id=?', [$age])) $err[] = 'Select your age group.';
    if ($err) { foreach ($err as $m) flash('error', $m); redirect('/profile'); }
    if (!in_array($state, STATES, true)) $state = '';
    if (!in_array($gender, ['Female', 'Male', 'Prefer not to say'], true) || setting('collect_gender') !== '1') $gender = '';
    db_exec('UPDATE users SET full_name=?, phone=?, age_group_id=?, state=?, gender=?, updated_at=? WHERE id=?', [$name, $phone, $age, $state ?: null, $gender ?: null, now(), $u['id']]);
    flash('success', 'Profile updated.');
    redirect('/profile');
});
route('POST', '/profile/request', function () {
    $u = require_login();
    $type = post('type'); $msg = post('message');
    if (!in_array($type, ['correction', 'deletion'], true) || mb_strlen($msg) < 5) { flash('error', 'Choose a request type and describe it.'); redirect('/profile'); }
    db_exec('INSERT INTO data_requests(user_id,type,message) VALUES(?,?,?)', [$u['id'], $type, mb_substr($msg, 0, 1000)]);
    flash('success', 'Your request was sent to the administrators.');
    redirect('/profile');
});

/* ----- public (participant-facing) official results: first name + initial only ----- */
route('GET', '/results/{id}', function ($p) {
    $u = require_login();
    $quiz = db_one('SELECT * FROM quizzes WHERE id=?', [(int)$p['id']]);
    $res = $quiz ? db_one("SELECT * FROM official_results WHERE quiz_id=? AND status='official' AND published_at IS NOT NULL ORDER BY result_version DESC LIMIT 1", [$quiz['id']]) : null;
    if (!$res) { flash('info', 'Results for this quiz have not been published yet.'); redirect('/dashboard'); }
    $entries = db_all('SELECT * FROM official_result_entries WHERE result_id=? AND final_rank IS NOT NULL ORDER BY final_rank, duration_seconds, id', [$res['id']]);
    view('participant/results', compact('u', 'quiz', 'res', 'entries'));
});

/* ----- profile pictures: stored in the database; only the owner and administrators can view them ----- */
route('GET', '/avatar/{id}', function ($p) {
    $me = current_user();
    if (!$me) abort(404);
    $id = (int)$p['id'];
    if ($id !== (int)$me['id'] && !is_admin()) abort(404);
    $a = load_avatar($id);
    if (!$a) abort(404);
    header('Content-Type: ' . $a['mime']);
    header('Content-Length: ' . strlen($a['data']));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    echo $a['data'];
    exit;
});
route('POST', '/account/avatar', function () {
    $u = current_user();
    if (!$u) redirect('/login');
    $back = is_admin() ? '/admin' : '/profile';
    if (post('remove') === '1') {
        db_exec('DELETE FROM user_avatars WHERE user_id=?', [$u['id']]);
        flash('success', 'Profile picture removed.');
        redirect($back);
    }
    try { [$mime, $bytes] = process_avatar_upload($_FILES['avatar'] ?? []); save_avatar((int)$u['id'], $mime, $bytes); flash('success', 'Profile picture updated.'); }
    catch (UserError $e) { flash('error', $e->getMessage()); }
    redirect($back);
});
