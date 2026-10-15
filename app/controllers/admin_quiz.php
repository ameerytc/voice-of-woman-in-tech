<?php
declare(strict_types=1);

const QUIZ_STATUSES = ['draft', 'scheduled', 'published', 'closed', 'archived'];

function quiz_from_post(): array {
    $d = []; $err = [];
    $d['title'] = mb_substr(post('title'), 0, 150);
    $d['description'] = mb_substr(post('description'), 0, 1000);
    $d['category'] = mb_substr(post('category') ?: 'General', 0, 60);
    $d['instructions'] = mb_substr(post('instructions'), 0, 3000);
    $d['duration_minutes'] = (int)post('duration_minutes');
    $d['opens_at'] = local_to_utc(post('opens_at')) ?? '';
    $d['closes_at'] = local_to_utc(post('closes_at')) ?? '';
    $d['max_attempts'] = max(1, (int)post('max_attempts', '1'));
    foreach (['shuffle_questions', 'shuffle_options', 'allow_back', 'allow_change', 'timeout_counts'] as $b) $d[$b] = post($b) === '1' ? 1 : 0;
    $d['resume_policy'] = post('resume_policy') === 'no_resume' ? 'no_resume' : 'resume';
    $d['passing_percent'] = post('passing_percent') === '' ? null : (int)post('passing_percent');
    $pick = fn(string $k, array $ok, string $def) => in_array(post($k), $ok, true) ? post($k) : $def;
    $d['score_visibility'] = $pick('score_visibility', ['immediate', 'after_close', 'after_publish'], 'immediate');
    $d['answers_visibility'] = $pick('answers_visibility', ['never', 'after_close', 'after_publish'], 'after_publish');
    $d['visibility_policy'] = $pick('visibility_policy', ['warn', 'review', 'disqualify'], 'review');
    $d['visibility_threshold'] = max(1, (int)post('visibility_threshold', '3'));
    $d['tiebreak'] = $pick('tiebreak', ['time', 'none'], 'time');
    $d['assessment_type'] = $pick('assessment_type', ['standalone', 'pre_test', 'post_test'], 'standalone');
    $d['assessment_group'] = $d['assessment_type'] === 'standalone' ? null : (mb_substr(post('assessment_group'), 0, 60) ?: null);
    if ($d['title'] === '') $err[] = 'Title is required.';
    if ($d['duration_minutes'] < 1 || $d['duration_minutes'] > 600) $err[] = 'Duration must be between 1 and 600 minutes.';
    if ($d['opens_at'] === '' || $d['closes_at'] === '') $err[] = 'Opening and closing date/time are required.';
    elseif (ts($d['closes_at']) <= ts($d['opens_at'])) $err[] = 'The closing time must be after the opening time.';
    if ($d['passing_percent'] !== null && ($d['passing_percent'] < 0 || $d['passing_percent'] > 100)) $err[] = 'Passing score must be between 0 and 100.';
    if ($d['assessment_type'] !== 'standalone' && !$d['assessment_group']) $err[] = 'Pre/post tests need an assessment group name so they can be paired.';
    return [$d, $err];
}
const QUIZ_COLS = ['title', 'description', 'category', 'instructions', 'duration_minutes', 'opens_at', 'closes_at', 'max_attempts', 'shuffle_questions', 'shuffle_options', 'allow_back', 'allow_change', 'resume_policy', 'passing_percent', 'score_visibility', 'answers_visibility', 'visibility_policy', 'visibility_threshold', 'timeout_counts', 'tiebreak', 'assessment_type', 'assessment_group'];

