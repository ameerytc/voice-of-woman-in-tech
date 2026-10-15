#!/bin/sh
# Proves a backup can really be restored. Restores a dump into an EMPTY scratch database and compares row counts.
# Usage: RESTORE_TEST_URL=postgresql://.../scratch SOURCE_URL=postgresql://.../live sh scripts/pg_restore_test.sh backups/vw_XXXX.dump
# NEVER point RESTORE_TEST_URL at the live database (it is overwritten with --clean). SOURCE_URL is only read.
set -eu
: "${RESTORE_TEST_URL:?Set RESTORE_TEST_URL to an empty scratch database}"
FILE="${1:?Usage: pg_restore_test.sh backup.dump}"
if [ -n "${SOURCE_URL:-}" ] && [ "$SOURCE_URL" = "$RESTORE_TEST_URL" ]; then echo "Refusing: RESTORE_TEST_URL equals SOURCE_URL." >&2; exit 1; fi
pg_restore --clean --if-exists --no-owner --no-privileges --dbname="$RESTORE_TEST_URL" "$FILE"
TABLES="users quizzes questions quiz_versions quiz_attempts attempt_answers official_results official_result_entries audit_logs notifications user_avatars"
FAIL=0
for t in $TABLES; do
  R=$(psql "$RESTORE_TEST_URL" -Atc "SELECT COUNT(*) FROM $t")
  if [ -n "${SOURCE_URL:-}" ]; then S=$(psql "$SOURCE_URL" -Atc "SELECT COUNT(*) FROM $t"); else S="n/a"; fi
  printf '%-26s restored=%-8s live=%s\n' "$t" "$R" "$S"
  if [ "$S" != "n/a" ] && [ "$R" -gt "$S" ]; then FAIL=1; fi   # live may have newer rows; restored must never exceed live
done
psql "$RESTORE_TEST_URL" -Atc "SELECT 'latest migration: ' || MAX(name) FROM schema_migrations"
[ "$FAIL" = "0" ] && echo "RESTORE TEST: archive restored and tables are readable." || { echo "RESTORE TEST: counts look wrong" >&2; exit 1; }
