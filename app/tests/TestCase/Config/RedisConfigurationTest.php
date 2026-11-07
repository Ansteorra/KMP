<?php
declare(strict_types=1);

namespace App\Test\TestCase\Config;

use Cake\Cache\Engine\RedisEngine;
use Cake\TestSuite\TestCase;

class RedisConfigurationTest extends TestCase
{
    public function testEncodedAccessKeysReachEverySharedCacheUnchanged(): void
    {
        $server = $_SERVER;
        $password = 'synthetic+/=%:@ key';
        try {
            $_SERVER['CACHE_ENGINE'] = 'redis';
            $_SERVER['REDIS_URL'] = 'rediss://:' . rawurlencode($password) . '@cache.example.test:10000/3';
            $config = (static fn() => require CONFIG . 'app.php')();

            foreach (['default', 'member_permissions', 'permissions_structure', 'restore_status'] as $name) {
                $cache = $config['Cache'][$name];
                $this->assertSame(RedisEngine::class, $cache['className']);
                $this->assertSame($password, $cache['password']);
                $this->assertSame('cache.example.test', $cache['server']);
                $this->assertSame(10000, $cache['port']);
                $this->assertSame(3, $cache['database']);
                $this->assertTrue($cache['tls']);
            }
        } finally {
            $_SERVER = $server;
        }
    }
}
