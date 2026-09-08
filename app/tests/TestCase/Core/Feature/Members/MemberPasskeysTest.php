<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/** Route-level protections remain in force around the WebAuthn verifier. */
class MemberPasskeysTest extends HttpIntegrationTestCase
{
    public function testManagementRequiresAuthentication(): void
    {
        $this->get('/passkeys');
        $this->assertRedirectContains('/members/login');
    }

    public function testManagementBookmarkReturnsToTheMemberProfile(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/passkeys');
        $this->assertRedirect('/members/profile');
        $this->assertResponseNotContains('data-passkey-target="password"');
    }

    public function testMobileFrameContainsOnlyTheGuidedSettings(): void
    {
        $this->authenticateAsSuperUser();
        $this->configRequest(['headers' => ['Turbo-Frame' => 'passkey-settings', 'User-Agent' => 'iPhone']]);
        $this->get('/passkeys');
        $this->assertResponseOk();
        $this->assertResponseContains('<turbo-frame id="passkey-settings">');
        $this->assertResponseContains('data-step="password" hidden');
        $this->assertResponseNotContains('<body');
        $this->assertResponseNotContains('id="passkeyModal"');
    }

    public function testSecurityDialogGroupsAllOwnAccountActions(): void
    {
        $this->authenticateAsSuperUser();
        $this->configRequest(['headers' => ['Turbo-Frame' => 'passkey-settings']]);
        $this->get('/members/security');
        $this->assertResponseOk();
        $this->assertResponseContains('Your account security');
        $this->assertResponseContains('Manage passkeys');
        $this->assertResponseContains('Change password');
        $this->assertResponseContains('Sign out this device');
        $this->assertResponseContains('Sign out all devices');
    }

    public function testOrdinaryMemberCannotOpenAnotherMembersSecurity(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/members/security/' . self::ADMIN_MEMBER_ID);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->assertResponseNotContains('security-settings#open');
    }

    public function testAdministratorCannotManageAnotherMembersPasskeys(): void
    {
        $this->authenticateAsSuperUser();
        $this->configRequest(['headers' => ['Turbo-Frame' => 'passkey-settings']]);
        $this->get('/members/security/' . self::TEST_MEMBER_AGATHA_ID);
        $this->assertResponseOk();
        $this->assertResponseContains('Reset password');
        $this->assertResponseNotContains('Change password');
        $this->assertResponseNotContains('Manage passkeys');
        $this->assertResponseNotContains('Sign out this device');
    }

    public function testOptionsRejectMissingCsrf(): void
    {
        $this->post('/passkeys/login-options');
        $this->assertResponseCode(403);
        $this->assertSame(0, $this->getTableLocator()->get('PasskeyChallenges')->find()->count());
    }

    public function testRegistrationNeedsPasswordReauthentication(): void
    {
        $this->authenticateAsSuperUser();
        $this->enableCsrfToken();
        $this->configRequest(['environment' => ['HTTPS' => 'on', 'HTTP_HOST' => 'localhost']]);
        $this->post('/passkeys/register-options', ['password' => 'incorrect']);
        $this->assertResponseCode(400);
        $this->assertHeader('Cache-Control', 'no-store');
        $this->assertSame(0, $this->getTableLocator()->get('PasskeyChallenges')->find()->count());
    }

    public function testInvalidAssertionCannotCreateIdentity(): void
    {
        $this->enableCsrfToken();
        $this->configRequest(['environment' => ['HTTPS' => 'on', 'HTTP_HOST' => 'localhost']]);
        $this->post('/passkeys/login', ['credential' => ['id' => 'invalid']]);
        $this->assertResponseCode(400);
        $this->assertSession(null, 'Auth');
    }

    public function testLoginOptionsRequireVerificationAndExposeNoAccountLookup(): void
    {
        $this->enableCsrfToken();
        $this->configRequest(['environment' => ['HTTPS' => 'on', 'HTTP_HOST' => 'localhost']]);
        $this->post('/passkeys/login-options');
        $this->assertResponseOk();
        $data = json_decode((string)$this->_response->getBody(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame('required', $data['publicKey']['userVerification']);
        $this->assertEmpty($data['publicKey']['allowCredentials'] ?? []);
        $this->assertArrayNotHasKey('user', $data['publicKey']);
        $this->assertCount(1, $this->getTableLocator()->get('PasskeyChallenges')->find()->all());
    }
}
