<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\OpenApiMergeService;
use App\Test\TestCase\BaseTestCase;

class OpenApiMergeServiceTest extends BaseTestCase
{
    protected ?OpenApiMergeService $service = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->service = new OpenApiMergeService();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(OpenApiMergeService::class, $this->service);
    }

    public function testGetMergedSpecReturnsArray(): void
    {
        $basePath = ROOT . DS . 'webroot' . DS . 'api-docs' . DS . 'openapi.yaml';
        if (!file_exists($basePath)) {
            $this->markTestSkipped('Base openapi.yaml not found');
        }

        $spec = $this->service->getMergedSpec();

        $this->assertIsArray($spec);
        $this->assertNotEmpty($spec);
    }

    public function testGetMergedSpecContainsOpenApiVersion(): void
    {
        $basePath = ROOT . DS . 'webroot' . DS . 'api-docs' . DS . 'openapi.yaml';
        if (!file_exists($basePath)) {
            $this->markTestSkipped('Base openapi.yaml not found');
        }

        $spec = $this->service->getMergedSpec();

        $this->assertArrayHasKey('openapi', $spec);
        $this->assertStringStartsWith('3.', $spec['openapi']);
    }

    public function testGetMergedSpecContainsInfo(): void
    {
        $basePath = ROOT . DS . 'webroot' . DS . 'api-docs' . DS . 'openapi.yaml';
        if (!file_exists($basePath)) {
            $this->markTestSkipped('Base openapi.yaml not found');
        }

        $spec = $this->service->getMergedSpec();

        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('title', $spec['info']);
    }

    public function testGetMergedSpecContainsPaths(): void
    {
        $basePath = ROOT . DS . 'webroot' . DS . 'api-docs' . DS . 'openapi.yaml';
        if (!file_exists($basePath)) {
            $this->markTestSkipped('Base openapi.yaml not found');
        }

        $spec = $this->service->getMergedSpec();

        $this->assertArrayHasKey('paths', $spec);
        $this->assertIsArray($spec['paths']);
    }

    public function testGetMergedSpecMergesPluginTags(): void
    {
        $basePath = ROOT . DS . 'webroot' . DS . 'api-docs' . DS . 'openapi.yaml';
        if (!file_exists($basePath)) {
            $this->markTestSkipped('Base openapi.yaml not found');
        }

        $spec = $this->service->getMergedSpec();

        if (!isset($spec['tags'])) {
            $this->markTestSkipped('No tags in spec');
        }

        $tagNames = array_column($spec['tags'], 'name');
        // Tags should be unique
        $this->assertEquals(count($tagNames), count(array_unique($tagNames)), 'Tags should be unique by name');
    }

    public function testGetMergedSpecIsIdempotent(): void
    {
        $basePath = ROOT . DS . 'webroot' . DS . 'api-docs' . DS . 'openapi.yaml';
        if (!file_exists($basePath)) {
            $this->markTestSkipped('Base openapi.yaml not found');
        }

        $spec1 = $this->service->getMergedSpec();
        $spec2 = $this->service->getMergedSpec();

        $this->assertEquals($spec1, $spec2);
    }

    public function testGetMergedSpecContainsComponents(): void
    {
        $basePath = ROOT . DS . 'webroot' . DS . 'api-docs' . DS . 'openapi.yaml';
        if (!file_exists($basePath)) {
            $this->markTestSkipped('Base openapi.yaml not found');
        }

        $spec = $this->service->getMergedSpec();

        // Components section should exist if there are schemas
        if (isset($spec['components'])) {
            $this->assertIsArray($spec['components']);
        }
    }
}
