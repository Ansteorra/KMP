#!/bin/bash
# Start KMP development environment
# Usage: ./dev-up.sh [--build]
#
# Options:
#   --build    Force rebuild of containers

set -e

cd "$(dirname "$0")"

ENV_FILE="app/config/.env"
ENV_SAMPLE_FILE="app/config/.env.example"

if [ ! -f "$ENV_FILE" ]; then
    if [ ! -f "$ENV_SAMPLE_FILE" ]; then
        echo "❌ Error: Missing $ENV_SAMPLE_FILE; cannot create $ENV_FILE"
        exit 1
    fi

    echo "Creating $ENV_FILE from $ENV_SAMPLE_FILE..."
    cp "$ENV_SAMPLE_FILE" "$ENV_FILE"
fi

COMPOSE=(docker compose)
COMPOSE+=(--env-file "$ENV_FILE")

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

APP_PORT="$(env_or_file KMP_APP_PORT 8080)"
APP_URL="$(env_or_file KMP_APP_URL "http://localhost:${APP_PORT}")"
MAILPIT_WEB_PORT="$(env_or_file KMP_MAILPIT_WEB_PORT 8025)"
MAILPIT_SMTP_PORT="$(env_or_file KMP_MAILPIT_SMTP_PORT 1025)"
DB_HOST_PORT="$(env_or_file KMP_DB_HOST_PORT 5432)"
PGADMIN_PORT="$(env_or_file KMP_PGADMIN_PORT 5050)"
HOST_ALIASES="$(env_or_file KMP_HOST_ALIASES "")"
RESET_DB_ON_UP="$(env_or_file KMP_RESET_DB_ON_UP true)"
RESET_DB_ON_UP_ARGS="$(env_or_file KMP_RESET_DB_ON_UP_ARGS "--seed")"
KMP_VOLUMES=("kmp-pg-data" "kmp-composer-cache" "kmp-node-modules" "kmp-pgadmin-data")

ensure_named_volumes() {
    for volume in "${KMP_VOLUMES[@]}"; do
        if ! docker volume inspect "$volume" >/dev/null 2>&1; then
            echo "Creating Docker volume: $volume"
            docker volume create "$volume" >/dev/null
        fi
    done
}

remove_container() {
    container_id="$1"
    reason="$2"
    container_name="$(docker ps -a --filter "id=${container_id}" --format '{{.Names}}' | head -n 1)"
    if [ -z "$container_name" ]; then
        container_name="$container_id"
    fi

    echo "  Removing ${container_name} (${reason})"
    docker rm -f "$container_id" >/dev/null
}

cleanup_existing_dev_containers() {
    mkdir -p app/tmp
    conflicts_file="app/tmp/kmp-dev-conflicts.$$"
    : > "$conflicts_file"

    for container_name in kmp-app kmp-worker kmp-scheduler kmp-db kmp-pgadmin kmp-mailpit; do
        container_id="$(docker container inspect --format '{{.Id}}' "$container_name" 2>/dev/null || true)"
        if [ -n "$container_id" ]; then
            echo "$container_id fixed KMP container name ${container_name}" >> "$conflicts_file"
        fi
    done

    for port in "$APP_PORT" "$DB_HOST_PORT" "$PGADMIN_PORT" "$MAILPIT_WEB_PORT" "$MAILPIT_SMTP_PORT"; do
        docker ps --filter "publish=${port}" --format "{{.ID}} published port ${port} ({{.Names}})" >> "$conflicts_file"
    done

    if [ ! -s "$conflicts_file" ]; then
        rm -f "$conflicts_file"
        return
    fi

    echo "🧹 Removing existing containers that would conflict with this dev session..."
    removed_ids=""
    while IFS= read -r conflict; do
        raw_container_id="${conflict%% *}"
        reason="${conflict#* }"
        container_id="$(docker container inspect --format '{{.Id}}' "$raw_container_id" 2>/dev/null || true)"
        if [ -z "$container_id" ] || printf '%s\n' "$removed_ids" | grep -qx "$container_id"; then
            continue
        fi
        remove_container "$container_id" "$reason"
        removed_ids="${removed_ids}
${container_id}"
    done < "$conflicts_file"
    rm -f "$conflicts_file"
}

echo "🚀 Starting KMP Development Environment..."

ensure_named_volumes
cleanup_existing_dev_containers

if [ "$1" == "--build" ]; then
    echo "Building containers..."
    "${COMPOSE[@]}" build --no-cache
fi

"${COMPOSE[@]}" up -d

echo ""
echo "⏳ Waiting for services to be healthy..."
sleep 5

# Wait for app to be ready
max_wait=120
waited=0
while ! curl -sf "$APP_URL/" > /dev/null 2>&1; do
    if [ $waited -ge $max_wait ]; then
        echo "⚠️  App not responding after ${max_wait}s - check logs with: docker compose logs app"
        exit 1
    fi
    sleep 2
    waited=$((waited + 2))
    echo "  Waiting for app... (${waited}s)"
done

case "$(printf '%s' "$RESET_DB_ON_UP" | tr '[:upper:]' '[:lower:]')" in
    false|0|no)
        echo ""
        echo "Skipping database reset (KMP_RESET_DB_ON_UP=${RESET_DB_ON_UP})."
        ;;
    *)
        echo ""
        echo "🗄️  Resetting database for this dev session..."
        reset_args=()
        if [ -n "$RESET_DB_ON_UP_ARGS" ]; then
            # shellcheck disable=SC2206
            reset_args=($RESET_DB_ON_UP_ARGS)
        fi
        ./dev-reset-db.sh "${reset_args[@]}"
        ;;
esac

echo ""
echo "✅ KMP Development Environment is running!"
echo ""
echo "   📱 Application:  $APP_URL"
if [ -n "$HOST_ALIASES" ]; then
    for alias in $HOST_ALIASES; do
        echo "   🌐 Host alias:   http://${alias}:${APP_PORT}"
    done
fi
echo "   📧 Mailpit:      http://localhost:${MAILPIT_WEB_PORT}"
echo "   🗄️  PostgreSQL:   127.0.0.1:${DB_HOST_PORT}"
echo "   🐘 pgAdmin:      http://localhost:${PGADMIN_PORT}"
echo "   ⚙️  Background:    docker compose logs -f scheduler"
echo ""
echo "Useful commands:"
echo "   docker compose logs -f app    # Follow app logs"
echo "   docker compose logs -f scheduler # Follow queue and scheduled task logs"
echo "   docker compose exec app bash  # Shell into app container"
echo "   ./dev-reset-db.sh             # Reset database"
echo "   ./dev-down.sh                 # Stop environment"
