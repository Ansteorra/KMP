#!/usr/bin/env bash
set -euo pipefail

if [ "${KMP_ENABLE_TENANT_CANARY:-}" != "true" ]; then
    echo "Tenant migration canary is disabled. Set KMP_ENABLE_TENANT_CANARY=true to run." >&2
    exit 1
fi

APP_ENVIRONMENT="${APP_ENV:-${CAKE_ENV:-local}}"
if [ "$APP_ENVIRONMENT" = "production" ] && [ "${KMP_ALLOW_PRODUCTION_TENANT_CANARY:-}" != "true" ]; then
    echo "Refusing to run tenant migration canary in production without explicit override." >&2
    exit 1
fi

cd "$(dirname "$0")/.."

CANARY_TOKEN="$(date -u +%Y%m%d%H%M%S)-$$"
CANARY_IDENTIFIER_TOKEN="$(printf '%s' "$CANARY_TOKEN" | tr '-' '_')"
CANARY_SLUG="${KMP_CANARY_TENANT_SLUG:-canary-$CANARY_TOKEN}"
CANARY_HOST="${KMP_CANARY_TENANT_HOST:-$CANARY_SLUG.canary.localhost}"
CANARY_DB_PREFIX="${KMP_CANARY_DB_PREFIX:-kmp_canary}"
CANARY_DB="${KMP_CANARY_DB_NAME:-${CANARY_DB_PREFIX}_${CANARY_IDENTIFIER_TOKEN}}"
CANARY_ROLE="${KMP_CANARY_DB_ROLE:-${CANARY_DB}_role}"
CANARY_BLOB_CONTAINER="${KMP_CANARY_BLOB_CONTAINER:-canary-$CANARY_TOKEN}"
CANARY_SMOKE_TABLE="${KMP_CANARY_SMOKE_TABLE:-members}"
CANARY_SECRET_NAME="tenant.$CANARY_SLUG.db.password"
CANARY_KEK_SECRET_NAME="tenant.$CANARY_SLUG.kek"

export KMP_LOCAL_BACKUPS_ENABLED="${KMP_LOCAL_BACKUPS_ENABLED:-true}"

export CANARY_DB
export CANARY_ROLE
export CANARY_SLUG
export CANARY_SECRET_NAME
export CANARY_KEK_SECRET_NAME

cleanup() {
    if [ "${KMP_CANARY_KEEP:-}" = "true" ]; then
        echo "Keeping canary tenant resources for inspection: $CANARY_SLUG"
        return
    fi

    php <<'PHP' || true
<?php
declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';
require getcwd() . '/config/bootstrap.php';

use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Core\Configure;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;

$slug = (string)getenv('CANARY_SLUG');
$dbName = (string)getenv('CANARY_DB');
$dbRole = (string)getenv('CANARY_ROLE');
$secretName = (string)getenv('CANARY_SECRET_NAME');
$kekSecretName = (string)getenv('CANARY_KEK_SECRET_NAME');

if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/', $slug)) {
    fwrite(STDERR, "Skipping canary cleanup: invalid slug.\n");
    exit(0);
}
if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/', $dbName) || !preg_match('/^[a-z][a-z0-9_]{0,62}$/', $dbRole)) {
    fwrite(STDERR, "Skipping canary database cleanup: invalid identifier.\n");
    exit(0);
}

try {
    $platform = ConnectionManager::get('platform');
    if (!$platform->getDriver() instanceof Postgres) {
        fwrite(STDERR, "Skipping canary database cleanup: platform datasource is not PostgreSQL.\n");
        exit(0);
    }

    $tenantId = $platform->execute('SELECT id FROM tenants WHERE slug = ?', [$slug])->fetchColumn(0);
    if ($tenantId !== false && $tenantId !== null) {
        $backupUris = $platform->execute(
            'SELECT object_uri FROM tenant_backups WHERE tenant_id = ? AND object_uri IS NOT NULL',
            [$tenantId],
        )->fetchAll('assoc');
        cleanupLocalBackupObjects($slug, $backupUris);
        $platform->delete('tenant_hosts', ['tenant_id' => $tenantId]);
        $platform->delete('platform_jobs', ['tenant_id' => $tenantId]);
        $platform->delete('tenants', ['id' => $tenantId]);
    }

    $quotedDb = $platform->getDriver()->quoteIdentifier($dbName);
    $quotedRole = $platform->getDriver()->quoteIdentifier($dbRole);
    $platform->execute('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?', [$dbName]);
    $platform->execute(sprintf('DROP DATABASE IF EXISTS %s', $quotedDb));
    $platform->execute(sprintf('DROP ROLE IF EXISTS %s', $quotedRole));

    $store = SecretStoreFactory::fromConfig();
    if ($store instanceof WritableSecretStoreInterface) {
        $store->delete($secretName);
        $store->delete($kekSecretName);
    }

    fwrite(STDOUT, "Cleaned canary tenant resources: $slug\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Canary cleanup did not complete; inspect disposable resources manually.\n");
}

/**
 * Remove local backup objects produced by the canary marker.
 *
 * @param list<array<string, mixed>> $backupUris Backup rows
 */
