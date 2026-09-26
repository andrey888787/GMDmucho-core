#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/.." && pwd)"
SECRETS="$ROOT/.secrets"
ENV_FILE="$ROOT/.env"
BACKUP_DIR="$ROOT/backups/database"
LOG_DIR="$ROOT/logs"
LOG="$LOG_DIR/db-backup.log"
LOCK="/run/mucho-db-backup.lock"
RETENTION_MINUTES=20160

cd "$ROOT"
mkdir -p "$BACKUP_DIR" "$LOG_DIR"

exec 9>"$LOCK"
if ! flock -n 9; then
    echo "[$(date -Is)] Backup already running, skip." >> "$LOG"
    exit 0
fi

[[ -s "$SECRETS/db_root_password" ]] || {
    echo "[$(date -Is)] ERROR: db_root_password secret is missing" >> "$LOG"
    exit 1
}

DB_NAME="$(sed -n 's/^DB_NAME=//p' "$ENV_FILE" | head -n1)"
SAFE_DB="$(printf '%s' "$DB_NAME" | tr -cd 'A-Za-z0-9_.-')"
[[ -n "$SAFE_DB" ]] || SAFE_DB="muchocore"

compose_args=()
if [[ -n "$(sed -n 's/^MUCHO_TUNNEL_TOKEN=//p' "$ENV_FILE" | head -n1)" ]]; then
    compose_args=(-f docker-compose.yml -f docker-compose.tunnel.yml)
else
    compose_args=()
fi

STAMP="$(date -u +%Y%m%d_%H%M%S)"
FINAL="$BACKUP_DIR/${SAFE_DB}_${STAMP}.sql.gz"
TMP="$FINAL.tmp"

{
    echo
    echo "========================================"
    echo "START $(date -Is)"
    echo "DATABASE=$DB_NAME"
    echo "FILE=$FINAL"
    echo "========================================"
} >> "$LOG"

set +e
docker compose "${compose_args[@]}" exec -T db sh -c '
    set -eu
    exec mariadb-dump \
      -h 127.0.0.1 \
      -u root \
      -p"$(cat /run/secrets/db_root_password)" \
      --single-transaction \
      --quick \
      --triggers \
      --hex-blob \
      --default-character-set=utf8mb4 \
      "$1"
' sh "$DB_NAME" | gzip -9 > "$TMP"
dump_status=${PIPESTATUS[0]}
set -e

if [[ "$dump_status" -ne 0 ]]; then
    rm -f "$TMP"
    echo "[$(date -Is)] ERROR: database dump failed" >> "$LOG"
    exit 1
fi

gzip -t "$TMP" || {
    rm -f "$TMP"
    echo "[$(date -Is)] ERROR: gzip verification failed" >> "$LOG"
    exit 1
}

SIZE="$(stat -c '%s' "$TMP")"
if [[ "$SIZE" -lt 100 ]]; then
    rm -f "$TMP"
    echo "[$(date -Is)] ERROR: backup suspiciously small" >> "$LOG"
    exit 1
fi

mv "$TMP" "$FINAL"
chmod 640 "$FINAL"
sha256sum "$FINAL" > "$FINAL.sha256"
chmod 640 "$FINAL.sha256"

DELETED="$(
    find "$BACKUP_DIR" \
      -type f \
      -mmin +"$RETENTION_MINUTES" \
      \\( -name '*.sql.gz' -o -name '*.sql.gz.sha256' \\) \
      -print -delete | wc -l
)"

HUMAN_SIZE="$(du -h "$FINAL" | awk '{print $1}')"
HASH="$(awk '{print $1}' "$FINAL.sha256")"

{
    echo "BACKUP_OK"
    echo "SIZE=$HUMAN_SIZE"
    echo "SHA256=$HASH"
    echo "OLD_FILES_DELETED=$DELETED"
    echo "FINISH $(date -Is)"
    echo "========================================"
} >> "$LOG"

echo "BACKUP_OK"
echo "FILE=$FINAL"
echo "SIZE=$HUMAN_SIZE"
echo "SHA256=$HASH"
echo "OLD_FILES_DELETED=$DELETED"
