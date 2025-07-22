---
layout: default
---
[← Back to Table of Contents](index.md)

# 8. Deployment

This section covers the processes and considerations for deploying the Kingdom Management Portal to production environments.

## 8.1 Production Setup

### Server Requirements

For a production deployment, the following server configuration is recommended:

- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: PHP 8.1+ with required extensions (see [System Requirements](1-introduction.md#13-system-requirements))
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Memory**: Minimum 2GB RAM (4GB+ recommended)
- **Storage**: 10GB+ disk space (more if storing many attachments)
- **SSL Certificate**: Required for secure user authentication

### Directory Structure

The recommended production directory structure separates public and non-public files:

```
/var/www/kmp/                  # Root application directory (non-public)
├── app/                       # CakePHP application
│   ├── config/                # Configuration files
│   ├── logs/                  # Log files
│   ├── src/                   # Application source code
│   ├── templates/             # View templates
│   ├── assets/                # Source asset files (CSS/JS)
│   ├── ...                    # Other application directories
│   └── webroot/               # Public web files (document root)
├── bin/                       # CLI commands
├── vendor/                    # Composer dependencies
└── tmp/                       # Temporary files (cache, sessions, etc.)

/etc/apache2/sites-available/  # Apache configuration
/etc/nginx/sites-available/    # Nginx configuration
/etc/php/8.0/                  # PHP configuration
/etc/mysql/                    # MySQL configuration
```

### Web Server Configuration

#### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName kmp.example.com
    DocumentRoot /var/www/kmp/app/webroot
    
    <Directory /var/www/kmp/app/webroot>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/kmp-error.log
    CustomLog ${APACHE_LOG_DIR}/kmp-access.log combined
    
    # Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName kmp.example.com
    DocumentRoot /var/www/kmp/app/webroot
    
    <Directory /var/www/kmp/app/webroot>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/kmp-error.log
    CustomLog ${APACHE_LOG_DIR}/kmp-access.log combined
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/kmp.example.com.crt
    SSLCertificateKeyFile /etc/ssl/private/kmp.example.com.key
    SSLCertificateChainFile /etc/ssl/certs/kmp.example.com.chain.crt
    
    # HTTP Strict Transport Security
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</VirtualHost>
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name kmp.example.com;
    
    # Redirect to HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name kmp.example.com;
    
    root /var/www/kmp/app/webroot;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /etc/ssl/certs/kmp.example.com.crt;
    ssl_certificate_key /etc/ssl/private/kmp.example.com.key;
    ssl_trusted_certificate /etc/ssl/certs/kmp.example.com.chain.crt;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Logs
    access_log /var/log/nginx/kmp-access.log;
    error_log /var/log/nginx/kmp-error.log;
    
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
```

### PHP Configuration

Optimize PHP for production with these `php.ini` settings:

```ini
; Maximum memory allocation
memory_limit = 256M

; Maximum upload file size
upload_max_filesize = 20M
post_max_size = 20M

; Error reporting (disable display, enable logging)
display_errors = Off
log_errors = On
error_log = /var/www/kmp/app/logs/php_errors.log

; Opcode caching
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
```

### Environment Configuration

Create an `app_local.php` file in the `config` directory with production-specific settings:

```php
return [
    'debug' => false,
    'Security' => [
        'salt' => 'your-long-random-security-salt-string',
    ],
    'Datasources' => [
        'default' => [
            'host' => 'localhost',
            'username' => 'kmp_db_user',
            'password' => 'secure-password',
            'database' => 'kmp_production',
            'log' => false,
        ],
    ],
    'EmailTransport' => [
        'default' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'email@example.com',
            'password' => 'email-password',
            'tls' => true,
        ],
    ],
    // Additional production settings...
];
```

## 8.2 Migrations

Database changes in KMP are managed through CakePHP's migrations system, which provides version control for your database schema.

### Creating Migrations

When making database changes, create a migration file:

```bash
# Create a new migration
cd /var/www/kmp
bin/cake bake migration CreateUsers

# Create a migration for an existing table
bin/cake bake migration AlterUsers

# Create a migration for a plugin
bin/cake bake migration -p PluginName CreatePluginTable
```

### Running Migrations

To apply migrations in production:

```bash
# Apply all pending migrations
bin/cake migrations migrate

# Apply migrations for a plugin
bin/cake migrations migrate -p PluginName

# Apply migrations up to a specific version
bin/cake migrations migrate --target=20250401000000

# Rollback the last migration
bin/cake migrations rollback
```

### Migration Deployment Process

Follow this process when deploying database changes:

1. **Backup**: Always back up the production database before applying migrations
   ```bash
   mysqldump -u user -p kmp_production > kmp_backup_YYYYMMDD.sql
   ```

2. **Maintenance Mode**: Enable maintenance mode during database updates
   ```php
   # Set maintenance mode app setting
   StaticHelpers::setAppSetting('KMP.MaintenanceMode', 'yes');
   ```

3. **Apply Migrations**: Run the migrations
   ```bash
   bin/cake migrations migrate
   bin/cake migrations migrate -p PluginName1
   bin/cake migrations migrate -p PluginName2
   # etc.
   ```

4. **Verify**: Confirm that migrations were applied successfully
   ```bash
   bin/cake migrations status
   ```

5. **Disable Maintenance Mode**: Return the application to normal operation
   ```php
   StaticHelpers::setAppSetting('KMP.MaintenanceMode', 'no');
   ```

### Migration Order

Plugins in KMP have a defined migration order, as specified in their plugin registration. This ensures that dependencies between plugins are respected during migration. The order is defined in `config/plugins.php`:

```php
'Activities' => [
    'migrationOrder' => 1,
],
'Officers' => [
    'migrationOrder' => 2,
],
'Awards' => [
    'migrationOrder' => 3,
],
```

## 8.3 Updates

Keeping your KMP installation up-to-date involves updating both the application code and the database schema.

### Update Process

Follow these steps to update KMP in a production environment:

1. **Backup**: Create backups of both code and database
   ```bash
   # Backup code
   cp -r /var/www/kmp /var/www/kmp-backup-YYYYMMDD
   
   # Backup database
   mysqldump -u user -p kmp_production > kmp_backup_YYYYMMDD.sql
   ```

2. **Maintenance Mode**: Enable maintenance mode
   ```php
   StaticHelpers::setAppSetting('KMP.MaintenanceMode', 'yes');
   ```

3. **Get Updates**: Pull the latest code from the repository
   ```bash
   cd /var/www/kmp
   git fetch origin
   git checkout v1.x.x  # Replace with the target version
   ```

4. **Update Dependencies**: Update Composer and NPM dependencies
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run prod
   ```

5. **Clear Cache**: Remove cached files
   ```bash
   bin/cake cache clear_all
   ```

6. **Apply Migrations**: Update the database schema
   ```bash
   bin/cake migrations migrate
   # Apply plugin migrations as needed
   ```

7. **Update Settings**: Apply any new app settings
   ```bash
   bin/cake app_settings initialize
   ```

8. **Test**: Verify the application works correctly
   ```bash
   bin/cake server -p 8080  # Test on a non-production port
   ```

9. **Disable Maintenance Mode**: Return to normal operation
   ```php
   StaticHelpers::setAppSetting('KMP.MaintenanceMode', 'no');
   ```

### Rollback Procedure

If issues are encountered during the update, follow these steps to roll back:

1. **Restore Code**: Replace the code directory with the backup
   ```bash
   rm -rf /var/www/kmp
   cp -r /var/www/kmp-backup-YYYYMMDD /var/www/kmp
   ```

2. **Restore Database**: Restore the database backup
   ```bash
   mysql -u user -p kmp_production < kmp_backup_YYYYMMDD.sql
   ```

3. **Clear Cache**: Clear any cached files
   ```bash
   bin/cake cache clear_all
   ```

4. **Disable Maintenance Mode**: Return to normal operation
   ```php
   StaticHelpers::setAppSetting('KMP.MaintenanceMode', 'no');
   ```

### Version Management

KMP uses semantic versioning (MAJOR.MINOR.PATCH):

- **MAJOR**: Incompatible API changes requiring significant migration effort
- **MINOR**: Backward-compatible new features
- **PATCH**: Backward-compatible bug fixes

The current application version is stored in the `app_settings` table:
```php
StaticHelpers::getAppSetting('App.version');
```
