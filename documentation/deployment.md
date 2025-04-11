# Deployment and Environment Setup

This document describes the deployment process and environment setup for the Kingdom Management Portal (KMP).

## Development Environment

### Requirements

- PHP 8.1 or higher
- MySQL 8.0 or higher
- Composer
- Node.js and NPM
- Git

### Development Container

KMP includes a development container configuration for VS Code, providing a consistent development environment:

```
.devcontainer/
├── devcontainer.json      # Container configuration
├── Dockerfile             # Custom Docker image definition
└── init_env/              # Environment initialization scripts
    └── app_local.php      # Local application configuration
```

To use the development container:

1. Install [Docker](https://www.docker.com/products/docker-desktop) and the [VS Code Remote - Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers)
2. Open the KMP project in VS Code
3. When prompted, click "Reopen in Container" or run the "Remote-Containers: Reopen in Container" command
4. VS Code will build and start the development container

### Manual Setup

If you prefer not to use the development container:

1. Clone the repository:
   ```bash
   git clone https://github.com/Ansteorra/KMP.git
   cd KMP
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install JavaScript dependencies:
   ```bash
   npm install
   ```

4. Create a local configuration file:
   ```bash
   cp config/app_local.example.php config/app_local.php
   ```

5. Edit `config/app_local.php` with your database connection details

6. Run database migrations:
   ```bash
   bin/cake migrations migrate
   ```

7. Run the development server:
   ```bash
   bin/cake server
   ```

## Database Setup

### Initial Database Setup

1. Create a MySQL database for KMP
2. Update the database connection details in `config/app_local.php`:
   ```php
   'Datasources' => [
       'default' => [
           'host' => 'localhost',
           'username' => 'my_user',
           'password' => 'my_password',
           'database' => 'kmp',
           'url' => env('DATABASE_URL', null),
       ],
   ],
   ```

3. Run migrations to create the initial schema:
   ```bash
   bin/cake migrations migrate
   ```

4. Seed the database with initial data:
   ```bash
   bin/cake migrations seed
   ```

### Database Management Scripts

KMP includes several scripts for database management:

- `bin/reset_dev_database.sh`: Resets the development database (caution: destroys all data)
- `bin/update_database.sh`: Applies pending migrations
- `bin/revert_database.sh`: Reverts the most recent migration

### Migration Management

To create a new migration:

```bash
bin/cake bake migration CreateTable --connection default
```

To run specific migrations:

```bash
bin/cake migrations migrate -t 20241001141705
```

## Asset Compilation

KMP uses Laravel Mix (a wrapper for webpack) for asset compilation:

### Development Build

```bash
npm run dev
```

This builds the assets once for development.

### Watch Mode

```bash
npm run watch
```

This watches for changes and rebuilds assets automatically.

### Production Build

```bash
npm run production
```

This builds minified and optimized assets for production.

## Application Configuration

### Configuration Files

- `config/app.php`: Main application configuration
- `config/app_local.php`: Environment-specific configuration (not version controlled)
- `config/app_queue.php`: Queue plugin configuration
- `config/bootstrap.php`: Application bootstrap process
- `config/routes.php`: URL routing configuration

### Environment Variables

KMP supports configuration through environment variables:

```
# Example .env file
DEBUG=true
APP_ENCODING=UTF-8
APP_DEFAULT_LOCALE=en_US
APP_DEFAULT_TIMEZONE=UTC
DATABASE_URL=mysql://user:password@localhost/kmp
EMAIL_TRANSPORT_DEFAULT_URL=smtp://user:password@localhost:25
```

Environment variables can be loaded using the `josegonzalez/dotenv` package included in CakePHP.

### Application Settings

Many KMP settings are stored in the database and managed through the `AppSettings` controller:

- These settings can be accessed via `StaticHelpers::getAppSetting()`
- Initial settings are defined in `Application.php` during bootstrap
- Settings are categorized (e.g., "KMP.", "Member.", "Email.")

## Deployment to Production

### Production Requirements

- PHP 8.1 or higher
- MySQL 8.0 or higher
- Web server (Apache or Nginx)
- SSL certificate (recommended)
- Adequate storage space (min. 1GB)
- Adequate memory (min. 512MB)

### Deployment Steps

1. **Prepare the environment**
   - Set up a production server with PHP and MySQL
   - Configure a web server (Apache or Nginx)
   - Set up SSL certificate

2. **Clone or upload the application**
   ```bash
   git clone https://github.com/Ansteorra/KMP.git /var/www/kmp
   cd /var/www/kmp
   ```

3. **Install dependencies**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm install --production
   ```

4. **Build assets**
   ```bash
   npm run production
   ```

5. **Configure the application**
   - Create and edit `config/app_local.php`
   - Set `debug` to `false`
   - Configure database connection
   - Configure email settings
   - Set appropriate security salt

6. **Set file permissions**
   ```bash
   chmod -R 755 tmp/
   chmod -R 755 logs/
   ```

7. **Run migrations**
   ```bash
   bin/cake migrations migrate
   ```

8. **Configure the web server**

   **Apache example**:
   ```apache
   <VirtualHost *:80>
       ServerName kmp.example.com
       DocumentRoot /var/www/kmp/webroot
       
       <Directory /var/www/kmp/webroot>
           Options FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/kmp_error.log
       CustomLog ${APACHE_LOG_DIR}/kmp_access.log combined
   </VirtualHost>
   ```

   **Nginx example**:
   ```nginx
   server {
       listen 80;
       server_name kmp.example.com;
       root /var/www/kmp/webroot;
       index index.php;
       
       location / {
           try_files $uri $uri/ /index.php?$args;
       }
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
           fastcgi_index index.php;
           include fastcgi_params;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       }
   }
   ```

9. **Set up SSL**
   - Configure Let's Encrypt or another SSL provider
   - Update web server configuration to use HTTPS

10. **Set up cron jobs**
    ```cron
    # Run queue worker every minute
    * * * * * cd /var/www/kmp && bin/cake queue run -q
    
    # Check for expired warrants daily
    0 0 * * * cd /var/www/kmp && bin/cake check_warrants
    ```

### Deployment Automation

For automated deployments, consider using:
- GitHub Actions
- Jenkins
- GitLab CI/CD
- Other CI/CD systems

Example GitHub Actions workflow for deployment:

```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, intl, pdo_mysql
        
    - name: Setup Node.js
      uses: actions/setup-node@v2
      with:
        node-version: '16'
        
    - name: Install PHP dependencies
      run: composer install --no-dev --optimize-autoloader
      
    - name: Install JS dependencies
      run: npm install --production
      
    - name: Build assets
      run: npm run production
      
    - name: Deploy to server
      uses: easingthemes/ssh-deploy@main
      env:
        SSH_PRIVATE_KEY: ${{ secrets.SERVER_SSH_KEY }}
        ARGS: "-rltgoDzvO --delete"
        SOURCE: "./"
        REMOTE_HOST: ${{ secrets.REMOTE_HOST }}
        REMOTE_USER: ${{ secrets.REMOTE_USER }}
        TARGET: "/var/www/kmp"
        EXCLUDE: ".git/, .github/, node_modules/, tests/"
        
    - name: Run migrations
      uses: appleboy/ssh-action@master
      with:
        host: ${{ secrets.REMOTE_HOST }}
        username: ${{ secrets.REMOTE_USER }}
        key: ${{ secrets.SERVER_SSH_KEY }}
        script: |
          cd /var/www/kmp
          bin/cake migrations migrate
          chmod -R 755 tmp/
          chmod -R 755 logs/
```

## Server Maintenance

### Regular Maintenance Tasks

1. **Database backup**
   ```bash
   mysqldump -u user -p kmp > kmp_backup_$(date +%Y%m%d).sql
   ```

2. **Application backup**
   ```bash
   tar -czf kmp_backup_$(date +%Y%m%d).tar.gz /var/www/kmp
   ```

3. **Log rotation**
   Configure `logrotate` to manage log files:
   ```
   /var/www/kmp/logs/*.log {
       daily
       missingok
       rotate 14
       compress
       delaycompress
       notifempty
       create 0640 www-data www-data
   }
   ```

4. **Updates and security patches**
   ```bash
   composer update
   npm update
   bin/cake migrations migrate
   ```

### Monitoring

Consider setting up monitoring for:
- Server health
- Application errors
- Database performance
- Queue worker status

## Scaling Considerations

For larger kingdoms or high-traffic deployments:

1. **Database optimization**
   - Use database indexing strategically
   - Consider database replication
   - Implement query caching

2. **Caching**
   - Enable CakePHP's built-in caching
   - Consider using Redis or Memcached
   - Cache frequently accessed data and rendered views

3. **Queue workers**
   - Run multiple queue workers for parallel processing
   - Use supervisor or systemd to manage queue worker processes

4. **Load balancing**
   - Use multiple web servers behind a load balancer
   - Ensure session management works across servers

## Troubleshooting

### Common Issues

1. **Permission problems**
   ```bash
   chmod -R 755 tmp/
   chmod -R 755 logs/
   chown -R www-data:www-data /var/www/kmp
   ```

2. **Database connection issues**
   - Verify database credentials in `config/app_local.php`
   - Check MySQL server status
   - Verify network connectivity to the database server

3. **Blank pages or 500 errors**
   - Check PHP error logs
   - Check KMP logs in `logs/`
   - Temporarily enable debug mode

4. **Slow performance**
   - Check database query logs
   - Monitor server resource usage
   - Review application logs for bottlenecks

## Next Steps

- For information about contributing to the project, see [Contributing Guidelines](./contributing.md)
- To understand the API documentation, see [API Documentation](./api-docs.md)
- For overall project architecture, see [System Architecture](./architecture.md)