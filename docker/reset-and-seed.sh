#!/usr/bin/env bash
# =============================================================================
# KMP — In-container reset-and-seed (engine-agnostic, backup-based)
#
# Runs inside the production Docker image (as an Azure Container Apps Job or
# `docker run`). Restores the bundled encrypted backup to produce a
# deterministic environment state.
#
#   1. bin/cake resetDatabase               — drop & recreate schema
#   2. bin/cake updateDatabase              — apply core + plugin migrations
#   3. bin/cake backup restore <seed>       — restore dev data from .kmpbackup
#   4. Reset every member password          — set to TestPassword via ORM
#   5. Clear CakePHP caches
#
# Seeding works on MySQL/MariaDB AND Postgres because restore is ORM-based.
# The image ships the encrypted backup at /opt/kmp/seed/nightly-seed.kmpbackup
# (see deploy/azure/seed/ for how it's produced).
#
# Environment:
#   BACKUP_ENCRYPTION_KEY   — required unless KMP_SKIP_SEED=true
#   KMP_SEED_FILE           — override seed path (default: auto-detected)
#   KMP_SKIP_SEED=true      — do schema reset only, no data restore
#   DOCUMENT_STORAGE_ADAPTER=local — recommended, forces backup storage to
#                                    use the local filesystem (not blob).
#
# The web container's entrypoint is expected to have already:
#   - generated config/app_local.php from DATABASE_URL / *_* env vars
#   - waited for the database to accept connections
# because in Azure Container Apps Jobs the container command is:
#   /usr/local/bin/docker-entrypoint.sh /opt/kmp/reset-and-seed.sh
# so the entrypoint runs before this script. When called directly (no
# entrypoint) we re-trigger config generation below.
# =============================================================================
set -euo pipefail

echo "=== KMP reset-and-seed ==="

APP_DIR="/var/www/html"
# Prefer a bundled image path; fall back to the older default for back-compat.
if [[ -z "${KMP_SEED_FILE:-}" ]]; then
    if [[ -f /opt/kmp/seed/nightly-seed.kmpbackup ]]; then
        SEED_FILE="/opt/kmp/seed/nightly-seed.kmpbackup"
    else
        SEED_FILE="/opt/kmp/seed/nightly-seed.kmpbackup"  # expected path; error handled below
    fi
else
    SEED_FILE="$KMP_SEED_FILE"
fi

cd "$APP_DIR"

# The entrypoint normally regenerates config/app_local.php. When this script
# runs as a Job, the entrypoint has already executed. If called directly,
# trigger entrypoint just to build config.
if [[ ! -f "$APP_DIR/config/app_local.php" ]]; then
    echo "app_local.php missing — re-running entrypoint for config only..."
    KMP_SKIP_CRON=true KMP_SKIP_MIGRATIONS=true \
        /usr/local/bin/docker-entrypoint.sh /bin/true
fi

run_cake() {
    CACHE_ENGINE=apcu bin/cake "$@"
}

# Step 1: drop all tables. `resetDatabase` requires DEBUG=true (by design —
# it's a destructive command). Override only for this call; the rest of the
# job runs with production defaults.
echo "[1/5] Resetting database schema (bin/cake resetDatabase)..."
DEBUG=true run_cake resetDatabase

# Step 2: apply migrations (updateDatabase runs core + every active plugin's
# migrations; no need to call `migrations migrate` separately). Fail hard —
# if migrations don't apply, nothing downstream will work.
echo "[2/5] Applying migrations (bin/cake updateDatabase)..."
run_cake updateDatabase

# Step 3: restore from encrypted seed backup (skippable for schema-only resets)
if [[ "${KMP_SKIP_SEED:-false}" == "true" ]]; then
    echo "[3/5] KMP_SKIP_SEED=true — skipping data restore"
else
    if [[ ! -f "$SEED_FILE" ]]; then
        echo "ERROR: seed backup $SEED_FILE not found in image." >&2
        echo "  Bake one with deploy/azure/seed/bake-seed.sh, commit it to" >&2
        echo "  deploy/azure/seed/nightly-seed.kmpbackup, and rebuild the image." >&2
        echo "  Or set KMP_SKIP_SEED=true for a schema-only reset." >&2
        exit 1
    fi
    : "${BACKUP_ENCRYPTION_KEY:?BACKUP_ENCRYPTION_KEY required to decrypt seed backup}"

    # The backup restore CLI reads from the configured backup storage adapter
    # (see BackupStorageService). For this flow we want the local filesystem,
    # not Azure Blob — callers should set DOCUMENT_STORAGE_ADAPTER=local.
    BACKUPS_DIR="$APP_DIR/backups"
    mkdir -p "$BACKUPS_DIR"
    SEED_BASENAME="$(basename "$SEED_FILE")"
    if [[ "$SEED_FILE" != "$BACKUPS_DIR/$SEED_BASENAME" ]]; then
        cp "$SEED_FILE" "$BACKUPS_DIR/$SEED_BASENAME"
    fi

    size=$(stat -c%s "$BACKUPS_DIR/$SEED_BASENAME" 2>/dev/null || stat -f%z "$BACKUPS_DIR/$SEED_BASENAME")
    echo "[3/5] Restoring $SEED_BASENAME ($((size/1024)) KB) via bin/cake backup restore..."
    run_cake backup restore "$SEED_BASENAME" \
        --key "$BACKUP_ENCRYPTION_KEY" \
        --yes \
        --fail-on-not-valid-fk
fi

# Step 4: enforce the known dev password for every member so stakeholders can
# log in. Idempotent.
echo "[4/5] Resetting ALL member passwords to TestPassword..."
php -r '
require "vendor/autoload.php";
require "config/bootstrap.php";
use Cake\ORM\TableRegistry;
$members = TableRegistry::getTableLocator()->get("Members");
$all = $members->find("all");
$count = 0; $errors = 0;
foreach ($all as $m) {
    $m->password = "TestPassword";
    if ($members->save($m)) { $count++; } else { $errors++; fwrite(STDERR, "Failed to save member ID {$m->id}\n"); }
}
echo "Updated passwords for $count members. Errors: $errors\n";
exit($errors > 0 ? 1 : 0);
'

echo "[5/5] Clearing CakePHP caches..."
php -r '
require "vendor/autoload.php";
require "config/bootstrap.php";
use Cake\Cache\Cache;
foreach (Cache::configured() as $config) {
    if (in_array($config, ["_cake_core_", "_cake_routes_"], true)) { continue; }
    try { Cache::clear($config); } catch (Throwable $e) { fwrite(STDERR, "warn: $config: ".$e->getMessage()."\n"); }
}
'

echo "=== reset-and-seed complete ==="
echo "Every member can now log in with password: TestPassword"
