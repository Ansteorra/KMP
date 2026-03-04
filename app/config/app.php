<?php

declare(strict_types=1);

/**
 * KMP Application Configuration
 *
 * This file contains the core CakePHP framework configuration.
 * For detailed documentation on each section, see the docs/ folder:
 *
 * @see docs/2-configuration.md For application configuration overview
 * @see docs/8.1-environment-setup.md For environment variables
 * @see docs/7.1-security-best-practices.md For security configuration
 * @see docs/6.4-caching-strategy.md For caching strategy
 * @see docs/8-deployment.md For deployment configuration
 *
 * Environment-specific overrides should be in app_local.php or .env
 */

use Cake\Cache\Engine\ApcuEngine;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Cache\Engine\FileEngine;
use Cake\Cache\Engine\RedisEngine;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Log\Engine\FileLog;
use Cake\Mailer\Transport\MailTransport;
use Templating\View\Icon\BootstrapIcon;

// Determine cache engine and Redis config from environment
$cacheEngine = env('CACHE_ENGINE', 'apcu') === 'redis' ? RedisEngine::class : ApcuEngine::class;
$cliArgs = array_map('strtolower', $_SERVER['argv'] ?? []);
$isSetupCommand = PHP_SAPI === 'cli' && (in_array('migrations', $cliArgs, true) || in_array('update_database', $cliArgs, true));
if ($cacheEngine === RedisEngine::class && $isSetupCommand) {
    $cacheEngine = ArrayEngine::class;
}
$redisExtensionLoaded = extension_loaded('redis');
if ($cacheEngine === RedisEngine::class && !$redisExtensionLoaded) {
    $cacheEngine = ApcuEngine::class;
}
$redisConfig = [];
if ($cacheEngine === RedisEngine::class) {
    $redisUrl = trim((string)env('REDIS_URL', ''));
    if ($redisUrl === '') {
        $cacheEngine = ApcuEngine::class;
    } else {
        $parsed = parse_url($redisUrl) ?: [];
        $redisConfig = [
            'server'   => $parsed['host'] ?? 'redis',
            'port'     => $parsed['port'] ?? 6379,
            'password' => ($parsed['pass'] ?? null) ?: env('REDIS_PASSWORD', null),
            'database' => (int)(ltrim($parsed['path'] ?? '/0', '/') ?: '0'),
            'timeout'  => 0,
            'persistent' => false,
        ];
    }
}
$restoreStatusPath = env('RESTORE_STATUS_CACHE_PATH', sys_get_temp_dir() . DS . 'kmp_restore_status_shared' . DS);
if (!is_dir($restoreStatusPath)) {
    @mkdir($restoreStatusPath, 0777, true);
}
@chmod($restoreStatusPath, 0777);

