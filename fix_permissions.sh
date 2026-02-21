#!/bin/bash
# Script to fix runtime permissions for Apache web server access.
# This mirrors production-style writable paths for www-data.

set -euo pipefail

REPO_PATH="${REPO_PATH:-/workspaces/KMP}"
APP_PATH="$REPO_PATH/app"

echo "Fixing runtime permissions for Apache web server (www-data)..."

echo "  - Ensuring runtime directories exist..."
sudo mkdir -p "$APP_PATH/logs"
sudo mkdir -p "$APP_PATH/tmp/cache/models"
sudo mkdir -p "$APP_PATH/tmp/cache/persistent"
sudo mkdir -p "$APP_PATH/tmp/cache/views"
sudo mkdir -p "$APP_PATH/tmp/sessions"
sudo mkdir -p "$APP_PATH/tmp/tests"
sudo mkdir -p "$APP_PATH/images/uploaded"
sudo mkdir -p "$APP_PATH/images/cache"
sudo mkdir -p "$APP_PATH/backups"

echo "  - Applying ownership and mode (www-data:www-data, 775)..."
for runtime_dir in logs tmp images backups; do
    sudo chown -R www-data:www-data "$APP_PATH/$runtime_dir"
    sudo chmod -R 775 "$APP_PATH/$runtime_dir"
done

if [[ "${SKIP_APACHE_RESTART:-0}" != "1" ]]; then
    echo "  - Restarting Apache..."
    sudo apachectl restart
fi

echo ""
echo "✓ Runtime permissions fixed successfully."
echo ""
echo "Directory ownership and permissions:"
ls -ld "$APP_PATH/logs" "$APP_PATH/tmp" "$APP_PATH/images" "$APP_PATH/backups"
