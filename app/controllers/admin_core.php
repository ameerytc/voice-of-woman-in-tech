<?php
declare(strict_types=1);

function admin_view(string $tpl, array $vars = []): void { view($tpl, $vars + ['me' => current_user()], 'admin'); }

function activity_feed(int $limit = 12): array {
    $ev = [];
    foreach (db_all("SELECT u.id, u.full_name, u.created_at FROM users u WHERE " . PART_COND . " ORDER BY u.id DESC LIMIT 6") as $r)
        $ev[] = ['t' => $r['created_at'], 'ic' => 'user', 'title' => 'New registration', 'text' => $r['full_name'], 'link' => '/admin/participants/' . $r['id']];
    foreach (db_all("SELECT a.id, a.submitted_at, a.status, a.eligibility, u.full_name, z.title FROM quiz_attempts a JOIN users u ON u.id=a.user_id JOIN quizzes z ON z.id=a.quiz_id WHERE a.status<>'in_progress' ORDER BY a.id DESC LIMIT 8") as $r)
        $ev[] = ['t' => $r['submitted_at'] ?? now(), 'ic' => 'inbox', 'title' => $r['status'] === 'timed_out' ? 'Attempt timed out' : 'Attempt completed', 'text' => $r['full_name'] . ' · ' . $r['title'], 'link' => '/admin/submissions/' . $r['id']];
    $map = ['quiz_status' => ['quiz', 'Quiz status changed'], 'quiz_created' => ['quiz', 'Quiz created'], 'results_finalised' => ['trophy', 'Results finalised'], 'eligibility_decision' => ['shield', 'Verification decision'],
        'score_adjusted' => ['alert', 'Score corrected'], 'announcement_sent' => ['mega', 'Announcement sent'], 'questions_imported' => ['help', 'Questions imported'], 'attempt_granted' => ['key', 'Extra attempt authorised']];
    foreach (db_all("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 30") as $r) if (isset($map[$r['action']]))
        $ev[] = ['t' => $r['created_at'], 'ic' => $map[$r['action']][0], 'title' => $map[$r['action']][1], 'text' => trim(($r['actor_name'] ?? '') . ' · ' . mb_substr((string)$r['detail'], 0, 70)), 'link' => null];
    usort($ev, fn($x, $y) => strcmp((string)$y['t'], (string)$x['t']));
    return array_slice($ev, 0, $limit);
}
route('GET', '/admin', function () {
    $me = require_admin();
    finalize_expired();
    $P = PART_COND; $week = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
    [$s, $okS] = guarded(fn() => [
        'participants' => (int)db_val("SELECT COUNT(*) FROM users u WHERE $P"),
        'active' => (int)db_val("SELECT COUNT(*) FROM users u WHERE $P AND u.status='active'"),
        'published' => (int)db_val("SELECT COUNT(*) FROM quizzes WHERE status='published'"),
        'attempts' => (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE status<>'in_progress'"),
        'completed' => (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE status='submitted'"),
        'pending' => (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE eligibility='pending_review'"),
        'disqualified' => (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE eligibility='disqualified'"),
        'eligible' => (int)db_val("SELECT COUNT(DISTINCT user_id) FROM quiz_attempts WHERE eligibility='eligible'"),
        'new' => (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE id > ? AND status<>'in_progress'", [$me['admin_seen_attempt_id']]),
        'live' => (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE status='in_progress'"),
        'reg7' => (int)db_val("SELECT COUNT(*) FROM users u WHERE $P AND u.created_at >= ?", [$week]),
        'att7' => (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE status<>'in_progress' AND started_at >= ?", [$week]),
    ], []);
    [$reg, $okR] = guarded(function () use ($P) {
        $since = gmdate('Y-m-01 00:00:00', strtotime('-11 months'));
        $rows = array_column(db_all("SELECT substr(u.created_at,1,7) m, COUNT(*) c FROM users u WHERE $P AND u.created_at >= ? GROUP BY substr(u.created_at,1,7) ORDER BY m", [$since]), 'c', 'm');
        $lab = []; $val = [];
        for ($i = 11; $i >= 0; $i--) { $k = gmdate('Y-m', strtotime(gmdate('Y-m-15') . " -$i months")); $lab[] = $k; $val[] = (int)($rows[$k] ?? 0); }
        return [$lab, $val];
    }, [[], []]);
    [$byQuiz, $okQ] = guarded(fn() => db_all("SELECT z.title, COUNT(a.id) n, SUM(CASE WHEN a.status='submitted' THEN 1 ELSE 0 END) done, SUM(CASE WHEN a.status IN ('submitted','timed_out','abandoned') THEN 1 ELSE 0 END) fin
        FROM quizzes z JOIN quiz_attempts a ON a.quiz_id=z.id GROUP BY z.id ORDER BY n DESC LIMIT 8"));
    [$dist, $okD] = guarded(function () { $d = [0, 0, 0, 0, 0]; foreach (db_all("SELECT percentage FROM quiz_attempts WHERE status IN ('submitted','timed_out')") as $r) $d[min(4, (int)floor((float)$r['percentage'] / 20))]++; return $d; }, [0, 0, 0, 0, 0]);
    [$ages, $okA] = guarded(fn() => db_all("SELECT COALESCE(g.label,'Not provided') l, COUNT(DISTINCT a.user_id) c FROM quiz_attempts a JOIN users u ON u.id=a.user_id LEFT JOIN age_groups g ON g.id=u.age_group_id
        WHERE a.status IN ('submitted','timed_out','abandoned') GROUP BY COALESCE(g.label,'Not provided') ORDER BY c DESC"));
    [$perf, $okF] = guarded(fn() => db_all("SELECT z.title, AVG(a.percentage) avg FROM quizzes z JOIN quiz_attempts a ON a.quiz_id=z.id WHERE a.status IN ('submitted','timed_out') GROUP BY z.id ORDER BY MAX(a.started_at) DESC LIMIT 6"));
    [$upcoming] = guarded(fn() => db_all("SELECT id,title,opens_at,closes_at FROM quizzes WHERE status IN ('scheduled','published') AND closes_at>? ORDER BY opens_at LIMIT 5", [now()]));
    [$feed, $okE] = guarded(fn() => activity_feed(12));
    $fin = (int)array_sum(array_column($byQuiz, 'fin')); $done = (int)array_sum(array_column($byQuiz, 'done'));
    admin_view('admin/overview', compact('s', 'okS', 'reg', 'okR', 'byQuiz', 'okQ', 'dist', 'okD', 'ages', 'okA', 'perf', 'okF', 'upcoming', 'feed', 'okE', 'fin', 'done'));
});
route('GET', '/admin/search', function () {
    require_admin();
    $q = get('q'); $people = []; $quizzes = [];
    if (mb_strlen($q) >= 2) {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%'; $L = sql_like();
        if (has_perm('participants.view')) $people = db_all("SELECT u.id, u.full_name, u.email, u.status, CASE WHEN EXISTS (SELECT 1 FROM user_avatars av WHERE av.user_id=u.id) THEN 1 ELSE 0 END has_avatar FROM users u WHERE " . PART_COND . " AND (u.full_name $L :q ESCAPE '\\' OR u.email $L :q ESCAPE '\\') ORDER BY u.full_name LIMIT 15", ['q' => $like]);
        if (has_perm('quizzes.manage')) $quizzes = db_all("SELECT id, title, status, category FROM quizzes WHERE (title $L :q ESCAPE '\\' OR category $L :q ESCAPE '\\') ORDER BY id DESC LIMIT 15", ['q' => $like]);
    }
    admin_view('admin/search', compact('q', 'people', 'quizzes'));
});
route('GET', '/admin/api/pulse', function () {
    $me = require_admin();
    json_out(['new' => (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE id > ? AND status<>'in_progress'", [$me['admin_seen_attempt_id']]),
              'pending' => (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE eligibility='pending_review'")]);
});

/* ================= participants ================= */
function participant_filter(): array {
    $w = [PART_COND]; $p = [];
    if (($q = get('q')) !== '') { $L = sql_like(); $w[] = "(u.full_name $L :q ESCAPE '\\' OR u.email $L :q ESCAPE '\\' OR u.phone $L :q ESCAPE '\\')"; $p['q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%'; }
    if (($g = (int)get('age')) > 0) { $w[] = 'u.age_group_id=:age'; $p['age'] = $g; }
    if (in_array(get('status'), ['active', 'inactive', 'suspended', 'deleted'], true)) { $w[] = 'u.status=:st'; $p['st'] = get('status'); }
    if (get('from') !== '') { $w[] = 'u.created_at>=:f'; $p['f'] = get('from') . ' 00:00:00'; }
    if (get('to') !== '') { $w[] = 'u.created_at<=:t'; $p['t'] = get('to') . ' 23:59:59'; }
    if (($z = (int)get('quiz')) > 0) { $w[] = 'EXISTS (SELECT 1 FROM quiz_attempts a WHERE a.user_id=u.id AND a.quiz_id=:z)'; $p['z'] = $z; }
    return [implode(' AND ', $w), $p];
}
const PART_SELECT = "SELECT u.*, CASE WHEN EXISTS (SELECT 1 FROM user_avatars av WHERE av.user_id=u.id) THEN 1 ELSE 0 END has_avatar, g.label age_label, (SELECT COUNT(*) FROM quiz_attempts a WHERE a.user_id=u.id) attempts,
    (SELECT COUNT(*) FROM quiz_attempts a WHERE a.user_id=u.id AND a.status='submitted') completed
    FROM users u LEFT JOIN age_groups g ON g.id=u.age_group_id";
route('GET', '/admin/participants', function () {
    require_admin('participants.view');
    [$w, $p] = participant_filter();
    $pg = paginate((int)db_val("SELECT COUNT(*) FROM users u WHERE $w", $p), 25);
    $rows = db_all(PART_SELECT . " WHERE $w ORDER BY u.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
    $requests = db_all("SELECT d.*, u.full_name, u.email FROM data_requests d JOIN users u ON u.id=d.user_id WHERE d.status='open' ORDER BY d.id");
    admin_view('admin/participants', ['rows' => $rows, 'pg' => $pg, 'ages' => db_all('SELECT * FROM age_groups ORDER BY sort_order'), 'quizzes' => db_all('SELECT id,title FROM quizzes ORDER BY id DESC'), 'requests' => $requests, 'phone' => has_perm('participants.phone')]);
});
route('GET', '/admin/participants/export', function () {
    require_admin('participants.export');
    [$w, $p] = participant_filter();
    $phone = has_perm('participants.phone');
    $h = ['Full name', 'Email', 'Age group', 'State', 'Registered (UTC)', 'Status', 'Attempts', 'Completed quizzes']; if ($phone) array_splice($h, 2, 0, 'Phone');
    $rows = [];
    foreach (db_all(PART_SELECT . " WHERE $w ORDER BY u.id", $p) as $r) {
        $row = [$r['full_name'], $r['email'], $r['age_label'] ?? '', $r['state'] ?? '', $r['created_at'], $r['status'], $r['attempts'], $r['completed']];
        if ($phone) array_splice($row, 2, 0, [$r['phone']]);
        $rows[] = $row;
    }
    audit('export_participants', 'users', null, count($rows) . ' rows; filters: ' . json_encode($_GET));
    csv_out('participants_' . gmdate('Ymd_His') . '.csv', $h, $rows);
});
route('GET', '/admin/participants/{id}', function ($p) {
    require_admin('participants.view');
    $u = db_one(PART_SELECT . ' WHERE u.id=?', [(int)$p['id']]);
    if (!$u) abort(404);
    $attempts = db_all('SELECT a.*, z.title FROM quiz_attempts a JOIN quizzes z ON z.id=a.quiz_id WHERE a.user_id=? ORDER BY a.id DESC', [$u['id']]);
    $grants = db_all('SELECT g.*, z.title FROM attempt_grants g JOIN quizzes z ON z.id=g.quiz_id WHERE g.user_id=? ORDER BY g.id DESC', [$u['id']]);
    admin_view('admin/participant', ['u' => $u, 'attempts' => $attempts, 'grants' => $grants, 'quizzes' => db_all('SELECT id,title FROM quizzes ORDER BY id DESC'), 'phone' => has_perm('participants.phone')]);
});
route('POST', '/admin/participants/{id}/status', function ($p) {
    $me = require_admin('participants.manage');
    $st = post('status');
    $u = db_one('SELECT * FROM users WHERE id=?', [(int)$p['id']]);
    if (!$u || !in_array($st, ['active', 'inactive', 'suspended'], true) || user_roles((int)$u['id'])) abort(404);
    db_exec('UPDATE users SET status=?, updated_at=? WHERE id=?', [$st, now(), $u['id']]);
    audit('participant_status', 'user', $u['id'], "{$u['status']} -> $st");
    flash('success', 'Account status updated.');
    redirect('/admin/participants/' . $u['id']);
});
route('POST', '/admin/participants/{id}/grant', function ($p) {
    require_admin('participants.manage');
    $u = db_one('SELECT * FROM users WHERE id=?', [(int)$p['id']]); $qid = (int)post('quiz_id'); $reason = post('reason');
    if (!$u || !db_val('SELECT 1 FROM quizzes WHERE id=?', [$qid]) || mb_strlen($reason) < 5) { flash('error', 'Choose a quiz and give a reason (at least 5 characters).'); redirect('/admin/participants/' . (int)$p['id']); }
    db_exec('INSERT INTO attempt_grants(user_id,quiz_id,extra_attempts,reason,granted_by) VALUES(?,?,1,?,?)', [$u['id'], $qid, $reason, current_user()['id']]);
    audit('attempt_granted', 'user', $u['id'], "quiz $qid: $reason");
    notify_user((int)$u['id'], 'Extra attempt authorised', 'An administrator authorised another attempt.', '/quiz/' . $qid);
    flash('success', 'One extra attempt authorised.');
    redirect('/admin/participants/' . $u['id']);
});
route('POST', '/admin/participants/{id}/anonymize', function ($p) {
    require_admin('participants.manage');
    $u = db_one('SELECT * FROM users WHERE id=?', [(int)$p['id']]);
    if (!$u || user_roles((int)$u['id'])) abort(404);
    if (post('confirm') !== 'DELETE') { flash('error', 'Type DELETE to confirm.'); redirect('/admin/participants/' . $u['id']); }
    db_exec("UPDATE users SET full_name='Deleted participant', email=?, phone=NULL, state=NULL, gender=NULL, status='deleted', password_hash=?, updated_at=? WHERE id=?",
        ['deleted-' . $u['id'] . '@invalid.local', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), now(), $u['id']]);
    db_exec("UPDATE data_requests SET status='resolved', handled_by=?, handled_at=? WHERE user_id=? AND type='deletion' AND status='open'", [current_user()['id'], now(), $u['id']]);
    audit('participant_anonymised', 'user', $u['id'], 'Personal data removed; quiz records kept without identity');
    flash('success', 'Personal data removed. Quiz records are kept without identity.');
    redirect('/admin/participants');
});
route('POST', '/admin/requests/{id}/resolve', function ($p) {
    require_admin('participants.manage');
    $r = db_one('SELECT * FROM data_requests WHERE id=?', [(int)$p['id']]);
    $st = post('status') === 'rejected' ? 'rejected' : 'resolved';
    if (!$r) abort(404);
    db_exec('UPDATE data_requests SET status=?, handled_by=?, handled_at=? WHERE id=?', [$st, current_user()['id'], now(), $r['id']]);
    audit('data_request_' . $st, 'data_request', $r['id']);
    flash('success', 'Request marked ' . $st . '.');
    redirect('/admin/participants');
});

/* ================= announcements ================= */
route('GET', '/admin/announcements', function () {
    require_admin('announcements.manage');
    admin_view('admin/announcements', ['rows' => db_all('SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON u.id=a.created_by ORDER BY a.id DESC LIMIT 50')]);
});
route('POST', '/admin/announcements', function () {
    $me = require_admin('announcements.manage');
    $t = post('title'); $b = post('body');
    if (mb_strlen($t) < 3 || mb_strlen($b) < 3) { flash('error', 'Enter a title and a message.'); keep_old(); redirect('/admin/announcements'); }
    $id = db_insert('INSERT INTO announcements(title,body,created_by) VALUES(?,?,?)', [mb_substr($t, 0, 150), mb_substr($b, 0, 2000), $me['id']]);
    notify_all_participants(mb_substr($t, 0, 150), mb_substr($b, 0, 2000), '/dashboard', 'ann:' . $id);
    audit('announcement_sent', 'announcement', $id, $t);
    flash('success', 'Announcement sent to all active participants.');
    redirect('/admin/announcements');
});

/* ================= settings ================= */
route('GET', '/admin/settings', function () {
    require_admin('settings.manage');
    admin_view('admin/settings', ['ages' => db_all('SELECT g.*, (SELECT COUNT(*) FROM users u WHERE u.age_group_id=g.id) n FROM age_groups g ORDER BY sort_order, id'), 'mail' => mail_configured()]);
});
route('POST', '/admin/settings', function () {
    require_admin('settings.manage');
    $org = post('org_name');
    if ($org === '') { flash('error', 'Organisation name is required.'); redirect('/admin/settings'); }
    set_setting('org_name', mb_substr($org, 0, 100));
    set_setting('collect_gender', post('collect_gender') === '1' ? '1' : '0');
    set_setting('about_text', mb_substr(post('about_text'), 0, 800));
    set_setting('public_identity', in_array(post('public_identity'), ['name', 'masked', 'id', 'anonymous'], true) ? post('public_identity') : 'masked');
    audit('settings_updated', 'settings', null, 'org name / gender field');
    flash('success', 'Settings saved.');
    redirect('/admin/settings');
});
route('POST', '/admin/settings/age', function () {
    require_admin('settings.manage');
    $act = post('action'); $id = (int)post('id'); $label = post('label'); $sort = (int)post('sort_order');
    if ($act === 'add') {
        if ($label === '' || db_val('SELECT 1 FROM age_groups WHERE label=?', [$label])) { flash('error', 'Enter a unique age group label.'); redirect('/admin/settings'); }
        db_exec('INSERT INTO age_groups(label,sort_order) VALUES(?,?)', [mb_substr($label, 0, 40), $sort ?: 99]);
    } elseif ($act === 'save' && $label !== '') {
        db_exec('UPDATE age_groups SET label=?, sort_order=? WHERE id=?', [mb_substr($label, 0, 40), $sort, $id]);
    } elseif ($act === 'toggle') {
        db_exec('UPDATE age_groups SET is_active = 1 - is_active WHERE id=?', [$id]);
    }
    audit('age_groups_updated', 'age_group', $id ?: null, $act . ' ' . $label);
    flash('success', 'Age groups updated.');
    redirect('/admin/settings');
});

/* ================= administrator accounts ================= */
function other_active_supers(int $exceptId): int {
    return (int)db_val("SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.name='super_admin' AND u.status='active' AND u.id<>?", [$exceptId]);
}
route('GET', '/admin/admins', function () {
    require_admin('admins.manage');
    $rows = db_all("SELECT u.*, (SELECT " . sql_str_agg('r.label') . " FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id) roles,
        (SELECT r.id FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id LIMIT 1) role_id FROM users u WHERE NOT (" . PART_COND . ") ORDER BY u.id");
    admin_view('admin/admins', ['rows' => $rows, 'roles' => db_all('SELECT * FROM roles ORDER BY id')]);
});
route('POST', '/admin/admins', function () {
    require_admin('admins.manage');
    $name = post('full_name'); $email = strtolower(post('email')); $role = (int)post('role_id'); $pw = (string)($_POST['password'] ?? '');
    $err = [];
    if (mb_strlen($name) < 2) $err[] = 'Enter the full name.';
    if (!valid_email($email)) $err[] = 'Enter a valid email.';
    if (!db_val('SELECT 1 FROM roles WHERE id=?', [$role])) $err[] = 'Choose a role.';
    if ($e = valid_password($pw)) $err[] = $e;
    if (!$err && db_val('SELECT 1 FROM users WHERE email=?', [$email])) $err[] = 'That email is already registered.';
    if ($err) { foreach ($err as $m) flash('error', $m); keep_old(); redirect('/admin/admins'); }
    tx(function () use ($name, $email, $role, $pw) {
        $id = db_insert('INSERT INTO users(full_name,email,password_hash,must_change_password) VALUES(?,?,?,1)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
        db_exec('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)', [$id, $role]);
        audit('admin_created', 'user', $id, $email . ' role ' . $role);
    });
    flash('success', 'Administrator created. They must change the temporary password at first login.');
    redirect('/admin/admins');
});
route('POST', '/admin/admins/{id}', function ($p) {
    $me = require_admin('admins.manage');
    $u = db_one('SELECT * FROM users WHERE id=?', [(int)$p['id']]);
    if (!$u || !user_roles((int)$u['id'])) abort(404);
    $act = post('action');
    $isSuper = in_array('super_admin', user_roles((int)$u['id']), true);
    if ($act === 'role') {
        $role = (int)post('role_id');
        $name = db_val('SELECT name FROM roles WHERE id=?', [$role]);
        if (!$name) abort(404);
        if ($isSuper && $name !== 'super_admin' && other_active_supers((int)$u['id']) < 1) { flash('error', 'You cannot remove the last Super Administrator.'); redirect('/admin/admins'); }
        tx(function () use ($u, $role) { db_exec('DELETE FROM user_roles WHERE user_id=?', [$u['id']]); db_exec('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)', [$u['id'], $role]); });
        audit('admin_role_changed', 'user', $u['id'], (string)$name);
        flash('success', 'Role updated.');
    } elseif ($act === 'status') {
        $st = post('status') === 'active' ? 'active' : 'inactive';
        if ($st === 'inactive' && ((int)$u['id'] === (int)$me['id'] || ($isSuper && other_active_supers((int)$u['id']) < 1))) { flash('error', 'You cannot deactivate yourself or the last Super Administrator.'); redirect('/admin/admins'); }
        db_exec('UPDATE users SET status=?, updated_at=? WHERE id=?', [$st, now(), $u['id']]);
        audit('admin_status', 'user', $u['id'], $st);
        flash('success', 'Account ' . $st . '.');
    } elseif ($act === 'password') {
        $pw = (string)($_POST['password'] ?? '');
        if ($e = valid_password($pw)) { flash('error', $e); redirect('/admin/admins'); }
        db_exec('UPDATE users SET password_hash=?, must_change_password=1, updated_at=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), now(), $u['id']]);
        audit('admin_password_reset', 'user', $u['id']);
        flash('success', 'Temporary password set. The administrator must change it at next login.');
    }
    redirect('/admin/admins');
});

/* ================= audit log ================= */
route('GET', '/admin/audit', function () {
    require_admin('audit.view');
    $w = ['1=1']; $p = [];
    if (($q = get('q')) !== '') { $L = sql_like(); $w[] = "(action $L :q OR actor_name $L :q OR detail $L :q OR entity_type $L :q)"; $p['q'] = '%' . $q . '%'; }
    $where = implode(' AND ', $w);
    $pg = paginate((int)db_val("SELECT COUNT(*) FROM audit_logs WHERE $where", $p), 50);
    admin_view('admin/audit', ['rows' => db_all("SELECT * FROM audit_logs WHERE $where ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p), 'pg' => $pg]);
});
