#!/usr/bin/env bash
# Reset the development database, load cleaned seed SQL, then apply any new migrations.
# Steps:
# 1. Drop & recreate / clean via Cake commands (resetDatabase)
# 2. Load raw seed data from dev_seed_clean.sql (MariaDB dump)
# 3. Run pending migrations (to test new migrations against previously deployed database)
# 4. Update database (updateDatabase)
# 5. Reset all member passwords to TestPassword

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$ROOT_DIR/app"
SEED_SQL="$ROOT_DIR/dev_seed_clean.sql"

# Strategy to avoid duplicate dotenv loading:
# The bootstrap only loads .env if APP_NAME is not set. We set a dummy APP_NAME early
# then (if needed) source the file ourselves ONLY when vars are missing.
export APP_NAME="KMP_DEV_APP"

ENV_FILE="$APP_DIR/config/.env"
for v in MYSQL_USERNAME MYSQL_PASSWORD MYSQL_DB_NAME; do
	if [ -z "${!v-}" ] && [ -f "$ENV_FILE" ]; then
		echo "[reset_dev_database] Loading $v from env file"
		# shellcheck disable=SC1090
		. "$ENV_FILE"
		break
	fi
done

echo "[reset_dev_database] Starting reset process..."

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

cd "$APP_DIR"

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

# If DevLoad seed is still desired after raw SQL import, uncomment next line:
# bin/cake migrations seed --seed DevLoad

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
