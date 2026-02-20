#!/bin/bash
set -e

echo "=== KMP Production Container Starting ==="

# ---------------------------------------------------------------------------
# Graceful shutdown: trap SIGTERM/SIGINT, stop cron, let Apache drain
# ---------------------------------------------------------------------------
cleanup() {
    echo "Received shutdown signal, cleaning up..."
    service cron stop 2>/dev/null || true
    # Apache handles graceful drain via exec below
    exit 0
}
trap cleanup SIGTERM SIGINT

# ---------------------------------------------------------------------------
# 1. Environment-based app_local.php generation
# ---------------------------------------------------------------------------
generate_app_local() {
    echo "Generating config/app_local.php from environment..."
    cat > /var/www/html/config/app_local.php << 'APPLOCAL'
<?php
$requireHttps = filter_var(env('REQUIRE_HTTPS', 'true'), FILTER_VALIDATE_BOOLEAN);
return [
    'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'Security' => [
        'salt' => env('SECURITY_SALT', '__GENERATED_SALT__'),
    ],
    'Session' => [
        'ini' => [
            'session.cookie_secure' => $requireHttps,
            'session.cookie_samesite' => $requireHttps ? 'Strict' : 'Lax',
        ],
    ],
    'Datasources' => [
        'default' => [
            'url' => env('DATABASE_URL'),
            // PgBouncer/Neon pooler requires emulated prepares (server-side PREPARE aborts transactions)
            'flags' => (strpos(env('DATABASE_URL', ''), 'postgres') !== false) ? [\PDO::ATTR_EMULATE_PREPARES => true] : [],
        ],
        'test' => [
            'url' => env('DATABASE_TEST_URL', env('DATABASE_URL') . '_test'),
        ],
    ],
    'EmailTransport' => [
        'default' => [
            'host' => env('EMAIL_SMTP_HOST', 'localhost'),
            'port' => (int)env('EMAIL_SMTP_PORT', 1025),
            'username' => env('EMAIL_SMTP_USERNAME', ''),
            'password' => env('EMAIL_SMTP_PASSWORD', ''),
            'client' => null,
            'tls' => filter_var(env('EMAIL_SMTP_TLS', false), FILTER_VALIDATE_BOOLEAN),
            'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
        ],
    ],
    'Email' => [
        'default' => [
            'transport' => 'default',
            'from' => env('EMAIL_FROM', 'noreply@localhost'),
        ],
    ],
    'Documents' => [
        'storage' => [
            'adapter' => env('DOCUMENT_STORAGE_ADAPTER', 'local'),
            'azure' => [
                'connectionString' => env('AZURE_STORAGE_CONNECTION_STRING'),
                'container' => env('AZURE_STORAGE_CONTAINER', 'documents'),
                'prefix' => '',
            ],
            's3' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', env('AWS_REGION', 'us-east-1')),
                'bucket' => env('AWS_S3_BUCKET', env('AWS_BUCKET')),
                'endpoint' => env('AWS_S3_ENDPOINT'),
            ],
        ],
    ],
];
APPLOCAL
    chown www-data:www-data /var/www/html/config/app_local.php
    echo "app_local.php generated."
}

if [ -n "$DATABASE_URL" ]; then
    generate_app_local
elif [ ! -f /var/www/html/config/app_local.php ]; then
    echo "WARNING: DATABASE_URL is not set and no app_local.php exists."
    echo "Copying default Docker app_local.php..."
    cp /opt/docker/app_local.php /var/www/html/config/app_local.php
    chown www-data:www-data /var/www/html/config/app_local.php
fi

# ---------------------------------------------------------------------------
# 2. Detect database type from environment
# ---------------------------------------------------------------------------
DB_TYPE="mysql"
if [ -n "$DATABASE_URL" ]; then
    case "$DATABASE_URL" in
        postgres://*|postgresql://*)
            DB_TYPE="postgres"
            ;;
        mysql://*)
            DB_TYPE="mysql"
            ;;
    esac
elif [ -z "$MYSQL_HOST" ]; then
    # If neither DATABASE_URL nor MYSQL_HOST is set, check for PG vars
    if [ -n "$PGHOST" ]; then
        DB_TYPE="postgres"
    fi
fi
echo "Detected database type: $DB_TYPE"

# ---------------------------------------------------------------------------
# 3. Wait for database readiness
# ---------------------------------------------------------------------------
echo "Waiting for database connection..."
max_attempts=60
attempt=0

check_mysql() {
    if [ -n "$DATABASE_URL" ]; then
        # Parse host/port/user/pass from DATABASE_URL
        # Format: mysql://user:pass@host:port/dbname
        local db_host db_port db_user db_pass
        db_host=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^@]*@([^:/]+).*|\1|')
        db_port=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^@]*@[^:]+:([0-9]+)/.*|\1|')
        db_user=$(echo "$DATABASE_URL" | sed -E 's|mysql://([^:]+):.*|\1|')
        db_pass=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')
        [ -z "$db_port" ] && db_port=3306
        mysql -h"$db_host" -P"$db_port" -u"$db_user" -p"$db_pass" -e "SELECT 1" &>/dev/null
    else
        mysql -h"${MYSQL_HOST:-db}" -u"$MYSQL_USERNAME" -p"$MYSQL_PASSWORD" -e "SELECT 1" &>/dev/null
    fi
}

