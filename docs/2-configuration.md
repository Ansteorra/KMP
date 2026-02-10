---
layout: default
---
[← Back to Table of Contents](index.md)

# 2. Configuration

This section covers the Kingdom Management Portal (KMP) configuration system, including application settings, environment variables, and configuration files.

## 2.1 Configuration File Overview

KMP configuration is managed through several files in the `config/` directory:

### Primary Configuration Files

- **`config/app.php`**: Core application settings (framework and CakePHP configuration)
- **`config/app_local.php`**: Environment-specific overrides (NOT version controlled)
- **`config/routes.php`**: URL routing configuration
- **`.env`**: Environment variables (NOT version controlled)

### Configuration Hierarchy

Configuration is applied in this order (later items override earlier ones):

1. `config/app.php` - Default/baseline configuration
2. `config/app_local.php` - Environment-specific overrides
3. Environment variables - Runtime configuration

This approach allows deploying to multiple environments (development, staging, production) with a single codebase.

## 2.2 Application Settings

The `App` configuration section defines core application identity and behavior:

```php
"App" => [
    "namespace" => "App",              // PSR-4 autoload namespace
    "title" => "AMS",                  // Display title
    "appGraphic" => "badge.png",       // Logo file in webroot/img/
    "encoding" => env("APP_ENCODING", "UTF-8"),
    "defaultLocale" => env("APP_DEFAULT_LOCALE", "en_US"),
    "defaultTimezone" => env("APP_DEFAULT_TIMEZONE", "UTC"),
    "base" => env("BASE_SUB", false),  // Subdirectory if not in document root
    "dir" => "src",
    "webroot" => "webroot",
    "wwwRoot" => WWW_ROOT,
]
```

### Application Configuration Options

| Setting | Type | Purpose | Default |
|---------|------|---------|---------|
| `namespace` | string | PSR-4 autoload namespace | `App` |
| `title` | string | Application display name | `AMS` |
| `appGraphic` | string | Logo filename in `webroot/img/` | `badge.png` |
| `encoding` | string | Character encoding for HTML/database | `UTF-8` |
| `defaultLocale` | string | Default locale for i18n | `en_US` |
| `defaultTimezone` | string | Default timezone for dates | `UTC` |
| `base` | string\|false | Subdirectory if app not in doc root | `false` |
| `version` | string | App version from `config/version.txt` | loaded at runtime |

### Locale and Timezone Configuration

KMP defaults to US English (`en_US`) and UTC timezone for consistency across deployments.

To configure for different locales or timezones:

```bash
# In .env
APP_DEFAULT_LOCALE=en_GB
APP_DEFAULT_TIMEZONE=America/Chicago
```

**Important:** The `defaultTimezone` setting controls server-side timezone handling. Kingdom-specific formatting is handled separately via `TimezoneHelper` for email templates and display.

## 2.3 Environment Variables

Environment variables control deployment-specific settings without modifying code or version control.

### Loading Environment Variables

KMP uses `.env` files to configure environment-specific settings:

```bash
# .env (NOT version controlled)
DEBUG=false
DATABASE_URL=mysql://user:pass@host/dbname
SECURITY_SALT=your-very-long-random-string
EMAIL_FROM_ADDRESS=noreply@kingdom.example.com
```

Access environment variables in PHP:

```php
$debug = env("DEBUG", false);              // Returns env value or default
$salt = env("SECURITY_SALT");              // Returns env value or throws error
```

### Common Environment Variables

| Variable | Purpose | Default | Required |
|----------|---------|---------|----------|
| `DEBUG` | Enable debug mode | `false` | No |
| `DATABASE_URL` | Database connection string | none | Yes |
| `SECURITY_SALT` | Cryptographic salt for passwords/tokens | none | Yes |
| `APP_ENCODING` | Character encoding | `UTF-8` | No |
| `APP_DEFAULT_LOCALE` | Default locale | `en_US` | No |
| `APP_DEFAULT_TIMEZONE` | Default timezone | `UTC` | No |
| `EMAIL_TRANSPORT_DEFAULT_URL` | Email transport configuration | none | No |
| `EMAIL_FROM_ADDRESS` | Default sender address | `you@localhost` | No |
| `BASE_SUB` | Subdirectory if app not in doc root | `false` | No |

### Generating a Security Salt

