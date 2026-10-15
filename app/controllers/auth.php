<?php
declare(strict_types=1);

const STATES = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','FCT Abuja','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba','Yobe','Zamfara','Outside Nigeria'];

function active_age_groups(): array { return db_all('SELECT * FROM age_groups WHERE is_active=1 ORDER BY sort_order, id'); }

function after_login(array $u): never {
    if (!empty($u['must_change_password'])) redirect('/account/password');
    if (user_roles((int)$u['id'])) redirect('/admin');
    $to = $_SESSION['intended'] ?? '';
    unset($_SESSION['intended']);
    redirect(($to !== '' && $to[0] === '/' && !str_starts_with($to, '//')) ? $to : '/dashboard');
}


route('GET', '/register', function () {
    if (current_user()) after_login(current_user());
    view('auth/register', ['ages' => active_age_groups()], 'public');
});
route('POST', '/register', function () {
    $name = post('full_name'); $email = strtolower(post('email')); $phoneRaw = post('phone');
    $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password_confirm'] ?? '');
    $age = (int)post('age_group_id'); $state = post('state'); $gender = post('gender');
    $err = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $err[] = 'Enter your full name.';
    if (!valid_email($email)) $err[] = 'Enter a valid email address.';
    $phone = normalize_phone($phoneRaw);
    if (!$phone) $err[] = 'Enter a valid phone number, for example 0803 123 4567.';
    if ($e = valid_password($pw)) $err[] = $e;
    if ($pw !== $pw2) $err[] = 'The two passwords do not match.';
    if (!db_val('SELECT 1 FROM age_groups WHERE id=? AND is_active=1', [$age])) $err[] = 'Select your age group.';
    if ($state !== '' && !in_array($state, STATES, true)) $state = '';
    if (!in_array($gender, ['Female', 'Male', 'Prefer not to say'], true) || setting('collect_gender') !== '1') $gender = '';
    if (!$err && db_val('SELECT 1 FROM users WHERE email=?', [$email])) $err[] = 'An account with this email already exists. Please log in instead.';
    if ($err) { foreach ($err as $m) flash('error', $m); keep_old(); redirect('/register'); }
    try {
        $id = db_insert('INSERT INTO users(full_name,email,phone,password_hash,age_group_id,state,gender) VALUES(?,?,?,?,?,?,?)',
            [$name, $email, $phone, password_hash($pw, PASSWORD_DEFAULT), $age, $state ?: null, $gender ?: null]);
    } catch (PDOException $ex) { flash('error', 'An account with this email already exists. Please log in instead.'); keep_old(); redirect('/register'); }
    $u = db_one('SELECT * FROM users WHERE id=?', [$id]);
    if (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try { [$mime, $bytes] = process_avatar_upload($_FILES['avatar']); save_avatar((int)$id, $mime, $bytes); }
        catch (UserError $e) { flash('warn', 'Your account was created, but the picture was not saved: ' . $e->getMessage() . ' You can add it from your profile.'); }
    }
    notify_user($id, 'Welcome to ' . setting('org_name', 'Voice of Women in Tech'), 'Your account is ready. Open Available Quizzes on your dashboard to take part.', '/dashboard');
    login_user($u);
    flash('success', 'Account created. Welcome!');
    redirect('/dashboard');
});

route('GET', '/login', function () { if (current_user()) after_login(current_user()); view('auth/login', ['admin' => false], 'public'); });
route('GET', '/admin/login', function () { if (current_user()) after_login(current_user()); view('auth/login', ['admin' => true], 'public'); });
$doLogin = function (bool $admin) {
    [$u, $err] = attempt_login(post('email'), (string)($_POST['password'] ?? ''));
    if (!$u) { flash('error', $err); keep_old(['email' => post('email')]); redirect($admin ? '/admin/login' : '/login'); }
    if ($admin && !user_roles((int)$u['id'])) { flash('error', 'This sign-in page is for administrators. Participants please use the main login.'); redirect('/login'); }
    login_user($u);
    audit('login', 'user', $u['id'], $admin ? 'admin sign-in' : null);
    after_login($u);
};
route('POST', '/login', fn() => $doLogin(false));
route('POST', '/admin/login', fn() => $doLogin(true));
route('POST', '/logout', function () { $admin = is_admin(); logout_user(); session_start(); session_regenerate_id(true); flash('success', 'You have been logged out.'); redirect($admin ? '/admin/login' : '/login'); });

