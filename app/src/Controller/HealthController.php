<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\Cache\TenantAwareCache;
use Cake\Cache\Cache;
use Cake\Cache\Engine\RedisEngine;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\Log\Log;
use RuntimeException;
use Throwable;

/**
 * Health check controller for load balancers and monitoring.
 *
 * Returns JSON status without authentication. Checks DB and cache connectivity.
 */
class HealthController extends AppController
{
    /**
     * Run before controller action execution.
     *
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->Authentication->addUnauthenticatedActions(['index']);
        $this->Authorization->skipAuthorization();
    }

    /**
     * Readiness probe: DB ping, read-only cache ping, and version info.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        $dbOk = false;
        try {
            $connection = ConnectionManager::get('default');
            $connection->execute('SELECT 1');
            $dbOk = true;
        } catch (Throwable $e) {
            Log::warning(sprintf('Readiness database check failed: %s', $e::class));
        }

        $cacheOk = false;
        try {
            $requestedCacheEngine = Configure::read('Platform.runtime.cache.requestedEngine');
            if (
                $requestedCacheEngine === 'redis'
                && (
                    !(Cache::pool('default') instanceof RedisEngine)
                    || Configure::read('Platform.runtime.session.defaults') !== 'cache'
                )
            ) {
                throw new RuntimeException('Required shared Redis cache/session backend is not active.');
            }
            Cache::read(TenantAwareCache::tenantScopedKey('health_check'), 'default');
            $cacheOk = true;
        } catch (Throwable $e) {
            Log::warning(sprintf('Readiness cache check failed: %s', $e::class));
        }

        $status = $dbOk && $cacheOk ? 'ok' : 'degraded';

        $data = [
            'status' => $status,
            'version' => trim((string)Configure::read('App.version', 'unknown')),
            'image_tag' => trim((string)Configure::read('App.imageTag', 'unknown')),
            'channel' => trim((string)Configure::read('App.releaseChannel', 'release')),
            'db' => $dbOk,
            'cache' => $cacheOk,
            'profile' => Configure::read('KMP.Deploy.Profile', 'unknown'),
            'timestamp' => date('c'),
        ];

        $response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data));

        if (!$dbOk || !$cacheOk) {
            $response = $response->withStatus(503);
        }

        return $response;
    }
}
