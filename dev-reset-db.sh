#!/bin/bash
# Reset KMP development database to clean state
# Usage: ./dev-reset-db.sh [--seed]
#
# Options:
#   --seed    Load seed data, then run migrations/update tasks to bring up to current
#
# Without --seed:
#   Runs resetDatabase + migrations (fresh schema with initial seeds)
#
# With --seed:
#   1. Drops and recreates the database
#   2. MySQL/MariaDB: loads dev_seed_clean.sql, then runs remaining migrations
#   3. PostgreSQL: migrates to the baseline, loads the converted baseline seed
#   4. Runs remaining migrations/update tasks
#   5. Migrates the platform DB and registers local tenants
#   6. Resets all baseline tenant member passwords
#   7. PostgreSQL seeded resets load a pruned second local tenant
#   8. Rebuilds the test database schema from the reset dev database

set -e
set -o pipefail

cd "$(dirname "$0")"

ENV_FILE="app/config/.env"
COMPOSE=(docker compose)
if [ -f "$ENV_FILE" ]; then
    COMPOSE+=(--env-file "$ENV_FILE")
fi

env_or_file() {
    name="$1"
    default="$2"
    value="$(printenv "$name" 2>/dev/null || true)"

    if [ -z "$value" ] && [ -f "$ENV_FILE" ]; then
        value="$(sed -nE "s/^(export[[:space:]]+)?${name}=//p" "$ENV_FILE" | tail -n 1)"
        value="${value%\"}"
        value="${value#\"}"
        value="${value%\'}"
        value="${value#\'}"
    fi

    if [ -z "$value" ]; then
        value="$default"
    fi

    printf '%s\n' "$value"
}

LOAD_SEED=false
if [ "${1:-}" == "--seed" ]; then
    LOAD_SEED=true
fi

echo "🗄️  Resetting KMP Development Database..."
echo ""

# Check if containers are running
RUNNING_SERVICES="$("${COMPOSE[@]}" ps --status running --services)"
if ! printf '%s\n' "$RUNNING_SERVICES" | grep -x "app" >/dev/null; then
    echo "❌ Error: App container is not running. Start it with: ./dev-up.sh"
    exit 1
fi

DB_DRIVER="$(env_or_file KMP_DB_DRIVER postgres)"
DB_DRIVER="$(printf '%s' "$DB_DRIVER" | tr '[:upper:]' '[:lower:]')"

if [ "$DB_DRIVER" = "postgres" ] || [ "$DB_DRIVER" = "pgsql" ]; then
    echo "[1/7] Dropping and recreating app and platform databases..."
    DB_NAME="$(env_or_file DB_DATABASE "$(env_or_file POSTGRES_DB KMP_DEV)")"
    DB_USER="$(env_or_file DB_USERNAME "$(env_or_file POSTGRES_USER KMPSQLDEV)")"
    DB_PASS="$(env_or_file DB_PASSWORD "$(env_or_file POSTGRES_PASSWORD kmpdevpass)")"
    PLATFORM_DB_NAME="$(env_or_file PLATFORM_DB_DATABASE KMP_PLATFORM)"
    PLATFORM_DB_TEST_NAME="$(env_or_file PLATFORM_DB_TEST_DATABASE "${PLATFORM_DB_NAME}_test")"
    SECOND_TENANT_SLUG="kmp2"
    SECOND_TENANT_HOST="kmp2.localhost"
    SECOND_TENANT_DISPLAY_NAME="Second Kingdom"
    SECOND_TENANT_DB="kmp2_dev"
    SECOND_TENANT_ROLE="kmp_tenant_kmp2_role"
    SECOND_TENANT_TEST_DB="${SECOND_TENANT_DB}_test"
    LEGACY_SECOND_TENANT_DB="KMP2_DEV"
    LEGACY_SECOND_TENANT_TEST_DB="${LEGACY_SECOND_TENANT_DB}_test"

    "${COMPOSE[@]}" exec -T db psql -U "${DB_USER}" -d postgres -v ON_ERROR_STOP=1 \
        -v db="${DB_NAME}" \
        -v testdb="${DB_NAME}_test" \
        -v platformdb="${PLATFORM_DB_NAME}" \
        -v platformtestdb="${PLATFORM_DB_TEST_NAME}" \
        -v tenant2db="${SECOND_TENANT_DB}" \
        -v tenant2testdb="${SECOND_TENANT_TEST_DB}" \
        -v legacytenant2db="${LEGACY_SECOND_TENANT_DB}" \
        -v legacytenant2testdb="${LEGACY_SECOND_TENANT_TEST_DB}" <<'SQL'
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname IN (
    :'db',
    :'testdb',
    :'platformdb',
    :'platformtestdb',
    :'tenant2db',
    :'tenant2testdb',
    :'legacytenant2db',
    :'legacytenant2testdb'
)
  AND pid <> pg_backend_pid();
