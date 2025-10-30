#!/bin/bash
# Optimized script for initializing codespace dev env.
# This script now only contains operations that must happen at runtime.

cd $(echo $REPO_PATH)

echo "Starting runtime configuration..."

# Configure Apache with the actual repo path
sudo rm -f /etc/apache2/sites-available/000-default.conf
sed "s|{{REPO_PATH}}|$REPO_PATH|g" /opt/templates/apache-vhost.template > /tmp/000-default.conf
sudo mv /tmp/000-default.conf /etc/apache2/sites-available/000-default.conf

# Start MariaDB
echo "Starting MariaDB..."
sudo service mariadb start

# Setup MySQL databases and user
echo "Setting up MySQL databases..."
sudo mysql <<EOFMYSQL
    CREATE USER '$(echo $MYSQL_DEV_USERNAME)'@'localhost' IDENTIFIED BY '$(echo $MYSQL_DEV_PASSWORD)'; 
    GRANT ALL PRIVILEGES ON *.* TO '$(echo $MYSQL_DEV_USERNAME)'@'localhost' WITH GRANT OPTION;
    CREATE DATABASE IF NOT EXISTS $(echo $MYSQL_DEV_DB_NAME) collate utf8_unicode_ci ;
    CREATE DATABASE IF NOT EXISTS $(echo $MYSQL_DEV_DB_NAME)_test collate utf8_unicode_ci ;
    flush privileges;
EOFMYSQL

# Create environment configuration
echo "Creating environment configuration..."
sudo rm -f $(echo $REPO_PATH)/app/config/.env
cat > /tmp/.env <<EOF
export MYSQL_USERNAME='$MYSQL_DEV_USERNAME'
export MYSQL_PASSWORD='$MYSQL_DEV_PASSWORD'
export MYSQL_DB_NAME='$MYSQL_DEV_DB_NAME'
export EMAIL_SMTP_HOST='$EMAIL_DEV_SMTP_HOST'
export EMAIL_SMTP_PORT='$EMAIL_DEV_SMTP_PORT'
export EMAIL_SMTP_USERNAME='$EMAIL_DEV_SMTP_USERNAME'
export EMAIL_SMTP_PASSWORD='$EMAIL_DEV_SMTP_PASSWORD'
export PATH_WKHTML='/usr/bin/wkhtmltopdf'
EOF
sudo mv /tmp/.env $(echo $REPO_PATH)/app/config/.env

# Create Mailpit configuration
echo "Configuring Mailpit..."
sudo rm -f /etc/default/mailpit
sudo touch /etc/default/mailpit
sudo sh -c "echo \"export MP_SMTP_AUTH='$MP_SMTP_AUTH'\" >> /etc/default/mailpit"
sudo sh -c "echo \"export MP_SMTP_AUTH_ALLOW_INSECURE='$MP_SMTP_AUTH_ALLOW_INSECURE'\" >> /etc/default/mailpit"

# Start Mailpit service
echo "Starting Mailpit..."
sudo service mailpit start

# Create mermerd configuration
echo "Configuring mermerd..."
cat > ~/.mermerd <<EOF
showAllConstraints: true
encloseWithMermaidBackticks: true
connectionStringSuggestions:
  - mysql://$MYSQL_DEV_USERNAME:$MYSQL_DEV_PASSWORD@tcp(127.0.0.1:3306)/$MYSQL_DEV_DB_NAME
EOF

# Copy application configuration
echo "Setting up application configuration..."
rm -f $(echo $REPO_PATH)/app/config/app_local.php
cp $(echo $REPO_PATH)/.devcontainer/init_env/app_local.php $(echo $REPO_PATH)/app/config/app_local.php

# Install project dependencies
echo "Installing project dependencies..."
cd $(echo $REPO_PATH)/app
sudo composer install -n

# Reset and setup database
echo "Setting up database..."
cd $(echo $REPO_PATH)
sudo bash reset_dev_database.sh

# Install PHP dependencies and setup project
echo "Bootstrapping application..."
cd $(echo $REPO_PATH)/app
sudo php bin/cake.php bootstrap install --latest

# Install Node.js dependencies
echo "Installing Node.js dependencies..."
sudo npm install

# Install Playwright browsers (only if not already installed)
if [ ! -d "node_modules/playwright" ] || [ ! -d "$HOME/.cache/ms-playwright" ]; then
    echo "Installing Playwright browsers..."
    sudo npx playwright install-deps
fi

# Setup proper permissions for logs and tmp directories
echo "Setting up logs and tmp directories..."
sudo mkdir -p "$REPO_PATH/app/logs"
sudo mkdir -p "$REPO_PATH/app/tmp"
sudo mkdir -p "$REPO_PATH/app/tmp/cache"
sudo mkdir -p "$REPO_PATH/app/tmp/cache/models"
sudo mkdir -p "$REPO_PATH/app/tmp/cache/persistent"
sudo mkdir -p "$REPO_PATH/app/tmp/cache/views"
sudo mkdir -p "$REPO_PATH/app/tmp/sessions"
sudo mkdir -p "$REPO_PATH/app/tmp/tests"
sudo chmod -R 775 "$REPO_PATH/app/logs"
sudo chmod -R 775 "$REPO_PATH/app/tmp"
sudo chown -R www-data:www-data "$REPO_PATH/app/logs"
sudo chown -R www-data:www-data "$REPO_PATH/app/tmp"

# Setup cron job for queue processing
echo "Setting up cron job..."
if ! crontab -u vscode -l &>/dev/null; then
  echo "Creating crontab environment for user 'vscode'."
  crontab -u vscode -l 2>/dev/null | crontab -u vscode -
fi

# Define the cron job schedule and command
cron_schedule="*/2 * * * *"
cron_command="cd $(echo $REPO_PATH)/app && bin/cake queue run -q"
cron_job="$cron_schedule $cron_command"

# Check if the cron job already exists
(crontab -l | grep -F "$cron_job") && echo "Cron job already exists." || (crontab -l 2>/dev/null; echo "$cron_job") | crontab -

# Configure cron logging and start service
sudo chmod 644 /var/log/cron.log && sudo chown vscode:vscode /var/log/cron.log
sudo service cron start

# Enable SSL module and generate self-signed cert if needed
sudo a2enmod ssl
if [ ! -f /etc/apache2/ssl/dev.crt ] || [ ! -f /etc/apache2/ssl/dev.key ]; then
  bash $REPO_PATH/.devcontainer/init_env/generate_dev_ssl.sh
fi

#sudo chown -R vscode:vscode /workspaces/KMP
curl -LsSf https://astral.sh/uv/install.sh | sh
uv tool install specify-cli --from git+https://github.com/github/spec-kit.git

sudo mkdir -p /workspaces/KMP/app/images/uploaded
sudo mkdir -p /workspaces/KMP/app/images/cache
sudo chmod -R 766 /workspaces/KMP/app/images
sudo chown -R www-data:www-data /workspaces/KMP/app/images

# Start Apache
echo "Starting Apache..."
sudo apachectl restart


