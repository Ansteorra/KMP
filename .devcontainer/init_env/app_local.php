<?php
/*
 * Local configuration file to provide any overrides to your app.php configuration.
 * Copy and save this file as app_local.php and make changes as required.
 * Note: It is not recommended to commit files with credentials such as app_local.php
 * into source code version control.
 */
$databaseUrl = env('DATABASE_URL', null);
$databaseTestUrl = env('DATABASE_TEST_URL', null);
$dbQueryLogEnabled = filter_var(env('PERF_DB_QUERY_LOG_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
$pdoFlags = [\PDO::ATTR_EMULATE_PREPARES => true];

return [
    /*
     * Debug Level:
     *
     * Production Mode:
     * false: No error messages, errors, or warnings shown.
     *
     * Development Mode:
     * true: Errors and warnings shown.
     */
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),

    'DebugKit' => [
        'ignoreAuthorization' => true,
        'variablesPanelMaxDepth' => 10,
    ],

    /*
     * Security and encryption configuration
     *
     * - salt - A random string used in security hashing methods.
     *   The salt value is also used as the encryption key.
     *   You should treat it as extremely sensitive data.
     */
    'Security' => [
        'salt' => env('SECURITY_SALT', '7479eaa68e57fc0bf648af17da085950a16c47f3b31af4eb1548a661b545a3fb'),
    ],

    /*
     * Connection information used by the ORM to connect
     * to your application's datastores.
     *
     * See app.php for more configuration options.
     */
    'Datasources' => [
        'default' => [
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 5432),

            'username' => env('DB_USERNAME', env('POSTGRES_USER')),
            'password' => env('DB_PASSWORD', env('POSTGRES_PASSWORD')),

            'database' => env('DB_DATABASE', env('POSTGRES_DB', 'kmp')),
            /*
             * If not using the default 'public' schema with the PostgreSQL driver
             * set it here.
             */
            //'schema' => 'myapp',

            /*
             * You can use a DSN string to set the entire configuration
             */
            'url' => $databaseUrl,
            'flags' => $pdoFlags,
            'log' => $dbQueryLogEnabled,
        ],

        /*
         * The test connection is used during the test suite.
         */
        'test' => [
            'host' => 'localhost',
            'port' => env('DB_PORT', 5432),

            'username' => env('DB_USERNAME', env('POSTGRES_USER')),
            'password' => env('DB_PASSWORD', env('POSTGRES_PASSWORD')),

            'database' => env('DB_DATABASE', env('POSTGRES_DB', 'kmp')) . '_test',
            /*
             * If not using the default 'public' schema with the PostgreSQL driver
             * set it here.
             */
            //'schema' => 'myapp',

            /*
             * You can use a DSN string to set the entire configuration
             */
            'url' => $databaseTestUrl,
            'log' => $dbQueryLogEnabled,
        ],
    ],

    /*
     * Email configuration.
     *
     * Host and credential configuration in case you are using SmtpTransport
     *
     * See app.php for more configuration options.
     */
    'EmailTransport' => [
        'default' => match (strtolower(env('EMAIL_DRIVER', 'smtp'))) {
            'azure' => [
                'className' => \App\Mailer\Transport\AzureCommunicationTransport::class,
                'connectionString' => env('AZURE_COMMUNICATION_CONNECTION_STRING'),
                'apiVersion' => env('AZURE_COMMUNICATION_API_VERSION', '2023-03-31'),
            ],
            'sendgrid' => [
                'className' => \App\Mailer\Transport\SendGridApiTransport::class,
                'apiKey' => env('EMAIL_API_KEY'),
            ],
            'resend' => [
                'className' => \App\Mailer\Transport\ResendApiTransport::class,
                'apiKey' => env('EMAIL_API_KEY'),
            ],
            default => [
                'className' => 'Smtp',
                'host' => env('EMAIL_SMTP_HOST', 'localhost'),
                'port' => (int)env('EMAIL_SMTP_PORT', 1025),
                'username' => env('EMAIL_SMTP_USERNAME', ''),
                'password' => env('EMAIL_SMTP_PASSWORD', ''),
                'client' => null,
                'tls' => filter_var(env('EMAIL_SMTP_TLS', false), FILTER_VALIDATE_BOOLEAN),
                'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
            ],
        },
    ],
    'Email' => [
        'default' => [
            'transport' => 'default',
            'from' => env('EMAIL_FROM', 'noreply@localhost'),
        ],
    ],
    'Documents' => [
        'storage' => [
            'adapter' => 'local', //azure to use azure and set the connection string.
            'azure' => [
                'connectionString' => env('AZURE_STORAGE_CONNECTION_STRING'),
                'container' => 'documents',
                'prefix' => '',
            ],
        ],
    ],
];