```bash
# Generate a random 64-character salt using OpenSSL
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

This generates a cryptographically secure salt suitable for use as `SECURITY_SALT` in `.env`.

## 2.4 Debug Mode

Debug mode controls error reporting, toolbar visibility, and development features:

### Development Mode (DEBUG=true)

- Detailed error pages with stack traces
- Framework errors displayed (e.g., missing controllers)
- Debug toolbar in UI
- Enhanced logging
- Cache duration reduced to 2 minutes
- SQL query logging available

### Production Mode (DEBUG=false)

- Generic error pages (no technical details)
- Errors logged but not displayed
- Optimized performance
- Caching enabled at full duration
- Minimal information disclosure

### Enabling Debug Mode

In `.env`:

```bash
DEBUG=true
```

**Security Note:** Never enable `DEBUG=true` in production. Exposed error pages can reveal application internals to attackers.

## 2.5 Database Configuration

Database connections are configured in the `Datasources` section of `app.php`:

```php
"Datasources" => [
    "default" => [
        "className" => Connection::class,
        "driver" => Mysql::class,
        "persistent" => false,
        "timezone" => "UTC",
        "cacheMetadata" => true,
        "log" => false,
        "quoteIdentifiers" => false,
    ],
    "test" => [
        // Separate test database for unit tests
    ],
]
```

### Database Connection Options

| Option | Purpose | Default |
|--------|---------|---------|
| `driver` | Database system (Mysql, Postgres, Sqlite) | `Mysql` |
| `persistent` | Keep connection alive (not recommended) | `false` |
| `timezone` | Database timezone for date/time | `UTC` |
| `cacheMetadata` | Cache table schema | `true` |
| `log` | Log database queries | `false` |
| `quoteIdentifiers` | Quote reserved word identifiers | `false` |

### Database Connection String

Configure via `DATABASE_URL` environment variable:

```bash
DATABASE_URL=mysql://username:password@localhost:3306/kmp_production?encoding=utf8mb4&timezone=UTC
```

Components:
- `username:password` - Database credentials
- `localhost:3306` - Host and port
- `kmp_production` - Database name
- `encoding=utf8mb4` - Character encoding (full UTF-8 support)
- `timezone=UTC` - Database timezone

### Test Database Configuration

Tests use a separate database configured in `Datasources.test` to avoid data contamination. Configure via `DATABASE_TEST_URL` or set separate credentials in `config/app_local.php`.

## 2.6 Configuration Best Practices

### Development

1. **Use `.env` files** - Keep environment-specific settings out of version control
2. **Enable debug mode** - Set `DEBUG=true` for detailed error reporting
3. **Use local overrides** - Create `config/app_local.php` for development-only changes
4. **Monitor logs** - Review `logs/debug.log` for application events

### Production

1. **Disable debug mode** - Set `DEBUG=false` to prevent information disclosure
2. **Secure the security salt** - Use a long, random, cryptographically secure value
3. **Use strong database credentials** - Store securely in environment variables
4. **Enable caching** - Use Apcu or Redis for performance
5. **Review error logs** - Monitor `logs/error.log` for production issues
6. **Restrict file permissions** - Ensure config files are not world-readable

### Sensitive Data

Never commit to version control:
- `.env` files
- `config/app_local.php`
- Database credentials
- API keys and tokens
- Security salts

Use environment variables or `.env` files for all sensitive configuration.

## 2.7 Configuration Validation

CakePHP provides configuration validation to catch common issues:

```php
// In config/bootstrap.php or Application.php
Configure::check('required.setting');  // Throws exception if missing
```

Check that required environment variables are set before application startup to catch configuration errors early.

## 2.8 Troubleshooting Configuration

### "SECURITY_SALT is not defined"

The application cannot start without a security salt. Generate one with:

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Add to `.env`:

```bash
SECURITY_SALT=<generated-salt-here>
```

### Database Connection Fails

Verify `DATABASE_URL` is correctly formatted and credentials are valid:

```bash
# Test connection manually
mysql -u username -p -h localhost database_name
```

### Configuration Not Applying

Configuration changes may require:
1. Clearing cache: `bin/cake cache clear_all`
2. Restarting PHP-FPM or web server
3. Restarting the development server

### Debug Mode Not Working

Ensure `DEBUG` is set to `true` (boolean) in `.env`, not a string:

```bash
# Correct
DEBUG=true

# Incorrect (will be treated as string "false")
DEBUG="false"
```

## 2.9 See Also

- [Environment Variables Reference](8.1-environment-setup.md)
- [Deployment Configuration](8-deployment.md)
- [Security Best Practices](7.1-security-best-practices.md)
- [CakePHP Configuration Documentation](https://book.cakephp.org/5/en/development/configuration.html)
