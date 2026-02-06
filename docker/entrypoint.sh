#!/bin/bash
set -e

echo "=== KMP Application Container Starting ==="

# Wait for database to be ready (belt and suspenders - compose healthcheck should handle this)
echo "Checking database connection..."
max_attempts=30
attempt=0
until mysql -h"$MYSQL_HOST" -u"$MYSQL_USERNAME" -p"$MYSQL_PASSWORD" -e "SELECT 1" &>/dev/null; do
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
mysql -h"$MYSQL_HOST" -u"$MYSQL_USERNAME" -p"$MYSQL_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS ${MYSQL_DB_NAME}_test COLLATE utf8_unicode_ci;" 2>/dev/null || true

# Skip .env file generation - APP_NAME is set which tells CakePHP to use container env vars directly
# This avoids the "Key already defined" error from josegonzalez/dotenv
echo "Using container environment variables (APP_NAME=$APP_NAME)"

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
TABLES=$(mysql -h"$MYSQL_HOST" -u"$MYSQL_USERNAME" -p"$MYSQL_PASSWORD" "$MYSQL_DB_NAME" -N -e "SHOW TABLES;" 2>/dev/null | wc -l)
if [ "$TABLES" -eq 0 ]; then
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

# Setup cron for queue processing (runs every 2 minutes)
echo "Configuring cron job..."
CRON_JOB="*/2 * * * * cd /var/www/html && bin/cake queue run -q >> /var/log/cron.log 2>&1"
(crontab -l 2>/dev/null | grep -v "queue run" ; echo "$CRON_JOB") | crontab -

# Start cron in background
service cron start

echo "=== KMP Application Ready ==="
echo "  App:     http://localhost:8080"
echo "  Mailpit: http://localhost:8025"
echo "  MySQL:   localhost:3306"
echo ""

# Execute the main command (apache2-foreground)
exec "$@"
