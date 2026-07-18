<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\Configure;

class HealthControllerTest extends HttpIntegrationTestCase
{
    public function testHealthReportsReadinessWithoutAuthentication(): void
    {
        $this->get('/health');

        $this->assertResponseOk();
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertSame('ok', $payload['status']);
        $this->assertTrue($payload['db']);
        $this->assertTrue($payload['cache']);
    }

    public function testHealthRejectsRedisFallbackToLocalCacheOrSessions(): void
    {
        $originalCacheRuntime = Configure::read('Platform.runtime.cache');
        $originalSessionRuntime = Configure::read('Platform.runtime.session');

        try {
            Configure::write('Platform.runtime.cache.requestedEngine', 'redis');
            Configure::write('Platform.runtime.session.defaults', 'php');

            $this->get('/health');

            $this->assertResponseCode(503);
            $payload = json_decode((string)$this->_response->getBody(), true);
            $this->assertIsArray($payload);
            $this->assertSame('degraded', $payload['status']);
            $this->assertFalse($payload['cache']);
        } finally {
            Configure::write('Platform.runtime.cache', $originalCacheRuntime);
            Configure::write('Platform.runtime.session', $originalSessionRuntime);
        }
    }
}
