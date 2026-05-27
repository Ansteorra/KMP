<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.3.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\Test\TestCase;

use App\Application;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\Configure;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\MiddlewareQueue;
use Cake\Log\Engine\FileLog;
use Cake\Log\Log;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ApplicationTest class
 */
class ApplicationTest extends HttpIntegrationTestCase
{
    /**
     * Test bootstrap in production.
     *
     * @return void
     */
    public function testBootstrap()
    {
        Configure::write('debug', false);
        $app = new Application(dirname(dirname(__DIR__)) . '/config');
        $app->bootstrap();
        $plugins = $app->getPlugins();

        $this->assertTrue($plugins->has('Bake'), 'plugins has Bake?');
        $this->assertFalse($plugins->has('DebugKit'), 'plugins has DebugKit?');
        $this->assertTrue(
            $plugins->has('Migrations'),
            'plugins has Migrations?',
        );
    }

    /**
     * Test bootstrap add DebugKit plugin in debug mode.
     *
     * @return void
     */
    public function testBootstrapInDebug()
    {
        Configure::write('debug', true);
        $app = new Application(dirname(dirname(__DIR__)) . '/config');
        $app->bootstrap();
        $plugins = $app->getPlugins();

        $this->assertTrue($plugins->has('DebugKit'), 'plugins has DebugKit?');
    }

    /**
     * testMiddleware
     *
     * @return void
     */
    public function testMiddleware()
    {
        $app = new Application(dirname(dirname(__DIR__)) . '/config');
        $middleware = new MiddlewareQueue();

        $middleware = $app->middleware($middleware);

        $this->assertInstanceOf(
            ErrorHandlerMiddleware::class,
            $middleware->current(),
        );

        $stack = iterator_to_array($middleware);
        $assetIndex = null;
        $routingIndex = null;
        foreach ($stack as $index => $layer) {
            if ($layer instanceof AssetMiddleware) {
                $assetIndex = $index;
            }
            if ($layer instanceof RoutingMiddleware) {
                $routingIndex = $index;
            }
        }

        $this->assertNotNull($assetIndex, 'AssetMiddleware should be in middleware stack');
        $this->assertNotNull($routingIndex, 'RoutingMiddleware should be in middleware stack');
        $this->assertTrue($assetIndex < $routingIndex, 'AssetMiddleware should execute before RoutingMiddleware');
    }

    public function testPerformanceMiddlewareLogsKingdomAndHostDimensions(): void
    {
        $previousEnabled = getenv('PERF_REQUEST_LOG_ENABLED');
        $previousLogAll = getenv('PERF_LOG_ALL_REQUESTS');
        $previousKingdomTag = getenv('PERF_KINGDOM_TAG');

        putenv('PERF_REQUEST_LOG_ENABLED=true');
        putenv('PERF_LOG_ALL_REQUESTS=true');
        putenv('PERF_KINGDOM_TAG=test-kingdom');

        try {
            $app = new Application(dirname(dirname(__DIR__)) . '/config');
            $middleware = iterator_to_array($app->middleware(new MiddlewareQueue()));
            $performanceMiddleware = $middleware[1];

            $logFile = TMP . 'performance-instrumentation-test.log';
            if (file_exists($logFile)) {
                unlink($logFile);
            }

            Log::drop('performance');
            Log::setConfig('performance', [
                'className' => FileLog::class,
                'path' => TMP,
                'file' => 'performance-instrumentation-test',
                'scopes' => ['app.performance'],
                'levels' => ['info', 'warning'],
            ]);

            $request = new ServerRequest([
                'environment' => [
                    'REQUEST_METHOD' => 'GET',
                    'HTTP_HOST' => 'metrics.example.test',
                ],
                'url' => '/members',
            ]);

            $handler = new class () implements RequestHandlerInterface {
                public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
                {
                    return new Response(status: 200);
                }
            };

            $performanceMiddleware($request, $handler);

            $this->assertFileExists($logFile);
            $logContents = (string)file_get_contents($logFile);
            $this->assertStringContainsString('host=metrics.example.test', $logContents);
            $this->assertStringContainsString('kingdom=test-kingdom', $logContents);
        } finally {
            if ($previousEnabled !== false) {
                putenv("PERF_REQUEST_LOG_ENABLED={$previousEnabled}");
            } else {
                putenv('PERF_REQUEST_LOG_ENABLED');
            }
            if ($previousLogAll !== false) {
                putenv("PERF_LOG_ALL_REQUESTS={$previousLogAll}");
            } else {
                putenv('PERF_LOG_ALL_REQUESTS');
            }
            if ($previousKingdomTag !== false) {
                putenv("PERF_KINGDOM_TAG={$previousKingdomTag}");
            } else {
                putenv('PERF_KINGDOM_TAG');
            }
        }
    }
}
