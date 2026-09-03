#!/usr/bin/env bash
#
# Blood Donor Management System - database backup
#
# WHY THIS EXISTS
#   Before this script there was no backup of any kind, while two
#   restores of an old dump silently rolled the schema backwards and
#   destroyed applied migrations. The filename therefore records the
#   schema fingerprint, so a dump can never again be restored without
#   knowing what version it contains.
#
# USAGE
#   ./scripts/backup.sh                 # uses defaults below
#   BACKUP_DIR=/srv/backups ./scripts/backup.sh
#
# CRON (daily 02:15, keeping output for the log)
#   15 2 * * * /path/to/scripts/backup.sh >> /var/log/bdms-backup.log 2>&1
#
# A dump of this database contains 488 people's names, phone numbers
# and blood groups. Store it outside the web root, and off this server.

set -euo pipefail

DB_NAME="${DB_NAME:-blood_donor_system}"
DB_USER="${DB_USER:-bdms_backup}"
BACKUP_DIR="${BACKUP_DIR:-$(cd "$(dirname "$0")/.." && pwd)/backups}"
RETAIN_DAYS="${RETAIN_DAYS:-14}"
MYSQL_BIN="${MYSQL_BIN:-mysql}"
DUMP_BIN="${DUMP_BIN:-mysqldump}"

# Credentials come from ~/.my.cnf or MYSQL_PWD, never from the command
# line - an argument is visible to every user via `ps`.
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

timestamp="$(date +%Y%m%d-%H%M%S)"

# Schema fingerprint: a short hash of every table and column name. Two
# dumps with different fingerprints have different schemas, which is
# exactly the situation that broke this application twice.
schema_hash="$(
  "$MYSQL_BIN" -u "$DB_USER" -N -B "$DB_NAME" -e \
    "SELECT CONCAT(TABLE_NAME,'.',COLUMN_NAME) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA='$DB_NAME' ORDER BY TABLE_NAME, COLUMN_NAME;" \
  | sha256sum | cut -c1-8
)"

outfile="$BACKUP_DIR/${DB_NAME}-${timestamp}-schema${schema_hash}.sql.gz"

echo "[$(date -Is)] dumping $DB_NAME (schema $schema_hash)"

# --single-transaction  : consistent snapshot without locking InnoDB
# --routines --triggers : keep stored programs
# --default-character-set: utf8mb4 or Sinhala names come back as ?
"$DUMP_BIN" \
  -u "$DB_USER" \
  --single-transaction \
  --routines \
  --triggers \
  --default-character-set=utf8mb4 \
  "$DB_NAME" \
| gzip -9 > "$outfile"

chmod 600 "$outfile"

size="$(du -h "$outfile" | cut -f1)"
echo "[$(date -Is)] wrote $outfile ($size)"

# A dump that cannot be decompressed is not a backup. Cheap sanity check.
if ! gzip -t "$outfile"; then
    echo "[$(date -Is)] FAILED: $outfile is corrupt" >&2
    exit 1
fi

# Refuse to keep an implausibly small dump - catches a failed or empty
# export that would otherwise sit there looking like a valid backup.
min_bytes="${MIN_BYTES:-10240}"
actual_bytes="$(wc -c < "$outfile")"
if [ "$actual_bytes" -lt "$min_bytes" ]; then
    echo "[$(date -Is)] FAILED: $outfile is only $actual_bytes bytes" >&2
    exit 1
fi

echo "[$(date -Is)] verified: archive intact, $actual_bytes bytes"

# Retention. Only ever deletes files this script's own naming produced.
find "$BACKUP_DIR" -name "${DB_NAME}-*-schema*.sql.gz" -type f \
     -mtime +"$RETAIN_DAYS" -print -delete

echo "[$(date -Is)] done. Retained: $(find "$BACKUP_DIR" -name "${DB_NAME}-*.sql.gz" | wc -l) archives"
echo
echo "REMINDER: this file is a backup only once it has been restored"
echo "into an empty database and the application used against it."
echo "See scripts/verify-restore.sh"
