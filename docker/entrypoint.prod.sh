#!/bin/bash
set -e

case "${KMP_DB_DRIVER:-postgres}" in
    postgres|pgsql) ;;
    *) echo 'ERROR: KMP supports PostgreSQL only.' >&2; exit 1 ;;
esac
for database_variable in DATABASE_URL DATABASE_TEST_URL DATABASE_ADMIN_URL PLATFORM_DATABASE_URL PLATFORM_DATABASE_ADMIN_URL PLATFORM_DATABASE_TEST_URL; do
    database_url="${!database_variable:-}"
    case "$database_url" in
        ''|postgres://*|postgresql://*|pgsql://*) ;;
        *) echo "ERROR: $database_variable must use PostgreSQL." >&2; exit 1 ;;
    esac
done

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
            // Flags and SSL are configured at container startup by entrypoint.prod.sh
            'flags' => [\PDO::ATTR_EMULATE_PREPARES => true],
        ],
        'test' => [
            'url' => env('DATABASE_TEST_URL'),
        ],
    ],
    'EmailTransport' => [
        'default' => match (strtolower(env('EMAIL_DRIVER', 'smtp'))) {
            'azure' => [
                'className' => \App\Mailer\Transport\AzureCommunicationTransport::class,
                'connectionString' => env('AZURE_COMMUNICATION_CONNECTION_STRING'),
                'apiVersion' => env('AZURE_COMMUNICATION_API_VERSION', '2023-03-31'),
            ],
            'sendgrid' => [
                'className' => \App\Mailer\Transport\SendGridApiTransport::class,
                'apiKey' => env('EMAIL_API_KEY'),
            ],
            'resend' => [
                'className' => \App\Mailer\Transport\ResendApiTransport::class,
                'apiKey' => env('EMAIL_API_KEY'),
            ],
            default => [
                'className' => 'Smtp',
                'host' => env('EMAIL_SMTP_HOST', 'localhost'),
                'port' => (int)env('EMAIL_SMTP_PORT', 1025),
                'username' => env('EMAIL_SMTP_USERNAME', ''),
                'password' => env('EMAIL_SMTP_PASSWORD', ''),
                'client' => null,
                'tls' => filter_var(env('EMAIL_SMTP_TLS', false), FILTER_VALIDATE_BOOLEAN),
                'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
            ],
        },
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
                'authMode' => env('AZURE_STORAGE_AUTH_MODE', 'connectionString'),
                'accountName' => env('AZURE_STORAGE_ACCOUNT_NAME'),
                'managedIdentityClientId' => env('AZURE_CLIENT_ID'),
                'connectionString' => env('AZURE_STORAGE_CONNECTION_STRING'),
                'container' => env('AZURE_STORAGE_CONTAINER', 'documents'),
                'containerPrefix' => env('AZURE_STORAGE_CONTAINER_PREFIX', 'documents'),
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
# 3. Wait for database readiness
# ---------------------------------------------------------------------------
echo "Waiting for database connection..."
max_attempts=60
attempt=0

check_postgres() {
    if [ -n "${DATABASE_URL:-}" ]; then
        # CakePHP URL options (such as ssl/ssl_mode) are not libpq options.
        # Probe the server address only; CakePHP retains authentication and TLS settings.
        local target db_host db_port
        target=$(php -r '
            $url = parse_url(getenv("DATABASE_URL"));
            if (!is_array($url) || empty($url["host"])) {
                exit(1);
            }
            echo trim($url["host"], "[]"), "\t", $url["port"] ?? 5432;
        ') || return 1
        IFS=$'\t' read -r db_host db_port <<< "$target"
        pg_isready -h "$db_host" -p "$db_port" -q 2>/dev/null
    else
        pg_isready -h "${DB_HOST:-${PGHOST:-db}}" -p "${DB_PORT:-${PGPORT:-5432}}" -q 2>/dev/null
    fi
}

while true; do
    attempt=$((attempt + 1))
    if [ $attempt -gt $max_attempts ]; then
        echo "ERROR: Could not connect to database after $max_attempts attempts ($(( max_attempts * 2 ))s)"
        exit 1
    fi

    check_postgres && break

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
    if [ -n "${DATABASE_URL:-}" ]; then
        local database_url="$DATABASE_URL"
        database_url="${database_url/#pgsql:/postgres:}"
        table_count=$(psql "$database_url" -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';" 2>/dev/null | tr -d ' ' || echo "0")
    else
        table_count=$(PGPASSWORD="${DB_PASSWORD:-${PGPASSWORD:-}}" psql -h "${DB_HOST:-${PGHOST:-db}}" -p "${DB_PORT:-${PGPORT:-5432}}" -U "${DB_USERNAME:-${PGUSER:-postgres}}" -d "${DB_DATABASE:-${PGDATABASE:-kmp}}" -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';" 2>/dev/null | tr -d ' ' || echo "0")
    fi

    if [ "$table_count" -eq 0 ] 2>/dev/null; then
        echo "Empty database detected — running full setup..."
        CACHE_ENGINE=apcu bin/cake update_database 2>&1 || {
            echo "WARNING: update_database failed, attempting migrations migrate..."
            CACHE_ENGINE=apcu bin/cake migrations migrate 2>&1 || true
        }
    else
        echo "Existing database detected ($table_count tables) — running incremental migrations..."
        CACHE_ENGINE=apcu bin/cake migrations migrate 2>&1 || true
        echo "Running plugin migrations via update_database..."
        CACHE_ENGINE=apcu bin/cake update_database 2>&1 || true
    fi
}

# Use flock for atomic locking to prevent concurrent migrations
if [ "${KMP_SKIP_MIGRATIONS:-false}" != "true" ]; then
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
else
    echo "KMP_SKIP_MIGRATIONS=true — skipping migrations (running as short-lived Azure Container Apps Job)."
fi

# ---------------------------------------------------------------------------
# 6. Cron for queue processing and scheduled backups
# ---------------------------------------------------------------------------
if [ "${KMP_SKIP_CRON:-false}" != "true" ]; then
    echo "Configuring cron jobs..."
    QUEUE_CRON="*/5 * * * * cd /var/www/html && QUEUE_EXIT_WHEN_NOTHING_TO_DO=true bin/cake queue run -q >> /var/log/cron.log 2>&1"
    # Managed tenant backups are driven by the platform scheduler
    # (tenant-backup-fleet schedule); this cron is a tenancy-mode safety net.
    if [ "${KMP_TENANCY_ENABLED:-false}" = "true" ]; then
        BACKUP_CRON="30 4 * * * cd /var/www/html && bin/cake platform schedule run tenant-backup-fleet >> /var/log/cron.log 2>&1"
    else
        BACKUP_CRON=""
    fi
    IMAGE_CACHE_GC_CRON="15 3 * * * cd /var/www/html && bin/cake image_cache_gc --days \"${KMP_IMAGE_CACHE_GC_DAYS:-7}\" >> /var/log/cron.log 2>&1"
    # grep -v backup_check scrubs stale legacy entries from upgraded crontabs.
    (crontab -l 2>/dev/null | grep -v "queue run" | grep -v "backup_check" | grep -v "platform schedule run backup-check" | grep -v "platform schedule run tenant-backup-fleet" | grep -v "image_cache_gc"; echo "$QUEUE_CRON"; if [ -n "$BACKUP_CRON" ]; then echo "$BACKUP_CRON"; fi; echo "$IMAGE_CACHE_GC_CRON") | crontab -

    service cron start
else
    echo "KMP_SKIP_CRON=true — skipping cron setup (running as Azure Container Apps Job)."
fi

# ---------------------------------------------------------------------------
# 7. Ensure exactly one Apache MPM is enabled
# ---------------------------------------------------------------------------
echo "Ensuring Apache MPM configuration is valid..."
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null

# Railway (and similar platforms) inject PORT; Apache defaults to 80.
APACHE_PORT="${PORT:-80}"
if ! [[ "$APACHE_PORT" =~ ^[0-9]+$ ]]; then
    echo "WARNING: Invalid PORT value '$APACHE_PORT'; falling back to 80"
    APACHE_PORT="80"
fi
sed -ri "s/^Listen [0-9]+$/Listen ${APACHE_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \\*:[0-9]+>/<VirtualHost *:${APACHE_PORT}>/" /etc/apache2/sites-available/000-default.conf

# ---------------------------------------------------------------------------
# 8. Start application
# ---------------------------------------------------------------------------
echo "=== KMP Production Container Ready ==="
echo "  Listening on port ${APACHE_PORT}"

# Execute the main command (apache2-foreground) — replaces this process
exec "$@"
