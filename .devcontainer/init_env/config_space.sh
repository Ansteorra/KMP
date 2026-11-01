#!/bin/bash
# Optimized script for initializing codespace dev env.
# This script now only contains operations that must happen at runtime.

cd $(echo $REPO_PATH)

echo "Starting runtime configuration..."

# Configure Apache with the actual repo path
sudo rm -f /etc/apache2/sites-available/000-default.conf
sed "s|{{REPO_PATH}}|$REPO_PATH|g" /opt/templates/apache-vhost.template > /tmp/000-default.conf
sudo mv /tmp/000-default.conf /etc/apache2/sites-available/000-default.conf

# Start PostgreSQL 16 (matches Azure Database for PostgreSQL Flex) for local development.
POSTGRES_USER="${POSTGRES_USER:-kmpdev}"
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-kmpdevpass}"
POSTGRES_DB="${POSTGRES_DB:-KMP_DEV}"
echo "Starting PostgreSQL 16..."
sudo pg_ctlcluster 16 main start || sudo service postgresql start

# Wait briefly for PG to accept connections before provisioning.
for i in 1 2 3 4 5 6 7 8 9 10; do
    if sudo -u postgres pg_isready -q; then break; fi
    sleep 1
done

echo "Setting up PostgreSQL role and databases..."
# Idempotent provisioning: role + PostgreSQL development/test databases.
sudo -u postgres psql -v ON_ERROR_STOP=1 <<EOFPG
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '${POSTGRES_USER}') THEN
        CREATE ROLE "${POSTGRES_USER}" LOGIN PASSWORD '${POSTGRES_PASSWORD}' CREATEDB;
    ELSE
        ALTER ROLE "${POSTGRES_USER}" LOGIN PASSWORD '${POSTGRES_PASSWORD}' CREATEDB;
    END IF;
END
\$\$;
EOFPG
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname = '${POSTGRES_DB}'" | grep -q 1 || \
    sudo -u postgres createdb -O "${POSTGRES_USER}" "${POSTGRES_DB}"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname = '${POSTGRES_DB}_test'" | grep -q 1 || \
    sudo -u postgres createdb -O "${POSTGRES_USER}" "${POSTGRES_DB}_test"

# Create environment configuration
echo "Creating environment configuration..."
sudo rm -f $(echo $REPO_PATH)/app/config/.env
cat > /tmp/.env <<EOF
export DB_USERNAME='$POSTGRES_USER'
export DB_PASSWORD='$POSTGRES_PASSWORD'
export DB_DATABASE='$POSTGRES_DB'
export KMP_DB_DRIVER='postgres'
export DB_HOST='localhost'
export DB_PORT='5432'
export EMAIL_SMTP_HOST='$EMAIL_DEV_SMTP_HOST'
export EMAIL_SMTP_PORT='$EMAIL_DEV_SMTP_PORT'
export EMAIL_SMTP_USERNAME='$EMAIL_DEV_SMTP_USERNAME'
export EMAIL_SMTP_PASSWORD='$EMAIL_DEV_SMTP_PASSWORD'
export PATH_WKHTML='/usr/bin/wkhtmltopdf'
export AZURE_STORAGE_CONNECTION_STRING='GO GET THIS FROM AZURE PORTAL'

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
  - postgres://$POSTGRES_USER:$POSTGRES_PASSWORD@127.0.0.1:5432/$POSTGRES_DB
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

# Apply production-like runtime permissions for Apache user
echo "Applying runtime permissions..."
SKIP_APACHE_RESTART=1 REPO_PATH="$REPO_PATH" bash "$REPO_PATH/fix_permissions.sh"

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
# Start Apache
echo "Starting Apache..."
sudo apachectl restart
