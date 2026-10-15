<?php $c = org_contact(); $org = setting('org_name', 'Voice of Women in Tech'); $me = current_user(); $path = request_path();
include __DIR__ . '/_head.php'; ?>
<body class="galaxy"><div class="stars" aria-hidden="true"></div>
<a class="skip" href="#main">Skip to main content</a>
<header class="site-header"><div class="wrap bar">
  <a class="brand" href="<?= e(url('/')) ?>"><img src="<?= e(asset('images/logo.png')) ?>" width="42" height="42" alt=""><span><?= e($org) ?></span></a>
  <button class="navtoggle" type="button" data-toggle="#topnav" aria-label="Menu" aria-expanded="false"><?= icon('menu') ?></button>
  <nav id="topnav" class="topnav" aria-label="Main">
    <a href="<?= e(url('/')) ?>" class="<?= $path === '/' ? 'on' : '' ?>">Home</a>
    <a href="<?= e(url('/#about')) ?>">About</a>
    <a href="<?= e(url('/#programs')) ?>">Our Programs</a>
    <a href="<?= e(url('/quizzes')) ?>" class="<?= $path === '/quizzes' ? 'on' : '' ?>">Cybersecurity Quiz</a>
    <a href="<?= e(url('/#contact')) ?>">Contact</a>
    <?php if ($me): ?><a class="btn btn-sm" href="<?= e(url(is_admin() ? '/admin' : '/dashboard')) ?>">My dashboard</a>
    <?php else: ?><a href="<?= e(url('/login')) ?>">Login</a><a class="btn btn-sm" href="<?= e(url('/register')) ?>">Create Account</a><?php endif; ?>
  </nav>
</div></header>
<main id="main"><?php if (flashes_peek()): ?><div class="wrap"><?php include __DIR__ . '/_flash.php'; ?></div><?php endif; ?><?= $content ?></main>
<footer class="site-footer"><div class="wrap foot-grid">
  <div><a class="brand" href="<?= e(url('/')) ?>"><img src="<?= e(asset('images/logo.png')) ?>" width="44" height="44" alt=""><span><?= e($org) ?></span></a>
    <p class="fine">Empowering women. Building a safer digital future.</p></div>
  <div><h3>Explore</h3><ul><li><a href="<?= e(url('/')) ?>">Home</a></li><li><a href="<?= e(url('/quizzes')) ?>">Cybersecurity Quiz</a></li><li><a href="<?= e(url('/register')) ?>">Create Account</a></li><li><a href="<?= e(url('/login')) ?>">Login</a></li></ul></div>
  <div id="contact-foot"><h3>Contact</h3><ul>
    <?php if ($c['email']): ?><li><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
    <?php if ($c['phone']): ?><li><?= e($c['phone']) ?></li><?php endif; ?>
    <?php if ($c['address']): ?><li><?= e($c['address']) ?></li><?php endif; ?>
    <?php if (!$c['email'] && !$c['phone'] && !$c['address']): ?><li class="fine">Contact details have not been configured yet.</li><?php endif; ?>
    <li><a href="<?= e(url('/privacy')) ?>">Privacy Policy</a> · <a href="<?= e(url('/terms')) ?>">Terms &amp; Conditions</a></li></ul></div>
  <p class="fine" style="grid-column:1/-1">© <?= gmdate('Y') ?> <?= e($org) ?>. All rights reserved.</p>
</div></footer>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body></html>
