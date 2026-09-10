<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\KMP\StaticHelpers;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\Configure;

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
        $this->assertResponseContains('Quick login');
        $this->assertResponseContains('Email + Password');
        $this->assertResponseContains('Device PIN');
        $this->assertResponseContains('Unlock ' . h(StaticHelpers::getAppSetting('KMP.ShortSiteTitle')));
        $this->assertResponseContains('name="kmp-short-site-title"');
        $this->assertStringNotContainsString('Quick login PIN (4-10 digits)', (string)$this->_response->getBody());
    }

    public function testConfiguredBrandingIsEscapedInLoginAndPublicOfflineShells(): void
    {
        $original = Configure::read('KMP.ShortSiteTitle');
        Configure::write('KMP.ShortSiteTitle', 'Guild & <Friends>');
        try {
            foreach (['/members/login', '/offline', '/offline?page=rsvps', '/offline?page=calendar'] as $url) {
                $this->get($url);
                $this->assertResponseOk();
                $this->assertResponseContains('name="kmp-short-site-title" content="Guild &amp; &lt;Friends&gt;"');
                $this->assertResponseContains('Unlock Guild &amp; &lt;Friends&gt;');
                $this->assertResponseNotContains('Unlock KMP');
                $this->assertResponseNotContains('Guild & <Friends>');
            }
        } finally {
            Configure::write('KMP.ShortSiteTitle', $original);
        }
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
        $this->assertResponseContains('Quick login was disabled on this device. Please sign in with your email and password.');
        $this->assertResponseContains('name="quick_login_disabled"');
        $this->assertResponseContains('data-login-device-auth-target="quickDisabled"');
        $this->assertResponseContains('data-login-device-auth-target="quickDisabledEmail"');
    }
}
