# A script for initializing codespace dev env.
# Note the script is intended to be run at root of project.
# It needs to be adjusted for projects using a non-web docroot.
cd /workspaces/$(echo $RepositoryName)
PROJECT_NAME="/workspaces/$(echo $RepositoryName)"


#phpdebug 
# Copy over xdebug config
sudo cp .devcontainer/init_env/20-xdebug.ini /etc/php/7.4/cli/conf.d/20-xdebug.ini

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
sudo apachectl restart

#setup mysql
sudo service mysql start
sudo mysql <<EOFMYSQL
    CREATE USER '$(echo $MYSQL_DEV_USERNAME)'@'localhost' IDENTIFIED BY '$(echo $MYSQL_DEV_PASSWORD)'; 
    GRANT ALL PRIVILEGES ON *.* TO '$(echo $MYSQL_DEV_USERNAME)'@'localhost' WITH GRANT OPTION;
    CREATE DATABASE IF NOT EXISTS $(echo $MYSQL_DEV_DB_NAME) collate utf8_unicode_ci ;
    flush privileges;
EOFMYSQL

sudo echo "export MYSQL_USERNAME='$MYSQL_DEV_USERNAME'" >> /workspaces/$(echo $RepositoryName)/app/config/.env
sudo echo "export MYSQL_PASSWORD='$MYSQL_DEV_PASSWORD'" >> /workspaces/$(echo $RepositoryName)/app/config/.env
sudo echo "export MYSQL_DB_NAME='$MYSQL_DEV_DB_NAME'" >> /workspaces/$(echo $RepositoryName)/app/config/.env
sudo echo "export EMAIL_SMTP_HOST='$EMAIL_DEV_SMTP_HOST'" >> /workspaces/$(echo $RepositoryName)/app/config/.env
sudo echo "export EMAIL_SMTP_PORT='$EMAIL_DEV_SMTP_PORT'" >> /workspaces/$(echo $RepositoryName)/app/config/.env
sudo echo "export EMAIL_SMTP_USERNAME='$EMAIL_DEV_SMTP_USERNAME'" >> /workspaces/$(echo $RepositoryName)/app/config/.env
sudo echo "export EMAIL_SMTP_PASSWORD='$EMAIL_DEV_SMTP_PASSWORD'" >> /workspaces/$(echo $RepositoryName)/app/config/.env
sudo echo "export PATH_WKHTML='/usr/bin/wkhtmltopdf'" >> /workspaces/$(echo $RepositoryName)/app/config/.env


cd /workspaces/$(echo $RepositoryName)/app
composer install -n
php bin/cake.php migrations migrate
php bin/cake.php migrations seed --source Seeds/DevInit
php bin/cake.php bootstrap install --latest