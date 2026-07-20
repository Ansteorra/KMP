<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformTotpVerifier;
use App\Services\Secrets\SensitiveString;
use Cake\TestSuite\TestCase;

class PlatformTotpVerifierTest extends TestCase
{
    public function testVerifiesCurrentPreviousAndNextWindowCodes(): void
    {
        $secretStore = new InMemoryWritableSecretStore();
        $secretStore->put('platform.admin.test.totp', new SensitiveString(
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
        ));
        $now = 1_111_111_111;
        $verifier = new PlatformTotpVerifier($secretStore, 1, 30, 6, 'sha1', static fn(): int => $now);

        $this->assertTrue($verifier->verify(
            'admin-id',
            'platform.admin.test.totp',
            $verifier->codeForTimestamp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $now),
        ));
        $this->assertTrue($verifier->verify(
            'admin-id',
            'platform.admin.test.totp',
            $verifier->codeForTimestamp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $now - 30),
        ));
        $this->assertTrue($verifier->verify(
            'admin-id',
            'platform.admin.test.totp',
            $verifier->codeForTimestamp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $now + 30),
        ));
        $this->assertFalse($verifier->verify(
            'admin-id',
            'platform.admin.test.totp',
            $verifier->codeForTimestamp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $now + 60),
        ));
    }

    public function testRejectsInvalidCodesMissingSecretsAndMalformedSecretReferences(): void
    {
        $secretStore = new InMemoryWritableSecretStore();
        $secretStore->put('invalid.totp', new SensitiveString('not-base32!'));
        $verifier = new PlatformTotpVerifier($secretStore, 1, 30, 6, 'sha1', static fn(): int => 1_111_111_111);

        $this->assertFalse($verifier->verify('admin-id', null, '123456'));
        $this->assertFalse($verifier->verify('admin-id', 'missing.totp', '123456'));
        $this->assertFalse($verifier->verify('admin-id', 'invalid.totp', '000000'));
        $this->assertFalse($verifier->verify('admin-id', 'invalid.totp', '12345'));
        $this->assertFalse($verifier->verify('admin-id', 'invalid.totp', '12345a'));
    }

    public function testMatchesRfc6238Sha1AndSha256Vectors(): void
    {
        $sha1Store = new InMemoryWritableSecretStore();
        $sha1Store->put('sha1.totp', new SensitiveString(
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
        ));
        $sha1 = new PlatformTotpVerifier($sha1Store, 0, 30, 8, 'sha1', static fn(): int => 59);
        $this->assertSame('94287082', $sha1->codeForTimestamp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
        $this->assertTrue($sha1->verify('admin-id', 'sha1.totp', '94287082'));

        $sha256Store = new InMemoryWritableSecretStore();
        $sha256Store->put('sha256.totp', new SensitiveString(
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZA',
        ));
        $sha256 = new PlatformTotpVerifier($sha256Store, 0, 30, 8, 'sha256', static fn(): int => 59);
        $this->assertSame(
            '46119246',
            $sha256->codeForTimestamp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZA', 59),
        );
        $this->assertTrue($sha256->verify('admin-id', 'sha256.totp', '46119246'));
    }
}
