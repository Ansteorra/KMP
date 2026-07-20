#!/bin/bash

# Setup Test Database Script
# This script recreates the test database with the correct schema from dev.

set -euo pipefail

# Load environment variables. Prefer the Docker workspace path used by this script,
# but allow local invocation from the repository checkout.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_ENV_FILE="/workspaces/KMP/app/config/.env"
LOCAL_ENV_FILE="${SCRIPT_DIR}/../config/.env"
if [ -n "${KMP_DB_DRIVER-}${DB_HOST-}${DB_USERNAME-}${POSTGRES_USER-}" ]; then
    :
elif [ -f "${DEFAULT_ENV_FILE}" ]; then
    # shellcheck disable=SC1090
    source "${DEFAULT_ENV_FILE}"
elif [ -f "${LOCAL_ENV_FILE}" ]; then
    # shellcheck disable=SC1090
    source "${LOCAL_ENV_FILE}"
fi

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "ERROR: Required command not found: $1" >&2
        exit 1
    fi
}

require_env() {
    if [ -z "${!1-}" ]; then
        echo "ERROR: Environment variable $1 is required" >&2
        exit 1
    fi
}

# Match app/config/app.php: KMP_DB_DRIVER chooses mysql/mariadb or postgres/pgsql.
DB_DRIVER_VALUE="${KMP_DB_DRIVER:-${DB_DRIVER:-mysql}}"
DB_DRIVER_VALUE="$(echo "${DB_DRIVER_VALUE}" | tr '[:upper:]' '[:lower:]')"

case "${DB_DRIVER_VALUE}" in
    mysql|mariadb|"")
        DB_ENGINE="mysql"
        ;;
    postgres|pgsql)
        DB_ENGINE="postgres"
        ;;
    *)
        echo "ERROR: Unsupported KMP_DB_DRIVER value: ${DB_DRIVER_VALUE}" >&2
        echo "Supported values: mysql, mariadb, postgres, pgsql" >&2
        exit 1
        ;;
esac

if [ "${DB_ENGINE}" = "postgres" ]; then
    require_command psql
    require_command pg_dump

    DB_HOST="${DB_HOST:-${MYSQL_HOST:-localhost}}"
    DB_PORT="${DB_PORT:-${POSTGRES_PORT:-5432}}"
    DB_USER="${DB_USERNAME:-${MYSQL_USERNAME:-}}"
    DB_PASS="${DB_PASSWORD:-${MYSQL_PASSWORD:-}}"
    DEV_DB="${DB_DATABASE:-${MYSQL_DB_NAME:-}}"
    TEST_DB="${DEV_DB}_test"
    MAINTENANCE_DB="${POSTGRES_MAINTENANCE_DB:-postgres}"

    require_env DB_USER
    require_env DB_PASS
    require_env DEV_DB

    echo "Setting up test database: ${TEST_DB}"
    echo "Database driver: PostgreSQL"

    PSQL_MAINT_CMD=(psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${MAINTENANCE_DB}" -v ON_ERROR_STOP=1)
    PSQL_TEST_CMD=(psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${TEST_DB}" -v ON_ERROR_STOP=1)
    PG_DUMP_CMD=(pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" --schema-only --no-owner --no-privileges "${DEV_DB}")

    echo "Dropping existing test database..."
    PGPASSWORD="${DB_PASS}" "${PSQL_MAINT_CMD[@]}" -c "DROP DATABASE IF EXISTS \"${TEST_DB}\";"

    echo "Creating test database..."
    PGPASSWORD="${DB_PASS}" "${PSQL_MAINT_CMD[@]}" -c "CREATE DATABASE \"${TEST_DB}\";"

    echo "Importing schema from dev database..."
    PGPASSWORD="${DB_PASS}" "${PG_DUMP_CMD[@]}" | PGPASSWORD="${DB_PASS}" "${PSQL_TEST_CMD[@]}"

    echo ""
    echo "Verifying gatherings table schema..."
    DELETED_EXISTS=$(PGPASSWORD="${DB_PASS}" "${PSQL_TEST_CMD[@]}" -Atc "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'gatherings' AND column_name = 'deleted';")
    MODIFIED_BY_EXISTS=$(PGPASSWORD="${DB_PASS}" "${PSQL_TEST_CMD[@]}" -Atc "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'gatherings' AND column_name = 'modified_by';")
else
    require_command mysql
    require_command mysqldump

    DB_HOST="${DB_HOST:-${MYSQL_HOST:-localhost}}"
    DB_PORT="${DB_PORT:-${MYSQL_PORT:-3306}}"
    DB_USER="${DB_USERNAME:-${MYSQL_USERNAME:-}}"
    DB_PASS="${DB_PASSWORD:-${MYSQL_PASSWORD:-}}"
    DEV_DB="${DB_DATABASE:-${MYSQL_DB_NAME:-}}"
    TEST_DB="${DEV_DB}_test"

    require_env DB_USER
    require_env DB_PASS
    require_env DEV_DB

    echo "Setting up test database: ${TEST_DB}"
    echo "Database driver: MySQL/MariaDB"

    MYSQL_CMD=(mysql -h "${DB_HOST}" -P "${DB_PORT}" -u"${DB_USER}" -p"${DB_PASS}")
    MYSQL_TEST_CMD=(mysql -h "${DB_HOST}" -P "${DB_PORT}" -u"${DB_USER}" -p"${DB_PASS}" "${TEST_DB}")
    MYSQL_DUMP_CMD=(mysqldump -h "${DB_HOST}" -P "${DB_PORT}" -u"${DB_USER}" -p"${DB_PASS}" --no-data "${DEV_DB}")

    echo "Dropping existing test database..."
    "${MYSQL_CMD[@]}" -e "DROP DATABASE IF EXISTS \`${TEST_DB}\`;"

    echo "Creating test database..."
    "${MYSQL_CMD[@]}" -e "CREATE DATABASE \`${TEST_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    echo "Importing schema from dev database..."
    "${MYSQL_DUMP_CMD[@]}" | "${MYSQL_TEST_CMD[@]}"

    echo ""
    echo "Verifying gatherings table schema..."
    DELETED_EXISTS=$("${MYSQL_TEST_CMD[@]}" -e "SHOW COLUMNS FROM gatherings LIKE 'deleted';" | wc -l)
    MODIFIED_BY_EXISTS=$("${MYSQL_TEST_CMD[@]}" -e "SHOW COLUMNS FROM gatherings LIKE 'modified_by';" | wc -l)
fi

if [ "${DELETED_EXISTS}" -lt 1 ]; then
    echo "ERROR: 'deleted' column missing from gatherings table!"
    exit 1
fi

if [ "${MODIFIED_BY_EXISTS}" -lt 1 ]; then
    echo "ERROR: 'modified_by' column missing from gatherings table!"
    exit 1
fi

echo "✓ Schema verified: deleted and modified_by columns present"
echo ""
echo "Test database setup complete!"
echo "Database: ${TEST_DB}"
echo "Tables imported from: ${DEV_DB}"
