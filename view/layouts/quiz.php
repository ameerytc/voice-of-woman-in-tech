<?php include __DIR__ . '/_head.php'; ?>
<body class="galaxy quiz-body"><div class="stars" aria-hidden="true"></div>
<header class="qbar"><div class="wrap bar">
  <span class="brand"><img src="<?= e(asset('images/logo.png')) ?>" width="36" height="36" alt=""><span><?= e($title ?? 'Quiz') ?></span></span>
  <span class="timer" id="timer" role="timer" aria-label="Time left">--:--</span>
</div></header>
<main class="wrap narrow" id="main"><?php include __DIR__ . '/_flash.php'; ?><?= $content ?></main>
</body></html>
