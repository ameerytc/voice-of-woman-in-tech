# Railway deployment, migration and backup guide

## 1. Prepare the repository
1. Create a **private** GitHub repository and push this project. `.gitignore`/`.dockerignore` already exclude `.env`, SQLite files, logs and backups. Never commit secrets.
2. Confirm these files exist: `Dockerfile`, `docker/`, `railway.json`, `.env.example`.

## 2. Provision PostgreSQL and the app
1. Railway → **New Project** → **Deploy PostgreSQL** (official Railway Postgres service).
2. In the same project → **New** → **GitHub Repo** → pick the repository. Railway builds the `Dockerfile` (php:8.3-apache with pdo_pgsql, gd, opcache). It listens on `$PORT` automatically (`docker/entrypoint.sh`).
3. Open the app service → **Variables** and add:

| Variable | Value |
|---|---|
| `DATABASE_URL` | `${{Postgres.DATABASE_URL}}` (reference variable → uses the **private network** address) |
| `PGSSLMODE` | `prefer` (private network) |
| `APP_ENV` | `production` |
| `APP_URL` | your final `https://…` address (set after step 4) |
| `APP_TIMEZONE` | `Africa/Lagos` |
| `TRUSTED_PROXY_HOPS` | `1` |
| `ORG_EMAIL`, `ORG_PHONE`, `ORG_ADDRESS` | shown in the public footer |
| `MAIL_*` | optional, for password-reset email |

   If you prefer the individual variables, reference `${{Postgres.PGHOST}}`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD` instead of `DATABASE_URL`. Do not paste real passwords into code or the repository.
4. Service **Settings → Networking → Generate Domain** (or add a custom domain). HTTPS is provided by Railway. Put the address in `APP_URL`.
5. `railway.json` sets: pre-deploy command `php scripts/migrate.php` (runs migrations before each release; safe to repeat; never drops data), health check `/health`, restart on failure. Confirm both appear in the service Settings after the first deploy; if your Railway plan/version does not read them from the file, enter the same values in the dashboard (Pre-deploy command, Healthcheck path).
6. Create the first administrator once: install the Railway CLI, `railway link`, then `railway run php scripts/create_admin.php` — or open the service **Shell**. (If `railway run` runs on your PC, use the public database URL variable shown in the Postgres service for `DATABASE_URL`.)
7. Visit `/health` → `{"status":"ok","database":"up","schema":"ready"}`, then `/admin/login`.

## 3. Deployments never erase participants
Migrations are additive and tracked in `schema_migrations`. Application data (including sessions and profile pictures) lives in PostgreSQL, not on the container disk, so redeploys keep everything.

## 4. Local development against SQLite
Leave `DATABASE_URL` empty; the app uses `storage/database/vw.sqlite`. Keep your local `.env` separate from Railway variables.

## 5. Migrating your existing SQLite data
1. Provision PostgreSQL (§2), deploy once so migrations create the schema (or run `php scripts/migrate.php` against it).
2. On your computer (public database URL from the Postgres service → Connect → Public):
   `DATABASE_URL="postgresql://…" php scripts/migrate_sqlite_to_pg.php storage/database/vw.sqlite --dry-run`
   Read the output: per-table read/inserted/rejected, and the validation checks. Nothing is saved on a dry run.
3. Run again without `--dry-run`. It commits only if every check passes; otherwise it rolls back and PostgreSQL stays empty. A JSON **migration report** is written to `storage/reports/migration_report_*.json`.
4. Compare reports before/after:
   `DB_DRIVER=sqlite php scripts/report_snapshot.php > before.json`
   `DATABASE_URL="postgresql://…" php scripts/report_snapshot.php > after.json`
   `diff before.json after.json` → must show no differences.
5. Test real logins: `DATABASE_URL="postgresql://…" php scripts/check_login.php` (try an admin and a participant). Run a test attempt and a score check in the live app.
6. **Do not delete the SQLite file** until steps 3–5 passed *and* you have made and test-restored a PostgreSQL backup (§6). Keep the verified copy in `storage/backups/`.
7. Then remove `vw.sqlite` from every server/PC you no longer need it on. Never use SQLite in production again.

## 6. Backups
Do not assume backups are on. Do all of these:
1. **Railway-side**: open the Postgres service → **Backups** (available on plans that include it) and enable a schedule (daily). Confirm what your plan offers; Railway's retention and features differ by plan.
2. **Independent dump (works on any plan)**, from a computer with `pg_dump`:
   `DATABASE_URL="<public postgres url>" sh scripts/pg_backup.sh ./backups`
   Schedule it (cron / Windows Task Scheduler) and copy files **off Railway** — e.g. set `BACKUP_S3_URI=s3://bucket/vw` (+ `BACKUP_S3_ENDPOINT` for Cloudflare R2/Backblaze) with the `aws` CLI installed.
3. **Restore procedure**: create an empty database, then
   `pg_restore --clean --if-exists --no-owner --no-privileges --dbname="<target url>" backups/vw_YYYYMMDD_HHMMSS.dump`
   Point the app's `DATABASE_URL` at it and open `/health`.
4. **Recovery test (do this before you trust any backup)**: create a *scratch* database, then
   `RESTORE_TEST_URL="<scratch url>" SOURCE_URL="<live url>" sh scripts/pg_restore_test.sh backups/vw_XXXX.dump`
   It restores and compares row counts for the key tables. Repeat monthly.

## 7. Updating
Push to GitHub → Railway builds → pre-deploy runs migrations → health check passes → traffic switches. If the health check fails, Railway keeps the previous release.

## 8. Pre-launch checklist (tick each yourself)
- [ ] `/health` is `ok`; `/` landing page loads; `/robots.txt`, `/sitemap.xml` load
- [ ] Register with a photo → card shows on dashboard; admin sees the photo on the participant record
- [ ] Admin creates + publishes a quiz; participant takes it on a phone; submission appears in admin Submissions
- [ ] Close the tab mid-quiz and resume; let a timer expire; try a second attempt (must be refused)
- [ ] Review a flagged attempt; finalise results; check the public results identity setting
- [ ] Download a CSV; print a report to PDF
- [ ] `TEST_DATABASE_URL=<scratch> php tests/run.php` all green
- [ ] Redeploy and confirm participants, quizzes, pictures and sessions are still there
- [ ] Backup made, restored into a scratch DB, counts compared
- [ ] Check logs for the visitor IP (login throttling) and adjust `TRUSTED_PROXY_HOPS` if it shows the proxy IP
