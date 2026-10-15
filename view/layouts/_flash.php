<?php foreach (flashes() as [$t, $m]): ?>
  <div class="alert a-<?= e($t) ?>" role="<?= $t === 'error' ? 'alert' : 'status' ?>"><?= e($m) ?></div>
<?php endforeach; unset($_SESSION['old']); ?>
