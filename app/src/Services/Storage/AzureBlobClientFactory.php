<?php
declare(strict_types=1);

namespace App\Services\Storage;

use AzureOss\Storage\Blob\BlobServiceClient;
use GuzzleHttp\Psr7\Uri;
use RuntimeException;

/**
 * Selects exactly the configured Azure credential; never downgrades authentication.
 */
final class AzureBlobClientFactory
{
    /**
     * @param array<string, mixed> $config Storage configuration
     * @return array<string, string|null>
     */
    public static function normalize(array $config): array
    {
        $mode = $config['authMode'] ?? 'connectionString';
        if ($mode === 'managedIdentity') {
            $account = $config['accountName'] ?? null;
            if (!is_string($account) || !preg_match('/^[a-z0-9]{3,24}$/D', $account)) {
                throw new RuntimeException('Azure managed identity requires a valid account name.');
            }
            $clientId = $config['managedIdentityClientId'] ?? null;
            if ($clientId !== null && (!is_string($clientId) || $clientId === '')) {
                throw new RuntimeException('Azure managed identity client ID is invalid.');
            }

            // A stale connection string cannot override managed identity.
            return ['authMode' => $mode, 'accountName' => $account, 'managedIdentityClientId' => $clientId];
        }
        if ($mode === 'connectionString') {
            $connectionString = $config['connectionString'] ?? null;
            if (!is_string($connectionString) || trim($connectionString) === '') {
                throw new RuntimeException('Azure connection-string authentication requires a connection string.');
            }

            return ['authMode' => $mode, 'connectionString' => $connectionString];
        }

        throw new RuntimeException('Unsupported Azure storage authentication mode.');
    }

    /**
     * @param array<string, mixed> $config Storage configuration
     */
    public static function create(array $config): BlobServiceClient
    {
        $config = self::normalize($config);
        if ($config['authMode'] === 'connectionString') {
            return BlobServiceClient::fromConnectionString((string)$config['connectionString']);
        }

        return new BlobServiceClient(
            new Uri(sprintf('https://%s.blob.core.windows.net/', $config['accountName'])),
            new AzureManagedIdentityTokenCredential($config['managedIdentityClientId']),
        );
    }
}
