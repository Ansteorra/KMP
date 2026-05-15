#!/bin/bash
# Reset KMP development database to clean state
# Usage: ./dev-reset-db.sh [--seed]
#
# Options:
#   --seed    Load seed data from dev_seed_clean.sql, then run migrations to bring up to current
#
# Without --seed:
#   Runs resetDatabase + migrations (fresh schema with initial seeds)
#
# With --seed:
#   1. Drops and recreates the database
#   2. Loads seed data snapshot from dev_seed_clean.sql
#   3. Runs migrations to bring schema up to current
#   4. Runs updateDatabase
#   5. Resets all member passwords to TestPassword

set -e

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
if [ "$1" == "--seed" ]; then
    LOAD_SEED=true
fi

echo "🗄️  Resetting KMP Development Database..."
echo ""

# Check if containers are running
if ! "${COMPOSE[@]}" ps --status running | grep -q "kmp-app"; then
    echo "❌ Error: App container is not running. Start it with: ./dev-up.sh"
    exit 1
fi

DB_NAME="$(env_or_file MYSQL_DB_NAME KMP_DEV)"
DB_USER="$(env_or_file MYSQL_USERNAME KMPSQLDEV)"
DB_PASS="$(env_or_file MYSQL_PASSWORD 'P@ssw0rd')"
DB_ROOT_PASS="$(env_or_file MYSQL_ROOT_PASSWORD rootpassword)"

echo "[1/5] Dropping and recreating database..."
"${COMPOSE[@]}" exec -T db mariadb -uroot -p"${DB_ROOT_PASS}" <<EOF
DROP DATABASE IF EXISTS ${DB_NAME};
CREATE DATABASE ${DB_NAME} COLLATE utf8_unicode_ci;
DROP DATABASE IF EXISTS ${DB_NAME}_test;
CREATE DATABASE ${DB_NAME}_test COLLATE utf8_unicode_ci;
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'%';
GRANT ALL PRIVILEGES ON ${DB_NAME}_test.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
EOF

if [ "$LOAD_SEED" = true ] && [ -f "dev_seed_clean.sql" ]; then
    echo "[2/5] Loading seed data snapshot from dev_seed_clean.sql..."
    "${COMPOSE[@]}" exec -T db mariadb -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < dev_seed_clean.sql
    
    echo "[3/5] Running migrations to bring schema up to current..."
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate
else
    echo "[2/5] Running CakePHP resetDatabase (fresh schema)..."
    "${COMPOSE[@]}" exec -T app bin/cake resetDatabase || echo "  (resetDatabase command may not exist yet)"
    
    echo "[3/5] Running migrations..."
    "${COMPOSE[@]}" exec -T app bin/cake migrations migrate
fi

echo "[4/5] Running updateDatabase..."
"${COMPOSE[@]}" exec -T app bin/cake updateDatabase || echo "  (updateDatabase command may not exist yet)"

echo "[5/5] Resetting all member passwords to TestPassword..."
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
