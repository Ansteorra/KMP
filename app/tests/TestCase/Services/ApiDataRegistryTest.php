<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\ApiDataRegistry;
use App\Test\TestCase\BaseTestCase;

class ApiDataRegistryTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        ApiDataRegistry::clear();
    }

    protected function tearDown(): void
    {
        ApiDataRegistry::clear();
        parent::tearDown();
    }

    public function testClearRemovesAllProviders(): void
    {
        ApiDataRegistry::register('TestProvider', fn() => [], []);
        $this->assertNotEmpty(ApiDataRegistry::getRegisteredSources());

        ApiDataRegistry::clear();
        $this->assertEmpty(ApiDataRegistry::getRegisteredSources());
    }

    public function testRegisterAndGetRegisteredSources(): void
    {
        ApiDataRegistry::register('Officers', fn() => [], [
            ['controller' => 'Members', 'action' => 'view'],
        ]);
        ApiDataRegistry::register('Awards', fn() => [], [
            ['controller' => 'Members', 'action' => 'view'],
        ]);

        $sources = ApiDataRegistry::getRegisteredSources();
        $this->assertCount(2, $sources);
        $this->assertContains('Officers', $sources);
        $this->assertContains('Awards', $sources);
    }

    public function testUnregister(): void
    {
        ApiDataRegistry::register('TestProvider', fn() => [], []);
        $this->assertContains('TestProvider', ApiDataRegistry::getRegisteredSources());

        ApiDataRegistry::unregister('TestProvider');
        $this->assertNotContains('TestProvider', ApiDataRegistry::getRegisteredSources());
    }

    public function testCollectReturnsEmptyForNoMatchingProviders(): void
    {
        ApiDataRegistry::register('Officers', fn() => ['officers' => []], [
            ['controller' => 'Members', 'action' => 'view'],
        ]);

        $data = ApiDataRegistry::collect('Branches', 'index', null);
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testCollectCallsMatchingProviders(): void
    {
        ApiDataRegistry::register('Officers', function (string $controller, string $action, $entity) {
            return ['officers' => ['count' => 5]];
        }, [
            ['controller' => 'Members', 'action' => 'view'],
        ]);

        $data = ApiDataRegistry::collect('Members', 'view', (object)['id' => 1]);
        $this->assertArrayHasKey('officers', $data);
        $this->assertEquals(['count' => 5], $data['officers']);
    }

    public function testCollectMergesMultipleProviders(): void
    {
        ApiDataRegistry::register('Officers', function () {
            return ['officers' => [1, 2, 3]];
        }, [
            ['controller' => 'Members', 'action' => 'view'],
        ]);

        ApiDataRegistry::register('Awards', function () {
            return ['awards' => [4, 5]];
        }, [
            ['controller' => 'Members', 'action' => 'view'],
        ]);

        $data = ApiDataRegistry::collect('Members', 'view', null);
        $this->assertArrayHasKey('officers', $data);
        $this->assertArrayHasKey('awards', $data);
        $this->assertEquals([1, 2, 3], $data['officers']);
        $this->assertEquals([4, 5], $data['awards']);
    }

    public function testCollectSkipsNonMatchingRoutes(): void
    {
        $called = false;
        ApiDataRegistry::register('Members', function () use (&$called) {
            $called = true;

            return ['members' => []];
        }, [
            ['controller' => 'Members', 'action' => 'view'],
        ]);

        ApiDataRegistry::collect('Branches', 'view', null);
        $this->assertFalse($called);
    }

    public function testCollectPassesEntityToCallback(): void
    {
        $receivedEntity = null;
        ApiDataRegistry::register('Test', function ($controller, $action, $entity) use (&$receivedEntity) {
            $receivedEntity = $entity;

            return [];
        }, [
            ['controller' => 'Members', 'action' => 'view'],
        ]);

        $testEntity = (object)['id' => 42, 'name' => 'Test'];
        ApiDataRegistry::collect('Members', 'view', $testEntity);

        $this->assertNotNull($receivedEntity);
        $this->assertEquals(42, $receivedEntity->id);
    }

    public function testCollectWithMultipleRoutes(): void
    {
        ApiDataRegistry::register('Shared', function () {
            return ['shared_data' => true];
        }, [
            ['controller' => 'Members', 'action' => 'view'],
            ['controller' => 'Branches', 'action' => 'view'],
        ]);

        $membersData = ApiDataRegistry::collect('Members', 'view', null);
        $this->assertArrayHasKey('shared_data', $membersData);

        $branchesData = ApiDataRegistry::collect('Branches', 'view', null);
        $this->assertArrayHasKey('shared_data', $branchesData);

        $noMatch = ApiDataRegistry::collect('Members', 'index', null);
        $this->assertEmpty($noMatch);
    }

    public function testCollectIgnoresNonArrayCallback(): void
    {
        ApiDataRegistry::register('BadCallback', function () {
            return null;
        }, [
            ['controller' => 'Members', 'action' => 'view'],
        ]);

        $data = ApiDataRegistry::collect('Members', 'view', null);
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testRegisterOverwritesSameSource(): void
    {
        ApiDataRegistry::register('Test', function () {
            return ['version' => 1];
        }, [
            ['controller' => 'Members', 'action' => 'view'],
        ]);

        ApiDataRegistry::register('Test', function () {
            return ['version' => 2];
        }, [
            ['controller' => 'Members', 'action' => 'view'],
        ]);

        $sources = ApiDataRegistry::getRegisteredSources();
        $this->assertCount(1, $sources);

        $data = ApiDataRegistry::collect('Members', 'view', null);
        $this->assertEquals(2, $data['version']);
    }

    public function testCollectWithEmptyRegistry(): void
    {
        $data = ApiDataRegistry::collect('Members', 'view', null);
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }
}