return [
    /** @var bool Enable debug mode - set via DEBUG environment variable */
    "debug" => filter_var(env("DEBUG", false), FILTER_VALIDATE_BOOLEAN),

    /** @see docs/2-configuration.md#application-settings */
    "App" => [
        /** @var string Application namespace for autoloading */
        "namespace" => "App",

        /** @var string Display title for the application */
        "title" => "AMS",

        /** @var string Application logo/badge filename in webroot/img/ */
        "appGraphic" => "badge.png",

        /** @var string Character encoding for HTML and database */
        "encoding" => env("APP_ENCODING", "UTF-8"),

        /** @var string Default locale for internationalization */
        "defaultLocale" => env("APP_DEFAULT_LOCALE", "en_US"),

        /** @var string Default timezone for date/time operations */
        "defaultTimezone" => env("APP_DEFAULT_TIMEZONE", "UTC"),

        /** @var string|false Base subdirectory if app is not in document root */
        "base" => env("BASE_SUB", false),

        /** @var string Source code directory name */
        "dir" => "src",

        /** @var string Public web files directory */
        "webroot" => "webroot",

        /** @var string Absolute path to webroot directory */
        "wwwRoot" => WWW_ROOT,

        /** @var string|null Base URL for non-mod_rewrite configurations */
        //'baseUrl' => env('SCRIPT_NAME'),

        /** @var string|false Full base URL for absolute links (auto-detected if false) */
        "fullBaseUrl" => env("APP_FULL_BASE_URL", false),

        /** @var string Web path to images directory */
        "imageBaseUrl" => "img/",

        /** @var string Web path to CSS directory */
        "cssBaseUrl" => "css/",

        /** @var string Web path to JavaScript directory */
        "jsBaseUrl" => "js/",

        /** @var array<string, array<string>> File system paths configuration */
        "paths" => [
            /** @var array<string> Plugin directories */
            "plugins" => [ROOT . DS . "plugins" . DS],

            /** @var array<string> Template directories */
            "templates" => [ROOT . DS . "templates" . DS],

            /** @var array<string> Locale files for translations */
            "locales" => [RESOURCES . "locales" . DS],
        ],

        /** @var string Application version loaded from version.txt */
        "version" => @file_get_contents(CONFIG . "version.txt") ?: 'unknown',

        /** @var string Container image tag (set at build time or via env) */
        "imageTag" => env("IMAGE_TAG", trim((string)@file_get_contents(CONFIG . "version.txt")) ?: 'unknown'),

        /** @var string Release channel: release, beta, dev, nightly */
        "releaseChannel" => env("RELEASE_CHANNEL", trim((string)@file_get_contents(CONFIG . "channel.txt")) ?: 'release'),

        /** @var string Container registry base (no tag) */
        "containerRegistry" => env("CONTAINER_REGISTRY", "ghcr.io/jhandel/kmp"),

        /** @var string Deployment provider: docker, railway, azure, aws, fly, vpc, shared */
        "deploymentProvider" => env("DEPLOYMENT_PROVIDER", env("KMP_DEPLOY_PROVIDER", "docker")),
    ],

    /** @see docs/7.1-security-best-practices.md#encryption-and-cryptographic-salt */
    "Security" => [
        /** @var string Cryptographic salt - must be 32+ characters, keep secret */
        "salt" => env("SECURITY_SALT"),
    ],

    /** @see docs/10.4-asset-management.md */
    "Asset" => [
        /** @var bool|string Asset timestamping for cache busting */
        //'timestamp' => true,

        /** @var string Browser cache duration for assets */
        // 'cacheTime' => '+1 year'
    ],

    /** @see docs/6.4-caching-strategy.md */
    "Cache" => [
        /** @var array Default cache configuration for general application data */
        "default" => $redisConfig + [
            "className" => $cacheEngine,
            'duration' => '+1 hours',
        ],

        /** 
         * Member Permissions Cache
         * Stores individual member permission data for fast authorization checks.
         * Short TTL ensures permission changes are reflected quickly.
         */
        "member_permissions" => $redisConfig + [
            "className" => $cacheEngine,
            "duration" => "+30 minutes",
            'groups' => ['security', 'member_security']
        ],

        /**
         * Permissions Structure Cache
         * Stores the role/permission hierarchy and relationships.
         * Long TTL as this structure changes infrequently.
         */
        "permissions_structure" => $redisConfig + [
            "className" => $cacheEngine,
            "duration" => "+999 days",
            'groups' => ['security']
        ],

        /**
         * Branch Structure Cache
         * Stores organizational hierarchy and branch relationships.
         * Long TTL as organizational structure is relatively stable.
         */
        "branch_structure" => $redisConfig + [
            "className" => $cacheEngine,
            "duration" => "+999 days",
            'groups' => ['security']
        ],

        /**
         * Restore Status Cache
         * Uses file cache so restore lock/progress state is shared between web and CLI.
         */
        "restore_status" => [
            "className" => FileEngine::class,
            "duration" => "+2 days",
            "prefix" => "kmp_restore_",
            "path" => $restoreStatusPath,
            "mask" => 0666,
            "dirMask" => 0777,
        ],

        /**
         * CakePHP Translation Cache
         * Stores compiled translation files for internationalization.
         * Framework-managed cache for i18n performance optimization.
         * Duration reduced to +2 minutes when debug = true.
         */
        "_cake_translations_" => $redisConfig + [
            "className" => $cacheEngine,
            "duration" => "+1 years",
            "url" => env("CACHE_CAKECORE_URL", null),
        ],

        /**
         * CakePHP Model Metadata Cache
         * Stores database schema descriptions and table listings.
         * Framework-managed cache for ORM performance optimization.
         * Duration reduced to +2 minutes when debug = true.
         */
        "_cake_model_" => $redisConfig + [
            "className" => $cacheEngine,
            "duration" => "+1 years",
            "url" => env("CACHE_CAKEMODEL_URL", null),
        ],
    ],

    /** @see docs/7.1-security-best-practices.md */
    "Error" => [
        /** @var string Custom renderer that returns JSON for /api/ routes */
        "exceptionRenderer" => \App\Error\ApiExceptionRenderer::class,

        /** @var int PHP error reporting level */
        "errorLevel" => E_ALL & ~E_USER_DEPRECATED,

        /** @var array<string> Exception classes to skip for logging */
        "skipLog" => [],

        /** @var bool Whether to log exceptions */
        "log" => true,

        /** @var bool Whether to include backtraces in logs */
        "trace" => true,

        /** @var array<string> File paths where deprecations should be ignored */
        "ignoredDeprecationPaths" => ['vendor/cakephp/cakephp/src/Event/EventManager.php'],
    ],

    /** @see docs/7.1-security-best-practices.md */
    "Debugger" => [
        /** @var string IDE for deep-linking from debug output */
        "editor" => "phpstorm",
    ],

    /** @see docs/8.1-environment-setup.md#email-configuration */
    "EmailTransport" => [
        /** @var array Default email transport configuration */
        "default" => [
            "className" => "Smtp",

            /** @var string SMTP server hostname */
            "host" => "localhost",

            /** @var int SMTP server port */
            "port" => 25,

            /** @var int Connection timeout in seconds */
            "timeout" => 30,

            /** @var string|null SMTP username (set via environment) */
            //'username' => null,

            /** @var string|null SMTP password (set via environment) */
            //'password' => null,

            /** @var string|null SMTP client identifier */
            "client" => null,

            /** @var bool Enable TLS encryption */
            "tls" => false,

            /** @var string|null Complete transport URL from environment */
            "url" => env("EMAIL_TRANSPORT_DEFAULT_URL", null),
        ],
    ],

    /** @see docs/8.1-environment-setup.md#email-configuration */
    "Email" => [
        /** @var array Default email delivery profile */
        "default" => [
            /** @var string Transport configuration to use */
            "transport" => "default",

            /** @var string Default sender address */
            "from" => "you@localhost",

            /** @var string Character encoding (inherits from App.encoding if not set) */
            //'charset' => 'utf-8',

            /** @var string Header character encoding */
            //'headerCharset' => 'utf-8',
        ],
    ],

    /** @see docs/2-configuration.md#database-configuration and docs/8.1-environment-setup.md#database-configuration */
    "Datasources" => [
        /**
         * Default Database Connection
         * 
         * Primary database connection for KMP application data.
         * Settings here provide safe defaults that work in most environments.
         * Production deployments should override in app_local.php.
         */
        "default" => [
            /** @var string Connection class for database abstraction */
            "className" => Connection::class,

            /** @var string Database driver (MySQL/MariaDB) */
            "driver" => Mysql::class,

            /** @var string Database hostname */
            "host" => env("DB_HOST", env("MYSQL_HOST", "localhost")),

            /** @var int Database port */
            "port" => env("DB_PORT", env("MYSQL_PORT", 3306)),

            /** @var string Database username */
            "username" => env("DB_USERNAME", env("MYSQL_USERNAME", "root")),

            /** @var string Database password */
            "password" => env("DB_PASSWORD", env("MYSQL_PASSWORD", "")),

            /** @var string Database name */
            "database" => env("DB_DATABASE", env("MYSQL_DB_NAME", "kmp")),

            /** @var string|null Complete database DSN URL */
            "url" => env("DATABASE_URL", null),

            /** @var bool Use persistent connections (false for better resource management) */
            "persistent" => false,

            /** @var string Database timezone (UTC for consistency) */
            "timezone" => "UTC",

            /** @var string Character encoding - utf8mb4 for full UTF-8 support */
            //'encoding' => 'utf8mb4',

            /** @var array PDO connection flags for MySQL-specific options */
            "flags" => [],

            /** @var bool Cache database metadata for performance */
            "cacheMetadata" => true,

            /** @var bool Log database queries (disabled for performance) */
            "log" => false,

            /** @var bool Quote identifiers for reserved words/special characters */
            "quoteIdentifiers" => false,

            /** @var array Database initialization commands */
            //'init' => ['SET GLOBAL innodb_stats_on_metadata = 0'],
        ],

        /**
         * Test Database Connection
         * 
         * Separate database connection for automated testing.
         * Provides isolation between test and development data.
         * Configuration mirrors default connection for consistency.
         */
        "test" => [
            /** @var string Connection class for database abstraction */
            "className" => Connection::class,

            /** @var string Database driver (MySQL/MariaDB) */
            "driver" => Mysql::class,

            /** @var string Test database hostname */
            "host" => env("DB_HOST", env("MYSQL_HOST", "localhost")),

            /** @var int Test database port */
            "port" => env("DB_PORT", env("MYSQL_PORT", 3306)),

            /** @var string Test database username */
            "username" => env("DB_USERNAME", env("MYSQL_USERNAME", "root")),

            /** @var string Test database password */
            "password" => env("DB_PASSWORD", env("MYSQL_PASSWORD", "")),

            /** @var string Test database name */
            "database" => env("DB_DATABASE", env("MYSQL_DB_NAME", "kmp")) . "_test",

            /** @var string|null Complete test database DSN URL */
            "url" => env("DATABASE_TEST_URL", env("DATABASE_URL", null)),

            /** @var bool Use persistent connections */
            "persistent" => false,

            /** @var string Database timezone */
            "timezone" => "UTC",

            /** @var string Character encoding */
            //'encoding' => 'utf8mb4',

            /** @var array PDO connection flags */
            "flags" => [],

            /** @var bool Cache database metadata */
            "cacheMetadata" => true,

            /** @var bool Quote identifiers */
            "quoteIdentifiers" => false,

            /** @var bool Log queries (disabled for test performance) */
            "log" => false,

            /** @var array Database initialization commands */
            //'init' => ['SET GLOBAL innodb_stats_on_metadata = 0'],
        ],
    ],

    /** @see docs/8.1-environment-setup.md#logging-configuration */
    "Log" => [
        /**
         * Debug Log Channel
         * 
         * Captures development and informational messages.
         * Includes notices, info, and debug level messages.
         * Primary channel for development troubleshooting.
         */
        "debug" => [
            /** @var string Log engine class */
            "className" => FileLog::class,

            /** @var string Log file directory path */
            "path" => LOGS,

            /** @var string Log filename */
            "file" => "debug",

            /** @var string|null External log service URL */
            "url" => env("LOG_DEBUG_URL", null),

            /** @var array|null Log scopes for filtering */
            "scopes" => null,

            /** @var array Log levels to capture */
            "levels" => ["notice", "info", "debug"],
        ],

        /**
         * Error Log Channel
         * 
         * Captures production errors and critical issues.
         * Includes warnings, errors, critical, alert, and emergency levels.
         * Primary channel for production monitoring.
         */
        "error" => [
            /** @var string Log engine class */
            "className" => FileLog::class,

            /** @var string Log file directory path */
            "path" => LOGS,

            /** @var string Log filename */
            "file" => "error",

            /** @var string|null External log service URL */
            "url" => env("LOG_ERROR_URL", null),

            /** @var array|null Log scopes for filtering */
            "scopes" => null,

            /** @var array Log levels to capture */
            "levels" => ["warning", "error", "critical", "alert", "emergency"],
        ],

        /**
         * Database Query Log Channel
         * 
         * Captures SQL queries when database logging is enabled.
         * Useful for performance analysis and debugging.
         * Enable by setting 'log' => true in datasource configuration.
         */
        "queries" => [
            /** @var string Log engine class */
            "className" => FileLog::class,

            /** @var string Log file directory path */
            "path" => LOGS,

            /** @var string Log filename */
            "file" => "queries",

            /** @var string|null External log service URL */
            "url" => env("LOG_QUERIES_URL", null),

            /** @var array Log scopes for database queries */
            "scopes" => ["cake.database.queries"],
        ],

        /**
         * Request Performance Log Channel
         *
         * Captures slow request timing emitted by optional middleware instrumentation.
         * This channel is intended for performance risk monitoring in production-like environments.
         */
        "performance" => [
            /** @var string Log engine class */
            "className" => FileLog::class,

            /** @var string Log file directory path */
            "path" => LOGS,

            /** @var string Log filename */
            "file" => "performance",

            /** @var string|null External log service URL */
            "url" => env("LOG_PERFORMANCE_URL", null),

            /** @var array Log scopes for request performance instrumentation */
            "scopes" => ["app.performance"],

            /** @var array Log levels to capture */
            "levels" => ["info", "warning"],
        ],
    ],

    /*
     * Session configuration.
     * @see docs/7.1-security-best-practices.md#session-security-configuration
     */
    /** @see docs/7.1-security-best-practices.md#session-security-configuration */
    "Session" => [
        /** @var string Session handler type */
        "defaults" => "php",

        /** @var int Session timeout in minutes */
        "timeout" => 30,

        /** @var string Session cookie name */
        "cookie" => "PHPSESSID",

        /** @var array PHP session ini settings for security */
        "ini" => [
            /** @var bool Require HTTPS for session cookies */
            "session.cookie_secure" => true,

            /** @var bool Prevent JavaScript access to session cookies */
            "session.cookie_httponly" => true,

            /** @var string CSRF protection via SameSite policy */
            "session.cookie_samesite" => "Strict",

            /** @var bool Validate session IDs for security */
            "session.use_strict_mode" => true,
        ],
    ],

    'Icon' => [
        /** @see docs/9.2-bootstrap-icons.md */
        'sets' => [
            /** @var array Bootstrap Icons configuration */
            'bs' => [
                /** @var string Icon rendering class */
                'class' => BootstrapIcon::class,

                /** @var string Path to icon definitions JSON file */
                'path' => WWW_ROOT . 'assets/bootstrap-icons/font/bootstrap-icons.json',
            ],
        ],
    ],
    /**
     * Document Management Configuration
     * @see docs/4.7-document-management-system.md#storage-configuration
     */
    'Documents' => [
        /**
         * Storage Configuration
         *
         * Select the storage adapter and configure adapter-specific settings.
         * Each adapter has its own configuration block with only the relevant options.
         */
        'storage' => [
            /**
             * Active Storage Adapter
             *
             * Options: 'local', 'azure', or 's3'
             * - local: Stores files in the local filesystem
             * - azure: Stores files in Azure Blob Storage
             * - s3: Stores files in an S3-compatible bucket (AWS S3, MinIO, etc)
             */
            'adapter' => 'local',

            /**
             * Local Filesystem Adapter Configuration
             *
             * Used when adapter is set to 'local'.
             */
            'local' => [
                /**
                 * Base path for storing uploaded documents
                 *
                 * Default: ROOT/images/uploaded
                 */
                'path' => ROOT . DS . 'images' . DS . 'uploaded',
            ],

            /**
             * Azure Blob Storage Adapter Configuration
             *
             * Used when adapter is set to 'azure'.
             */
            'azure' => [
                /**
                 * Azure Storage Connection String
                 *
                 * Format: DefaultEndpointsProtocol=https;AccountName=...;AccountKey=...;EndpointSuffix=core.windows.net
                 * Should be set via environment variable AZURE_STORAGE_CONNECTION_STRING
                 */
                'connectionString' => env('AZURE_STORAGE_CONNECTION_STRING'),

                /**
                 * Azure Blob Container Name
                 *
                 * The name of the container where documents will be stored.
                 * Default: 'documents'
                 */
                'container' => 'documents',

                /**
                 * Path Prefix (optional)
                 *
                 * Optional prefix to prepend to all file paths within the container.
                 * Useful for organizing files or supporting multiple environments in one container.
                 * Default: '' (no prefix)
                 */
                'prefix' => '',
            ],

            /**
             * S3 Adapter Configuration
             *
             * Used when adapter is set to 's3'.
             */
            's3' => [
                /**
                 * S3 Bucket Name
                 *
                 * Should be set via AWS_S3_BUCKET.
                 */
                'bucket' => env('AWS_S3_BUCKET'),

                /**
                 * AWS Region
                 *
                 * Should be set via AWS_DEFAULT_REGION.
                 */
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

                /**
                 * Access Key ID (optional)
                 *
                 * Leave null to use instance/task role credentials.
                 */
                'key' => env('AWS_ACCESS_KEY_ID'),

                /**
                 * Secret Access Key (optional)
                 *
                 * Leave null to use instance/task role credentials.
                 */
                'secret' => env('AWS_SECRET_ACCESS_KEY'),

                /**
                 * Session token (optional)
                 */
                'sessionToken' => env('AWS_SESSION_TOKEN'),

                /**
                 * Optional object key prefix.
                 */
                'prefix' => env('AWS_S3_PREFIX', ''),

                /**
                 * Optional custom endpoint (for S3-compatible providers like MinIO).
                 */
                'endpoint' => env('AWS_S3_ENDPOINT'),

                /**
                 * Force path-style addressing (required by some S3-compatible providers).
                 */
                'usePathStyleEndpoint' => filter_var(
                    env('AWS_S3_USE_PATH_STYLE_ENDPOINT', false),
                    FILTER_VALIDATE_BOOLEAN,
                ),
            ],
        ],

        /**
         * Maximum File Size (bytes)
         *
         * Files larger than this limit will be rejected during upload to prevent
         * memory exhaustion. The entire file is loaded into memory for checksum
         * calculation and storage operations.
         *
         * Common values:
         * - 10 MB: 10 * 1024 * 1024
         * - 50 MB: 50 * 1024 * 1024 (default)
         * - 100 MB: 100 * 1024 * 1024
         *
         * Note: Also ensure PHP's upload_max_filesize and post_max_size are set
         * appropriately in php.ini to handle files of this size.
         */
        'maxFileSize' => 50 * 1024 * 1024, // 50 MB
    ],
];
