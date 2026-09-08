<?php
declare(strict_types=1);

namespace App\Services\Storage;

use App\KMP\TenantMetadata;
use App\Services\Platform\AdministrativeDatabase;
use AzureOss\Storage\Common\Auth\TokenCredential;
use Cake\Core\Configure;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use RuntimeException;
use Throwable;

/**
 * Provision private tenant containers and their fixed, blob-only runtime grant.
 */
final class TenantDocumentProvisioner
{
    /**
     * @param \GuzzleHttp\Client|null $client Management HTTP client
     * @param \AzureOss\Storage\Common\Auth\TokenCredential|null $credential Management credential
     * @param \Closure|null $createContainer Optional test container creator
     */
    public function __construct(
        private readonly ?Client $client = null,
        private readonly ?TokenCredential $credential = null,
        private readonly ?Closure $createContainer = null,
    ) {
    }

    /**
     * @param \App\KMP\TenantMetadata|null $tenant Registered tenant or default container
     * @return void
     */
    public function ensure(?TenantMetadata $tenant = null): void
    {
        if (Configure::read('Documents.storage.adapter', 'local') !== 'azure') {
            return;
        }
        AdministrativeDatabase::requireJob();
        ['azure' => $azure, 'grant' => $grant] = $this->plan($tenant);
        try {
            if ($this->createContainer !== null) {
                ($this->createContainer)($azure);
            } else {
                AzureBlobClientFactory::create($azure)->getContainerClient($azure['container'])->createIfNotExists();
            }
            $credential = $this->credential ?? new AzureManagedIdentityTokenCredential(
                $azure['managedIdentityClientId'] ?? null,
                null,
                'https://management.azure.com/',
            );
            $client = $this->client ?? new Client(['timeout' => 20, 'allow_redirects' => false]);
            $response = $client->put($grant['url'], [
                RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $credential->getToken()->accessToken],
                RequestOptions::JSON => $grant['body'],
                RequestOptions::ALLOW_REDIRECTS => false,
            ]);
            if (!in_array($response->getStatusCode(), [200, 201], true)) {
                throw new RuntimeException('Document runtime grant was not accepted.');
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Tenant document provisioning failed; tenant activation was stopped.',
                0,
                $exception,
            );
        }
    }

    /**
     * Resolve and validate a proposed grant without making external writes.
     *
     * @param \App\KMP\TenantMetadata|null $tenant Registered tenant or default container
     * @return array{azure: array<string, mixed>, grant: array{url: string, body: array<string, mixed>}}
     */
    public function plan(?TenantMetadata $tenant = null): array
    {
        AdministrativeDatabase::requireJob();
        $azure = (new TenantDocumentStorageConfigResolver())->resolveAzureConfig($tenant);
        $config = [
            'accountResourceId' => (string)env('AZURE_DOCUMENT_STORAGE_RESOURCE_ID'),
            'roleDefinitionId' => (string)env('AZURE_DOCUMENT_RUNTIME_ROLE_ID'),
            'principalId' => (string)env('AZURE_DOCUMENT_RUNTIME_PRINCIPAL_ID'),
            'identityResourceId' => (string)env('AZURE_DOCUMENT_RUNTIME_ID'),
            'backupContainer' => (string)env('AZURE_BACKUP_STORAGE_CONTAINER', 'kmp-backups'),
        ];

        return ['azure' => $azure, 'grant' => self::grant($azure, $config)];
    }

    /**
     * Validate all target data before container creation or management requests.
     *
     * @param array<string, mixed> $azure Document adapter config
     * @param array<string, string> $config Administrative grant config
     * @return array{url: string, body: array<string, mixed>}
     */
    public static function grant(array $azure, array $config): array
    {
        $normalized = AzureBlobClientFactory::normalize($azure);
        if ($normalized['authMode'] !== 'managedIdentity') {
            throw new RuntimeException('Automatic Azure tenant provisioning requires explicit managed identity.');
        }
        $uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
        $account = $config['accountResourceId'] ?? '';
        $role = $config['roleDefinitionId'] ?? '';
        $identity = $config['identityResourceId'] ?? '';
        $principal = $config['principalId'] ?? '';
        if (
            !preg_match('#^/subscriptions/(' . $uuid . ')/resourceGroups/[A-Za-z0-9_.()-]+/providers/'
                . 'Microsoft.Storage/storageAccounts/([a-z0-9]{3,24})$#D', $account, $accountParts)
            || $accountParts[2] !== $normalized['accountName']
            || !preg_match('#^/subscriptions/' . $accountParts[1]
                . '(?:/resourceGroups/[A-Za-z0-9_.()-]+)?/providers/Microsoft.Authorization/roleDefinitions/'
                . $uuid . '$#D', $role)
            || !preg_match('#^/subscriptions/' . $accountParts[1] . '/resourceGroups/[A-Za-z0-9_.()-]+'
                . '/providers/Microsoft.ManagedIdentity/userAssignedIdentities/[A-Za-z0-9_-]+$#D', $identity)
            || !preg_match('/^' . $uuid . '$/D', $principal)
        ) {
            throw new RuntimeException(
                'Explicit account, runtime identity and restricted role configuration is required.',
            );
        }
        $container = $azure['container'] ?? '';
        if (
            !is_string($container) || !preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/D', $container)
            || str_contains($container, '--') || $container === ($config['backupContainer'] ?? 'kmp-backups')
        ) {
            throw new RuntimeException('Invalid or reserved tenant document container.');
        }
        $condition = file_get_contents(ROOT . DS . 'resources' . DS . 'security' . DS . 'document-blob-condition.txt');
        if ($condition === false || trim($condition) === '') {
            throw new RuntimeException('The document runtime access condition is unavailable.');
        }
        $scope = $account . '/blobServices/default/containers/' . $container;
        $id = self::armGuid($scope, $identity, 'document-blobs-v2');

        return [
            'url' => 'https://management.azure.com' . $scope
                . '/providers/Microsoft.Authorization/roleAssignments/' . $id . '?api-version=2022-04-01',
            'body' => ['properties' => [
                'roleDefinitionId' => $role,
                'principalId' => $principal,
                'principalType' => 'ServicePrincipal',
                'conditionVersion' => '2.0',
                'condition' => $condition,
            ]],
        ];
    }

    /**
     * Match ARM guid() so Bicep and provisioning update the same role assignment.
     */
    private static function armGuid(string ...$parts): string
    {
        $namespace = hex2bin('11fb06fb712d4ddd98c7e71bbd588830');
        $hash = sha1($namespace . implode('-', $parts));
        $hash[12] = '5';
        $hash[16] = dechex((hexdec($hash[16]) & 3) | 8);

        return substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-' . substr($hash, 12, 4)
            . '-' . substr($hash, 16, 4) . '-' . substr($hash, 20, 12);
    }
}