/* ----- forgot / reset password ----- */
route('GET', '/forgot-password', fn() => view('auth/forgot', ['mail' => mail_configured()], 'public'));
route('POST', '/forgot-password', function () {
    if (!mail_configured()) { flash('error', 'Password recovery by email is not available because email (SMTP) has not been configured on this server. Please contact the organisation administrator to reset your password.'); redirect('/forgot-password'); }
    $email = strtolower(post('email'));
    $u = valid_email($email) ? db_one("SELECT * FROM users WHERE email=? AND status='active'", [$email]) : null;
    if ($u && !throttled('reset:' . $email, client_ip())) {
        $tok = bin2hex(random_bytes(32));
        db_exec('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,?)', [$u['id'], hash('sha256', $tok), gmdate('Y-m-d H:i:s', time() + 3600)]);
        record_login('reset:' . $email, false);
        $link = rtrim((string)env('APP_URL', ''), '/') . url('/reset-password/' . $tok);
        $sent = send_mail($u['email'], 'Reset your password', "Hello {$u['full_name']},\n\nUse this link within 1 hour to choose a new password:\n$link\n\nIf you did not ask for this, ignore this message.");
        if (!$sent) app_log('Reset email could not be delivered to user ' . $u['id']);
    }
    flash('success', 'If an account exists for that email, a reset link has been sent. The link is valid for 1 hour.');
    redirect('/forgot-password');
});
function reset_token_row(string $tok): ?array {
    return db_one('SELECT * FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>?', [hash('sha256', $tok), now()]);
}
route('GET', '/reset-password/{token}', function ($p) {
    if (!reset_token_row($p['token'])) { flash('error', 'This reset link is invalid or has expired.'); redirect('/forgot-password'); }
    view('auth/reset', ['token' => $p['token']], 'public');
});
route('POST', '/reset-password/{token}', function ($p) {
    $row = reset_token_row($p['token']);
    if (!$row) { flash('error', 'This reset link is invalid or has expired.'); redirect('/forgot-password'); }
    $pw = (string)($_POST['password'] ?? '');
    if ($e = valid_password($pw)) { flash('error', $e); redirect('/reset-password/' . $p['token']); }
    if ($pw !== (string)($_POST['password_confirm'] ?? '')) { flash('error', 'The two passwords do not match.'); redirect('/reset-password/' . $p['token']); }
    tx(function () use ($row, $pw) {
        db_exec('UPDATE users SET password_hash=?, must_change_password=0, updated_at=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), now(), $row['user_id']]);
        db_exec('UPDATE password_reset_tokens SET used_at=? WHERE user_id=? AND used_at IS NULL', [now(), $row['user_id']]);
    });
    flash('success', 'Password changed. You can now log in.');
    redirect('/login');
});

/* ----- change password (participants and admins) ----- */
route('GET', '/account/password', function () {
    $u = current_user(); if (!$u) redirect('/login');
    view('auth/password', ['u' => $u, 'forced' => (int)$u['must_change_password'] === 1], is_admin() ? 'admin' : 'participant');
});
route('POST', '/account/password', function () {
    $u = current_user(); if (!$u) redirect('/login');
    $cur = (string)($_POST['current_password'] ?? ''); $new = (string)($_POST['new_password'] ?? '');
    if (!password_verify($cur, $u['password_hash'])) { flash('error', 'Your current password is incorrect.'); redirect('/account/password'); }
    if ($e = valid_password($new)) { flash('error', $e); redirect('/account/password'); }
    if ($new !== (string)($_POST['new_password_confirm'] ?? '')) { flash('error', 'The two new passwords do not match.'); redirect('/account/password'); }
    db_exec('UPDATE users SET password_hash=?, must_change_password=0, updated_at=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), now(), $u['id']]);
    session_regenerate_id(true);
    audit('password_changed', 'user', $u['id']);
    flash('success', 'Password updated.');
    redirect(is_admin() ? '/admin' : '/dashboard');
});
