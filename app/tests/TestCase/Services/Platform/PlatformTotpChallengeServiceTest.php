<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformTotpChallengeService;
use App\Services\Platform\PlatformTotpVerifier;
use App\Services\Secrets\SensitiveString;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;

class PlatformTotpChallengeServiceTest extends TestCase
{
    public function testTotpCannotBeReplayedAcrossServiceInstances(): void
    {
        $connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $connection->execute('CREATE TABLE security_rate_limits (bucket_key TEXT PRIMARY KEY, attempts INT, expires_at BIGINT)');
        $connection->execute('CREATE TABLE platform_users (id TEXT PRIMARY KEY, totp_secret_ref TEXT, status TEXT, last_accepted_totp_counter BIGINT)');
        $connection->insert('platform_users', ['id' => 'admin', 'totp_secret_ref' => 'test', 'status' => 'active']);
        $store = new InMemoryWritableSecretStore();
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $store->put('test', new SensitiveString($secret));
        $now = 1_111_111_111;
        $verifier = new PlatformTotpVerifier($store, 1, 30, 6, 'sha1', static fn(): int => $now);
        $code = $verifier->codeForTimestamp($secret, $now);
        $first = new PlatformTotpChallengeService($connection, $verifier);
        $second = new PlatformTotpChallengeService($connection, $verifier);
        $this->assertTrue($first->consume('admin', 'test', $code));
        $this->assertFalse($second->consume('admin', 'test', $code));
        $this->assertFalse($second->consume('admin', 'test', $verifier->codeForTimestamp($secret, $now - 30)));
        $this->assertTrue($second->consume('admin', 'test', $verifier->codeForTimestamp($secret, $now + 30)));
    }
}
