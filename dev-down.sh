#!/bin/bash
# Stop KMP development environment
# Usage: ./dev-down.sh [--volumes]
#
# Options:
#   --volumes  Also remove volumes (destroys database and pgAdmin data!)

set -e

cd "$(dirname "$0")"

KMP_VOLUMES=("kmp-pg-data" "kmp-composer-cache" "kmp-node-modules" "kmp-pgadmin-data")

ENV_FILE="app/config/.env"
COMPOSE=(docker compose)
if [ -f "$ENV_FILE" ]; then
    COMPOSE+=(--env-file "$ENV_FILE")
fi

if [ "$1" == "--volumes" ]; then
    echo "⚠️  Stopping containers AND removing volumes (database and pgAdmin data will be deleted)..."
    "${COMPOSE[@]}" down -v
    for volume in "${KMP_VOLUMES[@]}"; do
        if docker volume inspect "$volume" >/dev/null 2>&1; then
            docker volume rm "$volume" >/dev/null
        fi
    done
    echo "✅ All containers and volumes removed."
else
    echo "🛑 Stopping KMP Development Environment..."
    "${COMPOSE[@]}" down
    echo "✅ Containers stopped. Database and pgAdmin data preserved in Docker volumes."
    echo ""
    echo "To also remove database and pgAdmin data: ./dev-down.sh --volumes"
fi
