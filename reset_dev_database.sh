#!/usr/bin/env bash
# Reset the development database, load seed data, then apply any new migrations.
#
# Engine-aware: inspects DATABASE_URL (and falls back to MYSQL_* vars) to decide
# whether to run the MySQL flow (cleaned MariaDB dump) or the Postgres flow
# (seed chain, since there is no Postgres dump to load).
#
# Steps (MySQL):
# 1. Drop & recreate via Cake `resetDatabase`.
# 2. Load raw seed data from dev_seed_clean.sql (MariaDB dump).
# 3. Run pending migrations (catches newer migrations not in the dump).
# 4. Update database (plugin migrations).
# 5. Reset all member passwords to TestPassword.
#
# Steps (Postgres):
# 1. Drop & recreate via Cake `resetDatabase` (drops all tables in public schema).
# 2. Run migrations (core schema + init/backfill data seeded by migrations).
# 3. Update database (plugin migrations).
# 4. Seed DevLoad fixtures via `bin/cake migrations seed --seed DevLoadSeed`.
# 5. Reset all member passwords to TestPassword.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$ROOT_DIR/app"
SEED_SQL="$ROOT_DIR/dev_seed_clean.sql"
ENV_FILE="$APP_DIR/config/.env"

# When the Docker dev stack is running, use the compose-aware reset script so
# PostgreSQL loads pg_seed_baseline.sql instead of DevLoadSeed-only fixtures.
if [ -f "$ENV_FILE" ] && docker compose --env-file "$ENV_FILE" ps --status running --services 2>/dev/null | grep -qx 'app'; then
	echo "[reset_dev_database] Docker app container is running; delegating to ./dev-reset-db.sh --seed"
	exec "$ROOT_DIR/dev-reset-db.sh" --seed
fi

# Strategy to avoid duplicate dotenv loading:
# The bootstrap only loads .env if APP_NAME is not set. We set a dummy APP_NAME early
# then (if needed) source the file ourselves ONLY when vars are missing.
export APP_NAME="KMP_DEV_APP"

if [ -f "$ENV_FILE" ] && [ -z "${DATABASE_URL-}" ] && [ -z "${MYSQL_USERNAME-}" ]; then
	echo "[reset_dev_database] Loading env vars from $ENV_FILE"
	# shellcheck disable=SC1090
	. "$ENV_FILE"
fi

echo "[reset_dev_database] Starting reset process..."

# Decide engine. Prefer DATABASE_URL when set.
DB_ENGINE="mysql"
if [ -n "${DATABASE_URL-}" ]; then
	case "$(echo "${DATABASE_URL}" | tr '[:upper:]' '[:lower:]')" in
		postgres*|pgsql*)
			DB_ENGINE="postgres"
			;;
	esac
fi

cd "$APP_DIR"

if [ "$DB_ENGINE" = "postgres" ]; then
	echo "[reset_dev_database] Engine: PostgreSQL (DATABASE_URL=${DATABASE_URL})"

	echo "[1/4] Resetting database schema (bin/cake resetDatabase)..."
	bin/cake resetDatabase

	echo "[2/4] Running core migrations (bin/cake migrations migrate)..."
	bin/cake migrations migrate

	echo "[3/4] Running plugin migrations (bin/cake updateDatabase)..."
	bin/cake updateDatabase

	echo "[4/4] Seeding DevLoad fixtures (bin/cake migrations seed --seed DevLoadSeed)..."
	bin/cake migrations seed --seed DevLoadSeed
else
	echo "[reset_dev_database] Engine: MySQL/MariaDB"

	if [ ! -f "$SEED_SQL" ]; then
		echo "Seed SQL file not found: $SEED_SQL" >&2
		exit 1
	fi

	# Ensure required env vars are present (these are referenced in app_local.php)
	: "${MYSQL_USERNAME:?Environment variable MYSQL_USERNAME is required}"
	: "${MYSQL_PASSWORD:?Environment variable MYSQL_PASSWORD is required}"
	: "${MYSQL_DB_NAME:?Environment variable MYSQL_DB_NAME is required}"

	DB_USER="$MYSQL_USERNAME"
	DB_PASS="$MYSQL_PASSWORD"
	DB_NAME="$MYSQL_DB_NAME"
	DB_HOST="${MYSQL_HOST:-localhost}"

	echo "[1/4] Resetting database schema (bin/cake resetDatabase)..."
	bin/cake resetDatabase

	echo "[2/4] Loading seed SQL dump ($SEED_SQL) into $DB_NAME..."
	MYSQL_CMD=(mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME")

	# Disable foreign key checks during import for safety (dump likely already handles this)
	"${MYSQL_CMD[@]}" < "$SEED_SQL"

	echo "[3/4] Applying any new migrations (bin/cake migrations migrate)..."
	bin/cake migrations migrate

	echo "[4/4] Updating database (bin/cake updateDatabase)..."
	bin/cake updateDatabase
fi

echo "[post] Setting ALL member passwords to TestPassword via ORM..."
php -r '
require "vendor/autoload.php";
require "config/bootstrap.php";
use Cake\ORM\TableRegistry;
$members = TableRegistry::getTableLocator()->get("Members");
$all = $members->find("all");
$count = 0; $errors = 0;
foreach ($all as $m) {
	$m->password = "TestPassword"; // triggers entity mutator for hashing
	if ($members->save($m)) {
		$count++;
	} else {
		$errors++;
		fwrite(STDERR, "Failed to save member ID {$m->id}\n");
	}
}
echo "Updated passwords for $count members. Errors: $errors\n";
if ($errors > 0) { exit(1); }
'

echo "[post] Clearing CakePHP caches..."
php -r '
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

echo "[reset_dev_database] Complete."
