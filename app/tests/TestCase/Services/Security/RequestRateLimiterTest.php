<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Security;

use App\Services\Cache\TenantAwareCache;
use App\Services\Security\RequestRateLimiter;
use Cake\Cache\Cache;
use Cake\Cache\Engine\ArrayEngine;
use Cake\TestSuite\TestCase;

class RequestRateLimiterTest extends TestCase
{
    private const CONFIG = 'request_rate_limiter_test';

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array(self::CONFIG, Cache::configured(), true)) {
            Cache::setConfig(self::CONFIG, [
                'className' => ArrayEngine::class,
                'duration' => '+1 hour',
            ]);
        }
        Cache::clear(self::CONFIG);
    }

    protected function tearDown(): void
    {
        Cache::clear(self::CONFIG);
        parent::tearDown();
    }

    public function testAllowsRequestsWithinLimit(): void
    {
        $limiter = new RequestRateLimiter(new TenantAwareCache(), self::CONFIG);

        for ($i = 0; $i < 10; $i++) {
            $result = $limiter->attempt(RequestRateLimiter::BUCKET_EMAIL_TAKEN, '203.0.113.10');
            $this->assertTrue($result->allowed, "Attempt {$i} should be allowed");
        }
    }

    public function testBlocksAfterLimitExceeded(): void
    {
        $limiter = new RequestRateLimiter(new TenantAwareCache(), self::CONFIG);

        for ($i = 0; $i < 10; $i++) {
            $limiter->attempt(RequestRateLimiter::BUCKET_EMAIL_TAKEN, '203.0.113.11');
        }

        $blocked = $limiter->attempt(RequestRateLimiter::BUCKET_EMAIL_TAKEN, '203.0.113.11');
        $this->assertFalse($blocked->allowed);
        $this->assertSame(0, $blocked->remaining);
        $this->assertGreaterThan(0, $blocked->retryAfterSeconds);
    }

    public function testBucketsAreIndependent(): void
    {
        $limiter = new RequestRateLimiter(new TenantAwareCache(), self::CONFIG);

        for ($i = 0; $i < 10; $i++) {
            $limiter->attempt(RequestRateLimiter::BUCKET_EMAIL_TAKEN, '203.0.113.12');
        }

        $searchResult = $limiter->attempt(RequestRateLimiter::BUCKET_SEARCH_MEMBERS, '203.0.113.12');
        $this->assertTrue($searchResult->allowed);
    }

    public function testUnknownBucketThrows(): void
    {
        $limiter = new RequestRateLimiter(new TenantAwareCache(), self::CONFIG);

        $this->expectException(\InvalidArgumentException::class);
        $limiter->attempt('unknown.bucket', '203.0.113.13');
    }
}
