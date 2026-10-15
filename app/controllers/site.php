<?php
declare(strict_types=1);

function public_quizzes(int $limit = 0): array {
    return db_all("SELECT z.id,z.title,z.description,z.category,z.duration_minutes,z.opens_at,z.closes_at,z.status,(SELECT COUNT(*) FROM questions q WHERE q.quiz_id=z.id) qcount
        FROM quizzes z WHERE z.status IN ('scheduled','published') AND z.closes_at > ? ORDER BY z.opens_at" . ($limit ? " LIMIT $limit" : ''), [now()]);
}
function impact_stats(): array {
    $P = PART_COND;
    return [
        'participants' => (int)db_val("SELECT COUNT(*) FROM users u WHERE $P AND u.status<>'deleted'"),
        'delivered' => (int)db_val("SELECT COUNT(DISTINCT quiz_id) FROM quiz_attempts WHERE status IN ('submitted','timed_out','abandoned')"),
        'completed' => (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE status='submitted'"),
        'competitions' => (int)db_val("SELECT COUNT(DISTINCT quiz_id) FROM official_results WHERE published_at IS NOT NULL"),
    ];
}
route('GET', '/', function () {
    [$programs, $okP] = guarded(fn() => public_quizzes(6));
    [$stats, $okS] = guarded('impact_stats', ['participants' => 0, 'delivered' => 0, 'completed' => 0, 'competitions' => 0]);
    $cats = array_values(array_unique(array_column($programs, 'category')));
    $org = setting('org_name', 'Voice of Women in Tech');
    $about = setting('about_text', "Voice of Women in Tech helps women and girls build digital skills, understand online safety and take part in technology learning opportunities. Through practical programs and friendly competitions, we make learning about cybersecurity and technology accessible to everyone.");
    $title = $org . ' — Empowering Women. Building a Safer Digital Future.';
    $desc = 'Join ' . $org . ' to develop digital skills, improve cybersecurity awareness, and take part in technology learning opportunities and competitions.';
    view('site/home', compact('programs', 'okP', 'stats', 'okS', 'cats', 'about', 'org') + ['pageTitle' => '', 'meta' => seo($title, $desc, '/') . '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $org, 'url' => (string)env('APP_URL', ''), 'logo' => rtrim((string)env('APP_URL', ''), '/') . url('/assets/images/logo.png')], JSON_UNESCAPED_SLASHES) . '</script>'], 'site');
});
route('GET', '/quizzes', function () {
    [$rows, $ok] = guarded(fn() => public_quizzes());
    view('site/quizzes', ['rows' => $rows, 'ok' => $ok, 'pageTitle' => 'Cybersecurity Quizzes', 'meta' => seo('Cybersecurity Quizzes', 'Browse the quizzes and competitions currently published by ' . setting('org_name') . '.', '/quizzes')], 'site');
});
route('GET', '/privacy', fn() => view('site/privacy', ['pageTitle' => 'Privacy Policy', 'meta' => seo('Privacy Policy', 'How we collect, use and protect your information.', '/privacy')], 'site'));
route('GET', '/terms', fn() => view('site/terms', ['pageTitle' => 'Terms and Conditions', 'meta' => seo('Terms and Conditions', 'The rules for using the quiz platform.', '/terms')], 'site'));
route('GET', '/robots.txt', function () {
    header('Content-Type: text/plain; charset=utf-8');
    $b = rtrim((string)env('APP_URL', ''), '/');
    echo "User-agent: *\nDisallow: /admin\nDisallow: /attempt\nDisallow: /account\nDisallow: /dashboard\nDisallow: /profile\nDisallow: /avatar\nAllow: /\n" . ($b ? "Sitemap: $b/sitemap.xml\n" : '');
    exit;
});
route('GET', '/sitemap.xml', function () {
    $b = rtrim((string)env('APP_URL', ''), '/');
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach (['/', '/quizzes', '/register', '/login', '/privacy', '/terms'] as $p) echo '<url><loc>' . e($b . $p) . '</loc></url>';
    echo '</urlset>';
    exit;
});
