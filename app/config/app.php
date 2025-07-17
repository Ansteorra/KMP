<?php

declare(strict_types=1);

/**
 * KMP Application Configuration
 *
 * This file contains the core configuration settings for the Kingdom Management Portal (KMP).
 * It defines application-wide settings for:
 * - Application metadata and paths
 * - Security and encryption
 * - Caching strategies
 * - Database connections
 * - Email transport and delivery
 * - Error handling and debugging
 * - Logging configuration
 * - Session management
 * - UI components (Bootstrap Icons)
 *
 * Environment-specific overrides should be placed in app_local.php or use environment variables.
 * This configuration follows CakePHP 5.x conventions and KMP-specific requirements.
 *
 * @see config/app_local.php For environment-specific overrides
 * @see config/app_queue.php For queue-specific configuration
 */

use Cake\Cache\Engine\ApcuEngine;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Log\Engine\FileLog;
use Cake\Mailer\Transport\MailTransport;
use Templating\View\Icon\BootstrapIcon;

return [
    /**
     * Debug Level Configuration
     *
     * Controls error reporting and debugging features throughout the application.
     * 
     * Production Mode (false):
     * - No error messages, errors, or warnings shown to users
     * - Optimized performance with disabled debugging features
     * - Security-focused with minimal information disclosure
     *
     * Development Mode (true):
     * - Detailed error messages and warnings displayed
     * - Debug toolbar and profiling enabled
     * - Enhanced logging and stack traces
     * 
     * Environment Variable: DEBUG (boolean)
     * Default: false (production-safe)
     * 
     * @example Set DEBUG=true in .env for development
     * @example Set DEBUG=false in production environments
     */
    "debug" => filter_var(env("DEBUG", false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Application Configuration
     *
     * Core application settings that define the KMP's identity, structure, and behavior.
     * These settings control:
     * - Application namespace and identity
     * - Internationalization and localization
     * - Directory structure and file paths
     * - Asset organization and URLs
     * - Version management
     *
     * Key KMP-specific settings:
     * - title: Display name for the application (AMS - Activity Management System)
     * - appGraphic: Logo/badge file for branding
     * - version: Automatically loaded from version.txt file
     *
     * Environment Variables:
     * - APP_ENCODING: Character encoding (default: UTF-8)
     * - APP_DEFAULT_LOCALE: Locale for i18n (default: en_US)
     * - APP_DEFAULT_TIMEZONE: Timezone for date operations (default: UTC)
     * - BASE_SUB: Base subdirectory if app is not in document root
     * - SCRIPT_NAME: For non-mod_rewrite configurations
     *
     * @example For subdirectory installation: BASE_SUB=/kmp
     * @example For different locale: APP_DEFAULT_LOCALE=en_GB
     */
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
        "fullBaseUrl" => false,

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
        "version" => file_get_contents(CONFIG . "version.txt"),
    ],

    /**
     * Security and Encryption Configuration
     *
     * Critical security settings that protect the application and user data.
     * The security salt is used for:
     * - Password hashing and verification
     * - Session security and CSRF protection
     * - Encryption of sensitive data
     * - Security token generation
     *
     * SECURITY REQUIREMENTS:
     * - Salt must be at least 32 characters long
     * - Salt should be cryptographically random
     * - Salt must be kept secret and never committed to version control
     * - Salt should be different for each environment
     *
     * Environment Variable: SECURITY_SALT (required)
     * 
     * @example Generate with: bin/cake security generate_salt
     * @example Store in .env: SECURITY_SALT=your-very-long-random-string-here
     * @security Critical - Treat as extremely sensitive data
     */
    "Security" => [
        /** @var string Cryptographic salt for security operations */
        "salt" => env("SECURITY_SALT"),
    ],

    /**
     * Asset Management Configuration
     *
     * Controls how static assets (CSS, JavaScript, images) are served and cached.
     * Asset timestamping helps with browser cache busting when files change.
     *
     * Options:
     * - timestamp: true = apply timestamps when debug is true
     * - timestamp: 'force' = always apply timestamps regardless of debug
     * - cacheTime: how long browsers should cache assets
     *
     * KMP uses Laravel Mix for asset compilation, which handles versioning
     * in production builds through webpack.mix.js configuration.
     *
     * @example timestamp: true for development cache busting
     * @example cacheTime: '+1 year' for production optimization
     */
    "Asset" => [
        /** @var bool|string Asset timestamping for cache busting */
        //'timestamp' => true,

        /** @var string Browser cache duration for assets */
        // 'cacheTime' => '+1 year'
    ],

    /**
     * Cache Configuration
     *
     * KMP uses a multi-tier caching strategy with ApcuEngine for high performance.
     * Cache configurations are optimized for both development and production environments.
     *
     * Cache Tiers:
     * 1. Application cache (default) - General application data
     * 2. Security caches - Permission and authorization data
     * 3. Framework caches - CakePHP internal optimizations
     *
     * KMP-Specific Cache Stores:
     * - member_permissions: User permission data (30 min TTL, security group)
     * - permissions_structure: Role/permission hierarchy (long TTL, rarely changes)
     * - branch_structure: Organizational hierarchy (long TTL, rarely changes)
     *
     * Cache Groups:
     * - 'security': All security-related caches (can be cleared together)
     * - 'member_security': Member-specific security data
     *
     * Performance Notes:
     * - ApcuEngine provides memory-based caching for optimal performance
     * - Cache duration is automatically reduced to 2 minutes when debug=true
     * - Use cache groups for efficient bulk invalidation
     *
     * @example Clear security caches: Cache::clearGroup('security')
     * @example Clear member perms: Cache::clearGroup('member_security')
     */
    "Cache" => [
        /** @var array Default cache configuration for general application data */
        "default" => [
            "className" => ApcuEngine::class,
            'duration' => '+1 hours',
        ],

        /** 
         * Member Permissions Cache
         * Stores individual member permission data for fast authorization checks.
         * Short TTL ensures permission changes are reflected quickly.
         */
        "member_permissions" => [
            "className" => ApcuEngine::class,
            "duration" => "+30 minutes",
            'groups' => ['security', 'member_security']
        ],

        /**
         * Permissions Structure Cache
         * Stores the role/permission hierarchy and relationships.
         * Long TTL as this structure changes infrequently.
         */
        "permissions_structure" => [
            "className" => ApcuEngine::class,
            "duration" => "+999 days",
            'groups' => ['security']
        ],

        /**
         * Branch Structure Cache
         * Stores organizational hierarchy and branch relationships.
         * Long TTL as organizational structure is relatively stable.
         */
        "branch_structure" => [
            "className" => ApcuEngine::class,
            "duration" => "+999 days",
            'groups' => ['security']
        ],

        /**
         * CakePHP Translation Cache
         * Stores compiled translation files for internationalization.
         * Framework-managed cache for i18n performance optimization.
         * Duration reduced to +2 minutes when debug = true.
         */
        "_cake_translations_" => [
            "className" => ApcuEngine::class,
            "duration" => "+1 years",
            "url" => env("CACHE_CAKECORE_URL", null),
        ],

        /**
         * CakePHP Model Metadata Cache
         * Stores database schema descriptions and table listings.
         * Framework-managed cache for ORM performance optimization.
         * Duration reduced to +2 minutes when debug = true.
         */
        "_cake_model_" => [
            "className" => ApcuEngine::class,
            "duration" => "+1 years",
            "url" => env("CACHE_CAKEMODEL_URL", null),
        ],
    ],

    /**
     * Error and Exception Handling Configuration
     *
     * Controls how errors and exceptions are handled, logged, and displayed.
     * Configuration adapts based on debug mode for security and development needs.
     *
     * Production Behavior (debug = false):
     * - Errors logged but not displayed to users
     * - Generic HTTP error pages shown
     * - Framework errors converted to standard HTTP responses
     *
     * Development Behavior (debug = true):
     * - Detailed error pages with stack traces
     * - Framework errors like "Missing Controller" displayed
     * - Enhanced debugging information available
     *
     * Error Levels:
     * - E_ALL: All errors and warnings
     * - ~E_USER_DEPRECATED: Excludes user deprecation notices
     *
     * KMP-Specific Configuration:
     * - ignoredDeprecationPaths: Suppresses known framework deprecations
     * - Custom error renderer can be implemented in src/Error/
     *
     * Environment Variables:
     * - ERROR_LEVEL: Custom error reporting level
     * - SKIP_LOG: Comma-separated exception classes to skip logging
     *
     * @example Custom renderer: src/Error/KmpErrorRenderer.php
     * @example Skip logging: SKIP_LOG=NotFoundException,UnauthorizedException
     */
    "Error" => [
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

    /**
     * Debugger Configuration
     *
     * Development-specific debugging tools and IDE integration.
     * Controls the CakePHP debugger behavior and editor integration.
     *
     * Editor Integration:
     * Supports deep-linking from error pages and debug output directly to IDE.
     * Pre-configured editors: atom, emacs, macvim, phpstorm, sublime, textmate, vscode
     * 
     * Custom editors can be added using Debugger::addEditor() in bootstrap.
     *
     * Output Masking:
     * Can mask sensitive data in debug output and logs.
     * Useful for hiding passwords, API keys, or other sensitive information.
     *
     * @example Custom editor: Debugger::addEditor('myide', 'myide://open?file={file}&line={line}')
     * @example Output masking: 'outputMask' => ['password' => '***']
     */
    "Debugger" => [
        /** @var string IDE for deep-linking from debug output */
        "editor" => "phpstorm",
    ],

    /**
     * Email Transport Configuration
     *
     * Defines how emails are sent from the KMP application.
     * Supports multiple transport methods for different environments.
     *
     * Transport Types:
     * - Mail: PHP's built-in mail() function (simple but limited)
     * - Smtp: SMTP server connection (recommended for production)
     * - Debug: Development mode - captures emails without sending
     *
     * Security Considerations:
     * - Use TLS encryption for production SMTP
     * - Store credentials in environment variables
     * - Consider using authenticated SMTP services
     *
     * Environment Variables:
     * - EMAIL_TRANSPORT_DEFAULT_URL: Complete transport configuration URL
     * - SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD: Individual settings
     *
     * KMP Usage:
     * - Member notifications and communications
     * - System alerts and reports
     * - Password reset and verification emails
     *
     * @example Production SMTP: EMAIL_TRANSPORT_DEFAULT_URL=smtp://user:pass@smtp.gmail.com:587?tls=true
     * @example Development: Use Debug transport to capture emails without sending
     */
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

    /**
     * Email Delivery Profiles
     *
     * Delivery profiles combine transport configuration with message defaults.
     * This separation allows reusing transports across different message types.
     *
     * Profile Configuration:
     * - transport: Which transport configuration to use
     * - from: Default sender address and name
     * - charset: Character encoding for email content
     * - headerCharset: Character encoding for email headers
     *
     * KMP Email Usage:
     * - System notifications (activity approvals, awards, etc.)
     * - Member communications
     * - Administrative alerts
     * - Automated reports
     *
     * Environment Variables:
     * - EMAIL_FROM_ADDRESS: Default sender email address
     * - EMAIL_FROM_NAME: Default sender display name
     *
     * @example Set sender: EMAIL_FROM_ADDRESS=noreply@kingdom.example.com
     * @example Multiple profiles for different message types
     */
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

    /**
     * Database Connection Configuration
     *
     * Defines how the application connects to database systems.
     * KMP uses MySQL/MariaDB for primary data storage with optimized settings.
     *
     * Connection Strategy:
     * - Non-persistent connections for better resource management
     * - UTC timezone for consistent date/time handling
     * - Full UTF-8 support with utf8mb4 encoding
     * - Metadata caching for improved ORM performance
     *
     * Environment-Specific Configuration:
     * - Production settings in app_local.php override these defaults
     * - Environment variables provide flexible deployment options
     * - Test connection uses separate database for isolation
     *
     * Performance Optimizations:
     * - Metadata caching reduces schema lookups
     * - Query logging disabled by default (enable for debugging)
     * - Connection pooling through non-persistent connections
     *
     * Security Features:
     * - Identifier quoting available for reserved words
     * - PDO flags for secure connection options
     * - Timezone set to UTC prevents time zone attacks
     *
     * Environment Variables:
     * - DATABASE_URL: Complete connection string
     * - DB_HOST, DB_NAME, DB_USERNAME, DB_PASSWORD: Individual components
     * - DB_ENCODING: Character set (default: utf8mb4)
     * - DB_TIMEZONE: Database timezone (default: UTC)
     *
     * @example Production URL: DATABASE_URL=mysql://user:pass@localhost/kmp_production?encoding=utf8mb4
     * @example Enable query logging: 'log' => true (development only)
     */
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

    /**
     * Logging Configuration
     *
     * Multi-channel logging system for comprehensive application monitoring.
     * Separates different types of logs for easier analysis and debugging.
     *
     * Log Channels:
     * 1. debug: Development information, notices, and debug messages
     * 2. error: Production errors, warnings, and critical issues
     * 3. queries: Database query logging (when enabled)
     *
     * Log Levels (in order of severity):
     * - emergency: System unusable
     * - alert: Immediate action required
     * - critical: Critical conditions
     * - error: Error conditions
     * - warning: Warning conditions
     * - notice: Normal but significant
     * - info: Informational messages
     * - debug: Debug-level messages
     *
     * KMP Logging Strategy:
     * - Separate files prevent log mixing and simplify analysis
     * - File rotation handled by system or deployment tools
     * - Query logging available for performance debugging
     * - Environment-specific URLs allow external log aggregation
     *
     * Environment Variables:
     * - LOG_DEBUG_URL: External service for debug logs
     * - LOG_ERROR_URL: External service for error logs
     * - LOG_QUERIES_URL: External service for query logs
     *
     * @example Enable query logging in datasource: 'log' => true
     * @example External logging: LOG_ERROR_URL=syslog://localhost:514
     */
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
    ],

    /*
     * Session configuration.
     *
     * Contains an array of settings to use for session configuration. The
     * `defaults` key is used to define a default preset to use for sessions, any
     * settings declared here will override the settings of the default config.
     *
     * ## Options
     *
     * - `cookie` - The name of the cookie to use. Defaults to value set for `session.name` php.ini config.
     *    Avoid using `.` in cookie names, as PHP will drop sessions from cookies with `.` in the name.
     * - `cookiePath` - The url path for which session cookie is set. Maps to the
     *   `session.cookie_path` php.ini config. Defaults to base path of app.
     * - `timeout` - The time in minutes the session should be valid for.
     *    Pass 0 to disable checking timeout.
     *    Please note that php.ini's session.gc_maxlifetime must be equal to or greater
     *    than the largest Session['timeout'] in all served websites for it to have the
     *    desired effect.
     * - `defaults` - The default configuration set to use as a basis for your session.
     *    There are four built-in options: php, cake, cache, database.
     * - `handler` - Can be used to enable a custom session handler. Expects an
     *    array with at least the `engine` key, being the name of the Session engine
     *    class to use for managing the session. CakePHP bundles the `CacheSession`
     *    and `DatabaseSession` engines.
     * - `ini` - An associative array of additional 'session.*` ini values to set.
     *
     * The built-in `defaults` options are:
     *
     * - 'php' - Uses settings defined in your php.ini.
     * - 'cake' - Saves session files in CakePHP's /tmp directory.
     * - 'database' - Uses CakePHP's database sessions.
     * - 'cache' - Use the Cache class to save sessions.
     *
     * To define a custom session handler, save it at src/Http/Session/<name>.php.
     * Make sure the class implements PHP's `SessionHandlerInterface` and set
     * Session.handler to <name>
     *
    /**
     * Session Configuration
     *
     * Secure session management configuration for user authentication and state.
     * Implements security best practices to protect against session attacks.
     *
     * Security Features:
     * - HTTP-only cookies prevent JavaScript access
     * - Secure cookies require HTTPS connections
     * - SameSite=Strict prevents CSRF attacks
     * - Strict mode validates session IDs
     *
     * Session Handlers:
     * - php: Uses PHP's default session handling (files)
     * - cake: CakePHP file-based sessions in tmp/sessions/
     * - database: Database-backed sessions (requires sessions table)
     * - cache: Cache-backed sessions using configured cache
     *
     * KMP Session Strategy:
     * - 30-minute timeout balances security and usability
     * - Secure settings protect sensitive member data
     * - PHP default handler for simplicity and performance
     *
     * Database Sessions:
     * To use database sessions:
     * 1. Load SQL schema: config/schema/sessions.sql
     * 2. Set defaults to 'database'
     * 3. Configure database connection
     *
     * Custom Session Handlers:
     * Implement SessionHandlerInterface in src/Http/Session/
     * 
     * Environment Variables:
     * - SESSION_TIMEOUT: Session duration in minutes
     * - SESSION_SECURE: Force secure cookies (boolean)
     * - SESSION_NAME: Custom session cookie name
     *
     * @example Database sessions: 'defaults' => 'database'
     * @example Cache sessions: 'defaults' => 'cache'
     * @example Custom timeout: SESSION_TIMEOUT=60
     */
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

    /**
     * Icon Configuration
     *
     * Bootstrap Icons integration for the KMP user interface.
     * Provides scalable vector icons for consistent visual design.
     *
     * Icon Set Configuration:
     * - 'bs': Bootstrap Icons set identifier
     * - class: Icon rendering class from Templating plugin
     * - path: JSON file containing icon definitions and metadata
     *
     * KMP Icon Usage:
     * - Navigation elements and menus
     * - Action buttons and form controls
     * - Status indicators and badges
     * - Data visualization elements
     *
     * Icon Integration:
     * Icons are rendered through the Templating plugin's BootstrapIcon class.
     * The JSON file contains icon names, SVG paths, and metadata for rendering.
     * 
     * Asset Management:
     * Bootstrap Icons are included in the webroot/assets/ directory and
     * managed through the application's asset pipeline.
     *
     * @example Usage in templates: <?= $this->Icon->render('person-fill', ['set' => 'bs']) ?>
     * @example Custom icons: Add to bootstrap-icons.json or create new icon set
     */
    'Icon' => [
        /** @var array Icon set configurations */
        'sets' => [
            /** @var array Bootstrap Icons configuration */
            'bs' => [
                /** @var string Icon rendering class */
                'class' => BootstrapIcon::class,

                /** @var string Path to icon definitions JSON file */
                'path' => WWW_ROOT . 'assets/bootstrap-icons/font/bootstrap-icons.json',
            ],
        ]
    ]
];
