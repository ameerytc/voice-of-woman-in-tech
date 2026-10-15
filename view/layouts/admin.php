<?php
$me = current_user();
$pending = (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE eligibility='pending_review'");
$newSub = (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE id > ? AND status<>'in_progress'", [$me['admin_seen_attempt_id'] ?? 0]);
$reqs = has_perm('participants.manage') ? (int)db_val("SELECT COUNT(*) FROM data_requests WHERE status='open'") : 0;
$bell = $pending + $newSub + $reqs;
$path = request_path();
$nav = [
  ['Overview', '/admin', 'home', true], ['Participants', '/admin/participants', 'users', has_perm('participants.view')], ['Quizzes', '/admin/quizzes', 'quiz', has_perm('quizzes.manage')],
  ['Questions', '/admin/questions', 'help', has_perm('quizzes.manage')], ['Submissions', '/admin/submissions', 'inbox', has_perm('submissions.view')], ['Results and Winners', '/admin/results', 'trophy', has_perm('results.view')],
  ['Verification', '/admin/verification', 'shield', has_perm('submissions.view')], ['Reports', '/admin/reports', 'chart', has_perm('reports.view')], ['Announcements', '/admin/announcements', 'mega', has_perm('announcements.manage')],
  ['Settings', '/admin/settings', 'cog', has_perm('settings.manage')], ['Administrator Accounts', '/admin/admins', 'key', has_perm('admins.manage')], ['Audit Logs', '/admin/audit', 'log', has_perm('audit.view')],
];
$myAv = ['id' => $me['id'], 'full_name' => $me['full_name'], 'has_avatar' => has_avatar((int)$me['id'])];
include __DIR__ . '/_head.php'; ?>
<body class="admin">
<a class="skip" href="#main">Skip to main content</a>
<header class="admin-top">
  <button class="navtoggle" type="button" data-toggle="#sidebar" aria-label="Menu" aria-expanded="false"><?= icon('menu') ?></button>
  <form class="top-search" method="get" action="<?= e(url('/admin/search')) ?>" role="search"><?= icon('search', 18) ?><input type="search" name="q" placeholder="Search participants and quizzes" aria-label="Search" value="<?= e(get('q')) ?>"></form>
  <div class="hello"><b>Welcome, <?= e(explode(' ', (string)$me['full_name'])[0]) ?></b><small><?= e(fmt_dt(now(), 'l, d F Y')) ?></small></div>
  <span class="grow"></span>
  <button class="iconbtn" type="button" data-theme-toggle aria-label="Switch light / dark theme" title="Switch theme"><?= icon('moon', 20) ?></button>
  <details class="dd"><summary class="iconbtn" aria-label="Notifications"><?= icon('bell', 20) ?><?php if ($bell): ?><span class="count"><?= $bell ?></span><?php endif; ?></summary>
    <div class="dd-menu"><strong class="fine" style="padding:.4rem .7rem;display:block">Needs your attention</strong>
      <a href="<?= e(url('/admin/verification')) ?>"><?= icon('shield', 18) ?> <?= $pending ?> pending review</a>
      <a href="<?= e(url('/admin/submissions')) ?>"><?= icon('inbox', 18) ?> <?= $newSub ?> new submission(s)</a>
      <?php if (has_perm('participants.manage')): ?><a href="<?= e(url('/admin/participants')) ?>"><?= icon('users', 18) ?> <?= $reqs ?> privacy request(s)</a><?php endif; ?></div></details>
  <details class="dd"><summary class="iconbtn" style="padding:0;overflow:hidden" aria-label="Account menu"><?= avatar_html($myAv, 40) ?></summary>
    <div class="dd-menu"><p style="margin:.4rem .7rem"><b><?= e($me['full_name']) ?></b><br><small><?= e($me['email']) ?></small></p><hr>
      <form method="post" action="<?= e(url('/account/avatar')) ?>" enctype="multipart/form-data"><?= csrf_field() ?><label class="linkbtn" style="margin:0;cursor:pointer;display:flex;gap:.6rem;align-items:center;padding:.6rem .7rem"><?= icon('camera', 18) ?> Change photo<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-autosubmit data-preview="#none" hidden></label></form>
      <a href="<?= e(url('/account/password')) ?>"><?= icon('lock', 18) ?> Change password</a><hr>
      <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="linkbtn" type="submit"><?= icon('out', 18) ?> Logout</button></form></div></details>
</header>
<div class="admin-shell">
  <nav id="sidebar" class="sidebar" aria-label="Administrator">
    <a class="sb-brand" href="<?= e(url('/admin')) ?>"><img src="<?= e(asset('images/logo.png')) ?>" width="40" height="40" alt=""><span><?= e(setting('org_name', 'Voice of Women in Tech')) ?></span></a>
    <?php foreach ($nav as [$label, $href, $ic, $show]): if (!$show) continue; $on = $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href); ?>
      <a href="<?= e(url($href)) ?>" class="<?= $on ? 'on' : '' ?>"><?= icon($ic) ?><?= e($label) ?>
        <?php if ($href === '/admin/submissions' && $newSub): ?><span class="count" id="pulse-new"><?= $newSub ?></span><?php endif; ?>
        <?php if ($href === '/admin/verification' && $pending): ?><span class="count warn" id="pulse-pending"><?= $pending ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit" class="linkbtn"><?= icon('out') ?>Logout</button></form>
  </nav>
  <main class="admin-main" id="main"><?php include __DIR__ . '/_flash.php'; ?><?= $content ?></main>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/admin.js')) ?>" data-pulse="<?= e(url('/admin/api/pulse')) ?>"></script>
</body></html>
