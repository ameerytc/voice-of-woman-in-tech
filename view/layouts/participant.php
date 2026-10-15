<?php
$u0 = current_user(); $unread = $u0 ? (int)db_val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL', [$u0['id']]) : 0; $path = request_path();
$tabs = [['/dashboard', 'Home', 'home'], ['/dashboard#quizzes', 'Quizzes', 'quiz'], ['/notifications', 'Alerts', 'bell'], ['/profile', 'Profile', 'user']];
include __DIR__ . '/_head.php'; ?>
<body class="galaxy"><div class="stars" aria-hidden="true"></div>
<a class="skip" href="#main">Skip to main content</a>
<header class="site-header"><div class="wrap bar">
  <a class="brand" href="<?= e(url('/dashboard')) ?>"><img src="<?= e(asset('images/logo.png')) ?>" width="40" height="40" alt=""><span><?= e(setting('org_name', 'Voice of Women in Tech')) ?></span></a>
  <button class="navtoggle" type="button" data-toggle="#topnav" aria-label="Menu" aria-expanded="false"><?= icon('menu') ?></button>
  <nav id="topnav" class="topnav" aria-label="Main">
    <a data-tab href="<?= e(url('/dashboard')) ?>" class="<?= $path === '/dashboard' ? 'on' : '' ?>"><?= icon('home', 18) ?>Dashboard</a>
    <a data-tab href="<?= e(url('/notifications')) ?>"><?= icon('bell', 18) ?>Notifications<?php if ($unread): ?> <span class="count"><?= $unread ?></span><?php endif; ?></a>
    <a data-tab href="<?= e(url('/profile')) ?>"><?= icon('user', 18) ?>My profile</a>
    <a href="<?= e(url('/account/password')) ?>"><?= icon('lock', 18) ?>Password</a>
    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit">Log out</button></form>
  </nav>
</div></header>
<main class="wrap" id="main"><?php include __DIR__ . '/_flash.php'; ?><?= $content ?></main>
<nav class="tabbar" aria-label="Quick navigation"><?php foreach ($tabs as [$h, $l, $i]): ?><a href="<?= e(url($h)) ?>" class="<?= $h === $path ? 'on' : '' ?>"><?= icon($i, 22) ?><span><?= e($l) ?><?= $i === 'bell' && $unread ? ' (' . $unread . ')' : '' ?></span></a><?php endforeach; ?></nav>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body></html>