DROP DATABASE IF EXISTS :"db";
CREATE DATABASE :"db";
DROP DATABASE IF EXISTS :"testdb";
CREATE DATABASE :"testdb";
DROP DATABASE IF EXISTS :"platformdb";
CREATE DATABASE :"platformdb";
DROP DATABASE IF EXISTS :"platformtestdb";
CREATE DATABASE :"platformtestdb";
DROP DATABASE IF EXISTS :"tenant2db";
DROP DATABASE IF EXISTS :"tenant2testdb";
DROP DATABASE IF EXISTS :"legacytenant2db";
DROP DATABASE IF EXISTS :"legacytenant2testdb";
SQL
elif [ "$DB_DRIVER" = "mysql" ] || [ "$DB_DRIVER" = "mariadb" ] || [ -z "$DB_DRIVER" ]; then
    echo "[1/5] Dropping and recreating database..."
    DB_NAME="$(env_or_file MYSQL_DB_NAME KMP_DEV)"
    DB_USER="$(env_or_file MYSQL_USERNAME KMPSQLDEV)"
    DB_PASS="$(env_or_file MYSQL_PASSWORD 'P@ssw0rd')"
    DB_ROOT_PASS="$(env_or_file MYSQL_ROOT_PASSWORD rootpassword)"

    "${COMPOSE[@]}" exec -T db mariadb -uroot -p"${DB_ROOT_PASS}" <<EOF
DROP DATABASE IF EXISTS ${DB_NAME};
CREATE DATABASE ${DB_NAME} COLLATE utf8_unicode_ci;
DROP DATABASE IF EXISTS ${DB_NAME}_test;
CREATE DATABASE ${DB_NAME}_test COLLATE utf8_unicode_ci;
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'%';
GRANT ALL PRIVILEGES ON ${DB_NAME}_test.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
EOF
else
    echo "❌ Error: Unsupported KMP_DB_DRIVER value: ${DB_DRIVER}"
    exit 1
fi

if [ "$LOAD_SEED" = true ] && [ "$DB_DRIVER" != "postgres" ] && [ "$DB_DRIVER" != "pgsql" ] && [ -f "dev_seed_clean.sql" ]; then
    echo "[2/5] Loading seed data snapshot from dev_seed_clean.sql..."
    "${COMPOSE[@]}" exec -T db mariadb -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < dev_seed_clean.sql
    
    echo "[3/5] Running migrations to bring schema up to current..."
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate
elif [ "$LOAD_SEED" = true ] && { [ "$DB_DRIVER" = "postgres" ] || [ "$DB_DRIVER" = "pgsql" ]; }; then
    PG_SEED_FILE="app/tests/pg_seed_baseline.sql"
    if [ ! -f "$PG_SEED_FILE" ]; then
        echo "❌ Error: Missing PostgreSQL seed file: $PG_SEED_FILE"
        exit 1
    fi

    echo "[2/5] Running migrations to the PostgreSQL seed baseline..."
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate --target 20260206000001
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate --plugin Queue --target 20260210163129
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate --plugin Activities --target 20250228144601
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate --plugin Officers --target 20250228133830
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate --plugin Awards --target 20251130230000
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate --plugin Waivers --target 20260131001511
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate --plugin Tools --target 20200430170235

    SEEDED_TABLES="$(grep -oE 'INSERT INTO "[^"]+"' "$PG_SEED_FILE" \
        | sed -E 's/INSERT INTO "([^"]+)"/"\1"/' \
        | sort -u \
        | paste -sd, -)"
    if [ -n "$SEEDED_TABLES" ]; then
        printf 'TRUNCATE TABLE %s RESTART IDENTITY CASCADE;\n' "$SEEDED_TABLES" \
            | "${COMPOSE[@]}" exec -T app sh -lc 'psql "$DATABASE_URL" -q -v ON_ERROR_STOP=1' >/dev/null
    fi

    echo "[3/5] Loading PostgreSQL baseline seed from $PG_SEED_FILE..."
    "${COMPOSE[@]}" exec -T app sh -lc 'psql "$DATABASE_URL" -q -v ON_ERROR_STOP=1' < "$PG_SEED_FILE" >/dev/null

    SEEDED_MEMBER_COUNT="$("${COMPOSE[@]}" exec -T app sh -lc 'psql "$DATABASE_URL" -At -c "SELECT count(*) FROM members"')"
    echo "      Seeded members: ${SEEDED_MEMBER_COUNT}"

    echo "[4/5] Running remaining migrations/updateDatabase..."
    "${COMPOSE[@]}" exec -T app bin/cake updateDatabase
