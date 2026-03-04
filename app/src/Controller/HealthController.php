<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;

/**
 * Health check controller for load balancers and monitoring.
 *
 * Returns JSON status without authentication. Checks DB and cache connectivity.
 */
class HealthController extends AppController
{
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->Authentication->addUnauthenticatedActions(['index']);
        $this->Authorization->skipAuthorization();
    }

    /**
     * Lightweight health probe: DB ping, cache ping, version info.
     *
     * @return \Cake\Http\Response
     */
    public function index(): \Cake\Http\Response
    {
        $dbOk = false;
        try {
            $connection = ConnectionManager::get('default');
            $connection->execute('SELECT 1');
            $dbOk = true;
        } catch (\Throwable $e) {
            // DB is down
        }

        $cacheOk = false;
        try {
            $key = 'health_check_' . time();
            Cache::write($key, 'ok', 'default');
            $cacheOk = Cache::read($key, 'default') === 'ok';
            Cache::delete($key, 'default');
        } catch (\Throwable $e) {
            // Cache is down
        }

        $status = $dbOk ? 'ok' : 'degraded';

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

        if (!$dbOk) {
            $response = $response->withStatus(503);
        }

        return $response;
    }
}
