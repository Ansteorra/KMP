<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\RestoreMaintenanceMiddleware;
use App\Services\RestoreStatusService;
use Cake\Cache\Cache;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RestoreMaintenanceMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array('restore_status', Cache::configured(), true)) {
            Cache::setConfig('restore_status', [
                'className' => 'Cake\Cache\Engine\ArrayEngine',
                'duration' => '+1 hours',
            ]);
        }
        $this->clearRestoreCache();
    }

    protected function tearDown(): void
    {
        $this->clearRestoreCache();
        parent::tearDown();
    }

    public function testFailedRestoreRequiringMaintenanceShowsStandaloneLogPage(): void
    {
        $statusService = new RestoreStatusService();
        $statusService->acquireLock(['source' => 'test backup']);
        $statusService->markFailed('Restore/import failed: Cannot describe backups.', [
            'maintenance_required' => true,
            'source' => 'test backup',
        ]);
        $statusService->appendLog('Restore failed: Cannot describe backups.');

        $response = (new RestoreMaintenanceMiddleware())->process(
            ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/backups']),
            $this->handler(new Response(['status' => 204])),
        );

        $body = (string)$response->getBody();
        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('Restore failed', $body);
        $this->assertStringContainsString('Restore/import failed: Cannot describe backups.', $body);
        $this->assertStringContainsString('Restore failed: Cannot describe backups.', $body);
    }

    public function testFailedRestoreWithoutMaintenanceRequirementPassesThrough(): void
    {
        $statusService = new RestoreStatusService();
        $statusService->acquireLock(['source' => 'test backup']);
        $statusService->markFailed('Restore failed to start.');

        $response = (new RestoreMaintenanceMiddleware())->process(
            ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/backups']),
            $this->handler(new Response(['status' => 204])),
        );

        $this->assertSame(204, $response->getStatusCode());
    }

    private function clearRestoreCache(): void
    {
        try {
            Cache::clear('restore_status');
        } catch (Exception $e) {
            // Cache engine may not support clear.
        }
    }

    private function handler(Response $response): RequestHandlerInterface
    {
        return new class ($response) implements RequestHandlerInterface {
            public function __construct(private readonly Response $response)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}
