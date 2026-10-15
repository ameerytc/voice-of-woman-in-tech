#!/bin/sh
# Usage: DATABASE_URL=postgresql://... sh scripts/pg_backup.sh [target-dir]
# Needs pg_dump (postgresql-client). Use the PUBLIC connection URL when running from your own computer.
# Optional off-platform copy: set BACKUP_S3_URI=s3://bucket/path (and BACKUP_S3_ENDPOINT for R2/B2/MinIO) and install the aws CLI.
set -eu
: "${DATABASE_URL:?Set DATABASE_URL}"
DIR="${1:-./backups}"; mkdir -p "$DIR"
F="$DIR/vw_$(date -u +%Y%m%d_%H%M%S).dump"
pg_dump --format=custom --no-owner --no-privileges --file="$F" "$DATABASE_URL"
pg_restore --list "$F" > /dev/null          # archive must be readable
echo "Backup written: $F ($(wc -c < "$F") bytes)"
if [ -n "${BACKUP_S3_URI:-}" ]; then
  if command -v aws > /dev/null 2>&1; then
    aws s3 cp "$F" "${BACKUP_S3_URI%/}/" ${BACKUP_S3_ENDPOINT:+--endpoint-url "$BACKUP_S3_ENDPOINT"} && echo "Copied off-platform to $BACKUP_S3_URI"
  else echo "BACKUP_S3_URI set but the aws CLI is not installed: NO off-platform copy was made." >&2; exit 2; fi
fi
ls -1t "$DIR"/vw_*.dump 2> /dev/null | tail -n +15 | xargs -r rm -f   # keep the newest 14 locally
