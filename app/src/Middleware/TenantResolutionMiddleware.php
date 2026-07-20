<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\Platform\PlatformHealthCheckerInterface;
use App\Services\Platform\PlatformHealthService;
use App\Services\Platform\TenantHostResolver;
use App\Services\Platform\TenantOperationalMetricsService;
use App\Services\TenantConnectionManager;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Exception\DatabaseException;
use Cake\Database\Exception\MissingConnectionException;
use Cake\Database\Exception\MissingDriverException;
use Cake\Database\Exception\MissingExtensionException;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\Datasource\Exception\MissingDatasourceException;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class TenantResolutionMiddleware implements MiddlewareInterface
{
    /**
     * Constructor.
     *
     * @param bool $enabled Whether tenant resolution should run
     * @param \App\Services\TenantConnectionManager $connectionManager Tenant connection manager
     * @param string $requiredSchemaVersion Minimum schema version required by this app revision
     * @param \App\Services\Platform\PlatformHealthCheckerInterface|null $platformHealth Platform health checker
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly TenantConnectionManager $connectionManager,
        private readonly string $requiredSchemaVersion = '',
        private readonly ?PlatformHealthCheckerInterface $platformHealth = null,
        private readonly ?TenantHostResolver $tenantHostResolver = null,
        private readonly ?TenantOperationalMetricsService $operationalMetrics = null,
    ) {
    }

    /**
     * Resolve the request host to a tenant and bind tenant context for the request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        if ($request->getUri()->getPath() === '/health') {
            return $handler->handle($request);
        }

        $host = $request->getUri()->getHost();
        if ($this->isPlatformAdminHost($host)) {
            return $this->handlePlatformAdminHost($request, $handler);
        }

        $platformHealth = $this->platformHealth ?? new PlatformHealthService();
        $healthStatus = $platformHealth->check();
        if (!$healthStatus->isHealthy()) {
            return new Response(['status' => 503, 'body' => 'Platform metadata database unavailable.']);
        }

        try {
            $tenant = ($this->tenantHostResolver ?? new TenantHostResolver())->resolve($host);
        } catch (
            DatabaseException |
            MissingConnectionException |
            MissingDatasourceConfigException |
            MissingDatasourceException |
            MissingDriverException |
            MissingExtensionException |
            PDOException $exception
        ) {
            Log::error(sprintf('Platform tenant resolution failed closed: %s', $exception::class));

            return new Response(['status' => 503, 'body' => 'Platform metadata database unavailable.']);
        }
        if ($tenant === null) {
            return new Response(['status' => 404, 'body' => 'Tenant not found.']);
        }
        if ($tenant->status !== 'active') {
            return new Response(['status' => 503, 'body' => 'Tenant is not active.']);
        }
        $schemaIsTooOld = $this->requiredSchemaVersion !== ''
            && strcmp((string)$tenant->schemaVersion, $this->requiredSchemaVersion) < 0;
        if ($schemaIsTooOld) {
            return new Response(['status' => 503, 'body' => 'Tenant maintenance in progress.']);
        }

        $startedAt = hrtime(true);
        try {
            $response = $this->connectionManager->withTenant(
                $tenant,
                fn(): ResponseInterface => $handler->handle($request),
            );
            $this->recordOperationalMetric(
                $tenant->id,
                $request,
                $response->getStatusCode(),
                $startedAt,
            );

            return $response;
        } catch (Throwable $exception) {
            $statusCode = (int)$exception->getCode();
            if ($statusCode < 400 || $statusCode > 599) {
                $statusCode = 500;
            }
            $this->recordOperationalMetric($tenant->id, $request, $statusCode, $startedAt);

            throw $exception;
        }
    }

    /**
     * Allow the dedicated platform-admin host to bypass tenant DB binding.
     */
    private function handlePlatformAdminHost(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $path = $request->getUri()->getPath();
        if (
            str_starts_with($path, '/platform-admin')
            || str_starts_with($path, '/css/')
            || str_starts_with($path, '/js/')
            || str_starts_with($path, '/img/')
            || str_starts_with($path, '/fonts/')
            || str_starts_with($path, '/debug_kit/')
            || str_starts_with($path, '/debug-kit/')
            || $path === '/favicon.ico'
        ) {
            return $handler->handle($request);
        }

        return (new Response(['status' => 302]))->withLocation('/platform-admin');
    }

    /**
     * Check whether a request host is reserved for the platform-admin portal.
     */
    private function isPlatformAdminHost(string $host): bool
    {
        $normalizedHost = strtolower(rtrim($host, '.'));
        $configuredHosts = (array)Configure::read('Platform.adminPortal.hosts', []);
        $configuredHosts = array_map(
            static fn($value): string => strtolower(rtrim(trim((string)$value), '.')),
            $configuredHosts,
        );

        return in_array($normalizedHost, array_filter($configuredHosts), true);
    }

    /**
     * Record a privacy-safe request aggregate without affecting the response.
     */
    private function recordOperationalMetric(
        string $tenantId,
        ServerRequestInterface $request,
        int $statusCode,
        int $startedAt,
    ): void {
        if (!Configure::read('Platform.telemetry.enabled', true)) {
            return;
        }

        try {
            $metrics = $this->operationalMetrics;
            if ($metrics === null) {
                $connection = ConnectionManager::get('platform');
                if (!$connection instanceof Connection) {
                    return;
                }
                $metrics = new TenantOperationalMetricsService(
                    $connection,
                    (int)Configure::read(
                        'Platform.telemetry.slowRequestMs',
                        TenantOperationalMetricsService::DEFAULT_SLOW_REQUEST_MS,
                    ),
                );
            }
            $metrics->record(
                $tenantId,
                $this->routeName($request),
                $statusCode,
                (int)round((hrtime(true) - $startedAt) / 1_000_000),
            );
        } catch (Throwable $exception) {
            Log::warning(sprintf('Tenant operational metric write failed: %s', $exception::class));
        }
    }

    /**
     * Build a route identifier without IDs, query strings, or request data.
     */
    private function routeName(ServerRequestInterface $request): string
    {
        if (!$request instanceof ServerRequest) {
            return 'unrouted';
        }

        $parts = [];
        foreach (['plugin', 'prefix', 'controller', 'action'] as $parameter) {
            $value = trim((string)$request->getParam($parameter, ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? 'unrouted' : implode('/', $parts);
    }
}
