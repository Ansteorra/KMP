<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\KMP\DatabasePlatform;
use App\Test\TestCase\BaseTestCase;
use Cake\Database\Driver\Postgres;
use RuntimeException;

class DatabasePlatformTest extends BaseTestCase
{
    public function testPostgresConfigurationAndUrlAliasesAreAccepted(): void
    {
        foreach (['postgres', 'postgresql', 'pgsql'] as $scheme) {
            $config = $this->configurations();
            $config['default']['url'] = $scheme . '://user:secret@localhost/kmp?sslmode=require';
            DatabasePlatform::assertSupported($config);
        }
        $this->addToAssertionCount(3);
    }

    public function testEveryPlatformDatasourceRejectsUnsupportedDriversAndUrlOverrides(): void
    {
        $overrides = [
            ['driver' => 'Cake\\Database\\Driver\\Mysql'],
            ['url' => 'mysql://user:sensitive-password@localhost/kmp'],
            ['url' => 'postgres://user:sensitive-password@localhost/kmp?driver=Mysql'],
            ['url' => 'sqlite:///:memory:'],
        ];
        foreach (array_keys($this->configurations()) as $name) {
            foreach ($overrides as $override) {
                $config = $this->configurations();
                $config[$name] = $override + $config[$name];
                try {
                    DatabasePlatform::assertSupported($config);
                    $this->fail('Unsupported datasource was accepted: ' . $name);
                } catch (RuntimeException $exception) {
                    $this->assertStringContainsString('PostgreSQL', $exception->getMessage());
                    $this->assertStringNotContainsString('sensitive-password', $exception->getMessage());
                }
            }
        }
    }

    /** @return array<string, array<string, string>> */
    private function configurations(): array
    {
        return array_fill_keys(['default', 'test', 'platform', 'test_platform'], ['driver' => Postgres::class]);
    }
}
