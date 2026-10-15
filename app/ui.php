<?php
declare(strict_types=1);

/** Inline 24x24 stroke icons (no icon font, no extra request). */
function icon(string $name, int $size = 20): string {
    static $p = [
        'home' => 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10', 'users' => 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 20c0-3.3 3.6-6 8-6s8 2.7 8 6',
        'quiz' => 'M9 4h6v3H9zM6 5h12v16H6zM9 12h6M9 16h4', 'help' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM9.5 9.5a2.5 2.5 0 114 2c-.9.6-1.5 1-1.5 2M12 17h.01',
        'inbox' => 'M4 13l2-8h12l2 8v6H4zM4 13h5l1 2h4l1-2h5', 'trophy' => 'M8 4h8v5a4 4 0 01-8 0zM8 6H4v2a4 4 0 004 4M16 6h4v2a4 4 0 01-4 4M12 13v4M8 21h8M10 17h4',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4', 'chart' => 'M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-3',
        'mega' => 'M3 11v2l11 5V6zM14 8h3a3 3 0 010 6h-3M6 14l1 5h3', 'cog' => 'M4 7h10M18 7h2M4 17h2M10 17h10M14 5v4M8 15v4',
        'key' => 'M15 8a4 4 0 11-3.9 4.9L4 20v-3h3v-2h2v-2h1.5', 'log' => 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6',
        'bell' => 'M6 16V11a6 6 0 1112 0v5l2 2H4zM10 21h4', 'search' => 'M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.3-4.3',
        'sun' => 'M12 17a5 5 0 100-10 5 5 0 000 10zM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4',
        'moon' => 'M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z', 'out' => 'M10 4H5v16h5M15 8l4 4-4 4M19 12H9',
        'camera' => 'M4 8h3l2-3h6l2 3h3v12H4zM12 17a4 4 0 100-8 4 4 0 000 8z', 'cal' => 'M5 5h14v15H5zM5 10h14M9 3v4M15 3v4',
        'clock' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2', 'menu' => 'M4 6h16M4 12h16M4 18h16', 'check' => 'M5 12l5 5 9-10',
        'alert' => 'M12 3l10 18H2zM12 10v5M12 18h.01', 'user' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM5 21c0-4 3-6 7-6s7 2 7 6', 'x' => 'M6 6l12 12M18 6L6 18',
        'play' => 'M7 4l13 8-13 8z', 'doc' => 'M6 3h9l4 4v14H6zM14 3v5h5', 'lock' => 'M6 11h12v9H6zM8 11V8a4 4 0 118 0v3', 'spark' => 'M12 3l2.2 6.3L21 12l-6.8 2.7L12 21l-2.2-6.3L3 12l6.8-2.7z',
    ];
    $d = $p[$name] ?? $p['spark'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
}

/** Line/area chart as inline SVG (no chart library). */
function svg_line(array $labels, array $values, string $unit = ''): string {
    $n = count($values);
    if ($n === 0) return '<p class="empty">No data in this period yet.</p>';
    static $uid = 0; $uid++;
    $W = 640; $H = 240; $pl = 36; $pr = 12; $pt = 14; $pb = 30;
    $max = max(1, ...array_map('floatval', $values));
    $step = ceil($max / 4); $max = $step * 4;
    $x = fn($i) => $pl + ($n === 1 ? ($W - $pl - $pr) / 2 : $i * ($W - $pl - $pr) / ($n - 1));
    $y = fn($v) => $pt + ($H - $pt - $pb) * (1 - $v / $max);
    $s = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Line chart"><defs><linearGradient id="lg' . $uid . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7839D8" stop-opacity=".45"/><stop offset="1" stop-color="#7839D8" stop-opacity="0"/></linearGradient></defs>';
    for ($g = 0; $g <= 4; $g++) { $gy = $y($g * $step); $s .= '<line class="ch-grid" x1="' . $pl . '" x2="' . ($W - $pr) . '" y1="' . round($gy, 1) . '" y2="' . round($gy, 1) . '"/><text class="ch-lab" x="' . ($pl - 6) . '" y="' . round($gy + 4, 1) . '" text-anchor="end">' . ($g * $step) . '</text>'; }
    $pts = []; foreach ($values as $i => $v) $pts[] = round($x($i), 1) . ',' . round($y((float)$v), 1);
    if ($n > 1) $s .= '<polygon fill="url(#lg' . $uid . ')" points="' . round($x(0), 1) . ',' . round($y(0), 1) . ' ' . implode(' ', $pts) . ' ' . round($x($n - 1), 1) . ',' . round($y(0), 1) . '"/><polyline class="ch-line" fill="none" points="' . implode(' ', $pts) . '"/>';
    foreach ($values as $i => $v) {
        $s .= '<circle class="ch-dot" cx="' . round($x($i), 1) . '" cy="' . round($y((float)$v), 1) . '" r="4"><title>' . e($labels[$i] . ': ' . num($v) . $unit) . '</title></circle>';
        if ($i === 0 || $i === $n - 1 || ($n > 4 && $i === intdiv($n, 2))) $s .= '<text class="ch-lab" x="' . round($x($i), 1) . '" y="' . ($H - 8) . '" text-anchor="' . ($i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle')) . '">' . e($labels[$i]) . '</text>';
    }
    return $s . '</svg>';
}
function svg_donut(?float $pct, string $label): string {
    if ($pct === null) return '<p class="empty">Not yet available</p>';
    $r = 52; $c = 2 * M_PI * $r; $d = $c * max(0, min(100, $pct)) / 100;
    return '<div class="donut"><svg viewBox="0 0 140 140" role="img" aria-label="' . e($label . ' ' . num($pct) . '%') . '"><circle class="dn-bg" cx="70" cy="70" r="' . $r . '"/><circle class="dn-fg" cx="70" cy="70" r="' . $r . '" stroke-dasharray="' . round($d, 1) . ' ' . round($c, 1) . '" transform="rotate(-90 70 70)"/></svg><div class="dn-t"><b>' . e(num($pct)) . '%</b><span>' . e($label) . '</span></div></div>';
}
/** Runs a data block; one failing query shows an error card instead of breaking the whole page. */
function guarded(callable $fn, $fallback = []): array {
    try { return [$fn(), true]; } catch (Throwable $e) { app_log('dashboard block failed: ' . $e->getMessage()); return [$fallback, false]; }
}
function state_error(): string { return '<p class="state-error" role="alert">This section could not be loaded. Refresh to try again.</p>'; }
function public_identity(array $entry, int $resultId): string {
    $mode = setting('public_identity', 'masked');
    $uid = (int)($entry['user_id'] ?? 0);
    if ($mode === 'name') return (string)$entry['participant_name'];
    if ($mode === 'id') return 'VW-' . str_pad((string)$uid, 5, '0', STR_PAD_LEFT);
    if ($mode === 'anonymous') {
        $salt = setting('id_salt');
        if ($salt === '') { $salt = bin2hex(random_bytes(16)); set_setting('id_salt', $salt); }
        return 'Participant ' . strtoupper(substr(hash_hmac('sha256', $uid . ':' . $resultId, $salt), 0, 6));
    }
    return mask_name((string)$entry['participant_name']);
}
function org_contact(): array {
    return ['email' => (string)env('ORG_EMAIL', ''), 'phone' => (string)env('ORG_PHONE', ''), 'address' => (string)env('ORG_ADDRESS', ''), 'website' => (string)env('APP_URL', '')];
}
function seo(string $title, string $desc, string $path = '/'): string {
    $base = rtrim((string)env('APP_URL', ''), '/');
    $org = setting('org_name', 'Voice of Women in Tech');
    $h = '<meta name="description" content="' . e($desc) . '">';
    if ($base !== '') $h .= '<link rel="canonical" href="' . e($base . $path) . '"><meta property="og:url" content="' . e($base . $path) . '"><meta property="og:image" content="' . e($base . url('/assets/images/logo.png')) . '">';
    return $h . '<meta property="og:type" content="website"><meta property="og:site_name" content="' . e($org) . '"><meta property="og:title" content="' . e($title) . '"><meta property="og:description" content="' . e($desc) . '"><meta name="twitter:card" content="summary">';
}