else
    echo "[2/5] Running CakePHP resetDatabase (fresh schema)..."
    "${COMPOSE[@]}" exec -T app bin/cake resetDatabase || echo "  (resetDatabase command may not exist yet)"
    
    echo "[3/5] Running migrations..."
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate
fi

if [ "$LOAD_SEED" != true ] || { [ "$DB_DRIVER" != "postgres" ] && [ "$DB_DRIVER" != "pgsql" ]; }; then
    echo "[4/5] Running updateDatabase..."
    "${COMPOSE[@]}" exec -T app bin/cake updateDatabase || echo "  (updateDatabase command may not exist yet)"
fi

if [ "$DB_DRIVER" = "postgres" ] || [ "$DB_DRIVER" = "pgsql" ]; then
    echo "[5/7] Running platform migrations and registering local tenants..."
    "${COMPOSE[@]}" exec -T app bin/cake platform_migrate migrate
    "${COMPOSE[@]}" exec -T app php -r '
require "vendor/autoload.php";
require "config/bootstrap.php";

use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Text;

$tenantSlug = strtolower((string)(getenv("KMP_DEV_TENANT_SLUG") ?: "kmp"));
$tenantHost = strtolower((string)(getenv("KMP_DEV_TENANT_HOST") ?: "kmp.localhost"));
$tenantDisplayName = (string)(getenv("KMP_DEV_TENANT_DISPLAY_NAME") ?: "KMP Development");
$dbServer = (string)(getenv("DB_HOST") ?: "db");
$dbName = (string)(getenv("DB_DATABASE") ?: getenv("POSTGRES_DB") ?: "KMP_DEV");
$dbRole = (string)(getenv("DB_USERNAME") ?: getenv("POSTGRES_USER") ?: "KMPSQLDEV");
$dbPassword = (string)(getenv("DB_PASSWORD") ?: getenv("POSTGRES_PASSWORD") ?: "kmpdevpass");
$platformAdminId = "00000000-0000-4000-8000-000000000001";
$platformAdminEmail = strtolower((string)(getenv("KMP_DEV_PLATFORM_ADMIN_EMAIL") ?: "admin@example.org"));
$platformAdminPassword = (string)(getenv("KMP_DEV_PLATFORM_ADMIN_PASSWORD") ?: "TestPassword");
$platformAdminTotpSecret = (string)(getenv("KMP_DEV_PLATFORM_ADMIN_TOTP_SECRET") ?: "QJR6QMYZYRHDZCOK5STD");
$platformAdminTotpRef = sprintf("platform.admin.%s.totp", $platformAdminId);
$now = gmdate("Y-m-d H:i:s");

if (!preg_match("/^[a-z0-9][a-z0-9-]*[a-z0-9]$/", $tenantSlug)) {
    fwrite(STDERR, "Invalid KMP_DEV_TENANT_SLUG: $tenantSlug\n");
    exit(1);
}
if ($tenantHost === "" || str_contains($tenantHost, "/") || str_contains($tenantHost, ":")) {
    fwrite(STDERR, "Invalid KMP_DEV_TENANT_HOST: $tenantHost\n");
    exit(1);
}

