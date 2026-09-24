<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Services\Security\MemberSessionState;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;

final class TrustedDeviceLoginTest extends HttpIntegrationTestCase
{
    #[DataProvider('assertionValidity')]
    public function testOnlyVerifiedPasskeyLoginUpdatesLastLogin(bool $valid): void
    {
        $members = $this->getTableLocator()->get('Members');
        $member = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $previousLogin = new DateTime('2020-01-01 00:00:00');
        $members->updateAll(['last_login' => $previousLogin], ['id' => $member->id]);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $credentialId = random_bytes(32);
        $passkeys = $this->getTableLocator()->get('MemberPasskeys');
        $credential = $passkeys->newEmptyEntity();
        $credential->patch([
            'member_id' => $member->id, 'auth_version' => $member->auth_version,
            'credential_hash' => hash('sha256', $credentialId),
            'credential_id' => base64_encode($credentialId),
            'public_key' => openssl_pkey_get_details($key)['key'],
            'origin' => 'http://localhost', 'sign_count' => 0,
        ], ['guard' => false]);
        $passkeys->saveOrFail($credential);
        $challenge = base64_encode(random_bytes(32));
        $this->session(['Passkeys' => ['authentication' => [
            'challenge' => $challenge, 'expires' => time() + 300,
            'tenant' => MemberSessionState::tenantId(), 'origin' => 'http://localhost',
        ]]]);
        $client = json_encode([
            'type' => 'webauthn.get', 'origin' => 'http://localhost', 'challenge' => $challenge,
        ], JSON_THROW_ON_ERROR);
        $authData = hash('sha256', 'localhost', true) . chr(5) . pack('N', 0);
        openssl_sign($authData . hash('sha256', $client, true), $signature, $key, OPENSSL_ALGO_SHA256);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $started = DateTime::now()->subSeconds(1);
        $this->post('/members/passkey-login', [
            'credentialId' => base64_encode($credentialId), 'clientDataJSON' => base64_encode($client),
            'authenticatorData' => base64_encode($authData),
            'signature' => base64_encode($valid ? $signature : random_bytes(64)),
        ]);
        $saved = $members->get($member->id);
        $this->assertHeader('Cache-Control', 'no-store');
        if ($valid) {
            $this->assertRedirect();
            $this->assertSession($member->id, 'Auth.member_id');
            $this->assertGreaterThanOrEqual($started, $saved->last_login);
            $this->assertSame($member->auth_version, $saved->auth_version);
        } else {
            $this->assertResponseCode(403);
            $this->assertSession(null, 'Auth');
            $this->assertEquals($previousLogin, $saved->last_login);
        }
    }

    public static function assertionValidity(): array
    {
        return ['valid signature' => [true], 'invalid signature' => [false]];
    }

    public function testSetupRequiresTheCurrentMembersPasswordAndNeverEchoesIt(): void
    {
        $password = 'SyntheticDevicePassword123!';
        $members = $this->getTableLocator()->get('Members');
        $member = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $member->password = $password;
        $members->saveOrFail($member);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/offline/verify-login', ['password' => 'Wrong password']);
        $this->assertResponseCode(403);
        $this->assertResponseNotContains($member->email_address);
        $this->post('/offline/verify-login', ['password' => $password]);
        $this->assertResponseOk();
        $this->assertHeader('Cache-Control', 'no-store');
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame($member->email_address, $body['email']);
        $this->assertSame(32, strlen(base64_decode($body['passkey']['challenge'])));
        $this->assertResponseNotContains($password);
    }

    public function testUnauthenticatedSetupCannotValidateOrSaveADeviceLogin(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/offline/verify-login', ['password' => 'UnusedPassword']);
        $this->assertRedirectContains('/members/login');
    }
}
