#!/usr/bin/env bash
#
# Blood Donor Management System - restore verification
#
# "A backup exists" and "a backup has been restored and verified" are
# different claims. This script turns the first into the second.
#
# It restores a dump into a THROWAWAY database, checks the schema is
# complete and the data plausible, then drops it. Your live database is
# never touched.
#
# USAGE
#   ./scripts/verify-restore.sh backups/blood_donor_system-20260903-schemaa1b2c3d4.sql.gz
#
# RUN THIS
#   - after setting up backups for the first time
#   - once a quarter as a rehearsal
#   - ALWAYS before restoring a dump onto the live database
#
# WHY: an old dump was restored twice against this system. Both times it
# reported success, and both times it silently rolled the schema
# backwards - dropping camp_expenses and camp_contributions once, and
# the message_templates.whatsapp_* columns the next. The failure was
# invisible until pages went blank. This script would have caught both
# before they touched live data.

set -euo pipefail

DUMP="${1:?usage: verify-restore.sh <dump.sql.gz>}"
SCRATCH="${SCRATCH_DB:-bdms_restore_check}"
MYSQL_BIN="${MYSQL_BIN:-mysql}"
ADMIN_USER="${ADMIN_USER:-root}"

# Tables the current application requires. A restore missing any of
# these is a rollback, not a recovery.
REQUIRED_TABLES="admins blood_camps camp_contributions camp_expenses \
camp_registrations donors message_logs message_templates settings staff"

# Columns added by migrations. These are exactly what the two bad
# restores destroyed, so they are checked by name.
REQUIRED_COLUMNS="message_templates.whatsapp_template_name \
message_templates.whatsapp_language \
message_templates.whatsapp_variables \
blood_camps.budget_amount \
message_logs.staff_id \
staff.mobile"

fail=0
note() { printf '  %-58s %s\n' "$1" "$2"; }

echo "Restoring $DUMP into scratch database '$SCRATCH'"
"$MYSQL_BIN" -u "$ADMIN_USER" -e "DROP DATABASE IF EXISTS \`$SCRATCH\`; CREATE DATABASE \`$SCRATCH\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Strip CREATE DATABASE / USE so the dump cannot redirect itself onto
# the live schema - the dumps contain a hardcoded USE statement.
gunzip -c "$DUMP" \
  | grep -viE '^(CREATE DATABASE|USE )' \
  | "$MYSQL_BIN" -u "$ADMIN_USER" --default-character-set=utf8mb4 "$SCRATCH"

echo
echo "── Schema ──────────────────────────────────────────────────"
for t in $REQUIRED_TABLES; do
    n=$("$MYSQL_BIN" -u "$ADMIN_USER" -N -B -e \
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema='$SCRATCH' AND table_name='$t';")
    if [ "$n" = "1" ]; then note "table $t" "present"
    else note "table $t" "MISSING"; fail=1; fi
done

for c in $REQUIRED_COLUMNS; do
    tbl="${c%%.*}"; col="${c##*.}"
    n=$("$MYSQL_BIN" -u "$ADMIN_USER" -N -B -e \
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema='$SCRATCH' AND table_name='$tbl' AND column_name='$col';")
    if [ "$n" = "1" ]; then note "column $c" "present"
    else note "column $c" "MISSING"; fail=1; fi
done

echo
echo "── Schema version ──────────────────────────────────────────"
# The ledger answers "which migrations does this dump contain?" - the
# question nobody could answer during either of the bad restores.
has_ledger=$("$MYSQL_BIN" -u "$ADMIN_USER" -N -B -e     "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema='$SCRATCH' AND table_name='schema_migrations';")

if [ "$has_ledger" = "1" ]; then
    "$MYSQL_BIN" -u "$ADMIN_USER" -N -B "$SCRATCH" -e         "SELECT CONCAT('  ', filename, '  ', DATE_FORMAT(applied_at,'%Y-%m-%d'))
         FROM schema_migrations ORDER BY filename;"
    dump_count=$("$MYSQL_BIN" -u "$ADMIN_USER" -N -B "$SCRATCH" -e "SELECT COUNT(*) FROM schema_migrations;")
    repo_count=$(ls "$(dirname "$0")/.."/migration-*.sql 2>/dev/null | wc -l | tr -d ' ')
    note "migrations in dump / in repo" "$dump_count / $repo_count"
    if [ "$dump_count" -lt "$repo_count" ]; then
        note "dump is BEHIND the repository" "re-run scripts/migrate.php after restoring"
        fail=1
    fi
else
    note "schema_migrations table" "ABSENT - dump predates the ledger"
    echo "     This dump cannot state which migrations it contains. Treat it"
    echo "     as older than the current code and re-run migrate.php after."
fi

echo
echo "── Data ────────────────────────────────────────────────────"
donors=$("$MYSQL_BIN" -u "$ADMIN_USER" -N -B "$SCRATCH" -e "SELECT COUNT(*) FROM donors;" 2>/dev/null || echo 0)
admins=$("$MYSQL_BIN" -u "$ADMIN_USER" -N -B "$SCRATCH" -e "SELECT COUNT(*) FROM admins;" 2>/dev/null || echo 0)
note "donor rows" "$donors"
note "admin accounts" "$admins"
[ "$donors" -gt 0 ] || { note "donor rows > 0" "FAILED"; fail=1; }
[ "$admins" -gt 0 ] || { note "at least one admin" "FAILED"; fail=1; }

# Sinhala survived the dump/restore round trip. utf8mb4 mistakes turn
# Sinhala into literal "?" - which has happened to this data before.
mangled=$("$MYSQL_BIN" -u "$ADMIN_USER" -N -B "$SCRATCH" --default-character-set=utf8mb4 \
    -e "SELECT COUNT(*) FROM donors WHERE donor_name REGEXP '^[?[:space:]]+$';" 2>/dev/null || echo 0)
if [ "$mangled" = "0" ]; then note "names mangled to '?'" "none"
else note "names mangled to '?'" "$mangled rows - ENCODING LOST"; fail=1; fi

echo
"$MYSQL_BIN" -u "$ADMIN_USER" -e "DROP DATABASE \`$SCRATCH\`;"
echo "Scratch database dropped."
echo

if [ "$fail" -eq 0 ]; then
    echo "RESTORE VERIFIED - this dump is usable."
    echo "Record the date. An unrehearsed backup is not a backup."
else
    echo "RESTORE FAILED VERIFICATION - do NOT restore this dump onto live data."
    echo "If the schema is behind, re-run the migration-*.sql files after"
    echo "restoring; all of them are safely re-runnable."
    exit 1
fi
