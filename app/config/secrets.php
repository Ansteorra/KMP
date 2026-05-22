<?php
declare(strict_types=1);

return [
    'Secrets' => [
        'driver' => env('KMP_SECRETS_DRIVER', 'chain'),
        'drivers' => [
            'chain' => [
                'drivers' => ['env', 'file'],
                'writeTo' => 'file',
            ],
            'file' => [
                'path' => env('KMP_SECRETS_FILE', CONFIG . 'secrets.local.json'),
                'environment' => env('KMP_ENV', env('APP_ENV', 'production')),
                'allowInEnvironments' => ['local', 'development', 'dev', 'test', 'ci'],
            ],
            'env' => [
                'prefix' => env('KMP_SECRETS_ENV_PREFIX', 'KMP_SECRET_'),
            ],
            'database' => [
                'connection' => env('KMP_SECRETS_DB_CONNECTION', 'platform'),
                'namespace' => env('KMP_SECRETS_DB_NAMESPACE', 'platform'),
                'masterDriver' => env('KMP_SECRETS_DB_MASTER_DRIVER', 'env'),
                'masterKeyName' => env('KMP_SECRETS_DB_MASTER_KEY_NAME', 'platform.master_kek'),
                'keyName' => env('KMP_SECRETS_DB_KEY_NAME', 'platform-secrets'),
                'keyVersion' => env('KMP_SECRETS_DB_KEY_VERSION', 'v1'),
            ],
        ],
    ],
];
