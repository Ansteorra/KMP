#!/usr/bin/env bash
# KMP Database Backup Script
# Usage: ./backup.sh [--upload s3|azure|local]
# Cron:  0 3 * * * /opt/kmp/backup.sh --upload local >> /var/log/kmp-backup.log 2>&1

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="${SCRIPT_DIR}/backups"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
TIMESTAMP="$(date +%Y-%m-%d-%H%M%S)"
BACKUP_FILE="${BACKUP_DIR}/${TIMESTAMP}.sql.gz"
UPLOAD_TARGET=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case "$1" in
        --upload)
            UPLOAD_TARGET="${2:-local}"
            shift 2
            ;;
        --retention)
            RETENTION_DAYS="${2:-30}"
            shift 2
            ;;
        -h|--help)
            echo "Usage: $0 [--upload s3|azure|local] [--retention DAYS]"
            echo ""
            echo "Options:"
            echo "  --upload TARGET   Upload backup to: s3, azure, or local (default: no upload)"
            echo "  --retention DAYS  Delete local backups older than DAYS (default: 30)"
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

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

mkdir -p "${BACKUP_DIR}"

echo "=== KMP Backup: $(date) ==="
echo "Database: ${DB_NAME}"
echo "Backup file: ${BACKUP_FILE}"

# Run mysqldump inside the db container
docker compose -f "${SCRIPT_DIR}/docker-compose.yml" exec -T db \
    mysqldump \
    --user="${DB_USER}" \
    --password="${DB_PASS}" \
    --single-transaction \
    --routines \
    --triggers \
    "${DB_NAME}" | gzip > "${BACKUP_FILE}"

BACKUP_SIZE="$(du -h "${BACKUP_FILE}" | cut -f1)"
echo "Backup complete: ${BACKUP_SIZE}"

# Upload if requested
case "${UPLOAD_TARGET}" in
    s3)
        S3_BUCKET="${AWS_BUCKET:?AWS_BUCKET must be set for S3 uploads}"
        S3_PREFIX="${AWS_BACKUP_PREFIX:-kmp-backups}"
        echo "Uploading to s3://${S3_BUCKET}/${S3_PREFIX}/..."
        aws s3 cp "${BACKUP_FILE}" "s3://${S3_BUCKET}/${S3_PREFIX}/${TIMESTAMP}.sql.gz"
        echo "S3 upload complete."
        ;;
    azure)
        AZ_CONTAINER="${AZURE_BACKUP_CONTAINER:?AZURE_BACKUP_CONTAINER must be set for Azure uploads}"
        echo "Uploading to Azure container: ${AZ_CONTAINER}..."
        az storage blob upload \
            --container-name "${AZ_CONTAINER}" \
            --name "kmp-backups/${TIMESTAMP}.sql.gz" \
            --file "${BACKUP_FILE}" \
            --auth-mode login
        echo "Azure upload complete."
        ;;
    local|"")
        # No remote upload; backup stays in local directory
        ;;
    *)
        echo "Warning: Unknown upload target '${UPLOAD_TARGET}', skipping upload." >&2
        ;;
esac

# Rotate old backups
if [[ "${RETENTION_DAYS}" -gt 0 ]]; then
    DELETED=$(find "${BACKUP_DIR}" -name "*.sql.gz" -mtime +"${RETENTION_DAYS}" -print -delete | wc -l)
    echo "Rotation: deleted ${DELETED} backup(s) older than ${RETENTION_DAYS} days."
fi

echo "=== Backup Summary ==="
echo "  File:      ${BACKUP_FILE}"
echo "  Size:      ${BACKUP_SIZE}"
echo "  Upload:    ${UPLOAD_TARGET:-none}"
echo "  Retention: ${RETENTION_DAYS} days"
TOTAL_BACKUPS="$(find "${BACKUP_DIR}" -name "*.sql.gz" | wc -l)"
echo "  Total:     ${TOTAL_BACKUPS} backup(s) on disk"
echo "=== Done: $(date) ==="
