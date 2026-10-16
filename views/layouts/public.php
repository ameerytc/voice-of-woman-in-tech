<?php include __DIR__ . '/_head.php'; ?>
<body class="galaxy auth-body"><div class="stars" aria-hidden="true"></div>
<main class="auth-wrap" id="main">
  <a class="brand-big" href="<?= e(url('/')) ?>"><img src="<?= e(asset('images/logo.png')) ?>" width="60" height="60" alt=""><span><?= e(setting('org_name', 'Voice of Women in Tech')) ?></span></a>
  <?php include __DIR__ . '/_flash.php'; ?>
  <?= $content ?>
  <p class="fine center">Your phone number and email are private and never shown publicly.</p>
</main>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body></html>
