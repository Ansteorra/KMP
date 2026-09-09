<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Storage;

use App\Services\BackupStorageService;
use App\Services\DocumentService;
use App\Services\Storage\AzureBlobClientFactory;
use App\Services\Storage\AzureManagedIdentityTokenCredential;
use App\Test\TestCase\BaseTestCase;
use AzureOss\Identity\TokenCredential;
use AzureOss\Identity\TokenRequestContext;
use AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter;
use AzureOss\Storage\Common\Middleware\AddEntraIdAuthorizationHeaderMiddleware;
use Cake\Core\Configure;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ReflectionProperty;
use RuntimeException;

class AzureManagedIdentityTokenCredentialTest extends BaseTestCase
{
    private array $originalEnvironment = [];
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['IDENTITY_ENDPOINT', 'IDENTITY_HEADER', 'MSI_ENDPOINT', 'MSI_SECRET'] as $name) {
            $this->originalEnvironment[$name] = getenv($name);
            putenv($name);
        }
        $this->requests = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
        parent::tearDown();
    }

    private function credential(string $resource = 'https://storage.azure.com/'): AzureManagedIdentityTokenCredential
    {
        $handler = HandlerStack::create(new MockHandler([new Response(200, [], json_encode([
            'access_token' => 'synthetic-token', 'expires_on' => time() + 3600,
        ]))]));
        $handler->push(Middleware::history($this->requests));

        return new AzureManagedIdentityTokenCredential('synthetic-client-id', new Client(['handler' => $handler]), $resource);
    }

    public function testStorageSdkCanUseAndCacheTheManagedIdentityToken(): void
    {
        $middleware = new AddEntraIdAuthorizationHeaderMiddleware($this->credential());
        $authorize = $middleware(static fn($request, $options) => $request);
        $request = new Request('GET', 'https://syntheticstorage.blob.core.windows.net/documents');
        $this->assertSame('Bearer synthetic-token', $authorize($request, [])->getHeaderLine('Authorization'));
        $this->assertSame('Bearer synthetic-token', $authorize($request, [])->getHeaderLine('Authorization'));
        $this->assertCount(1, $this->requests);
        $identityRequest = $this->requests[0]['request'];
        $this->assertSame('169.254.169.254', $identityRequest->getUri()->getHost());
        $this->assertSame('true', $identityRequest->getHeaderLine('Metadata'));
        parse_str($identityRequest->getUri()->getQuery(), $query);
        $this->assertSame('https://storage.azure.com/', $query['resource']);
        $this->assertSame('synthetic-client-id', $query['client_id']);
    }

    public function testAdministrativeTokenUsesTheAppServiceEndpointAndManagementAudience(): void
    {
        putenv('IDENTITY_ENDPOINT=http://identity.example.test/token');
        putenv('IDENTITY_HEADER=synthetic-header');
        $token = $this->credential('https://management.azure.com/')
            ->getToken(new TokenRequestContext(['https://management.azure.com/.default']));
        $this->assertSame('synthetic-token', $token->token);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertGreaterThan(time(), $token->expiresOn->getTimestamp());
        $request = $this->requests[0]['request'];
        $this->assertSame('identity.example.test', $request->getUri()->getHost());
        $this->assertSame('synthetic-header', $request->getHeaderLine('X-IDENTITY-HEADER'));
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('https://management.azure.com/', $query['resource']);
    }

    public function testUnexpectedScopesAreRejectedBeforeRequestingAToken(): void
    {
        $credential = $this->credential();
        try {
            $credential->getToken(new TokenRequestContext(['https://management.azure.com/.default']));
            $this->fail('Storage credentials must reject management scopes.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('scopes', $exception->getMessage());
            $this->assertCount(0, $this->requests);
        }
    }

    public function testFactoryConstructsTheNewSdkWithTheConfiguredIdentity(): void
    {
        $client = AzureBlobClientFactory::create(['authMode' => 'managedIdentity', 'accountName' => 'syntheticstorage']);
        $this->assertInstanceOf(TokenCredential::class, $client->credential);
        $this->assertSame('https://syntheticstorage.blob.core.windows.net/', (string)$client->uri);
    }

    public function testDocumentAndBackupServicesConstructTheNewFlysystemAdapter(): void
    {
        $originalConfig = Configure::read();
        $azure = ['authMode' => 'managedIdentity', 'accountName' => 'syntheticstorage', 'container' => 'documents'];
        Configure::write('Documents.storage', ['adapter' => 'azure', 'azure' => $azure]);
        $azure['container'] = 'backups';
        Configure::write('Backups.storage', ['adapter' => 'azure', 'azure' => $azure]);
        try {
            foreach ([new DocumentService(), new BackupStorageService()] as $service) {
                $filesystem = (new ReflectionProperty($service, 'filesystem'))->getValue($service);
                $adapter = (new ReflectionProperty($filesystem, 'adapter'))->getValue($filesystem);
                $this->assertInstanceOf(AzureBlobStorageAdapter::class, $adapter);
            }
        } finally {
            Configure::clear();
            Configure::write($originalConfig);
        }
    }
}
