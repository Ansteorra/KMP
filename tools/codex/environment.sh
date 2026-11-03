#!/usr/bin/env bash
# Repository-owned lifecycle for Codex cloud environments. See app/docs/codex-cloud.md.
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APP_DIR="$REPO_DIR/app"
STATE_DIR="$REPO_DIR/.codex-runtime"
PGDATA="$STATE_DIR/postgres"
PG_BIN="${KMP_CODEX_PG_BINDIR:-/usr/lib/postgresql/16/bin}"
PG_PORT="${KMP_CODEX_PGPORT:-55432}"

fail() {
    echo "Codex setup: $*" >&2
    exit 1
}

usage() {
    echo 'Usage: bash tools/codex/environment.sh {doctor|install|start|run COMMAND [ARGS...]|stop}'
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Missing $1. See app/docs/codex-cloud.md for prerequisites."
}

check_postgres() {
    if [[ ! "$PG_PORT" =~ ^[1-9][0-9]{3,4}$ ]] || ((PG_PORT < 1024 || PG_PORT > 65535)); then
        fail 'KMP_CODEX_PGPORT must be a port between 1024 and 65535.'
    fi
    for tool in initdb pg_ctl psql createdb; do
        [[ -x "$PG_BIN/$tool" ]] || fail "Missing $PG_BIN/$tool. Install PostgreSQL 16 server and client."
        [[ "$("$PG_BIN/$tool" --version)" == *' 16.'* ]] || fail "$tool must be PostgreSQL 16."
    done
}

doctor() {
    for tool in php composer node npm git; do
        require_command "$tool"
    done
    php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 && version_compare(PHP_VERSION, "8.4.27", ">=") ? 0 : 1);' \
        || fail 'PHP 8.4.27 or later in the 8.4 series is required, matching CI.'
    node -e 'process.exit(Number(process.versions.node.split(".")[0]) === 24 ? 0 : 1)' \
        || fail 'Node.js 24 is required, matching CI.'
    # shellcheck disable=SC2016 # PHP variables must remain literal.
    php -r '
        $required = ["apcu", "bcmath", "curl", "dom", "gd", "grpc", "intl", "mbstring", "pdo_pgsql", "pdo_sqlite", "sodium", "xml", "xmlwriter", "yaml", "zip"];
        $missing = array_filter($required, fn ($name) => !extension_loaded($name));
        if ($missing) {
            fwrite(STDERR, "Missing PHP extensions: " . implode(", ", $missing) . PHP_EOL);
            exit(1);
        }
    ' || fail 'Install the missing PHP extensions before continuing.'
    check_postgres
    echo 'Codex prerequisites are ready.'
}

# These settings are scoped to the wrapped command. Setting APP_NAME and a
# non-local KMP_ENV prevents bootstrap from loading a workstation .env file.
command_environment() {
    export APP_NAME=KMP KMP_ENV=development KMP_LOAD_DOTENV=false DEBUG=true
    export KMP_DB_DRIVER=postgres DB_HOST=127.0.0.1 DB_PORT="$PG_PORT"
    export DB_USERNAME=kmp_codex DB_PASSWORD=kmp_codex_disposable DB_DATABASE=kmp_codex DB_NAME=kmp_codex
    export DATABASE_URL="postgres://$DB_USERNAME:$DB_PASSWORD@127.0.0.1:$PG_PORT/kmp_codex"
    export DATABASE_TEST_URL="${DATABASE_URL}_test"
    export PLATFORM_DB_HOST=127.0.0.1 PLATFORM_DB_PORT="$PG_PORT"
    export PLATFORM_DB_USERNAME="$DB_USERNAME" PLATFORM_DB_PASSWORD="$DB_PASSWORD"
    export PLATFORM_DB_DATABASE=kmp_codex_platform PLATFORM_DB_TEST_DATABASE=kmp_codex_platform_test
    export PLATFORM_DATABASE_URL="postgres://$DB_USERNAME:$DB_PASSWORD@127.0.0.1:$PG_PORT/kmp_codex_platform"
    export PLATFORM_DATABASE_TEST_URL="${PLATFORM_DATABASE_URL}_test"
    export SECURITY_SALT=codex-disposable-development-only-never-use-in-production
    export CACHE_ENGINE=apcu KMP_SESSION_DEFAULTS=php KMP_SESSION_COOKIE_SECURE=false
    export KMP_TENANCY_ENABLED=true KMP_AUTO_CREATE_DATABASES=true
    export EMAIL_SMTP_HOST=127.0.0.1 EMAIL_SMTP_PORT=1025
    export EMAIL_SMTP_USERNAME='' EMAIL_SMTP_PASSWORD=''
    export DOCUMENT_STORAGE_ADAPTER=local XDEBUG_MODE=off
    unset KMP_ADMIN_JOB DATABASE_ADMIN_URL PLATFORM_DATABASE_ADMIN_URL
}

