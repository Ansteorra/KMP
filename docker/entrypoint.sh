#!/bin/bash
set -e

echo "=== KMP Application Container Starting ==="

DB_DRIVER="$(printf '%s' "${KMP_DB_DRIVER:-mysql}" | tr '[:upper:]' '[:lower:]')"
if [ "$DB_DRIVER" = "postgres" ] || [ "$DB_DRIVER" = "pgsql" ]; then
    DB_ENGINE="postgres"
    DB_HOST="${DB_HOST:-${MYSQL_HOST:-db}}"
    DB_PORT="${DB_PORT:-5432}"
    DB_USER="${DB_USERNAME:-${POSTGRES_USER:-${MYSQL_USERNAME:-}}}"
    DB_PASS="${DB_PASSWORD:-${POSTGRES_PASSWORD:-${MYSQL_PASSWORD:-}}}"
    DB_NAME="${DB_DATABASE:-${POSTGRES_DB:-${MYSQL_DB_NAME:-}}}"
    PLATFORM_DB_NAME="${PLATFORM_DB_DATABASE:-${DB_NAME}_platform}"
else
    DB_ENGINE="mysql"
    DB_HOST="${DB_HOST:-${MYSQL_HOST:-db}}"
    DB_PORT="${DB_PORT:-${MYSQL_PORT:-3306}}"
    DB_USER="${DB_USERNAME:-${MYSQL_USERNAME:-}}"
    DB_PASS="${DB_PASSWORD:-${MYSQL_PASSWORD:-}}"
    DB_NAME="${DB_DATABASE:-${MYSQL_DB_NAME:-}}"
fi

# Wait for database to be ready (belt and suspenders - compose healthcheck should handle this)
echo "Checking database connection..."
max_attempts=30
attempt=0
database_ready() {
    if [ "$DB_ENGINE" = "postgres" ]; then
        PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1" >/dev/null 2>&1
    else
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1" >/dev/null 2>&1
    fi
}

until database_ready; do
    attempt=$((attempt + 1))
    if [ $attempt -ge $max_attempts ]; then
        echo "ERROR: Could not connect to database after $max_attempts attempts"
        exit 1
    fi
    echo "Waiting for database... (attempt $attempt/$max_attempts)"
    sleep 2
done
echo "Database connection established!"

# Create test database if it doesn't exist
echo "Ensuring test database exists..."
if [ "$DB_ENGINE" = "postgres" ]; then
    if [ "${KMP_AUTO_CREATE_DATABASES:-false}" = "true" ]; then
        PGPASSWORD="$DB_PASS" createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "${DB_NAME}_test" 2>/dev/null || true
        PGPASSWORD="$DB_PASS" createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "$PLATFORM_DB_NAME" 2>/dev/null || true
        PGPASSWORD="$DB_PASS" createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "${PLATFORM_DB_NAME}_test" 2>/dev/null || true
    fi
else
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" \
        -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME}_test COLLATE utf8_unicode_ci;" 2>/dev/null || true
fi

# Skip .env file generation; local Docker mounts app/config/.env and CakePHP
# reloads it at request time so development-only setting changes are picked up.
echo "Using container environment variables with local .env overrides (APP_NAME=$APP_NAME)"

# Always copy Docker-specific app_local.php (uses correct service hostnames)
echo "Copying Docker app_local.php..."
cp /opt/docker/app_local.php /var/www/html/config/app_local.php

# Install composer dependencies if vendor directory is missing or empty
if [ ! -d /var/www/html/vendor ] || [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    cd /var/www/html
    composer install --no-interaction --prefer-dist
fi

# Ensure proper directory structure and permissions
echo "Setting up directories and permissions..."
mkdir -p /var/www/html/logs \
         /var/www/html/tmp/cache/models \
         /var/www/html/tmp/cache/persistent \
         /var/www/html/tmp/cache/views \
         /var/www/html/tmp/sessions \
         /var/www/html/tmp/tests \
         /var/www/html/images/uploaded \
         /var/www/html/images/cache

chown -R www-data:www-data /var/www/html/logs /var/www/html/tmp /var/www/html/images 2>/dev/null || true
chmod -R 775 /var/www/html/logs /var/www/html/tmp /var/www/html/images 2>/dev/null || true

# Run migrations if database is empty (first-time setup)
if [ "$DB_ENGINE" = "postgres" ]; then
    TABLES=$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -Atc "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';" 2>/dev/null || echo 0)
