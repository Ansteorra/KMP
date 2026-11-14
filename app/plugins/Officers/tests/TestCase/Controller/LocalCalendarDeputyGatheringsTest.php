<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Controller;

use App\Model\Entity\Gathering;
use App\Model\Entity\Member;
use App\Policy\GatheringPolicy;
use App\Services\ServiceResult;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use InitLocalCalendarDeputySeed;
use PHPUnit\Framework\Attributes\DataProvider;
use Waivers\Policy\GatheringWaiverPolicy;
use Waivers\Services\WaiverFileService;

require_once dirname(__DIR__, 3) . '/config/Seeds/InitLocalCalendarDeputySeed.php';

class LocalCalendarDeputyGatheringsTest extends HttpIntegrationTestCase
{
    private bool $cacheWasEnabled;

    private mixed $warrantSetting;

    private int $typeId;

    private int $childId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheWasEnabled = Cache::enabled();
        Cache::disable();
        $this->warrantSetting = Configure::read('KMP.RequireActiveWarrantForSecurity');
        Configure::write('KMP.RequireActiveWarrantForSecurity', 'yes');
        (new InitLocalCalendarDeputySeed())->run();
        $tables = $this->getTableLocator();
        $type = $tables->get('GatheringTypes')->saveOrFail($tables->get('GatheringTypes')->newEntity([
            'name' => 'Local deputy empty type', 'description' => 'No template activities', 'clonable' => false,
        ]));
        $this->typeId = (int)$type->id;
        $child = $tables->get('Branches')->saveOrFail($tables->get('Branches')->newEntity([
            'name' => 'Local deputy dependent group', 'type' => 'Local Group',
            'parent_id' => self::TEST_BRANCH_STARGATE_ID, 'location' => 'Synthetic scope test',
        ], ['accessibleFields' => ['parent_id' => true]]));
        $this->childId = (int)$child->id;
        $members = $tables->get('Members');
        $member = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $member->status = Member::STATUS_VERIFIED_MEMBERSHIP;
        $member->membership_expires_on = DateTime::now()->addYears(1);
        $member->phone_number = null;
        $members->saveOrFail($member);
        $this->assertFalse($members->get($member->id)->warrantable);
        $tables->get('MemberRoles')->updateAll([
            'revoker_id' => self::ADMIN_MEMBER_ID, 'expires_on' => DateTime::now()->subDays(1),
        ], ['member_id' => $member->id]);
        $tables->get('Warrants')->updateAll([
            'expires_on' => DateTime::now()->subDays(1),
        ], ['member_id' => $member->id]);
        $role = $tables->get('Roles')->find()->where(['name' => InitLocalCalendarDeputySeed::ROLE])->firstOrFail();
        $assignment = $tables->get('MemberRoles')->newEmptyEntity();
        $assignment->patch([
            'member_id' => $member->id, 'role_id' => $role->id, 'branch_id' => self::TEST_BRANCH_STARGATE_ID,
            'start_on' => DateTime::now()->subDays(1), 'expires_on' => null,
            'created_by' => self::ADMIN_MEMBER_ID, 'approver_id' => self::ADMIN_MEMBER_ID,
        ], ['guard' => false]);
        $tables->get('MemberRoles')->saveOrFail($assignment);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->warrantSetting === null) {
                Configure::delete('KMP.RequireActiveWarrantForSecurity');
            } else {
                Configure::write('KMP.RequireActiveWarrantForSecurity', $this->warrantSetting);
            }
            if ($this->cacheWasEnabled) {
                Cache::enable();
            }
        } finally {
            parent::tearDown();
        }
    }

    public static function branchScopes(): array
    {
        return [['own', true], ['child', true], ['sibling', false], ['kingdom', false]];
    }

    #[DataProvider('branchScopes')]
    public function testCreateAndEditRespectScopeWithoutAnyPersonalWarrant(string $scope, bool $allowed): void
    {
        $branchId = $this->branch($scope);
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $event = $this->event($branchId);
        $actor = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertSame($allowed, (new GatheringPolicy())->canAdd($actor, $event));
        $this->assertSame($allowed, (new GatheringPolicy())->canEdit($actor, $event));
        $this->post('/gatherings/add', $this->eventData('Scoped creation', $branchId));
        $this->assertSame($allowed, $gatherings->exists(['name' => 'Scoped creation']));
        $this->post('/gatherings/edit/' . $event->id, $this->eventData('Scoped edit', $branchId));
        $this->assertSame($allowed ? 'Scoped edit' : $event->name, $gatherings->get($event->id)->name);
    }

    public function testForgedBranchMoveAndPublicationFieldsCannotEscapeScopeOrPublish(): void
    {
        $event = $this->event(self::TEST_BRANCH_STARGATE_ID);
        $this->post('/gatherings/edit/' . $event->id, $this->eventData('Moved outside scope', self::KINGDOM_BRANCH_ID));
        $table = $this->getTableLocator()->get('Gatherings');
        $this->assertSame(self::TEST_BRANCH_STARGATE_ID, $table->get($event->id)->branch_id);
        $this->post('/gatherings/edit/' . $event->id, $this->eventData('Legitimate edit', $event->branch_id) + [
            'published' => true, 'published_by' => self::TEST_MEMBER_AGATHA_ID, 'published_on' => DateTime::now(),
        ]);
        $this->assertFalse($table->get($event->id)->published);
        $this->assertNull($table->get($event->id)->published_by);
        $actor = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertFalse((new GatheringPolicy())->canPublish($actor, $event));
        $this->assertFalse((new GatheringPolicy())->canDelete($actor, $event));
        $this->post('/gatherings/publish/' . $event->id . '?publish=true');
        $this->assertFalse($table->get($event->id)->published);
        $table->updateAll(['published' => true, 'published_by' => self::ADMIN_MEMBER_ID], ['id' => $event->id]);
        $this->post('/gatherings/publish/' . $event->id . '?publish=false');
        $this->assertTrue($table->get($event->id)->published);
    }

    public function testAnEventCanMoveBetweenAuthorizedGroups(): void
    {
        $event = $this->event(self::TEST_BRANCH_STARGATE_ID);
        $this->post('/gatherings/edit/' . $event->id, $this->eventData('Moved within scope', $this->childId));
        $this->assertRedirect();
        $this->assertSame($this->childId, $this->getTableLocator()->get('Gatherings')->get($event->id)->branch_id);
    }

    public function testNormalOfficerHiringGrantsScopedRoleWithoutRequestingAWarrant(): void
    {
        $tables = $this->getTableLocator();
        $office = $tables->get('Officers.Offices')->find()->where(['name' => InitLocalCalendarDeputySeed::ROLE])
            ->firstOrFail();
        $tables->get('MemberRoles')->updateAll(['expires_on' => DateTime::now()->subDays(1)], [
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
        ]);
        $warrantCount = $tables->get('Warrants')->find()->where(['member_id' => self::TEST_MEMBER_AGATHA_ID])->count();
        $this->authenticateAsSuperUser();
        $this->post('/officers/officers/assign', [
            'office_id' => $office->id, 'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'branch_id' => self::TEST_BRANCH_STARGATE_ID, 'start_on' => DateTime::now()->format('Y-m-d'),
            'end_on' => DateTime::now()->addMonths(6)->format('Y-m-d'),
            'email_address' => 'local-deputy@example.test', 'deputy_description' => '',
        ]);
        $this->assertRedirect();
        $appointment = $tables->get('Officers.Officers')->find()->where([
            'office_id' => $office->id, 'member_id' => self::TEST_MEMBER_AGATHA_ID,
        ])->firstOrFail();
        $this->assertSame($office->deputy_to_id, $appointment->deputy_to_office_id);
        $this->assertSame(self::TEST_BRANCH_STARGATE_ID, $appointment->deputy_to_branch_id);
        $this->assertNotNull($appointment->granted_member_role_id);
        $assignment = $tables->get('MemberRoles')->get($appointment->granted_member_role_id);
        $this->assertSame($office->grants_role_id, $assignment->role_id);
        $this->assertSame(self::TEST_BRANCH_STARGATE_ID, $assignment->branch_id);
        $this->assertSame($warrantCount, $tables->get('Warrants')->find()->where([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
        ])->count());
        $actor = $tables->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertTrue((new GatheringPolicy())->canEdit($actor, $this->event(self::TEST_BRANCH_STARGATE_ID)));
        $this->assertFalse((new GatheringPolicy())->canEdit($actor, $this->event(self::KINGDOM_BRANCH_ID)));
    }

    #[DataProvider('branchScopes')]
    public function testWaiverUploadsRespectScopeOnDesktopAndMobile(string $scope, bool $allowed): void
    {
        $event = $this->event($this->branch($scope));
        $waiver = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $waiver->gathering = $event;
        $actor = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiverPolicy();
        $this->assertSame($allowed, $policy->canUploadWaivers($actor, $waiver));
        $this->assertFalse($policy->canCloseWaivers($actor, $waiver));
        $this->assertFalse($policy->canDownload($actor, $waiver));
        $this->assertFalse($policy->canRecordLost($actor, $waiver));
        $type = $this->getTableLocator()->get('Waivers.WaiverTypes')->find()->firstOrFail();
        $service = $this->createMock(WaiverFileService::class);
        $service->method('getRequiredWaiverTypes')->willReturn([$type]);
        $service->method('getUploadLimits')->willReturn([
            'maxFileSize' => 10485760, 'maxFileSizeMB' => 10, 'formatted' => '10 MB', 'postMaxSize' => 10485760,
        ]);
        $service->expects($allowed ? $this->once() : $this->never())->method('processMultipleWaiverImages')
            ->willReturn(new ServiceResult(true, data: ['page_count' => 1]));
        $this->mockService(WaiverFileService::class, fn() => $service);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']]);
        $this->post('/waivers/gathering-waivers/upload?gathering_id=' . $event->id, [
            'waiver_type_id' => $type->id, 'waiver_images' => ['synthetic-upload'],
        ]);
        if ($allowed) {
            $this->assertResponseOk();
            $this->assertTrue(json_decode((string)$this->_response->getBody(), true)['success']);
        } else {
            $this->assertResponseCode(403);
        }
        $this->get('/waivers/gathering-waivers/mobile-upload?gathering_id=' . $event->id);
        if ($allowed) {
            $this->assertResponseOk();
            $this->assertResponseContains('waiver-upload');
        } else {
            $this->assertRedirect();
            $this->assertResponseNotContains('data-controller="waiver-upload-wizard"');
        }
    }

    public static function blockedUploads(): array
    {
        return [['closed'], ['cancelled']];
    }

    #[DataProvider('blockedUploads')]
    public function testUploadDoesNotBypassClosureOrCancellation(string $reason): void
    {
        $event = $this->event(self::TEST_BRANCH_STARGATE_ID);
        if ($reason === 'closed') {
            $table = $this->getTableLocator()->get('Waivers.GatheringWaiverClosures');
            $table->saveOrFail($table->newEntity([
                'gathering_id' => $event->id, 'closed_at' => DateTime::now(), 'closed_by' => self::ADMIN_MEMBER_ID,
            ]));
        } else {
            $this->getTableLocator()->get('Gatherings')->updateAll([
                'cancelled_at' => DateTime::now(),
            ], ['id' => $event->id]);
        }
        $service = $this->createMock(WaiverFileService::class);
        $service->expects($this->never())->method('processMultipleWaiverImages');
        $this->mockService(WaiverFileService::class, fn() => $service);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']]);
        $this->post('/waivers/gathering-waivers/upload?gathering_id=' . $event->id, [
            'waiver_type_id' => $this->getTableLocator()->get('Waivers.WaiverTypes')->find()->firstOrFail()->id,
            'waiver_images' => ['synthetic-upload'],
        ]);
        $this->assertResponseCode(403);
    }

    public static function lostEligibility(): array
    {
        return [['membership'], ['revocation']];
    }

    #[DataProvider('lostEligibility')]
    public function testExpiredMembershipOrRevokedRoleRemovesManagementAndUpload(string $reason): void
    {
        $tables = $this->getTableLocator();
        $event = $this->event(self::TEST_BRANCH_STARGATE_ID);
        if ($reason === 'membership') {
            $tables->get('Members')->updateAll(['membership_expires_on' => DateTime::now()->subDays(1)], [
                'id' => self::TEST_MEMBER_AGATHA_ID,
            ]);
        } else {
            $tables->get('MemberRoles')->updateAll(['expires_on' => DateTime::now()->subDays(1)], [
                'member_id' => self::TEST_MEMBER_AGATHA_ID,
            ]);
        }
        $actor = $tables->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertFalse((new GatheringPolicy())->canEdit($actor, $event));
        $waiver = $tables->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $waiver->gathering = $event;
        $this->assertFalse((new GatheringWaiverPolicy())->canUploadWaivers($actor, $waiver));
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/gatherings/edit/' . $event->id, $this->eventData('Denied edit', $event->branch_id));
        $this->assertSame($event->name, $tables->get('Gatherings')->get($event->id)->name);
    }

    private function branch(string $scope): int
    {
        return match ($scope) {
            'own' => self::TEST_BRANCH_STARGATE_ID,
            'child' => $this->childId,
            'sibling' => self::TEST_BRANCH_LOCAL_ID,
            'kingdom' => self::KINGDOM_BRANCH_ID,
        };
    }

    private function eventData(string $name, int $branchId): array
    {
        return [
            'name' => $name, 'branch_id' => $branchId, 'gathering_type_id' => $this->typeId,
            'start_date' => DateTime::now()->addDays(7)->format('Y-m-d') . 'T10:00',
            'end_date' => DateTime::now()->addDays(7)->format('Y-m-d') . 'T18:00',
            'location' => 'Local deputy regression fixture', 'timezone' => 'UTC',
        ];
    }

    private function event(int $branchId): Gathering
    {
        $table = $this->getTableLocator()->get('Gatherings');

        return $table->saveOrFail($table->newEntity($this->eventData('Local deputy event', $branchId) + [
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
    }
}