function cleanupLocalBackupObjects(string $slug, array $backupUris): void
{
    $root = (string)Configure::read('TenantBackups.local.path', TMP . 'backups');
    $configuredRoot = getenv('KMP_LOCAL_BACKUPS_PATH');
    if (is_string($configuredRoot) && $configuredRoot !== '') {
        $root = $configuredRoot;
    }
    $rootPath = realpath($root);
    if ($rootPath === false) {
        return;
    }

    foreach ($backupUris as $row) {
        $objectUri = (string)($row['object_uri'] ?? '');
        if (!preg_match('#^local://' . preg_quote($slug, '#') . '/([^/]+\.pgdump\.enc\.json)$#', $objectUri, $matches)) {
            continue;
        }
        $path = $rootPath . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . $matches[1];
        $realPath = realpath($path);
        if ($realPath !== false && str_starts_with($realPath, $rootPath . DIRECTORY_SEPARATOR)) {
            unlink($realPath);
        }
    }

    $tenantObjectDir = $rootPath . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . $slug;
    if (is_dir($tenantObjectDir)) {
        rmdir($tenantObjectDir);
    }
}
PHP
}

trap cleanup EXIT

php <<'PHP'
<?php
declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';
require getcwd() . '/config/bootstrap.php';

use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;

foreach (['platform', 'default'] as $connectionName) {
    $config = ConnectionManager::getConfig($connectionName);
    if ($config === null) {
        fwrite(STDERR, "Missing required datasource configuration.\n");
        exit(1);
    }
    foreach (['host', 'database', 'username', 'password'] as $key) {
        if (!array_key_exists($key, $config) || trim((string)$config[$key]) === '') {
            fwrite(STDERR, "Missing required PostgreSQL datasource configuration.\n");
            exit(1);
        }
    }
}

$platform = ConnectionManager::get('platform');
if (!$platform->getDriver() instanceof Postgres) {
    fwrite(STDERR, "Tenant migration canary requires a PostgreSQL platform datasource.\n");
    exit(1);
}
$default = ConnectionManager::get('default');
if (!$default->getDriver() instanceof Postgres) {
    fwrite(STDERR, "Tenant migration canary requires a PostgreSQL default datasource for tenant connections.\n");
    exit(1);
}
$platform->execute('SELECT 1');

$store = SecretStoreFactory::fromConfig();
if (!$store instanceof WritableSecretStoreInterface) {
    fwrite(STDERR, "Tenant migration canary requires a writable secret store.\n");
    exit(1);
}
PHP

echo "Provisioning disposable canary tenant: $CANARY_SLUG"
bin/cake tenant provision "$CANARY_SLUG" \
    --display-name "Tenant Migration Canary" \
    --host "$CANARY_HOST" \
    --db-name "$CANARY_DB" \
    --db-role "$CANARY_ROLE" \
    --blob-container "$CANARY_BLOB_CONTAINER" \
    --create-database \
    --skip-migrations \
    --status provisioning

php <<'PHP'
<?php
declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';
require getcwd() . '/config/bootstrap.php';

use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;

$slug = (string)getenv('CANARY_SLUG');
ConnectionManager::get('platform')->update('tenants', [
    'status' => 'active',
    'activated_at' => DateTime::now('UTC')->format('Y-m-d H:i:s'),
    'modified_at' => DateTime::now('UTC')->format('Y-m-d H:i:s'),
], ['slug' => $slug]);

$store = SecretStoreFactory::fromConfig();
if (!$store instanceof WritableSecretStoreInterface) {
    fwrite(STDERR, "Tenant migration canary requires a writable secret store.\n");
    exit(1);
}
$store->put((string)getenv('CANARY_KEK_SECRET_NAME'), new SensitiveString(base64_encode(random_bytes(32))));
PHP

echo "Checking canary tenant migration status..."
bin/cake tenant migrate --tenant "$CANARY_SLUG" --status

echo "Running canary tenant migrations..."
bin/cake tenant migrate --tenant "$CANARY_SLUG"

echo "Running canary tenant migration dry-run after apply..."
bin/cake tenant migrate --tenant "$CANARY_SLUG" --dry-run

echo "Verifying canary tenant migrations remain idempotent..."
bin/cake tenant migrate --tenant "$CANARY_SLUG"

echo "Final canary tenant migration status..."
bin/cake tenant migrate --tenant "$CANARY_SLUG" --status

php <<'PHP'
<?php
declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';
require getcwd() . '/config/bootstrap.php';

use App\KMP\TenantMetadata;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\TenantConnectionManager;
use Cake\Datasource\ConnectionManager;

$slug = (string)getenv('CANARY_SLUG');
$smokeTable = (string)getenv('KMP_CANARY_SMOKE_TABLE') ?: 'members';
if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/', $smokeTable)) {
    fwrite(STDERR, "Invalid canary smoke table.\n");
    exit(1);
}

$row = ConnectionManager::get('platform')
    ->execute('SELECT * FROM tenants WHERE slug = ? AND status = ?', [$slug, 'active'])
    ->fetch('assoc');
if (!is_array($row) || empty($row['schema_version'])) {
    fwrite(STDERR, "Canary tenant did not reach active schema state.\n");
    exit(1);
}

$manager = new TenantConnectionManager(SecretStoreFactory::fromConfig());
$manager->withTenant(TenantMetadata::fromPlatformRow($row), function () use ($smokeTable): void {
    $tenant = ConnectionManager::get(TenantConnectionManager::CONNECTION_ALIAS);
    if (!in_array($smokeTable, $tenant->getSchemaCollection()->listTables(), true)) {
        fwrite(STDERR, "Canary schema verification failed.\n");
        exit(1);
    }
    $tenant->execute('SELECT 1');
});
PHP

echo "Tenant migration canary passed for $CANARY_SLUG."