as_postgres() {
    if [[ "$(id -u)" == 0 ]]; then
        runuser -u postgres -- "$@"
    else
        "$@"
    fi
}

start_postgres() {
    check_postgres
    if [[ "$(id -u)" == 0 ]]; then
        require_command runuser
        id postgres >/dev/null 2>&1 || fail 'A postgres OS user is required when running as root.'
    fi
    mkdir -p "$STATE_DIR" "$PGDATA"
    # A root-run environment must let the postgres OS user traverse this parent.
    chmod 755 "$STATE_DIR"
    if [[ "$(id -u)" == 0 ]]; then
        chown postgres:postgres "$PGDATA"
    fi
    chmod 700 "$PGDATA"
    if [[ ! -f "$PGDATA/PG_VERSION" ]]; then
        local password_file
        password_file="$(mktemp "$STATE_DIR/initdb-password.XXXXXX")"
        printf '%s\n' kmp_codex_disposable > "$password_file"
        if [[ "$(id -u)" == 0 ]]; then
            chown postgres:postgres "$password_file"
        fi
        if ! as_postgres "$PG_BIN/initdb" -D "$PGDATA" --username=kmp_codex --pwfile="$password_file" \
            --auth-local=scram-sha-256 --auth-host=scram-sha-256 --encoding=UTF8 --no-locale; then
            rm -f "$password_file"
            fail 'Could not initialize the disposable PostgreSQL cluster.'
        fi
        rm -f "$password_file"
    fi
    if ! as_postgres "$PG_BIN/pg_ctl" -D "$PGDATA" status >/dev/null 2>&1; then
        as_postgres "$PG_BIN/pg_ctl" -D "$PGDATA" -l "$PGDATA/server.log" \
            -o "-h 127.0.0.1 -p $PG_PORT -c unix_socket_directories=''" -w start
    fi
    local psql=("$PG_BIN/psql" -X -h 127.0.0.1 -p "$PG_PORT" -U kmp_codex -d postgres -v ON_ERROR_STOP=1)
    local actual_data_dir
    actual_data_dir="$(PGPASSWORD=kmp_codex_disposable "${psql[@]}" -Atc 'SHOW data_directory')"
    [[ "$actual_data_dir" == "$PGDATA" ]] || fail 'Refusing to provision a PostgreSQL cluster outside this checkout.'
    local database
    for database in kmp_codex kmp_codex_test kmp_codex_platform kmp_codex_platform_test; do
        if [[ "$(PGPASSWORD=kmp_codex_disposable "${psql[@]}" -Atc "SELECT 1 FROM pg_database WHERE datname = '$database'")" != 1 ]]; then
            PGPASSWORD=kmp_codex_disposable "$PG_BIN/createdb" -h 127.0.0.1 -p "$PG_PORT" -U kmp_codex "$database"
        fi
    done
    echo "Isolated Codex PostgreSQL is ready on 127.0.0.1:$PG_PORT."
}

action="${1:---help}"
if [[ "$#" -gt 0 ]]; then
    shift
fi
case "$action" in
    doctor)
        [[ "$#" == 0 ]] || fail 'doctor takes no arguments.'
        doctor
        ;;
    install)
        [[ "$#" == 0 ]] || fail 'install takes no arguments.'
        doctor
        cd "$APP_DIR"
        mkdir -p logs tmp/cache/models tmp/cache/persistent tmp/cache/views tmp/sessions tmp/tests webroot/js
        # The legacy Composer post-install hook rewrites app_local.php/salt.
        # Cloud commands use environment configuration instead.
        composer install --no-interaction --prefer-dist --no-scripts
        composer check-platform-reqs
        npm ci --no-audit --no-fund
        npm run dev
        ;;
    start)
        [[ "$#" == 0 ]] || fail 'start takes no arguments.'
        start_postgres
        ;;
    run)
        [[ "$#" -gt 0 ]] || fail 'run requires a command and optional arguments.'
        check_postgres
        command_environment
        cd "$APP_DIR"
        exec "$@"
        ;;
    stop)
        [[ "$#" == 0 ]] || fail 'stop takes no arguments.'
        check_postgres
        if [[ -f "$PGDATA/PG_VERSION" ]] && as_postgres "$PG_BIN/pg_ctl" -D "$PGDATA" status >/dev/null 2>&1; then
            as_postgres "$PG_BIN/pg_ctl" -D "$PGDATA" -m fast -w stop
        fi
        ;;
    --help|-h)
        usage
        ;;
    *)
        usage >&2
        exit 1
        ;;
esac
