<h1>Audit logs</h1>
<form class="card filters" method="get" action="<?= e(url('/admin/audit')) ?>"><input name="q" type="search" placeholder="Search action, person or detail" value="<?= e(get('q')) ?>"><button class="btn btn-sm" type="submit">Search</button></form>
<div class="table-wrap"><table class="cards-sm"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Target</th><th>Detail</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td data-l="When"><?= e(fmt_dt($r['created_at'], 'd M Y, H:i:s')) ?></td><td data-l="Who"><?= e($r['actor_name']) ?></td><td data-l="Action"><?= e($r['action']) ?></td><td data-l="Target"><?= e(trim(($r['entity_type'] ?? '') . ' ' . ($r['entity_id'] ?? ''))) ?></td><td data-l="Detail"><?= e($r['detail']) ?></td><td data-l="IP"><?= e($r['ip']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No log entries.</td></tr><?php endif; ?></tbody></table></div><?= pager($pg, '/admin/audit') ?>
