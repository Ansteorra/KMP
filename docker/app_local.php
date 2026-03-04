<?php
/*
 * Docker-specific local configuration for KMP development.
 * This file is automatically copied to config/app_local.php by the entrypoint script.
 * 
 * Key differences from .devcontainer version:
 *   - Database host uses MYSQL_HOST env var (defaults to 'db' service)
 *   - All config pulled from environment variables
 */
$databaseUrl = env('DATABASE_URL', null);
$databaseTestUrl = env('DATABASE_TEST_URL', null);
$isPostgresUrl = str_starts_with(strtolower((string)$databaseUrl), 'postgres');
$mysqlSsl = filter_var(env('MYSQL_SSL', false), FILTER_VALIDATE_BOOLEAN);

// Build PDO connection flags based on driver and SSL requirements
$pdoFlags = [];
if ($isPostgresUrl) {
    $pdoFlags[\PDO::ATTR_EMULATE_PREPARES] = true;
} elseif ($mysqlSsl) {
    $pdoFlags[\PDO::MYSQL_ATTR_SSL_CA] = env('MYSQL_SSL_CA', '');
    $pdoFlags[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = filter_var(
        env('MYSQL_SSL_VERIFY', false),
        FILTER_VALIDATE_BOOLEAN
    );
}

return [
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),

    'DebugKit' => [
        'ignoreAuthorization' => true,
        'variablesPanelMaxDepth' => 10,
    ],

    'Security' => [
        'salt' => env('SECURITY_SALT', '7479eaa68e57fc0bf648af17da085950a16c47f3b31af4eb1548a661b545a3fb'),
    ],

    'Datasources' => [
        'default' => [
            'host' => env('MYSQL_HOST', 'db'),  // 'db' is the Docker service name
            'username' => env('MYSQL_USERNAME'),
            'password' => env('MYSQL_PASSWORD'),
            'database' => env('MYSQL_DB_NAME'),
            'url' => $databaseUrl,
            'flags' => $pdoFlags,
        ],
        'test' => [
            'host' => env('MYSQL_HOST', 'db'),
            'username' => env('MYSQL_USERNAME'),
            'password' => env('MYSQL_PASSWORD'),
            'database' => env('MYSQL_DB_NAME') . '_test',
            'url' => $databaseTestUrl ?? ($isPostgresUrl ? $databaseUrl : null),
        ],
    ],

    'EmailTransport' => [
        'default' => [
            'host' => env('EMAIL_SMTP_HOST', 'mailpit'),
            'port' => env('EMAIL_SMTP_PORT', 1025),
            'username' => env('EMAIL_SMTP_USERNAME', ''),
            'password' => env('EMAIL_SMTP_PASSWORD', ''),
            'client' => null,
            'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
        ],
    ],

    'Documents' => [
        'storage' => [
            'adapter' => env('DOCUMENT_STORAGE_ADAPTER', 'local'),
            'azure' => [
                'connectionString' => env('AZURE_STORAGE_CONNECTION_STRING'),
                'container' => env('AZURE_STORAGE_CONTAINER', 'documents'),
                'prefix' => '',
            ],
            's3' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', env('AWS_REGION', 'us-east-1')),
                'bucket' => env('AWS_S3_BUCKET', env('AWS_BUCKET')),
                'endpoint' => env('AWS_S3_ENDPOINT'),
            ],
        ],
    ],
];