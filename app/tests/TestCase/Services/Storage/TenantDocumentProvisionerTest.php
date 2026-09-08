<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Storage;

use App\Services\BackupStorageService;
use App\Services\Storage\TenantDocumentProvisioner;
use AzureOss\Storage\Common\Auth\AccessToken;
use AzureOss\Storage\Common\Auth\TokenCredential;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use RuntimeException;

class TenantDocumentProvisionerTest extends TestCase
{
    private array $originalConfig;
    private array $originalEnvironment = [];
    private array $azure = ['authMode' => 'managedIdentity', 'accountName' => 'syntheticstorage', 'container' => 'documents'];
    private array $grantConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConfig = Configure::read();
        $subscription = '11111111-2222-3333-4444-555555555555';
        $scope = '/subscriptions/' . $subscription . '/resourceGroups/synthetic';
        $this->grantConfig = [
            'accountResourceId' => $scope . '/providers/Microsoft.Storage/storageAccounts/syntheticstorage',
            'roleDefinitionId' => $scope . '/providers/Microsoft.Authorization/roleDefinitions/' . $subscription,
            'principalId' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'identityResourceId' => $scope . '/providers/Microsoft.ManagedIdentity/userAssignedIdentities/runtime',
            'backupContainer' => 'kmp-backups',
        ];
        foreach (
            [
            'AZURE_DOCUMENT_STORAGE_RESOURCE_ID' => 'accountResourceId',
            'AZURE_DOCUMENT_RUNTIME_ROLE_ID' => 'roleDefinitionId',
            'AZURE_DOCUMENT_RUNTIME_PRINCIPAL_ID' => 'principalId',
            'AZURE_DOCUMENT_RUNTIME_ID' => 'identityResourceId',
            'AZURE_BACKUP_STORAGE_CONTAINER' => 'backupContainer',
            ] as $variable => $key
        ) {
            $this->originalEnvironment[$variable] = getenv($variable);
            putenv($variable . '=' . $this->grantConfig[$key]);
        }
        Configure::write('Database.adminJob', true);
        Configure::write('Documents.storage', ['adapter' => 'azure', 'azure' => $this->azure]);
    }

    protected function tearDown(): void
    {
        Configure::clear();
        Configure::write($this->originalConfig);
        foreach ($this->originalEnvironment as $variable => $value) {
            putenv($value === false ? $variable : $variable . '=' . $value);
        }
        parent::tearDown();
    }

    public function testGrantUsesFixedManagementEndpointAndStableArmIdentity(): void
    {
        $plan = TenantDocumentProvisioner::grant($this->azure, $this->grantConfig);
        $this->assertSame($plan, TenantDocumentProvisioner::grant($this->azure, $this->grantConfig));
        $this->assertStringStartsWith(
            'https://management.azure.com' . $this->grantConfig['accountResourceId'] . '/blobServices/default/containers/documents/',
            $plan['url'],
        );
        $properties = $plan['body']['properties'];
        $this->assertSame($this->grantConfig['principalId'], $properties['principalId']);
        $this->assertSame($this->grantConfig['roleDefinitionId'], $properties['roleDefinitionId']);
        $this->assertSame('ServicePrincipal', $properties['principalType']);
        $this->assertSame('2.0', $properties['conditionVersion']);
        $this->assertStringContainsString("StringLike 'backups/*'", $properties['condition']);
        $this->assertStringContainsString("!(SubOperationMatches{'Blob.List'})", $properties['condition']);
    }

    public function testProvisioningCreatesContainerThenSendsOnlyRestrictedGrant(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([new Response(201), new Response(200)]));
        $handler->push(Middleware::history($history));
        $created = [];
        $credential = new class implements TokenCredential {
            public function getToken(): AccessToken
            {
                return new AccessToken('synthetic-management-token', new DateTimeImmutable('+1 hour'));
            }
        };
        $provisioner = new TenantDocumentProvisioner(
            new Client(['handler' => $handler]),
            $credential,
            function (array $azure) use (&$created): void {
                $created[] = $azure['container'];
            },
        );
        $provisioner->ensure();
        $provisioner->ensure();
        $this->assertSame(['documents', 'documents'], $created);
        $this->assertCount(2, $history);
        $expected = TenantDocumentProvisioner::grant($this->azure, $this->grantConfig);
        foreach ($history as $exchange) {
            $this->assertSame('PUT', $exchange['request']->getMethod());
            $this->assertSame($expected['url'], (string)$exchange['request']->getUri());
            $this->assertSame($expected['body'], json_decode((string)$exchange['request']->getBody(), true));
            $this->assertFalse($exchange['options']['allow_redirects']);
        }
    }

    public function testInvalidTargetsNeverCreateAContainer(): void
    {
        $created = false;
        $provisioner = new TenantDocumentProvisioner(createContainer: function () use (&$created): void {
            $created = true;
        });
        foreach (
            [
            ['container' => 'kmp-backups'],
            ['container' => 'invalid/container'],
            ['accountName' => 'differentaccount'],
            ['authMode' => 'connectionString', 'connectionString' => 'synthetic'],
            ] as $invalid
        ) {
            Configure::write('Documents.storage.azure', array_replace($this->azure, $invalid));
            try {
                $provisioner->ensure();
                $this->fail('Invalid target was accepted.');
            } catch (RuntimeException | InvalidArgumentException) {
                $this->assertFalse($created);
            }
        }
    }

    public function testOrdinaryProcessCannotProvision(): void
    {
        Configure::write('Database.adminJob', false);
        $this->expectException(RuntimeException::class);
        (new TenantDocumentProvisioner())->ensure();
    }

    public function testFailedGrantStopsActivationWithoutCredentialDiagnostic(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(403)]))]);
        $credential = new class implements TokenCredential {
            public function getToken(): AccessToken
            {
                return new AccessToken('synthetic-management-token', new DateTimeImmutable('+1 hour'));
            }
        };
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant document provisioning failed; tenant activation was stopped.');
        (new TenantDocumentProvisioner($client, $credential, static function (): void {
        }))->ensure();
    }

    public function testArchiveConfigDoesNotFallBackToDocumentContainer(): void
    {
        Configure::write('Backups.storage', ['adapter' => 'azure', 'azure' => [
            'authMode' => 'managedIdentity', 'accountName' => 'syntheticstorage',
        ]]);
        $this->expectException(RuntimeException::class);
        new BackupStorageService();
    }

    public function testUnknownBackupAdapterFailsClosed(): void
    {
        Configure::write('Backups.storage', ['adapter' => 'azuer']);
        $this->expectException(RuntimeException::class);
        new BackupStorageService();
    }
}