check_postgres() {
    if [ -n "$DATABASE_URL" ]; then
        local db_host db_port
        db_host=$(echo "$DATABASE_URL" | sed -E 's|postgres(ql)?://[^@]*@([^:/]+).*|\2|')
        db_port=$(echo "$DATABASE_URL" | sed -E 's|postgres(ql)?://[^@]*@[^:]+:([0-9]+)/.*|\2|')
        [ -z "$db_port" ] && db_port=5432
        pg_isready -h "$db_host" -p "$db_port" -q 2>/dev/null
    else
        pg_isready -h "${PGHOST:-db}" -p "${PGPORT:-5432}" -q 2>/dev/null
    fi
}

while true; do
    attempt=$((attempt + 1))
    if [ $attempt -gt $max_attempts ]; then
        echo "ERROR: Could not connect to database after $max_attempts attempts ($(( max_attempts * 2 ))s)"
        exit 1
    fi

    if [ "$DB_TYPE" = "postgres" ]; then
        check_postgres && break
    else
        check_mysql && break
    fi

    echo "Waiting for database... (attempt $attempt/$max_attempts)"
    sleep 2
done
echo "Database connection established!"

# ---------------------------------------------------------------------------
# 4. Directory setup for volume mounts
# ---------------------------------------------------------------------------
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

# ---------------------------------------------------------------------------
# 5. Migration lock & auto-migrate
# ---------------------------------------------------------------------------
LOCK_FILE="/tmp/kmp-migration.lock"
LOCK_TIMEOUT=600  # 10 minutes

run_migrations() {
    cd /var/www/html

    # Detect whether database has existing tables
    local table_count=0
    if [ "$DB_TYPE" = "postgres" ]; then
        if [ -n "$DATABASE_URL" ]; then
            table_count=$(psql "$DATABASE_URL" -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';" 2>/dev/null | tr -d ' ' || echo "0")
        else
            table_count=$(PGPASSWORD="$PGPASSWORD" psql -h "${PGHOST:-db}" -U "$PGUSER" -d "$PGDATABASE" -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';" 2>/dev/null | tr -d ' ' || echo "0")
        fi
    else
        if [ -n "$DATABASE_URL" ]; then
            local db_host db_port db_user db_pass db_name
            db_host=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^@]*@([^:/]+).*|\1|')
            db_port=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^@]*@[^:]+:([0-9]+)/.*|\1|')
            db_user=$(echo "$DATABASE_URL" | sed -E 's|mysql://([^:]+):.*|\1|')
            db_pass=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')
            db_name=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^/]+/([^?]+).*|\1|')
            [ -z "$db_port" ] && db_port=3306
            table_count=$(mysql -h"$db_host" -P"$db_port" -u"$db_user" -p"$db_pass" "$db_name" -N -e "SHOW TABLES;" 2>/dev/null | wc -l)
        else
            table_count=$(mysql -h"${MYSQL_HOST:-db}" -u"$MYSQL_USERNAME" -p"$MYSQL_PASSWORD" "$MYSQL_DB_NAME" -N -e "SHOW TABLES;" 2>/dev/null | wc -l)
        fi
    fi

    if [ "$table_count" -eq 0 ] 2>/dev/null; then
        echo "Empty database detected — running full setup..."
        bin/cake update_database 2>&1 || {
            echo "WARNING: update_database failed, attempting migrations migrate..."
            bin/cake migrations migrate 2>&1 || true
        }
    else
        echo "Existing database detected ($table_count tables) — running incremental migrations..."
        bin/cake migrations migrate 2>&1 || true
        echo "Running plugin migrations via update_database..."
        bin/cake update_database 2>&1 || true
    fi
}

# Use flock for atomic locking to prevent concurrent migrations
echo "Acquiring migration lock..."
(
    if flock -w "$LOCK_TIMEOUT" 200; then
        echo "Migration lock acquired (PID $$)"
        run_migrations
        echo "Migration lock released."
    else
        echo "WARNING: Could not acquire migration lock after ${LOCK_TIMEOUT}s, skipping migrations."
    fi
) 200>"$LOCK_FILE"

# ---------------------------------------------------------------------------
# 6. Cron for queue processing and scheduled backups
# ---------------------------------------------------------------------------
echo "Configuring cron jobs..."
QUEUE_CRON="*/2 * * * * cd /var/www/html && bin/cake queue run -q >> /var/log/cron.log 2>&1"
BACKUP_CRON="0 3 * * * cd /var/www/html && bin/cake backup_check >> /var/log/cron.log 2>&1"
(crontab -l 2>/dev/null | grep -v "queue run" | grep -v "backup_check"; echo "$QUEUE_CRON"; echo "$BACKUP_CRON") | crontab -

service cron start

# ---------------------------------------------------------------------------
# 7. Start application
# ---------------------------------------------------------------------------
echo "=== KMP Production Container Ready ==="
echo "  Listening on port 80"

# Execute the main command (apache2-foreground) — replaces this process
exec "$@"
