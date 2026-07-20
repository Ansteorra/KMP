#!/usr/bin/env bash
# =============================================================================
# bake-seed.sh — produce deploy/azure/seed/nightly-seed.kmpbackup
#
# Creates the encrypted backup that the nightly Azure environment uses to
# seed its database. Run from a machine with a working local KMP dev stack
# after the database is in the state you want to snapshot.
#
# Requires BACKUP_ENCRYPTION_KEY in the environment — this must match the
# `backup-encryption-key` secret in Azure Key Vault for the nightly reset
# job to be able to decrypt the blob it restores.
#
# The script deliberately does NOT run reset_dev_database.sh for you. We
# want the bake step to be explicit about what it's snapshotting — running
# a reset would discard whatever state the maintainer just curated.
# =============================================================================
set -euo pipefail

: "${BACKUP_ENCRYPTION_KEY:?export BACKUP_ENCRYPTION_KEY before running (rotate to match Key Vault secret 'backup-encryption-key')}"

REPO_ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
APP_DIR="$REPO_ROOT/app"
SEED_DIR="$REPO_ROOT/deploy/azure/seed"
OUT_PATH="$SEED_DIR/nightly-seed.kmpbackup"

if [[ ! -f "$APP_DIR/bin/cake" ]]; then
    echo "ERROR: $APP_DIR/bin/cake not found. Are you running from a KMP checkout?" >&2
    exit 1
fi

cd "$APP_DIR"

mkdir -p "$APP_DIR/backups"

echo "=== Baking nightly seed backup ==="
echo "output: $OUT_PATH"
echo

# `bin/cake backup create` auto-generates a name like kmp-backup-YYYYMMDD-HHMMSS.kmpbackup.
# Snapshot the backups/ dir before + after so we know which file it produced.
BEFORE_LIST=$(ls -1 "$APP_DIR/backups" 2>/dev/null | sort || true)

CACHE_ENGINE=apcu bin/cake backup create --key "$BACKUP_ENCRYPTION_KEY"

AFTER_LIST=$(ls -1 "$APP_DIR/backups" 2>/dev/null | sort || true)
NEW_FILE=$(comm -13 <(echo "$BEFORE_LIST") <(echo "$AFTER_LIST") | grep -E '\.kmpbackup$' | tail -1 || true)

if [[ -z "$NEW_FILE" ]]; then
    echo "ERROR: no new .kmpbackup file appeared in $APP_DIR/backups/" >&2
    exit 1
fi

SRC_PATH="$APP_DIR/backups/$NEW_FILE"
mv "$SRC_PATH" "$OUT_PATH"
size=$(stat -c%s "$OUT_PATH" 2>/dev/null || stat -f%z "$OUT_PATH")
echo
echo "Wrote $OUT_PATH ($((size/1024)) KB)"
echo
echo "Next steps:"
echo "  1. git add deploy/azure/seed/nightly-seed.kmpbackup"
echo "  2. git commit -m 'chore(seed): refresh nightly seed backup'"
echo "  3. Make sure Key Vault secret 'backup-encryption-key' matches \$BACKUP_ENCRYPTION_KEY"
