#!/usr/bin/env bash
# KMP Database Restore Script
# Usage: ./restore.sh backups/2026-02-19-030000.sql.gz

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <backup-file.sql.gz>"
    echo ""
    echo "Examples:"
    echo "  $0 backups/2026-02-19-030000.sql.gz"
    echo "  $0 /path/to/backup.sql.gz"
    echo ""
    echo "Available backups:"
    if [[ -d "${SCRIPT_DIR}/backups" ]]; then
        ls -1t "${SCRIPT_DIR}/backups/"*.sql.gz 2>/dev/null || echo "  (none found)"
    else
        echo "  (backups directory not found)"
    fi
    exit 1
fi

BACKUP_FILE="$1"

if [[ ! -f "${BACKUP_FILE}" ]]; then
    echo "Error: Backup file not found: ${BACKUP_FILE}" >&2
    exit 1
fi

# Load environment
if [[ -f "${SCRIPT_DIR}/.env" ]]; then
    set -a
    # shellcheck disable=SC1091
    source "${SCRIPT_DIR}/.env"
    set +a
else
    echo "Error: .env file not found in ${SCRIPT_DIR}" >&2
    exit 1
fi

DB_NAME="${MYSQL_DB_NAME:-kmp}"
DB_USER="${MYSQL_USERNAME:-kmpuser}"
DB_PASS="${MYSQL_PASSWORD:?MYSQL_PASSWORD must be set in .env}"

BACKUP_SIZE="$(du -h "${BACKUP_FILE}" | cut -f1)"

echo "=== KMP Restore: $(date) ==="
echo "Database: ${DB_NAME}"
echo "Backup:   ${BACKUP_FILE} (${BACKUP_SIZE})"
echo ""
echo "WARNING: This will overwrite the current '${DB_NAME}' database."
read -rp "Continue? [y/N] " confirm
if [[ "${confirm}" != "y" && "${confirm}" != "Y" ]]; then
    echo "Restore cancelled."
    exit 0
fi

echo "Restoring database..."

gunzip -c "${BACKUP_FILE}" | \
    docker compose -f "${SCRIPT_DIR}/docker-compose.yml" exec -T db \
    mysql \
    --user="${DB_USER}" \
    --password="${DB_PASS}" \
    "${DB_NAME}"

echo ""
echo "=== Restore Summary ==="
echo "  File:     ${BACKUP_FILE}"
echo "  Size:     ${BACKUP_SIZE}"
echo "  Database: ${DB_NAME}"
echo "=== Done: $(date) ==="
echo ""
echo "Tip: Restart the app to clear any cached state:"
echo "  docker compose restart app"
