<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Model\Entity\Member;
use App\Services\MemberPasskeyService;
use App\Test\TestCase\BaseTestCase;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Laminas\Diactoros\Uri;
use lbuchs\WebAuthn\WebAuthnException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/** Real signatures and database-backed ceremonies, including zero-counter passkeys. */
class MemberPasskeyServiceTest extends BaseTestCase
{
    private MemberPasskeyService $service;
    private Member $member;
    private OpenSSLAsymmetricKey $privateKey;
    private string $credentialId;
    private string $handle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $this->privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->credentialId = random_bytes(32);
        $this->handle = random_bytes(32);
        $this->service = new MemberPasskeyService($this->request());
        $table = $this->getTableLocator()->get('MemberPasskeys');
        $record = $table->newEmptyEntity();
        $record->patch([
            'member_id' => $this->member->id,
            'credential_hash' => hash('sha256', $this->credentialId),
            'credential_id' => MemberPasskeyService::encode($this->credentialId),
            'public_key' => openssl_pkey_get_details($this->privateKey)['key'],
            'user_handle' => MemberPasskeyService::encode($this->handle),
            'auth_version' => $this->member->auth_version,
            'rp_id' => 'tenant.example.test', 'signature_counter' => 0,
            'revision' => bin2hex(random_bytes(32)), 'label' => 'Synthetic passkey', 'created_at' => time(),
        ], ['guard' => false]);
        $table->saveOrFail($record);
    }

    private function request(string $origin = 'https://tenant.example.test'): ServerRequest
    {
        return (new ServerRequest())->withUri(new Uri($origin . '/passkeys/login'))
            ->withAttribute('session', new Session());
    }

    private function assertion(array $changes = [], int $flags = 5, string $rp = 'tenant.example.test'): array
    {
        $options = $this->service->options();
        $client = json_encode(array_replace([
            'type' => 'webauthn.get', 'challenge' => $options['publicKey']['challenge'],
            'origin' => 'https://tenant.example.test', 'crossOrigin' => false,
        ], $changes), JSON_THROW_ON_ERROR);
        $authData = hash('sha256', $rp, true) . chr($flags) . pack('N', 0);
        openssl_sign($authData . hash('sha256', $client, true), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return array_map(MemberPasskeyService::encode(...), [
            'id' => $this->credentialId, 'clientDataJSON' => $client,
            'authenticatorData' => $authData, 'signature' => $signature, 'userHandle' => $this->handle,
        ]);
    }

    public function testRealSignatureAuthenticatesOnceAndReplayFails(): void
    {
        $response = $this->assertion();
        $this->assertSame($this->member->id, $this->service->authenticate($response)->id);
        $this->expectExceptionMessage('expired or already used');
        $this->service->authenticate($response);
    }

    public function testRejectsSignedWrongOriginRpIdAndMissingVerification(): void
    {
        foreach (
            [
            fn() => $this->assertion(['origin' => 'https://sibling.example.test']),
            fn() => $this->assertion([], 5, 'example.test'),
            fn() => $this->assertion([], 1),
            ] as $create
        ) {
            $response = $create();
            try {
                $this->service->authenticate($response);
                $this->fail('Invalid assertion authenticated.');
            } catch (RuntimeException | WebAuthnException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function testWrongHandleCannotAuthenticate(): void
    {
        $response = $this->assertion();
        $response['userHandle'] = MemberPasskeyService::encode(random_bytes(32));
        $this->expectExceptionMessage('Credential unavailable');
        $this->service->authenticate($response);
    }

    public function testPasswordRevocationRejectsEvenValidSignedAssertion(): void
    {
        $response = $this->assertion();
        $this->getTableLocator()->get('Members')->updateAll(['auth_version' => bin2hex(random_bytes(32))], ['id' => $this->member->id]);
        $this->expectExceptionMessage('Credential unavailable');
        $this->service->authenticate($response);
    }

    public function testTenantBindingRejectsReplayedCeremony(): void
    {
        $response = $this->assertion();
        $tenant = new TenantMetadata('other-tenant', 'other', 'Other', 'active', 'localhost', 'other', 'other');
        TenantContext::with($tenant, function () use ($response): void {
            $other = new MemberPasskeyService($this->request());
            $this->expectExceptionMessage('expired or already used');
            $other->authenticate($response);
        });
    }

    public function testExpiredChallengeFailsBeforeCredentialVerification(): void
    {
        $response = $this->assertion();
        $this->getTableLocator()->get('PasskeyChallenges')->updateAll(['expires_at' => time() - 1], []);
        $this->expectExceptionMessage('expired or already used');
        $this->service->authenticate($response);
    }

    public function testChallengeFromAnotherBrowserSessionCannotAuthenticate(): void
    {
        $response = $this->assertion();
        $session = $this->getMockBuilder(Session::class)->onlyMethods(['id', 'start'])->getMock();
        $session->method('id')->willReturn('another-browser-session');
        $session->method('start')->willReturn(true);
        $other = new MemberPasskeyService($this->request()->withAttribute('session', $session));
        $this->expectExceptionMessage('expired or already used');
        $other->authenticate($response);
    }

    public function testInvalidSignatureConsumesChallengeWithoutAuthenticating(): void
    {
        $response = $this->assertion();
        $response['signature'] = MemberPasskeyService::encode(random_bytes(64));
        try {
            $this->service->authenticate($response);
            $this->fail('Invalid signature authenticated.');
        } catch (WebAuthnException $exception) {
            $this->assertStringContainsString('signature', $exception->getMessage());
        }
        $this->expectExceptionMessage('expired or already used');
        $this->service->authenticate($response);
    }
}
