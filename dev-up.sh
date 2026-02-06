#!/bin/bash
# Start KMP development environment
# Usage: ./dev-up.sh [--build]
#
# Options:
#   --build    Force rebuild of containers

set -e

cd "$(dirname "$0")"

echo "🚀 Starting KMP Development Environment..."

if [ "$1" == "--build" ]; then
    echo "Building containers..."
    docker compose build --no-cache
fi

docker compose up -d

echo ""
echo "⏳ Waiting for services to be healthy..."
sleep 5

# Wait for app to be ready
max_wait=120
waited=0
while ! curl -sf http://localhost:8080/ > /dev/null 2>&1; do
    if [ $waited -ge $max_wait ]; then
        echo "⚠️  App not responding after ${max_wait}s - check logs with: docker compose logs app"
        break
    fi
    sleep 2
    waited=$((waited + 2))
    echo "  Waiting for app... (${waited}s)"
done

echo ""
echo "✅ KMP Development Environment is running!"
echo ""
echo "   📱 Application:  http://localhost:8080"
echo "   📧 Mailpit:      http://localhost:8025"
echo "   🗄️  MySQL:        localhost:3306"
echo ""
echo "Useful commands:"
echo "   docker compose logs -f app    # Follow app logs"
echo "   docker compose exec app bash  # Shell into app container"
echo "   ./dev-reset-db.sh             # Reset database"
echo "   ./dev-down.sh                 # Stop environment"
