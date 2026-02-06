#!/bin/bash
# Stop KMP development environment
# Usage: ./dev-down.sh [--volumes]
#
# Options:
#   --volumes  Also remove volumes (destroys database data!)

set -e

cd "$(dirname "$0")"

if [ "$1" == "--volumes" ]; then
    echo "⚠️  Stopping containers AND removing volumes (database will be deleted)..."
    docker compose down -v
    echo "✅ All containers and volumes removed."
else
    echo "🛑 Stopping KMP Development Environment..."
    docker compose down
    echo "✅ Containers stopped. Database data preserved in Docker volume."
    echo ""
    echo "To also remove database data: ./dev-down.sh --volumes"
fi
