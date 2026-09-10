<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

final class MemberSecurityDialogTest extends HttpIntegrationTestCase
{
    public function testOwnSecurityContainsGuidedActionsWithoutThePageLayout(): void
    {
        $this->authenticateAsSuperUser();
        $this->configRequest(['headers' => ['Turbo-Frame' => 'security-settings']]);
        $this->get('/members/security');
        $this->assertResponseOk();
        $this->assertResponseContains('<turbo-frame id="security-settings">');
        $this->assertResponseContains('Your account security');
        $this->assertResponseContains('Change password');
        $this->assertResponseContains('Sign out this device');
        $this->assertResponseContains('Sign out all devices');
        $this->assertResponseNotContains('<body');
        $this->assertResponseNotContains('Manage passkeys');
    }

    public function testOrdinaryMemberCannotOpenAnotherMembersSecurity(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->configRequest(['headers' => ['Turbo-Frame' => 'security-settings']]);
        $this->get('/members/security/' . self::ADMIN_MEMBER_ID);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->assertResponseNotContains('security-settings#open');
    }

    public function testAdministratorGetsMemberResetActionsWithoutSelfSignOut(): void
    {
        $this->authenticateAsSuperUser();
        $this->configRequest(['headers' => ['Turbo-Frame' => 'security-settings']]);
        $this->get('/members/security/' . self::TEST_MEMBER_AGATHA_ID);
        $this->assertResponseOk();
        $this->assertResponseContains('Reset password');
        $this->assertResponseNotContains('Change password');
        $this->assertResponseNotContains('Sign out this device');
    }

    public function testDirectNavigationReturnsToTheMemberProfile(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/members/security/' . self::ADMIN_MEMBER_ID);
        $this->assertRedirectContains('/members/view/' . self::ADMIN_MEMBER_ID);
    }

    public function testCachedMobilePagesUseNormalTemplatesWithoutSessionMaterial(): void
    {
        $this->authenticateAsSuperUser();
        foreach (
            [
                '/offline' => 'data-member-mobile-card-profile-target="memberDetails"',
                '/offline?page=rsvps' => 'data-controller="my-rsvps"',
                '/offline?page=calendar' => 'data-controller="mobile-calendar"',
            ] as $url => $controller
        ) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertHeader('X-KMP-Public-Offline', '1');
            $this->assertResponseContains('class="mobile-header-bar"');
            $this->assertResponseContains($controller);
            $this->assertResponseNotContains('name="csrf-token"');
            $this->assertResponseNotContains('name="kmp-offline-session"');
            $this->assertResponseNotContains('name="_csrfToken"');
            $this->assertResponseNotContains('id="securityModal"');
            $this->assertResponseNotContains('data-offline-vault-target="card"');
        }
    }
}
