<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\TenantResolutionMiddleware;
use App\Services\Platform\PlatformHealthCheckerInterface;
use App\Services\Platform\PlatformHealthStatus;
use App\Services\Platform\TenantHostResolver;
use App\Services\Platform\TenantOperationalMetricsService;
use App\Services\Secrets\SecretStoreInterface;
use App\Services\Secrets\SensitiveString;
use App\Services\TenantConnectionManager;
use Cake\Core\Configure;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TenantResolutionMiddlewareTest extends TestCase
{
    private mixed $platformConfig = null;

    /**
     * @var array<string, mixed>
     */
    private array $previousPortalConfig = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->platformConfig = ConnectionManager::getConfig('platform');
        $this->previousPortalConfig = (array)Configure::read('Platform.adminPortal', []);
        // The tenant host map is cached in a shared, persistent (file/redis) store
        // with a long duration. Clear it so each test resolves against the platform
        // database it configures rather than a stale map left by the running app or
        // a prior test.
        TenantHostResolver::clearCache();
    }

    protected function tearDown(): void
    {
        TenantHostResolver::clearCache();
        ConnectionManager::drop('platform');
        if ($this->platformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->platformConfig);
        }
        Configure::write('Platform.adminPortal', $this->previousPortalConfig);
        parent::tearDown();
    }

    public function testDisabledMiddlewarePassesThrough(): void
    {
        ConnectionManager::drop('platform');

        $middleware = new TenantResolutionMiddleware(
            false,
            new TenantConnectionManager($this->secretStore(null)),
        );
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/', 'HTTP_HOST' => 'demo.localhost']);
        $response = $middleware->process(
            $request,
            $this->handler(new Response(['status' => 204])),
        );

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testEnabledMiddlewareFailsClosedWhenPlatformIsDegraded(): void
    {
        $middleware = new TenantResolutionMiddleware(
            true,
            new TenantConnectionManager($this->secretStore(null)),
            '',
            $this->platformHealth(PlatformHealthStatus::degraded('platform', 'test-degraded')),
        );
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/', 'HTTP_HOST' => 'demo.localhost']);

        $response = $middleware->process($request, $this->handler(new Response(['status' => 204])));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('Platform metadata database unavailable', (string)$response->getBody());
    }

    public function testPlatformAdminHostBypassesTenantHealthGate(): void
    {
        Configure::write('Platform.adminPortal.hosts', ['Platform.Kmp.Localhost']);
        $middleware = new TenantResolutionMiddleware(
            true,
            new TenantConnectionManager($this->secretStore(null)),
            '',
            $this->platformHealth(PlatformHealthStatus::degraded('platform', 'test-degraded')),
        );
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/platform-admin/login',
            'HTTP_HOST' => 'platform.kmp.localhost',
        ]);

        $response = $middleware->process($request, $this->handler(new Response(['status' => 204])));

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testHealthRouteBypassesTenantResolutionForUnknownHosts(): void
    {
        $middleware = new TenantResolutionMiddleware(
            true,
            new TenantConnectionManager($this->secretStore(null)),
            '',
            $this->platformHealth(PlatformHealthStatus::degraded('platform', 'test-degraded')),
        );
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/health',
            'HTTP_HOST' => 'internal-probe.local',
        ]);

        $response = $middleware->process($request, $this->handler(new Response(['status' => 204])));

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testPlatformAdminHostRootRedirectsToPortal(): void
    {
        Configure::write('Platform.adminPortal.hosts', ['platform.kmp.localhost']);
        $middleware = new TenantResolutionMiddleware(
            true,
            new TenantConnectionManager($this->secretStore(null)),
            '',
            $this->platformHealth(PlatformHealthStatus::healthy('platform')),
        );
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'platform.kmp.localhost',
        ]);

        $response = $middleware->process($request, $this->handler(new Response(['status' => 204])));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/platform-admin', $response->getHeaderLine('Location'));
    }

    public function testPlatformAdminHostAllowsDebugKitRoutes(): void
    {
        Configure::write('Platform.adminPortal.hosts', ['platform.kmp.localhost']);
        $middleware = new TenantResolutionMiddleware(
            true,
            new TenantConnectionManager($this->secretStore(null)),
            '',
            $this->platformHealth(PlatformHealthStatus::degraded('platform', 'test-degraded')),
        );
        foreach (['/debug_kit/js/inject-iframe.js', '/debug-kit/toolbar/example-id'] as $path) {
            $request = ServerRequestFactory::fromGlobals([
                'REQUEST_URI' => $path,
                'HTTP_HOST' => 'platform.kmp.localhost',
            ]);

            $response = $middleware->process($request, $this->handler(new Response(['status' => 204])));

            $this->assertSame(204, $response->getStatusCode(), $path);
        }
    }

    public function testEnabledMiddlewareFailsClosedWhenPlatformQueryFails(): void
    {
        ConnectionManager::drop('platform');

        $middleware = new TenantResolutionMiddleware(
            true,
            new TenantConnectionManager($this->secretStore(null)),
            '',
            $this->platformHealth(PlatformHealthStatus::healthy('platform')),
        );
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/', 'HTTP_HOST' => 'demo.localhost']);

        $response = $middleware->process($request, $this->handler(new Response(['status' => 204])));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('Platform metadata database unavailable', (string)$response->getBody());
    }

    public function testEnabledMiddlewareResolvesHealthyTenant(): void
    {
        $this->configureHealthyPlatformDatabase();

        $middleware = new TenantResolutionMiddleware(
            true,
            new TenantConnectionManager($this->secretStore(new SensitiveString('tenant-db-password'))),
            '20260516000000',
            $this->platformHealth(PlatformHealthStatus::healthy('platform')),
        );
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/', 'HTTP_HOST' => 'Demo.Localhost.']);

        $response = $middleware->process($request, $this->handler(new Response(['status' => 204])));

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testEnabledMiddlewareRecordsPrivacySafeTenantMetric(): void
    {
        $this->configureHealthyPlatformDatabase();
        $connection = ConnectionManager::get('platform');
        $middleware = new TenantResolutionMiddleware(
            true,
            new TenantConnectionManager($this->secretStore(new SensitiveString('tenant-db-password'))),
            '20260516000000',
            $this->platformHealth(PlatformHealthStatus::healthy('platform')),
            null,
            new TenantOperationalMetricsService($connection, 1),
        );
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/members/42',
            'HTTP_HOST' => 'demo.localhost',
        ])
            ->withParam('controller', 'Members')
            ->withParam('action', 'view');

        $response = $middleware->process($request, $this->handler(new Response(['status' => 503])));

        $this->assertSame(503, $response->getStatusCode());
        $metric = $connection->execute(
            'SELECT route_name, request_count, error_count, server_error_count
               FROM tenant_request_metrics_hourly',
        )->fetch('assoc');
        $this->assertSame('Members/view', $metric['route_name']);
        $this->assertSame(1, (int)$metric['request_count']);
        $this->assertSame(1, (int)$metric['error_count']);
        $this->assertSame(1, (int)$metric['server_error_count']);
        $this->assertStringNotContainsString('42', $metric['route_name']);
    }

    private function configureHealthyPlatformDatabase(): void
    {
        ConnectionManager::drop('platform');
        ConnectionManager::setConfig('platform', [
            'className' => 'Cake\Database\Connection',
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'cacheMetadata' => false,
        ]);

        $connection = ConnectionManager::get('platform');
        $connection->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT,
                display_name TEXT,
                status TEXT,
                db_server TEXT,
                db_name TEXT,
                db_role TEXT,
                schema_version TEXT
            )',
        );
        $connection->execute(
            'CREATE TABLE tenant_hosts (
                id TEXT PRIMARY KEY,
                tenant_id TEXT,
                host_normalized TEXT,
                status TEXT
            )',
        );
        $connection->execute(
            'CREATE TABLE tenant_request_metrics_hourly (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                metric_hour TEXT NOT NULL,
                route_name TEXT NOT NULL,
                request_count INTEGER NOT NULL,
                error_count INTEGER NOT NULL,
                server_error_count INTEGER NOT NULL,
                slow_request_count INTEGER NOT NULL,
                duration_total_ms INTEGER NOT NULL,
                duration_max_ms INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                modified_at TEXT NULL,
                UNIQUE (tenant_id, metric_hour, route_name)
            )',
        );
        $connection->execute(
            'INSERT INTO tenants (
                id, slug, display_name, status, db_server, db_name, db_role, schema_version
            ) VALUES (
                :id, :slug, :displayName, :status, :dbServer, :dbName, :dbRole, :schemaVersion
            )',
            [
                'id' => 'tenant-1',
                'slug' => 'demo',
                'displayName' => 'Demo Tenant',
                'status' => 'active',
                'dbServer' => 'tenant-db.local',
                'dbName' => 'tenant_demo',
                'dbRole' => 'tenant_demo_role',
                'schemaVersion' => '20260516000000',
            ],
        );
        $connection->execute(
            'INSERT INTO tenant_hosts (id, tenant_id, host_normalized, status)
            VALUES (:id, :tenantId, :host, :status)',
            [
                'id' => 'host-1',
                'tenantId' => 'tenant-1',
                'host' => 'demo.localhost',
                'status' => 'active',
            ],
        );
    }

    private function platformHealth(PlatformHealthStatus $status): PlatformHealthCheckerInterface
    {
        return new class ($status) implements PlatformHealthCheckerInterface {
            public function __construct(private readonly PlatformHealthStatus $status)
            {
            }

            public function check(): PlatformHealthStatus
            {
                return $this->status;
            }
        };
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new class ($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function secretStore(?SensitiveString $secret): SecretStoreInterface
    {
        return new class ($secret) implements SecretStoreInterface {
            public function __construct(private readonly ?SensitiveString $secret)
            {
            }

            public function get(string $name): ?SensitiveString
            {
                return $this->secret;
            }

            public function exists(string $name): bool
            {
                return $this->secret !== null;
            }

            public function list(string $prefix = ''): array
            {
                return [];
            }

            public function rotatedAt(string $name): ?DateTimeImmutable
            {
                return null;
            }
        };
    }
}
