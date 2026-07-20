<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\I18n\DateTime;

final class MembersQuickLoginSetupTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testSetupQuickLoginPinRedirectsWhenNotPending(): void
    {
        $this->get('/members/setup-quick-login-pin');

        $this->assertRedirectContains('/members/profile');
    }

    public function testSetupQuickLoginPinSavesDevicePinAndClearsPendingState(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $member = $membersTable->find()
            ->where(['email_address IN' => ['admin@amp.ansteorra.org', 'admin@test.com']])
            ->firstOrFail();

        $deviceId = 'test-device-quick-login-1234';
        $this->session([
            'Auth' => $member,
            'QuickLoginSetup' => [
                'member_id' => (int)$member->id,
                'device_id' => $deviceId,
                'email_address' => (string)$member->email_address,
                'redirect_target' => '/members/profile',
            ],
        ]);
        $this->configRequest([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
                'CloudFront-Viewer-Country' => 'US',
            ],
        ]);

        $this->post('/members/setup-quick-login-pin', [
            'quick_login_pin' => '1234',
            'quick_login_pin_confirm' => '1234',
        ]);

        $this->assertRedirectContains('/members/profile');
        $this->assertSession(null, 'QuickLoginSetup');

        $quickLoginDevices = $this->getTableLocator()->get('MemberQuickLoginDevices');
        $device = $quickLoginDevices->find()
            ->where([
                'member_id' => (int)$member->id,
                'device_id' => $deviceId,
            ])
            ->first();

        $this->assertNotNull($device);
        $this->assertSame(0, (int)$device->failed_attempts);
        $this->assertTrue((new DefaultPasswordHasher())->check('1234', (string)$device->pin_hash));
        $this->assertSame('Windows 10/11', (string)$device->configured_os);
        $this->assertSame('Google Chrome', (string)$device->configured_browser);
        $this->assertSame('US', (string)$device->configured_location_hint);
        $this->assertStringContainsString('Windows NT 10.0', (string)$device->configured_user_agent);
        $this->assertSame('US', (string)$device->last_used_location_hint);
    }

    public function testSetupQuickLoginPinUpsertsByDeviceIdAcrossMembers(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $member = $membersTable->find()
            ->where(['email_address IN' => ['admin@amp.ansteorra.org', 'admin@test.com']])
            ->firstOrFail();
        $otherMember = $membersTable->find()
            ->where(['Members.id !=' => (int)$member->id])
            ->select(['id'])
            ->firstOrFail();

        $quickLoginDevices = $this->getTableLocator()->get('MemberQuickLoginDevices');
        $deviceId = 'shared-device-upsert-1234';
        $existingDevice = $quickLoginDevices->newEntity([
            'member_id' => (int)$otherMember->id,
            'device_id' => $deviceId,
            'pin_hash' => (new DefaultPasswordHasher())->hash('9876'),
            'failed_attempts' => 2,
            'last_failed_login' => null,
            'last_used' => null,
        ]);
        $this->assertNotFalse($quickLoginDevices->save($existingDevice));

        $this->session([
            'Auth' => $member,
            'QuickLoginSetup' => [
                'member_id' => (int)$member->id,
                'device_id' => $deviceId,
                'email_address' => (string)$member->email_address,
                'redirect_target' => '/members/profile',
            ],
        ]);

        $this->post('/members/setup-quick-login-pin', [
            'quick_login_pin' => '1234',
            'quick_login_pin_confirm' => '1234',
        ]);

        $this->assertRedirectContains('/members/profile');

        $recordsForDevice = $quickLoginDevices->find()
            ->where(['device_id' => $deviceId])
            ->all()
            ->toList();
        $this->assertCount(1, $recordsForDevice);
        $this->assertSame((int)$member->id, (int)$recordsForDevice[0]->member_id);
        $this->assertSame(0, (int)$recordsForDevice[0]->failed_attempts);
        $this->assertTrue((new DefaultPasswordHasher())->check('1234', (string)$recordsForDevice[0]->pin_hash));
    }

    public function testProfileShowsQuickLoginDevicesTabWhenDevicesExist(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $member = $membersTable->find()
            ->where(['email_address IN' => ['admin@amp.ansteorra.org', 'admin@test.com']])
            ->firstOrFail();

        $quickLoginDevices = $this->getTableLocator()->get('MemberQuickLoginDevices');
        $device = $quickLoginDevices->newEntity([
            'member_id' => (int)$member->id,
            'device_id' => 'profile-tab-device-1234',
            'pin_hash' => (new DefaultPasswordHasher())->hash('1234'),
            'configured_os' => 'Android',
            'configured_browser' => 'Google Chrome',
            'configured_location_hint' => 'US',
            'last_used_location_hint' => 'US',
            'last_used' => new DateTime(),
        ]);
        $this->assertNotFalse($quickLoginDevices->save($device));

        // Profile now redirects to view, so test the view endpoint directly
        $this->get('/members/view/' . $member->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Quick login devices');
        $this->assertResponseContains('Android / Google Chrome');
    }

    public function testRemoveQuickLoginDeviceDeletesDeviceForCurrentMember(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $member = $membersTable->find()
            ->where(['email_address IN' => ['admin@amp.ansteorra.org', 'admin@test.com']])
            ->firstOrFail();

        $quickLoginDevices = $this->getTableLocator()->get('MemberQuickLoginDevices');
        $device = $quickLoginDevices->newEntity([
            'member_id' => (int)$member->id,
            'device_id' => 'remove-device-quick-login-1234',
            'pin_hash' => (new DefaultPasswordHasher())->hash('1234'),
            'configured_os' => 'macOS',
            'configured_browser' => 'Safari',
        ]);
        $saved = $quickLoginDevices->save($device);
        $this->assertNotFalse($saved);

        $this->post('/members/remove-quick-login-device/' . $saved->id);

        $this->assertRedirectContains('/members/profile');
        $remaining = $quickLoginDevices->find()
            ->where(['id' => (int)$saved->id])
            ->count();
        $this->assertSame(0, $remaining);
    }
}
