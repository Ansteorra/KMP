# A script for initializing codespace dev env.
# Note the script is intended to be run at root of project.
# It needs to be adjusted for projects using a non-web docroot.
cd /workspaces/$(echo $REPO_PATH)
PROJECT_NAME="/workspaces/$(echo $REPO_PATH)"


#phpdebug 
# Copy over xdebug config
sudo cp .devcontainer/init_env/20-xdebug.ini /etc/php/8.3/cli/conf.d/20-xdebug.ini

# Configure Apache
sudo rm /etc/apache2/sites-available/000-default.conf
cat > 000-default.conf <<EOF
<VirtualHost *:8080>
  DocumentRoot $PROJECT_NAME/app
  <Directory "$PROJECT_NAME/app" >
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
    flush privileges;
EOFMYSQL
sudo rm /workspaces/$(echo $REPO_PATH)/app/config/.env

sudo echo "export MYSQL_USERNAME='$MYSQL_DEV_USERNAME'" >> /workspaces/$(echo $REPO_PATH)/app/config/.env
sudo echo "export MYSQL_PASSWORD='$MYSQL_DEV_PASSWORD'" >> /workspaces/$(echo $REPO_PATH)/app/config/.env
sudo echo "export MYSQL_DB_NAME='$MYSQL_DEV_DB_NAME'" >> /workspaces/$(echo $REPO_PATH)/app/config/.env
sudo echo "export EMAIL_SMTP_HOST='$EMAIL_DEV_SMTP_HOST'" >> /workspaces/$(echo $REPO_PATH)/app/config/.env
sudo echo "export EMAIL_SMTP_PORT='$EMAIL_DEV_SMTP_PORT'" >> /workspaces/$(echo $REPO_PATH)/app/config/.env
sudo echo "export EMAIL_SMTP_USERNAME='$EMAIL_DEV_SMTP_USERNAME'" >> /workspaces/$(echo $REPO_PATH)/app/config/.env
sudo echo "export EMAIL_SMTP_PASSWORD='$EMAIL_DEV_SMTP_PASSWORD'" >> /workspaces/$(echo $REPO_PATH)/app/config/.env
sudo echo "export PATH_WKHTML='/usr/bin/wkhtmltopdf'" >> /workspaces/$(echo $REPO_PATH)/app/config/.env

rm /workspaces/$(echo $REPO_PATH)/app/config/app_local.php
cp /workspaces/$(echo $REPO_PATH)/.devcontainer/init_env/app_local.php /workspaces/$(echo $REPO_PATH)/app/config/app_local.php

cd /workspaces/$(echo $REPO_PATH)/app
composer install -n
cd ..
sudo bash reset_dev_database.sh
cd app
php bin/cake.php bootstrap install --latest