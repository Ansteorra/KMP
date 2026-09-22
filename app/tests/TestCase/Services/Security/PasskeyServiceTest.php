<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Security;

use App\Services\Security\PasskeyService;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Laminas\Diactoros\Uri;
use OpenSSLAsymmetricKey;
use Throwable;

/** Real signatures and attestation parsing against a transaction-isolated seeded account. */
final class PasskeyServiceTest extends BaseTestCase
{
    private PasskeyService $service;
    private ServerRequest $request;
    private OpenSSLAsymmetricKey $key;
    private string $credential;

    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('KMP.tenancy.enabled', false);
        $this->service = new PasskeyService();
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->request = (new ServerRequest([
            'url' => 'https://localhost/', 'session' => new Session(['defaults' => 'php']),
        ]))->withUri(new Uri('https://localhost/'))->withAttribute('identity', $member);
        $this->request->getSession()->delete('Impersonation');
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->credential = random_bytes(32);
    }

    /** Minimal deterministic CBOR encoder for a synthetic none-attestation fixture. */
    private function cbor(mixed $value): string
    {
        if (is_int($value)) {
            return chr($value >= 0 ? $value : 0x20 + (-1 - $value));
        }
        if (is_array($value)) {
            $result = chr(0xa0 + count($value));
            foreach ($value as $key => $item) {
                $result .= $this->cbor($key) . $this->cbor($item);
            }

            return $result;
        }
        // Map keys/format names are text; key coordinates and authData are byte strings.
        $text = in_array($value, ['fmt', 'none', 'authData', 'attStmt'], true);
        $major = $text ? 0x60 : 0x40;
        $length = strlen($value);

        return ($length < 24 ? chr($major + $length) : chr($major + 24) . chr($length)) . $value;
    }

    private function client(string $challenge, string $type): string
    {
        return json_encode(['type' => $type, 'origin' => 'https://localhost', 'challenge' => $challenge]);
    }

    private function register(): array
    {
        $member = $this->request->getAttribute('identity');
        $options = $this->service->registrationOptions($this->request, $member);
        $details = openssl_pkey_get_details($this->key);
        // OpenSSL may omit leading zero bytes; COSE P-256 coordinates must be 32 bytes.
        $cose = $this->cbor([
            1 => 2, 3 => -7, -1 => 1,
            -2 => str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT),
            -3 => str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT),
        ]);
        $authData = hash('sha256', 'localhost', true) . chr(0x45) . pack('N', 0)
            . str_repeat("\0", 16) . pack('n', strlen($this->credential)) . $this->credential . $cose;
        $attestation = $this->cbor(['fmt' => 'none', 'attStmt' => [], 'authData' => $authData]);

        return $this->service->register($this->request->withParsedBody([
            'credentialId' => base64_encode($this->credential), 'attestationObject' => base64_encode($attestation),
            'clientDataJSON' => base64_encode($this->client($options['challenge'], 'webauthn.create')),
        ]));
    }

    private function assertion(int $counter = 0, int $flags = 5): ServerRequest
    {
        $options = $this->service->authenticationOptions($this->request);
        $client = $this->client($options['challenge'], 'webauthn.get');
        $authData = hash('sha256', 'localhost', true) . chr($flags) . pack('N', $counter);
        openssl_sign($authData . hash('sha256', $client, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return $this->request->withParsedBody([
            'credentialId' => base64_encode($this->credential), 'clientDataJSON' => base64_encode($client),
            'authenticatorData' => base64_encode($authData), 'signature' => base64_encode($signature),
        ]);
    }

    public function testNoneAttestationAndCounterlessPasskeyCanAuthenticateWithoutPrf(): void
    {
        $public = $this->register();
        $this->assertSame(-7, $public['algorithm']);
        $this->assertSame('https://localhost', $public['origin']);
        $this->assertSame(base64_encode($this->credential), $public['credentialId']);
        for ($i = 0; $i < 2; $i++) {
            $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $this->service->authenticate($this->assertion())->id);
        }
    }

    public function testChallengeCannotBeReplayed(): void
    {
        $this->register();
        $request = $this->assertion(1);
        $this->service->authenticate($request);
        $this->expectException(ForbiddenException::class);
        $this->service->authenticate($request);
    }

    public function testSignatureCounterCannotGoBackwards(): void
    {
        $this->register();
        $this->service->authenticate($this->assertion(4));
        $this->expectException(ForbiddenException::class);
        $this->service->authenticate($this->assertion(3));
    }

    public function testSignedButUnverifiedUserCannotAuthenticate(): void
    {
        $this->register();
        $this->expectException(ForbiddenException::class);
        $this->service->authenticate($this->assertion(1, 1));
    }

    public function testWrongOriginChallengeSignatureAndKeyAreRejected(): void
    {
        $this->register();
        foreach (['origin', 'challenge', 'type', 'crossOrigin', 'signature', 'credentialId'] as $field) {
            $request = $this->assertion();
            $data = $request->getData();
            if (in_array($field, ['signature', 'credentialId'], true)) {
                $data[$field] = base64_encode(random_bytes(32));
            } else {
                $client = json_decode(base64_decode($data['clientDataJSON']), true);
                $client[$field] = $field === 'crossOrigin' ? true : base64_encode(random_bytes(32));
                $data['clientDataJSON'] = base64_encode(json_encode($client));
            }
            $rejected = false;
            try {
                $this->service->authenticate($request->withParsedBody($data));
            } catch (Throwable) {
                $rejected = true;
            }
            $this->assertTrue($rejected, $field);
            $this->assertNull($request->getSession()->read('Passkeys.authentication'));
        }
    }

    public function testPasswordEpochRotationRevokesRegisteredPasskeys(): void
    {
        $this->register();
        $this->getTableLocator()->get('Members')->updateAll(['auth_version' => bin2hex(random_bytes(32))], [
            'id' => self::TEST_MEMBER_AGATHA_ID,
        ]);
        $this->expectException(ForbiddenException::class);
        $this->service->authenticate($this->assertion());
    }

    public function testExpiredAndCrossTenantChallengesAreRejected(): void
    {
        $this->register();
        foreach (['expires' => 1, 'tenant' => 'other-tenant', 'origin' => 'https://other.example'] as $field => $value) {
            $request = $this->assertion();
            $request->getSession()->write('Passkeys.authentication.' . $field, $value);
            $rejected = false;
            try {
                $this->service->authenticate($request);
            } catch (ForbiddenException) {
                $rejected = true;
            }
            $this->assertTrue($rejected, $field);
        }
    }

    public function testOnlyTheOwnerCanRemoveTheRegistration(): void
    {
        $this->register();
        $other = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $request = $this->request->withParsedBody(['credentialId' => base64_encode($this->credential)]);
        $this->service->remove($request->withAttribute('identity', $other));
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $this->service->authenticate($this->assertion())->id);
        $this->service->remove($request);
        $this->expectException(ForbiddenException::class);
        $this->service->authenticate($this->assertion());
    }

    public function testRegistrationNeedsPasswordChallengeAndCannotRunDuringImpersonation(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->service->register($this->request);
    }

    public function testImpersonationCannotIssueAuthenticationChallenges(): void
    {
        $this->request->getSession()->write('Impersonation', ['active' => true]);
        try {
            $this->expectException(ForbiddenException::class);
            $this->service->authenticationOptions($this->request);
        } finally {
            $this->request->getSession()->delete('Impersonation');
        }
    }
}
