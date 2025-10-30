#!/bin/bash
# Script to fix permissions for Apache web server access
# Run this script if logs, tmp, or images directories have permission issues

REPO_PATH="${REPO_PATH:-/workspaces/KMP}"

echo "Fixing permissions for Apache web server (www-data)..."

# Fix logs directory
echo "  - Setting up logs directory..."
sudo mkdir -p "$REPO_PATH/app/logs"
sudo chmod -R 777 "$REPO_PATH/app/logs"
sudo chown -R www-data:www-data "$REPO_PATH/app/logs"

# Fix tmp directory and all subdirectories
echo "  - Setting up tmp directory..."
sudo mkdir -p "$REPO_PATH/app/tmp"
sudo mkdir -p "$REPO_PATH/app/tmp/cache"
sudo mkdir -p "$REPO_PATH/app/tmp/cache/models"
sudo mkdir -p "$REPO_PATH/app/tmp/cache/persistent"
sudo mkdir -p "$REPO_PATH/app/tmp/cache/views"
sudo mkdir -p "$REPO_PATH/app/tmp/sessions"
sudo mkdir -p "$REPO_PATH/app/tmp/tests"
sudo chmod -R 777 "$REPO_PATH/app/tmp"
sudo chown -R www-data:www-data "$REPO_PATH/app/tmp"

# Fix images directory
echo "  - Setting up images directory..."
sudo mkdir -p "$REPO_PATH/app/images/uploaded"
sudo mkdir -p "$REPO_PATH/app/images/cache"
sudo chmod -R 777 "$REPO_PATH/app/images"
sudo chown -R www-data:www-data "$REPO_PATH/app/images"

# Restart Apache to ensure changes take effect
echo "  - Restarting Apache..."
sudo apachectl restart

echo ""
echo "✓ Permissions fixed successfully!"
echo ""
echo "Directory ownership and permissions:"
ls -ld "$REPO_PATH/app/logs" "$REPO_PATH/app/tmp" "$REPO_PATH/app/images"
