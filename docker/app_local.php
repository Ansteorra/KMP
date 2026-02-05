<?php
/*
 * Docker-specific local configuration for KMP development.
 * This file is automatically copied to config/app_local.php by the entrypoint script.
 * 
 * Key differences from .devcontainer version:
 *   - Database host uses MYSQL_HOST env var (defaults to 'db' service)
 *   - All config pulled from environment variables
 */
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
            'url' => env('DATABASE_URL', null),
        ],
        'test' => [
            'host' => env('MYSQL_HOST', 'db'),
            'username' => env('MYSQL_USERNAME'),
            'password' => env('MYSQL_PASSWORD'),
            'database' => env('MYSQL_DB_NAME') . '_test',
            'url' => env('DATABASE_URL', null),
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
            'adapter' => 'local',
            'azure' => [
                'connectionString' => env('AZURE_STORAGE_CONNECTION_STRING'),
                'container' => 'documents',
                'prefix' => '',
            ],
        ],
    ],
];