route('GET', '/admin/quizzes', function () {
    require_admin('quizzes.manage');
    $rows = db_all("SELECT z.*, (SELECT COUNT(*) FROM questions WHERE quiz_id=z.id) qn, (SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id=z.id) marks,
        (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=z.id) attempts,
        (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=z.id AND status='submitted') done,
        (SELECT AVG(percentage) FROM quiz_attempts WHERE quiz_id=z.id AND status IN ('submitted','timed_out')) avg FROM quizzes z ORDER BY z.id DESC");
    admin_view('admin/quizzes', ['rows' => $rows]);
});
route('GET', '/admin/quizzes/new', function () {
    require_admin('quizzes.manage');
    admin_view('admin/quiz_form', ['q' => null]);
});
route('POST', '/admin/quizzes/new', function () {
    $me = require_admin('quizzes.manage');
    [$d, $err] = quiz_from_post();
    if ($err) { foreach ($err as $m) flash('error', $m); keep_old(); redirect('/admin/quizzes/new'); }
    $cols = QUIZ_COLS;
    $id = db_insert('INSERT INTO quizzes(' . implode(',', $cols) . ',created_by) VALUES(' . implode(',', array_fill(0, count($cols) + 1, '?')) . ')', array_merge(array_map(fn($c) => $d[$c], $cols), [$me['id']]));
    audit('quiz_created', 'quiz', $id, $d['title']);
    flash('success', 'Quiz created as a draft. Now add its questions.');
    redirect('/admin/quizzes/' . $id . '/questions');
});
route('GET', '/admin/quizzes/{id}/edit', function ($p) {
    require_admin('quizzes.manage');
    $q = db_one('SELECT * FROM quizzes WHERE id=?', [(int)$p['id']]);
    if (!$q) abort(404);
    admin_view('admin/quiz_form', ['q' => $q, 'attempts' => (int)db_val('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=?', [$q['id']])]);
});
route('POST', '/admin/quizzes/{id}/edit', function ($p) {
    require_admin('quizzes.manage');
    $q = db_one('SELECT * FROM quizzes WHERE id=?', [(int)$p['id']]);
    if (!$q) abort(404);
    [$d, $err] = quiz_from_post();
    if ($err) { foreach ($err as $m) flash('error', $m); keep_old(); redirect('/admin/quizzes/' . $q['id'] . '/edit'); }
    $sets = implode(',', array_map(fn($c) => "$c=?", QUIZ_COLS));
    db_exec("UPDATE quizzes SET $sets, updated_at=? WHERE id=?", array_merge(array_map(fn($c) => $d[$c], QUIZ_COLS), [now(), $q['id']]));
    audit('quiz_updated', 'quiz', $q['id'], $d['title'] . ' (historical attempts keep the version they started with)');
    flash('success', 'Quiz saved. Attempts already started keep the rules and questions they began with.');
    redirect('/admin/quizzes');
});
route('POST', '/admin/quizzes/{id}/status', function ($p) {
    require_admin('quizzes.manage');
    $q = db_one('SELECT * FROM quizzes WHERE id=?', [(int)$p['id']]);
    $st = post('status');
    if (!$q || !in_array($st, QUIZ_STATUSES, true)) abort(404);
    if (in_array($st, ['scheduled', 'published'], true)) {
        $qs = quiz_questions((int)$q['id']);
        $bad = [];
        if (!$qs) $bad[] = 'add at least one question';
        foreach ($qs as $i => $x) { $c = array_sum(array_column($x['options'], 'is_correct')); if (count($x['options']) < 2 || $c !== 1) $bad[] = 'question ' . ($i + 1) . ' needs 2+ options and exactly one correct answer'; }
        if (ts($q['closes_at']) <= time()) $bad[] = 'the closing time is already in the past (edit the quiz dates)';
        if ($bad) { flash('error', 'Cannot publish: ' . implode('; ', array_slice($bad, 0, 4)) . '.'); redirect('/admin/quizzes'); }
    }
    db_exec('UPDATE quizzes SET status=?, updated_at=? WHERE id=?', [$st, now(), $q['id']]);
    if ($st === 'published') notify_all_participants('New quiz available: ' . $q['title'], 'Opens ' . fmt_dt($q['opens_at']) . ' and closes ' . fmt_dt($q['closes_at']) . '.', '/quiz/' . $q['id'], 'quiz:' . $q['id']);
    audit('quiz_status', 'quiz', $q['id'], $q['status'] . ' -> ' . $st);
    flash('success', 'Quiz is now ' . $st . '.');
    redirect('/admin/quizzes');
});
route('POST', '/admin/quizzes/{id}/duplicate', function ($p) {
    $me = require_admin('quizzes.manage');
    $q = db_one('SELECT * FROM quizzes WHERE id=?', [(int)$p['id']]);
    if (!$q) abort(404);
    $new = tx(function () use ($q, $me) {
        $cols = QUIZ_COLS; $vals = array_map(fn($c) => $q[$c], $cols); $vals[0] = mb_substr('Copy of ' . $q['title'], 0, 150);
        $id = db_insert('INSERT INTO quizzes(' . implode(',', $cols) . ',created_by,status) VALUES(' . implode(',', array_fill(0, count($cols) + 2, '?')) . ')', array_merge($vals, [$me['id'], 'draft']));
        foreach (quiz_questions((int)$q['id']) as $x) {
            $qid = db_insert('INSERT INTO questions(quiz_id,type,body,marks,sort_order,explanation) VALUES(?,?,?,?,?,?)', [$id, $x['type'], $x['body'], $x['marks'], $x['sort_order'], $x['explanation']]);
            foreach ($x['options'] as $o) db_exec('INSERT INTO question_options(question_id,body,is_correct,sort_order) VALUES(?,?,?,?)', [$qid, $o['body'], $o['is_correct'], $o['sort_order']]);
        }
        return $id;
    });
    audit('quiz_duplicated', 'quiz', $new, 'from ' . $q['id']);
    flash('success', 'Quiz duplicated as a draft.');
    redirect('/admin/quizzes/' . $new . '/questions');
});
route('GET', '/admin/quizzes/{id}/preview', function ($p) {
    require_admin('quizzes.manage');
    $q = db_one('SELECT * FROM quizzes WHERE id=?', [(int)$p['id']]);
    if (!$q) abort(404);
    $qs = quiz_questions((int)$q['id']);
    admin_view('admin/quiz_preview', ['q' => $q, 'qs' => $qs, 'rules' => quiz_rules_list($q, count($qs))]);
});

/* ================= questions ================= */
route('GET', '/admin/questions', function () {
    require_admin('quizzes.manage');
    admin_view('admin/questions_pick', ['rows' => db_all('SELECT z.id,z.title,z.status,(SELECT COUNT(*) FROM questions WHERE quiz_id=z.id) qn FROM quizzes z ORDER BY z.id DESC')]);
});
route('GET', '/admin/quizzes/{id}/questions', function ($p) {
    require_admin('quizzes.manage');
    $q = db_one('SELECT * FROM quizzes WHERE id=?', [(int)$p['id']]);
    if (!$q) abort(404);
    $qs = quiz_questions((int)$q['id']);
    $edit = null;
    if (($eid = (int)get('edit')) > 0) foreach ($qs as $x) if ((int)$x['id'] === $eid) $edit = $x;
    admin_view('admin/questions', ['q' => $q, 'qs' => $qs, 'edit' => $edit, 'attempts' => (int)db_val('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=?', [$q['id']])]);
});
route('POST', '/admin/quizzes/{id}/questions', function ($p) {
    require_admin('quizzes.manage');
    $q = db_one('SELECT * FROM quizzes WHERE id=?', [(int)$p['id']]);
    if (!$q) abort(404);
    $qid = (int)post('question_id'); $type = post('type') === 'tf' ? 'tf' : 'mcq';
    $body = mb_substr(post('body'), 0, 1000); $marks = (int)post('marks', '1'); $sort = (int)post('sort_order'); $expl = mb_substr(post('explanation'), 0, 1000);
    $err = [];
    if ($body === '') $err[] = 'Question text is required.';
    if ($marks < 1 || $marks > 100) $err[] = 'Marks must be between 1 and 100.';
    $opts = []; $correct = -1;
    if ($type === 'tf') {
        $opts = ['True', 'False'];
        $correct = post('correct_tf') === 'false' ? 1 : 0;
    } else {
        $raw = $_POST['opt'] ?? []; $sel = (int)post('correct', '-1');
        if (is_array($raw)) foreach ($raw as $i => $o) { $o = is_string($o) ? trim($o) : ''; if ($o === '') continue; if ((int)$i === $sel) $correct = count($opts); $opts[] = mb_substr($o, 0, 300); }
        if (count($opts) < 2) $err[] = 'Provide at least two answer options.';
        if ($correct < 0) $err[] = 'Select which option is correct (and make sure it is filled in).';
        if (count($opts) !== count(array_unique(array_map('mb_strtolower', $opts)))) $err[] = 'Answer options must be different from each other.';
    }
    if ($err) { foreach ($err as $m) flash('error', $m); keep_old(); redirect('/admin/quizzes/' . $q['id'] . '/questions' . ($qid ? '?edit=' . $qid : '')); }
    tx(function () use ($q, $qid, $type, $body, $marks, $sort, $expl, $opts, $correct) {
        if ($qid && db_val('SELECT 1 FROM questions WHERE id=? AND quiz_id=?', [$qid, $q['id']])) {
            db_exec('UPDATE questions SET type=?, body=?, marks=?, sort_order=?, explanation=?, updated_at=? WHERE id=?', [$type, $body, $marks, $sort, $expl, now(), $qid]);
            db_exec('DELETE FROM question_options WHERE question_id=?', [$qid]);
        } else {
            if ($sort === 0) $sort = (int)db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM questions WHERE quiz_id=?', [$q['id']]);
            $qid = db_insert('INSERT INTO questions(quiz_id,type,body,marks,sort_order,explanation) VALUES(?,?,?,?,?,?)', [$q['id'], $type, $body, $marks, $sort, $expl]);
        }
        foreach ($opts as $i => $o) db_exec('INSERT INTO question_options(question_id,body,is_correct,sort_order) VALUES(?,?,?,?)', [$qid, $o, $i === $correct ? 1 : 0, $i + 1]);
    });
    audit('question_saved', 'quiz', $q['id'], mb_substr($body, 0, 80));
    flash('success', 'Question saved.');
    redirect('/admin/quizzes/' . $q['id'] . '/questions');
});
route('POST', '/admin/quizzes/{id}/questions/{qid}/delete', function ($p) {
    require_admin('quizzes.manage');
    $n = db_exec('DELETE FROM questions WHERE id=? AND quiz_id=?', [(int)$p['qid'], (int)$p['id']]);
    if ($n) { audit('question_deleted', 'quiz', $p['id'], 'question ' . $p['qid']); flash('success', 'Question deleted. Past attempts are unaffected.'); }
    redirect('/admin/quizzes/' . (int)$p['id'] . '/questions');
});
route('GET', '/admin/questions/template.csv', function () {
    require_admin('quizzes.manage');
    csv_out('question_import_template.csv', ['question', 'type', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f', 'correct', 'marks', 'explanation'], [
        ['Which password is strongest?', 'mcq', 'password123', 'A long passphrase with numbers and symbols', '12345678', 'Your birth year', '', '', 'B', 2, 'Long, unique passphrases are hardest to guess.'],
        ['You should share your OTP with bank staff.', 'tf', '', '', '', '', '', '', 'false', 1, 'Never share an OTP with anyone.'],
    ]);
});
route('POST', '/admin/quizzes/{id}/import', function ($p) {
    require_admin('quizzes.manage');
    $q = db_one('SELECT * FROM quizzes WHERE id=?', [(int)$p['id']]);
    if (!$q) abort(404);
    $f = $_FILES['csv'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size'] > 1048576 || strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'csv') {
        flash('error', 'Upload a .csv file smaller than 1 MB.'); redirect('/admin/quizzes/' . $q['id'] . '/questions');
    }
    $h = fopen($f['tmp_name'], 'r');
    $head = fgetcsv($h, 0, ',', '"', '\\');
    if ($head) $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$head[0]);
    $head = array_map(fn($x) => strtolower(trim((string)$x)), $head ?: []);
    foreach (['question', 'type', 'correct'] as $need) if (!in_array($need, $head, true)) { fclose($h); flash('error', "Missing required column: $need. Download the template."); redirect('/admin/quizzes/' . $q['id'] . '/questions'); }
    $items = []; $err = []; $row = 1;
    while (($r = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
        $row++;
        if (count(array_filter($r, fn($x) => trim((string)$x) !== '')) === 0) continue;
        $c = []; foreach ($head as $i => $name) $c[$name] = trim((string)($r[$i] ?? ''));
        $type = strtolower($c['type'] ?? '');
        $marks = (int)(($c['marks'] ?? '') === '' ? 1 : $c['marks']);
        if (($c['question'] ?? '') === '') $err[] = "Row $row: question text is empty.";
        if (!in_array($type, ['mcq', 'tf'], true)) $err[] = "Row $row: type must be mcq or tf.";
        if ($marks < 1 || $marks > 100) $err[] = "Row $row: marks must be 1–100.";
        $opts = []; $correct = -1;
        if ($type === 'tf') {
            $opts = ['True', 'False'];
            $cv = strtolower($c['correct'] ?? '');
            if (!in_array($cv, ['true', 'false'], true)) $err[] = "Row $row: correct must be true or false.";
            else $correct = $cv === 'true' ? 0 : 1;
        } elseif ($type === 'mcq') {
            foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $L) if (($c["option_$L"] ?? '') !== '') $opts[$L] = $c["option_$L"];
            $cl = strtolower($c['correct'] ?? '');
            if (count($opts) < 2) $err[] = "Row $row: at least two options are required.";
            elseif (!isset($opts[$cl])) $err[] = "Row $row: correct must be the letter of a filled option (A–F).";
            else $correct = array_search($cl, array_keys($opts), true);
            $opts = array_values($opts);
        }
        if (count($items) >= 500) { $err[] = 'More than 500 questions in one file.'; break; }
        $items[] = [$type, $c['question'] ?? '', $marks, $c['explanation'] ?? '', $opts, $correct];
    }
    fclose($h);
    if (!$items) $err[] = 'The file contains no questions.';
    if ($err) { flash('error', 'Nothing was imported. ' . implode(' ', array_slice($err, 0, 8)) . (count($err) > 8 ? ' …and ' . (count($err) - 8) . ' more.' : '')); redirect('/admin/quizzes/' . $q['id'] . '/questions'); }
    tx(function () use ($q, $items) {
        $sort = (int)db_val('SELECT COALESCE(MAX(sort_order),0) FROM questions WHERE quiz_id=?', [$q['id']]);
        foreach ($items as [$type, $body, $marks, $expl, $opts, $correct]) {
            $qid = db_insert('INSERT INTO questions(quiz_id,type,body,marks,sort_order,explanation) VALUES(?,?,?,?,?,?)', [$q['id'], $type, mb_substr($body, 0, 1000), $marks, ++$sort, mb_substr($expl, 0, 1000)]);
            foreach ($opts as $i => $o) db_exec('INSERT INTO question_options(question_id,body,is_correct,sort_order) VALUES(?,?,?,?)', [$qid, mb_substr($o, 0, 300), $i === $correct ? 1 : 0, $i + 1]);
        }
    });
    audit('questions_imported', 'quiz', $q['id'], count($items) . ' questions from CSV');
    flash('success', count($items) . ' questions imported.');
    redirect('/admin/quizzes/' . $q['id'] . '/questions');
});