else
    TABLES=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SHOW TABLES;" 2>/dev/null | wc -l)
fi
if [ -f /var/www/html/tmp/skip-initial-db-setup ]; then
    echo "Skipping initial database setup because tmp/skip-initial-db-setup exists."
elif [ "${KMP_SKIP_INITIAL_DB_SETUP:-false}" = "true" ]; then
    echo "KMP_SKIP_INITIAL_DB_SETUP=true - skipping initial database setup."
elif [ "$TABLES" -eq 0 ]; then
    echo "Empty database detected - running initial setup..."
    cd /var/www/html
    if [ -f bin/cake ]; then
        # Check if resetDatabase command exists
        if bin/cake help resetDatabase &>/dev/null; then
            echo "Running resetDatabase..."
            bin/cake resetDatabase || true
        fi
        echo "Running migrations..."
        bin/cake migrations migrate || true
        echo "Running updateDatabase..."
        bin/cake updateDatabase || true
    fi
fi

# Setup cron for queue processing and scheduled maintenance.
if [ "${KMP_SKIP_CRON:-false}" != "true" ]; then
    echo "Configuring cron jobs..."
    touch /var/log/cron.log
    chmod 664 /var/log/cron.log 2>/dev/null || true

    CRON_FILE="/var/www/html/tmp/kmp-cron.$$"
    crontab -l 2>/dev/null \
        | grep -v -E "bin/cake (queue run|workflow_scheduler|sync_active_window_statuses|sync_member_warrantable_statuses|age_up_members|backup_check|platform schedule run)" \
        > "$CRON_FILE" || true
    cat >> "$CRON_FILE" <<'CRON'
*/2 * * * * cd /var/www/html && bin/cake queue run -q >> /var/log/cron.log 2>&1
* * * * * cd /var/www/html && if [ "${KMP_TENANCY_ENABLED:-false}" = "true" ]; then bin/cake platform schedule due; else bin/cake workflow_scheduler; fi >> /var/log/cron.log 2>&1
*/15 * * * * cd /var/www/html && if [ "${KMP_TENANCY_ENABLED:-false}" != "true" ]; then bin/cake sync_active_window_statuses; fi >> /var/log/cron.log 2>&1
10 0 * * * cd /var/www/html && if [ "${KMP_TENANCY_ENABLED:-false}" != "true" ]; then bin/cake sync_member_warrantable_statuses; fi >> /var/log/cron.log 2>&1
20 0 * * * cd /var/www/html && if [ "${KMP_TENANCY_ENABLED:-false}" != "true" ]; then bin/cake age_up_members; fi >> /var/log/cron.log 2>&1
0 3 * * * cd /var/www/html && if [ "${KMP_TENANCY_ENABLED:-false}" != "true" ]; then bin/cake backup_check; fi >> /var/log/cron.log 2>&1
CRON
    crontab "$CRON_FILE"
    rm -f "$CRON_FILE"

    # Start cron in background
    service cron start
else
    echo "KMP_SKIP_CRON=true - skipping cron setup."
fi

echo "=== KMP Application Ready ==="
echo "  App:     http://localhost:${KMP_APP_PORT:-8080}"
echo "  Mailpit: http://localhost:${KMP_MAILPIT_WEB_PORT:-8025}"
if [ "$DB_ENGINE" = "postgres" ]; then
    echo "  PostgreSQL: localhost:${KMP_DB_HOST_PORT:-5432}"
else
    echo "  MySQL:   localhost:${KMP_DB_HOST_PORT:-3306}"
fi
if [ "${KMP_SKIP_CRON:-false}" != "true" ]; then
    echo "  Cron log: /var/log/cron.log"
fi
echo ""

# Execute the main command (apache2-foreground)
exec "$@"