$connection = ConnectionManager::get("platform");
function registerLocalTenant(
    $connection,
    string $tenantSlug,
    string $tenantHost,
    string $tenantDisplayName,
    string $dbServer,
    string $dbName,
    string $dbRole,
    string $now,
): void {
    $tenant = $connection->execute("SELECT * FROM tenants WHERE slug = ?", [$tenantSlug])->fetch("assoc") ?: null;
    $tenantConfig = json_encode([
        "documents" => ["blob_container" => "tenant-" . $tenantSlug],
        "local_dev" => true,
    ], JSON_UNESCAPED_SLASHES);
    if ($tenantConfig === false) {
        throw new RuntimeException("Unable to encode tenant_config JSON.");
    }

    if ($tenant === null) {
        $tenant = [
            "id" => Text::uuid(),
            "slug" => $tenantSlug,
            "display_name" => $tenantDisplayName,
            "status" => "active",
            "region" => "local",
            "primary_host" => $tenantHost,
            "db_server" => $dbServer,
            "db_name" => $dbName,
            "db_role" => $dbRole,
            "key_vault_prefix" => "tenant." . $tenantSlug,
            "schema_version" => null,
            "feature_flags" => "{}",
            "tenant_config" => $tenantConfig,
            "queue_concurrency_limit" => 5,
            "created_at" => $now,
            "activated_at" => $now,
            "suspended_at" => null,
            "archived_at" => null,
            "modified_at" => $now,
        ];
        $connection->insert("tenants", $tenant);
    } else {
        $connection->update("tenants", [
            "display_name" => $tenantDisplayName,
            "status" => "active",
            "region" => "local",
            "primary_host" => $tenantHost,
            "db_server" => $dbServer,
            "db_name" => $dbName,
            "db_role" => $dbRole,
            "key_vault_prefix" => "tenant." . $tenantSlug,
            "tenant_config" => $tenantConfig,
            "activated_at" => $tenant["activated_at"] ?: $now,
            "modified_at" => $now,
        ], ["id" => $tenant["id"]]);
        $tenant = $connection->execute("SELECT * FROM tenants WHERE slug = ?", [$tenantSlug])->fetch("assoc");
    }

    $hostRow = $connection->execute(
        "SELECT id, tenant_id FROM tenant_hosts WHERE host_normalized = ?",
        [$tenantHost],
    )->fetch("assoc") ?: null;
    if ($hostRow !== null && (string)$hostRow["tenant_id"] !== (string)$tenant["id"]) {
        throw new RuntimeException(sprintf("Host %s is already assigned to another tenant.", $tenantHost));
    }
    if ($hostRow === null) {
        $connection->insert("tenant_hosts", [
            "id" => Text::uuid(),
            "tenant_id" => $tenant["id"],
            "host" => $tenantHost,
            "host_normalized" => $tenantHost,
            "is_primary" => true,
            "status" => "active",
            "created_at" => $now,
            "modified_at" => $now,
        ]);
    } else {
        $connection->update("tenant_hosts", [
            "host" => $tenantHost,
            "is_primary" => true,
            "status" => "active",
            "modified_at" => $now,
        ], ["id" => $hostRow["id"]]);
    }
}

