<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Model\Entity\Member;
use App\Services\MemberAuthenticationService;
use App\Services\QuickLoginDeviceService;
use App\Services\Security\MemberSessionState;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\I18n\DateTime;

class MemberSecurityLifecycleTest extends HttpIntegrationTestCase
{
    private function admin(): Member
    {
        return $this->getTableLocator()->get('Members')->find()
            ->where(['email_address IN' => ['admin@amp.ansteorra.org', 'admin@test.com']])->firstOrFail();
    }

    public function testRecoveryResponsesAreEquivalentAndCooldownPreservesIssuedToken(): void
    {
        $member = $this->admin();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/members/forgot-password', ['email_address' => $member->email_address]);
        $this->assertRedirectContains('/members/login');
        $this->assertFlashMessage('If your email is on file, a password reset link has been sent.');
        $issued = $this->getTableLocator()->get('Members')->get($member->id)->password_token;
        $this->assertNotEmpty($issued);
        $this->session([]);
        $this->post('/members/forgot-password', ['email_address' => 'unknown-security-test@example.invalid']);
        $this->assertRedirectContains('/members/login');
        $this->assertFlashMessage('If your email is on file, a password reset link has been sent.');
        $this->session([]);
        $this->post('/members/forgot-password', ['email_address' => $member->email_address]);
        $this->assertRedirectContains('/members/login');
        $this->assertFlashMessage('If your email is on file, a password reset link has been sent.');
        $this->assertSame($issued, $this->getTableLocator()->get('Members')->get($member->id)->password_token);
    }

    public function testPasswordChangeRevokesPersistedSessionsAndDeviceEpoch(): void
    {
        $member = $this->admin();
        $state = MemberSessionState::fromMember($member);
        $devices = new QuickLoginDeviceService();
        $this->assertTrue($devices->saveDevicePin($member, 'security-test-device-123456', '438259'));
        $member->password = 'ReplacementPasswordWithEntropy123!';
        $this->getTableLocator()->get('Members')->saveOrFail($member);
        $this->assertNotSame($state['auth_version'], $member->auth_version);
        $device = $this->getTableLocator()->get('MemberQuickLoginDevices')->find()
            ->where(['device_id' => 'security-test-device-123456'])->firstOrFail();
        $this->assertNotSame($member->auth_version, $device->auth_version);
        $this->session(['Auth' => $state]);
        $this->get('/members/profile');
        $this->assertRedirectContains('/members/login');
    }

    public function testDisableThenReactivateDoesNotRestoreAnOldSession(): void
    {
        $member = $this->admin();
        $state = MemberSessionState::fromMember($member);
        $oldStatus = $member->status;
        $member->status = Member::STATUS_DEACTIVATED;
        $this->getTableLocator()->get('Members')->saveOrFail($member);
        $member->status = $oldStatus;
        $this->getTableLocator()->get('Members')->saveOrFail($member);
        $this->session(['Auth' => $state]);
        $this->get('/members/profile');
        $this->assertRedirectContains('/members/login');
    }

    public function testRoleRevocationRemovesPrivilegesFromAnExistingSession(): void
    {
        $member = $this->admin();
        $this->assertTrue($member->isSuperUser());
        $state = MemberSessionState::fromMember($member);
        $roles = $this->getTableLocator()->get('MemberRoles');
        foreach ($roles->find()->where(['member_id' => $member->id])->all() as $role) {
            $role->revoked_on = DateTime::now();
            $role->expires_on = DateTime::now()->subDays(1);
            $roles->saveOrFail($role);
        }
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->session(['Auth' => $state]);
        $this->post('/members/impersonate/' . self::TEST_MEMBER_BRYCE_ID);
        $this->assertResponseCode(403);
        $this->assertSession(null, 'Impersonation');
    }

    public function testPasswordResetTokenCanOnlyBeConsumedOnce(): void
    {
        $member = $this->admin();
        $member->password_token = bin2hex(random_bytes(32));
        $member->password_token_expires_on = DateTime::now()->addHours(1);
        $this->getTableLocator()->get('Members')->saveOrFail($member);
        $oldEpoch = $member->auth_version;
        $service = new MemberAuthenticationService();
        $this->assertTrue($service->resetPassword($member, 'FirstReplacementPassword123!'));
        $this->assertFalse($service->resetPassword($member, 'SecondReplacementPassword123!'));
        $fresh = $this->getTableLocator()->get('Members')->get($member->id);
        $this->assertNotSame($oldEpoch, $fresh->auth_version);
        $this->assertNull($fresh->password_token);
        $this->assertTrue((new DefaultPasswordHasher())
            ->check('FirstReplacementPassword123!', $fresh->password));
    }

    public function testSelfRevokeAllEndsCurrentSession(): void
    {
        $member = $this->admin();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
        $this->post('/members/revoke-sessions/' . $member->id);
        $this->assertRedirectContains('/members/login');
        $this->assertHeader('X-KMP-Offline-Clear', '1');
        $this->assertSession(null, 'Auth');
        $this->assertNotSame($member->auth_version, $this->getTableLocator()->get('Members')->get($member->id)->auth_version);
    }

    public function testExpiredPinEnrollmentRequiresPasswordLoginAgain(): void
    {
        $member = $this->admin();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->session([
            'Auth' => MemberSessionState::fromMember($member),
            'QuickLoginSetup' => [
                'member_id' => (int)$member->id,
                'tenant_id' => MemberSessionState::tenantId(),
                'auth_version' => (string)$member->auth_version,
                'created_at' => time() - 601,
                'device_id' => 'expired-enrollment-123456',
            ],
        ]);
        $this->post('/members/setup-quick-login-pin', ['quick_login_pin' => '438259', 'quick_login_pin_confirm' => '438259']);
        $this->assertSession(null, 'QuickLoginSetup');
        $this->assertFalse($this->getTableLocator()->get('MemberQuickLoginDevices')
            ->exists(['device_id' => 'expired-enrollment-123456']));
    }
}
