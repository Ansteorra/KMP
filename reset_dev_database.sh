#!/usr/bin/env bash
# Reset the development database, load seed data, then apply any new migrations.
#
# PostgreSQL only. The Docker workflow restores the canonical PostgreSQL seed;
# direct devcontainer invocation recreates schema and runs DevLoad seeders.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$ROOT_DIR/app"
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

if [ -f "$ENV_FILE" ] && [ -z "${DATABASE_URL-}" ] && [ -z "${DB_USERNAME-}" ]; then
	echo "[reset_dev_database] Loading env vars from $ENV_FILE"
	# shellcheck disable=SC1090
	. "$ENV_FILE"
fi

echo "[reset_dev_database] Starting reset process..."

case "${KMP_DB_DRIVER:-postgres}" in
    postgres|pgsql) ;;
    *) echo 'ERROR: KMP supports PostgreSQL only.' >&2; exit 1 ;;
esac

cd "$APP_DIR"
echo "[1/4] Resetting PostgreSQL database schema..."
bin/cake resetDatabase
echo "[2/4] Running core migrations..."
bin/cake migrations migrate
echo "[3/4] Running plugin migrations..."
bin/cake updateDatabase
echo "[4/4] Seeding development fixtures..."
bin/cake seeds run DevLoad

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