function seedDevPlatformAdmin(
    $connection,
    WritableSecretStoreInterface $secretStore,
    string $platformAdminId,
    string $platformAdminEmail,
    string $platformAdminPassword,
    string $platformAdminTotpSecret,
    string $platformAdminTotpRef,
    string $now,
): void {
    if (!filter_var($platformAdminEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Invalid KMP_DEV_PLATFORM_ADMIN_EMAIL: $platformAdminEmail");
    }
    if ($platformAdminPassword === "") {
        throw new RuntimeException("KMP_DEV_PLATFORM_ADMIN_PASSWORD cannot be empty.");
    }
    if ($platformAdminTotpSecret === "") {
        throw new RuntimeException("KMP_DEV_PLATFORM_ADMIN_TOTP_SECRET cannot be empty.");
    }

    $secretStore->put($platformAdminTotpRef, new SensitiveString($platformAdminTotpSecret));
    $fields = [
        "email" => $platformAdminEmail,
        "password_hash" => password_hash($platformAdminPassword, PASSWORD_DEFAULT),
        "status" => "active",
        "totp_secret_ref" => $platformAdminTotpRef,
        "totp_enrolled_at" => $now,
        "failed_login_count" => 0,
        "locked_until" => null,
        "last_login_at" => null,
        "modified_at" => $now,
    ];
    $existing = $connection->execute(
        "SELECT id FROM platform_users WHERE id = ? OR lower(email) = ? LIMIT 1",
        [$platformAdminId, $platformAdminEmail],
    )->fetch("assoc") ?: null;
    $existingId = is_array($existing) ? (string)$existing["id"] : null;

    if ($existingId === null) {
        $fields["id"] = $platformAdminId;
        $fields["created_at"] = $now;
        $connection->insert("platform_users", $fields);
    } else {
        $connection->update("platform_users", $fields, ["id" => $existingId]);
    }
}

$connection->begin();
try {
    registerLocalTenant($connection, $tenantSlug, $tenantHost, $tenantDisplayName, $dbServer, $dbName, $dbRole, $now);
    $connection->commit();
} catch (Throwable $exception) {
    $connection->rollback();
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$secretStore = SecretStoreFactory::fromConfig();
if (!$secretStore instanceof WritableSecretStoreInterface) {
    fwrite(STDERR, "Configured secret store is not writable; cannot seed tenant DB password.\n");
    exit(1);
}
$secretStore->put(sprintf("tenant.%s.db.password", $tenantSlug), new SensitiveString($dbPassword));
seedDevPlatformAdmin(
    $connection,
    $secretStore,
    $platformAdminId,
    $platformAdminEmail,
    $platformAdminPassword,
    $platformAdminTotpSecret,
    $platformAdminTotpRef,
    $now,
);
echo sprintf("Registered local tenant %s for host %s using database %s.\n", $tenantSlug, $tenantHost, $dbName);
echo sprintf("Seeded dev platform admin %s with password %s and TOTP secret %s.\n", $platformAdminEmail, $platformAdminPassword, $platformAdminTotpSecret);
'

    echo "      Provisioning second local tenant through the platform command..."
    "${COMPOSE[@]}" exec -T app bin/cake tenant provision "$SECOND_TENANT_SLUG" \
        --display-name "$SECOND_TENANT_DISPLAY_NAME" \
        --host "$SECOND_TENANT_HOST" \
        --db-name "$SECOND_TENANT_DB" \
        --db-role "$SECOND_TENANT_ROLE" \
        --create-database \
        --skip-migrations \
        --status provisioning
    "${COMPOSE[@]}" exec -T db psql -U "${DB_USER}" -d postgres -q -v ON_ERROR_STOP=1 \
        -v tenant_role="${SECOND_TENANT_ROLE}" -v tenant_password="${DB_PASS}" <<'SQL'
ALTER ROLE :"tenant_role" WITH LOGIN PASSWORD :'tenant_password';
SQL
    "${COMPOSE[@]}" exec -T \
        -e SECOND_TENANT_SLUG="${SECOND_TENANT_SLUG}" \
        -e SECOND_TENANT_DB_PASSWORD="${DB_PASS}" \
        app php -r '
require "vendor/autoload.php";
require "config/bootstrap.php";

use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Datasource\ConnectionManager;

$slug = getenv("SECOND_TENANT_SLUG") ?: "kmp2";
$password = getenv("SECOND_TENANT_DB_PASSWORD");
if ($password === false || $password === "") {
    fwrite(STDERR, "Missing second tenant database password.\n");
    exit(1);
}
$secretStore = SecretStoreFactory::fromConfig();
if (!$secretStore instanceof WritableSecretStoreInterface) {
    fwrite(STDERR, "Configured secret store is not writable; cannot seed second tenant DB password.\n");
    exit(1);
}
$secretStore->put(sprintf("tenant.%s.db.password", $slug), new SensitiveString($password));
ConnectionManager::get("platform")->update("tenants", [
    "status" => "active",
    "modified_at" => gmdate("Y-m-d H:i:s"),
], ["slug" => $slug]);
'
    "${COMPOSE[@]}" exec -T app bin/cake tenant migrate \
        --tenant "$SECOND_TENANT_SLUG" \
        --skip-pre-migration-marker
fi

if [ "$DB_DRIVER" = "postgres" ] || [ "$DB_DRIVER" = "pgsql" ]; then
    echo "[6/7] Resetting all baseline tenant member passwords to TestPassword..."
else
    echo "[5/5] Resetting all member passwords to TestPassword..."
fi
"${COMPOSE[@]}" exec -T app php -r '
require "vendor/autoload.php";
require "config/bootstrap.php";
use Cake\ORM\TableRegistry;
$members = TableRegistry::getTableLocator()->get("Members");
$all = $members->find("all");
$count = 0; $errors = 0;
foreach ($all as $m) {
    $m->password = "TestPassword";
    if ($members->save($m)) { $count++; } else { $errors++; }
}
echo "Updated passwords for $count members. Errors: $errors\n";
if ($errors > 0) { exit(1); }
'

if [ "$LOAD_SEED" = true ] && { [ "$DB_DRIVER" = "postgres" ] || [ "$DB_DRIVER" = "pgsql" ]; }; then
    SECOND_TENANT_PRUNE_FILE="app/tests/pg_seed_second_tenant_prune.sql"
    if [ ! -f "$SECOND_TENANT_PRUNE_FILE" ]; then
        echo "❌ Error: Missing PostgreSQL second tenant prune file: $SECOND_TENANT_PRUNE_FILE"
        exit 1
    fi

    echo "[7/7] Loading starter data into second local tenant database..."
    SECOND_TENANT_EXCLUDE_TABLE_DATA=(
        --exclude-table-data=activities_authorization_approvals
        --exclude-table-data=activities_authorizations
        --exclude-table-data=awards_recommendations
        --exclude-table-data=awards_recommendations_events
        --exclude-table-data=awards_recommendations_states_logs
        --exclude-table-data=backups
        --exclude-table-data=documents
        --exclude-table-data=gathering_attendances
        --exclude-table-data=gathering_staff
        --exclude-table-data=gathering_scheduled_activities
        --exclude-table-data=gatherings
        --exclude-table-data=gatherings_gathering_activities
        --exclude-table-data=grid_view_preferences
        --exclude-table-data=grid_views
        --exclude-table-data=impersonation_action_logs
        --exclude-table-data=impersonation_session_logs
        --exclude-table-data=member_quick_login_devices
        --exclude-table-data=officers_officers
        --exclude-table-data=queued_jobs
        --exclude-table-data=service_principal_audit_logs
        --exclude-table-data=service_principal_roles
        --exclude-table-data=service_principal_tokens
        --exclude-table-data=service_principals
        --exclude-table-data=tokens
        --exclude-table-data=waivers_gathering_waiver_closures
        --exclude-table-data=waivers_gathering_waivers
        --exclude-table-data=warrants
        --exclude-table-data=workflow_approvals
        --exclude-table-data=workflow_approval_responses
        --exclude-table-data=workflow_execution_logs
        --exclude-table-data=workflow_instance_migrations
        --exclude-table-data=workflow_instances
        --exclude-table-data=workflow_tasks
    )
    "${COMPOSE[@]}" exec -T db psql -U "${DB_USER}" -d "${SECOND_TENANT_DB}" -q -v ON_ERROR_STOP=1 <<'SQL' >/dev/null
DO $$
DECLARE
    table_list text;
BEGIN
    SELECT string_agg(format('%I.%I', schemaname, tablename), ', ')
    INTO table_list
    FROM pg_tables
    WHERE schemaname = 'public';

    IF table_list IS NOT NULL THEN
        EXECUTE 'TRUNCATE TABLE ' || table_list || ' RESTART IDENTITY CASCADE';
    END IF;
END $$;
SQL
    "${COMPOSE[@]}" exec -T db pg_dump -U "${DB_USER}" --data-only --disable-triggers --no-owner --no-privileges "${SECOND_TENANT_EXCLUDE_TABLE_DATA[@]}" "${DB_NAME}" \
        | "${COMPOSE[@]}" exec -T db psql -U "${DB_USER}" -d "${SECOND_TENANT_DB}" -q -v ON_ERROR_STOP=1 >/dev/null
    "${COMPOSE[@]}" exec -T db psql -U "${DB_USER}" -d "${SECOND_TENANT_DB}" -q -v ON_ERROR_STOP=1 \
        < "$SECOND_TENANT_PRUNE_FILE" >/dev/null
    "${COMPOSE[@]}" exec -T db psql -U "${DB_USER}" -d "${SECOND_TENANT_DB}" -q -v ON_ERROR_STOP=1 \
        -v owner="${DB_USER}" -v tenant_role="${SECOND_TENANT_ROLE}" -v tenant_db="${SECOND_TENANT_DB}" <<'SQL' >/dev/null
GRANT CONNECT ON DATABASE :"tenant_db" TO :"tenant_role";
GRANT USAGE, CREATE ON SCHEMA public TO :"tenant_role";
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO :"tenant_role";
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO :"tenant_role";
SELECT set_config('kmp.tenant_role', :'tenant_role', false);
DO $$
DECLARE
    object_row record;
    tenant_role text := current_setting('kmp.tenant_role');
BEGIN
    EXECUTE format('ALTER SCHEMA public OWNER TO %I', tenant_role);
    FOR object_row IN
        SELECT table_schema, table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_type IN ('BASE TABLE', 'VIEW')
    LOOP
        EXECUTE format(
            'ALTER TABLE %I.%I OWNER TO %I',
            object_row.table_schema,
            object_row.table_name,
            tenant_role
        );
    END LOOP;
    FOR object_row IN
        SELECT sequence_schema, sequence_name
        FROM information_schema.sequences
        WHERE sequence_schema = 'public'
    LOOP
        EXECUTE format(
            'ALTER SEQUENCE %I.%I OWNER TO %I',
            object_row.sequence_schema,
            object_row.sequence_name,
            tenant_role
        );
    END LOOP;
END $$;
SQL
    SECOND_TENANT_SCHEMA_VERSION="$("${COMPOSE[@]}" exec -T db psql -U "${DB_USER}" -d "${SECOND_TENANT_DB}" -At -c "SELECT MAX(version) FROM phinxlog")"
    "${COMPOSE[@]}" exec -T \
        -e SECOND_TENANT_SLUG="${SECOND_TENANT_SLUG}" \
        -e SECOND_TENANT_SCHEMA_VERSION="${SECOND_TENANT_SCHEMA_VERSION}" \
        app php -r '
require "vendor/autoload.php";
require "config/bootstrap.php";

use Cake\Datasource\ConnectionManager;

$slug = getenv("SECOND_TENANT_SLUG") ?: "kmp2";
$schemaVersion = getenv("SECOND_TENANT_SCHEMA_VERSION") ?: null;
$connection = ConnectionManager::get("platform");
$connection->update("tenants", [
    "status" => "active",
    "schema_version" => $schemaVersion,
    "activated_at" => gmdate("Y-m-d H:i:s"),
    "modified_at" => gmdate("Y-m-d H:i:s"),
], ["slug" => $slug]);
'
    SECOND_TENANT_MEMBER_COUNT="$("${COMPOSE[@]}" exec -T db psql -U "${DB_USER}" -d "${SECOND_TENANT_DB}" -At -c "SELECT count(*) FROM members")"
    SECOND_TENANT_GATHERING_COUNT="$("${COMPOSE[@]}" exec -T db psql -U "${DB_USER}" -d "${SECOND_TENANT_DB}" -At -c "SELECT count(*) FROM gatherings")"
    echo "      Second tenant members: ${SECOND_TENANT_MEMBER_COUNT}"
    echo "      Second tenant gatherings: ${SECOND_TENANT_GATHERING_COUNT}"
elif [ "$DB_DRIVER" = "postgres" ] || [ "$DB_DRIVER" = "pgsql" ]; then
    echo "[7/7] Skipping second local tenant starter data; run ./dev-reset-db.sh --seed for demo users."
fi

echo "[post] Rebuilding test database schema..."
"${COMPOSE[@]}" exec -T app bash bin/setup_test_database.sh >/dev/null

echo "[post] Clearing CakePHP caches..."
"${COMPOSE[@]}" exec -T app php -r '
require "vendor/autoload.php";
require "config/bootstrap.php";
use Cake\Cache\Cache;
$failed = [];
foreach (Cache::configured() as $config) {
    if (in_array($config, ["_cake_core_", "_cake_routes_"], true)) {
        continue;
    }
    try {
        if (!Cache::clear($config)) {
            $failed[] = "$config (clear returned false)";
        }
    } catch (Throwable $e) {
        $failed[] = $config . " (" . $e->getMessage() . ")";
    }
}
if (!empty($failed)) {
    fwrite(STDERR, "Warning: Failed to clear cache configs: " . implode(", ", $failed) . "\n");
}
'

echo ""
echo "✅ Database reset complete!"
echo ""
echo "Default test credentials:"
echo "   Password: TestPassword"
echo ""
echo "Member logins (use http://kmp.localhost:${KMP_APP_PORT:-8080}/members/login):"
"${COMPOSE[@]}" exec -T db psql -U "${DB_USER}" -d "${DB_NAME}" -At -c \
    "SELECT email_address FROM members WHERE deleted IS NULL ORDER BY id LIMIT 15;" 2>/dev/null \
    | while IFS= read -r email; do
        if [ -n "$email" ]; then
            echo "   Email: $email"
        fi
    done
if [ "$LOAD_SEED" = true ]; then
    echo "   (Seeded reset — admin@amp.ansteorra.org and other demo users should appear above.)"
else
    echo "   (Fresh reset without --seed — typically only admin@test.com until you run with --seed.)"
fi
