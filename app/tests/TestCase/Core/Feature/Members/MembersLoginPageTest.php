<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * Smoke-test the public login page to validate routing and rendering.
 */
final class MembersLoginPageTest extends HttpIntegrationTestCase
{
    public function testLoginPageRenders(): void
    {
        $this->get('/members/login');

        $this->assertResponseOk();
        $this->assertResponseContains('Log in');
        $this->assertResponseContains('Sign in');
        $this->assertResponseContains('Use a passkey');
        $this->assertResponseContains('autocomplete="username webauthn"');
        $this->assertResponseContains('data-login-device-auth-target="continue"');
        $this->assertResponseContains('Open saved offline cards and RSVPs');
        $this->assertResponseNotContains('name="quick_login_pin"');
    }

    public function testQuickLoginOutOfSyncShowsClearErrorAndResetInstruction(): void
    {
        $members = $this->getTableLocator()->get('Members');
        $member = $members->find()
            ->where(['email_address IN' => ['admin@amp.ansteorra.org', 'admin@test.com']])
            ->firstOrFail();

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/members/login', [
            'login_method' => 'quick_pin',
            'email_address' => (string)$member->email_address,
            'quick_login_pin' => '1234',
            'quick_login_device_id' => 'missing-device-login-1234',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('PIN login has been retired.');
        $this->assertSession(null, 'Auth');
    }
}
