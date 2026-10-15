# Voice of Women in Tech — Quiz, Competition and Reporting Platform (v2: Railway + PostgreSQL + Galaxy UI)

PHP 8.3 · PDO (PostgreSQL in production, SQLite for local development) · HTML5/CSS3 · vanilla JavaScript. No frameworks, no Composer, no external JS/CSS/fonts.

| Area | Where |
|---|---|
| Public landing page, quizzes list, privacy, terms, SEO (`robots.txt`, `sitemap.xml`) | `/`, `/quizzes`, `/privacy`, `/terms` |
| Participant dashboard with profile card + profile picture | `/dashboard`, `/profile` |
| Administrator dashboard (overview charts, search, activity, theme switcher) | `/admin` (sign in at `/admin/login`) |
| Quiz editor (4 tabs), questions + CSV import, submissions, verification, podium results, reports | `/admin/...` |
| Health check for Railway | `/health` |

**Profile pictures**: participants upload a JPG/PNG/WebP while registering (preview shown instantly), on `/profile`, or with the camera button on their dashboard card. The server crops it to a square, re-encodes it (removes EXIF/location data) and stores it **in the database** — so it survives redeploys and is included in backups. Only the owner and administrators can view it. No picture → a gradient initials avatar. (No stock/celebrity photos are bundled; every user uploads their own.)

## 1. Run locally (Windows / Termux / Linux)
1. PHP 8.1+ with `pdo_sqlite` (and `gd` for picture resizing — optional; without it pictures are accepted only up to 1 MB and stored as uploaded).
2. `cp .env.example .env` (set `APP_ENV=development`; leave `DATABASE_URL` empty → SQLite).
3. `php scripts/migrate.php` → creates `storage/database/vw.sqlite`.
4. `php scripts/create_admin.php` (one time; disables itself).
5. `php -S localhost:8000 -t public public/index.php` — development only. Open http://localhost:8000 and http://localhost:8000/admin/login.
6. Tests: `php tests/run.php`.

## 2. Deploy on Railway (summary — full guide in RAILWAY.md)
GitHub repo → Railway project → add **PostgreSQL** → add the repo as a service (Dockerfile is detected) → set variables → generate domain → `php scripts/create_admin.php` once (Railway shell/`railway run`). Migrations run automatically as the **pre-deploy command** (`railway.json`). Do not use `php -S` in production: the Docker image runs Apache.

## 3. Moving your existing SQLite data to PostgreSQL
See RAILWAY.md §5. Short version: `DATABASE_URL=<postgres url> php scripts/migrate_sqlite_to_pg.php storage/database/vw.sqlite --dry-run`, then without `--dry-run`. It backs up the SQLite file, imports in one transaction, validates (row counts, every account's password hash, re-scores every attempt from its saved answer key, orphan checks) and only commits if everything passes. Then compare reports with `scripts/report_snapshot.php` on both databases and test logins with `scripts/check_login.php`. The SQLite file is never modified or deleted.

## 4. What changed in v2
* Database layer works on SQLite **and** PostgreSQL (`app/db.php`): `DATABASE_URL` or `PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD`, named parameters rewritten so they may repeat, `RETURNING id`, row locks, advisory lock during migrations. Timestamps stay UTC text so every existing query and historical record keeps working unchanged.
* Sessions are stored in the database on PostgreSQL (survive redeploys). Logs go to stderr (Railway logs).
* Scoring, attempt limits, ranking, tie-breaks, review exclusion, snapshots and versioning are the **same code** as v1 and are covered by the same tests.
* New: landing page, Galaxy UI, admin overview analytics (SVG charts, no chart library), admin search, activity feed, participant profile card, profile pictures, winners podium + filters, quiz editor tabs, seven reports with age-group / status filters and metric definitions, public-results identity setting (name / first name + initial / participant ID / anonymous), health endpoint, Docker/Railway configuration, migration + backup tooling.

## 5. Security notes
Password hashing (`password_hash`), CSRF on every POST, output escaping, prepared statements, server-side authorisation on every admin route, login throttling (set `TRUSTED_PROXY_HOPS=1` on Railway so the visitor IP is read from the proxy header — verify in the logs), secure/HttpOnly/SameSite cookies, CSP without inline scripts, uploads validated by content (not filename) and re-encoded, audit log for sensitive actions. Never commit `.env`.

## 6. Tests and honest status
`php tests/run.php` (SQLite) and `TEST_DATABASE_URL=postgresql://…/scratch php tests/run.php` (PostgreSQL, scratch schema `vw_test`). The PostgreSQL path, the Docker image, the Railway pre-deploy/health-check settings, the SQLite→PostgreSQL migration, backup and restore scripts were **written but not executed** where this code was produced (no PostgreSQL, Docker or PHP there). Run the checklist in RAILWAY.md §8 and send me any failure. Layout was checked by reading the CSS, not on real phones: please test on an Android phone and an iPhone.

## 7. Known limitations
* Railway backup features depend on your plan — confirm in the dashboard; the scripts here give you a plan-independent `pg_dump` route.
* One role per administrator on screen (schema allows several).
* Browser focus events can be triggered by calls/notifications, hence "flag for review" is the default.
* The SMTP client is minimal; test with your provider.
* Privacy/Terms pages are templates — have them reviewed.
