# A script for initializing codespace dev env.
# Note the script is intended to be run at root of project.
# It needs to be adjusted for projects using a non-web docroot.
cd $(echo $REPO_PATH)


#phpdebug 
# Copy over xdebug config
sudo cp .devcontainer/init_env/20-xdebug.ini /etc/php/8.3/cli/conf.d/20-xdebug.ini

# Configure Apache
sudo rm /etc/apache2/sites-available/000-default.conf
cat > 000-default.conf <<EOF
<VirtualHost *:8080>
  DocumentRoot $REPO_PATH/app
  <Directory "$REPO_PATH/app" >
    Options FollowSymLinks
    AllowOverride All
    DirectoryIndex index.php
    Require all granted
  </Directory>
  ErrorLog ${APACHE_LOG_DIR}/error.log
  CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF
sudo mv 000-default.conf /etc/apache2/sites-available/000-default.conf
sudo bash -c 'echo "zend.assertions=1" >> /etc/php/8.3/apache2/php.ini'
sudo apachectl restart

sudo sudo bash -c 'echo "zend.assertions=1" >> /etc/php/8.3/cli/php.ini'

#setup mysql
sudo service mariadb start
sudo mysql <<EOFMYSQL
    CREATE USER '$(echo $MYSQL_DEV_USERNAME)'@'localhost' IDENTIFIED BY '$(echo $MYSQL_DEV_PASSWORD)'; 
    GRANT ALL PRIVILEGES ON *.* TO '$(echo $MYSQL_DEV_USERNAME)'@'localhost' WITH GRANT OPTION;
    CREATE DATABASE IF NOT EXISTS $(echo $MYSQL_DEV_DB_NAME) collate utf8_unicode_ci ;
    CREATE DATABASE IF NOT EXISTS $(echo $MYSQL_DEV_DB_NAME)_test collate utf8_unicode_ci ;
    flush privileges;
EOFMYSQL
sudo rm $(echo $REPO_PATH)/app/config/.env

sudo echo "export MYSQL_USERNAME='$MYSQL_DEV_USERNAME'" >> $(echo $REPO_PATH)/app/config/.env
sudo echo "export MYSQL_PASSWORD='$MYSQL_DEV_PASSWORD'" >> $(echo $REPO_PATH)/app/config/.env
sudo echo "export MYSQL_DB_NAME='$MYSQL_DEV_DB_NAME'" >> $(echo $REPO_PATH)/app/config/.env
sudo echo "export EMAIL_SMTP_HOST='$EMAIL_DEV_SMTP_HOST'" >> $(echo $REPO_PATH)/app/config/.env
sudo echo "export EMAIL_SMTP_PORT='$EMAIL_DEV_SMTP_PORT'" >> $(echo $REPO_PATH)/app/config/.env
sudo echo "export EMAIL_SMTP_USERNAME='$EMAIL_DEV_SMTP_USERNAME'" >> $(echo $REPO_PATH)/app/config/.env
sudo echo "export EMAIL_SMTP_PASSWORD='$EMAIL_DEV_SMTP_PASSWORD'" >> $(echo $REPO_PATH)/app/config/.env
sudo echo "export PATH_WKHTML='/usr/bin/wkhtmltopdf'" >> $(echo $REPO_PATH)/app/config/.env



# Remove any existing Go installation
sudo apt-get remove -y golang-go

# Detect architecture and download appropriate Go version
cd ~
ARCH=$(uname -m)
if [ "$ARCH" = "x86_64" ]; then
    # AMD64 architecture
    GO_PACKAGE="go1.22.0.linux-amd64.tar.gz"
elif [ "$ARCH" = "aarch64" ] || [ "$ARCH" = "arm64" ]; then
    # ARM64 architecture
    GO_PACKAGE="go1.22.0.linux-arm64.tar.gz"
else
    echo "Unsupported architecture: $ARCH"
    exit 1
fi

echo "Detected architecture: $ARCH, downloading $GO_PACKAGE"
wget "https://go.dev/dl/$GO_PACKAGE"
sudo rm -rf /usr/local/go
sudo tar -C /usr/local -xzf "$GO_PACKAGE"
rm "$GO_PACKAGE"

# Update PATH to include Go
export PATH=$PATH:/usr/local/go/bin
echo 'export PATH=$PATH:/usr/local/go/bin' >> ~/.bashrc

# Verify Go installation
go version

# Now install mermerd with the updated Go version
go install github.com/KarnerTh/mermerd@latest

sudo bash < <(curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh)



# create systemd service file
sudo rm /etc/init.d/mailpit
sudo cp $(echo $REPO_PATH)/.devcontainer/init_env/mailpit.init.d /etc/init.d/mailpit
sudo chmod +x /etc/init.d/mailpit
sudo update-rc.d mailpit defaults

#make default config for mailpit
sudo rm /etc/default/mailpit
sudo touch /etc/default/mailpit
sudo rm -rf  $(echo $REPO_PATH)/app/webroot/bootstrap_u_l
sudo sh -c "echo \"export MP_SMTP_AUTH='$MP_SMTP_AUTH'\" >> /etc/default/mailpit"
sudo sh -c "echo \"export MP_SMTP_AUTH_ALLOW_INSECURE='$MP_SMTP_AUTH_ALLOW_INSECURE'\" >> /etc/default/mailpit"

# enable and start the service
sudo service mailpit start


rm ~/.meremerd
cat > ~/.mermerd <<EOF
showAllConstraints: true
encloseWithMermaidBackticks: true
connectionStringSuggestions:
  - mysql://$MYSQL_DEV_USERNAME:$MYSQL_DEV_PASSWORD@tcp(127.0.0.1:3306)/$MYSQL_DEV_DB_NAME
EOF

rm $(echo $REPO_PATH)/app/config/app_local.php
cp $(echo $REPO_PATH)/.devcontainer/init_env/app_local.php $(echo $REPO_PATH)/app/config/app_local.php

cd $(echo $REPO_PATH)/app
sudo composer install -n
cd ..
sudo bash reset_dev_database.sh
cd app
sudo php bin/cake.php bootstrap install --latest
sudo npm install

#check if cron exists for the user
if ! crontab -u vscode -l &>/dev/null; then
  echo "Creating crontab environment for user 'vscode'."
  crontab -u vscode -l 2>/dev/null | crontab -u vscode -
fi

# Define the cron job schedule and command
cron_schedule="*/2 * * * *"  # This example runs the task every day at 7 AM
cron_command="cd $(echo $REPO_PATH)/app && bin/cake queue run -q"
cron_job="$cron_schedule $cron_command"

wget -O phpunit.phar https://phar.phpunit.de/phpunit-10.phar
chmod +x phpunit.phar
sudo mv phpunit.phar /usr/local/bin/phpunit

# Check if the cron job already exists
(crontab -l | grep -F "$cron_job") && echo "Cron job already exists." || (crontab -l 2>/dev/null; echo "$cron_job") | crontab -
sudo chmod 644 /var/log/cron.log && sudo chown vscode:vscode /var/log/cron.log
sudo service cron start
