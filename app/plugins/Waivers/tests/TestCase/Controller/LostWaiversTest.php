<?php
declare(strict_types=1);

namespace Waivers\Test\TestCase\Controller;

use AddRecordLostWaiversPermission;
use App\Model\Entity\Gathering;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Cache\Cache;
use Migrations\Db\Adapter\PostgresAdapter;
use Waivers\Services\WaiverDashboardService;
use Waivers\Services\WaiverFileService;
use Waivers\Services\WaiverStateService;

/** @uses \Waivers\Controller\GatheringWaiversController */
class LostWaiversTest extends HttpIntegrationTestCase
{
    private Gathering $gathering;
    private int $waiverTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();

        // The data-only test seed replaces permissions after schema migration.
        require_once ROOT . '/plugins/Waivers/config/Migrations/20261027120100_AddRecordLostWaiversPermission.php';
        $migration = new AddRecordLostWaiversPermission();
        $migration->setAdapter(new PostgresAdapter(['connection' => $this->connection]));
        $migration->up();
        $migration->up();
        Cache::clearGroup('security');

        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Lost waiver activity']));
        $types = $this->getTableLocator()->get('Waivers.WaiverTypes');
        $type = $types->saveOrFail($types->newEntity([
            'name' => 'Loss regression waiver', 'is_active' => true,
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
        ]));
        $this->waiverTypeId = (int)$type->id;
        $requirements = $this->getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $requirements->saveOrFail($requirements->newEntity([
            'gathering_activity_id' => $activity->id, 'waiver_type_id' => $type->id,
        ]));
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gatheringType = $this->getTableLocator()->get('GatheringTypes')->find()->firstOrFail();
        $this->gathering = $gatherings->saveOrFail($gatherings->newEntity([
            'name' => 'Lost waiver regression gathering', 'branch_id' => self::TEST_BRANCH_LOCAL_ID,
            'gathering_type_id' => $gatheringType->id, 'description' => 'Loss report regression',
            'start_date' => '2026-01-01 10:00:00', 'end_date' => '2026-01-01 12:00:00',
            'location' => 'Test location', 'created_by' => self::ADMIN_MEMBER_ID,
            'gathering_activities' => ['_ids' => [$activity->id]],
        ], ['associated' => ['GatheringActivities'], 'accessibleFields' => ['gathering_activities' => true]]));
    }

    private function postLoss(array $data = []): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']]);
        $this->post('/waivers/gathering-waivers/record-lost', $data + [
            'gathering_id' => $this->gathering->id, 'waiver_type_id' => $this->waiverTypeId,
            'notes' => 'Reported by the local officer by email on October 25, 2026.',
        ]);
    }

    private function deputy(): void
    {
        $roles = $this->getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity(['name' => 'Test Loss Reporting']));
        $permission = $this->getTableLocator()->get('Permissions')->find()
            ->where(['name' => 'Can Record Lost Waivers'])->firstOrFail();
        $this->connection->execute(
            'INSERT INTO roles_permissions (role_id, permission_id, created, created_by) VALUES (?, ?, NOW(), ?)',
            [$role->id, $permission->id, self::ADMIN_MEMBER_ID],
        );
        $staff = $this->getTableLocator()->get('GatheringStaff');
        $staff->saveOrFail($staff->newEntity([
            'gathering_id' => $this->gathering->id, 'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'role' => 'Steward', 'is_steward' => true, 'email' => 'agatha@ampdemo.com',
        ]));
        $this->connection->execute(
            'INSERT INTO member_roles
                (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type, created, modified)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [self::TEST_MEMBER_AGATHA_ID, $role->id, self::TEST_BRANCH_LOCAL_ID,
                '2020-01-01', '2100-01-01', self::ADMIN_MEMBER_ID, 'Direct Grant'],
        );
        Cache::clearGroup('security');
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
    }

    public function testPermissionRegistrationDoesNotGrantAccessToAnyRole(): void
    {
        $permission = $this->getTableLocator()->get('Permissions')->find()
            ->where(['name' => 'Can Record Lost Waivers'])->firstOrFail();
        $this->assertSame(0, $this->getTableLocator()->get('RolesPermissions')->find()
            ->where(['permission_id' => $permission->id])->count());
        $this->assertTrue($this->getTableLocator()->get('PermissionPolicies')->exists([
            'permission_id' => $permission->id,
            'policy_class' => 'Waivers\\Policy\\GatheringWaiverPolicy',
            'policy_method' => 'canRecordLost',
        ]));
    }

    public function testDeputyRecordsNotesAndActorWithoutClosingOrCreatingUpload(): void
    {
        $this->deputy();
        $this->postLoss(['created_by' => self::ADMIN_MEMBER_ID]);
        $this->assertResponseOk();
        $data = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($data['success']);
        $loss = $this->getTableLocator()->get('Waivers.GatheringWaiverLosses')->find()
            ->where(['gathering_id' => $this->gathering->id])->firstOrFail();
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $loss->created_by);
        $this->assertStringContainsString('October 25, 2026', $loss->notes);
        $this->assertNotNull($loss->created);
        $this->assertSame(0, $this->getTableLocator()->get('Waivers.GatheringWaivers')->find()
            ->where(['gathering_id' => $this->gathering->id])->count());
        $this->assertFalse($this->getTableLocator()->get('Waivers.GatheringWaiverClosures')
            ->isGatheringClosed((int)$this->gathering->id));
    }

    public function testLocalOfficerCannotForgeLossSubmission(): void
    {
        $staff = $this->getTableLocator()->get('GatheringStaff');
        $staff->saveOrFail($staff->newEntity([
            'gathering_id' => $this->gathering->id, 'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'role' => 'Steward', 'is_steward' => true, 'email' => 'agatha@ampdemo.com',
        ]));
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/waivers/gathering-waivers/upload?gathering_id=' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertResponseNotContains('id="mode-lost"');
        $this->postLoss(['canRecordLost' => true]);
        $this->assertResponseCode(403);
        $this->assertSame(0, $this->getTableLocator()->get('Waivers.GatheringWaiverLosses')->find()->count());
    }

    public function testDeputyPermissionDoesNotCrossBranchScope(): void
    {
        $this->deputy();
        $this->connection->execute('UPDATE gatherings SET branch_id = ? WHERE id = ?', [
            self::TEST_BRANCH_STARGATE_ID, $this->gathering->id,
        ]);
        $this->postLoss();
        $this->assertResponseCode(403);
    }

    public function testNotesAreRequiredAndDuplicateDoesNotReplaceOriginal(): void
    {
        $this->postLoss(['notes' => '   ']);
        $this->assertResponseCode(422);
        $this->postLoss();
        $this->assertResponseOk();
        $this->postLoss(['notes' => 'Replacement notes']);
        $this->assertResponseCode(422);
        $losses = $this->getTableLocator()->get('Waivers.GatheringWaiverLosses')->find();
        $this->assertSame(1, $losses->count());
        $this->assertStringContainsString('October 25', $losses->firstOrFail()->notes);
    }

    public function testClosedCollectionRejectsLoss(): void
    {
        (new WaiverStateService())->close((int)$this->gathering->id, self::ADMIN_MEMBER_ID);
        $this->postLoss();
        $this->assertResponseCode(422);
        $this->assertStringContainsString('closed', (string)$this->_response->getBody());
    }

    public function testUnrelatedWaiverTypeIsRejected(): void
    {
        $types = $this->getTableLocator()->get('Waivers.WaiverTypes');
        $otherType = $types->saveOrFail($types->newEntity([
            'name' => 'Unrelated loss waiver', 'is_active' => true,
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
        ]));
        $this->postLoss(['waiver_type_id' => $otherType->id]);
        $this->assertResponseCode(422);
    }

    public function testLossDoesNotSatisfyComplianceOrInflateCountsAndPreservesPartialUpload(): void
    {
        $this->postLoss();
        $fileService = new WaiverFileService();
        $type = $this->getTableLocator()->get('Waivers.WaiverTypes')->get($this->waiverTypeId);
        $summary = $fileService->getWaiverStatusSummary((int)$this->gathering->id, [$type])[0];
        $this->assertTrue($summary['loss_recorded']);
        $this->assertSame(0, $summary['uploaded_count']);
        $this->assertSame([], $summary['attestation_reasons']);
        $buckets = (new WaiverDashboardService())->getGatheringsWithIncompleteWaivers([self::TEST_BRANCH_LOCAL_ID], 30);
        $this->assertContains((int)$this->gathering->id, array_map(static fn($g) => (int)$g->id, $buckets['missing']));

        $waivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $upload = $waivers->saveOrFail($waivers->newEntity([
            'gathering_id' => $this->gathering->id, 'waiver_type_id' => $this->waiverTypeId,
            'is_exemption' => false, 'status' => 'active', 'retention_date' => '2033-01-01',
        ]));
        $this->assertNotNull($waivers->get($upload->id));
        $this->assertSame(1, $fileService->getWaiverStatusSummary((int)$this->gathering->id, [$type])[0]['uploaded_count']);
    }

    public function testLossRemainsVisibleAfterClosureAndNotesAreEscaped(): void
    {
        $this->postLoss(['notes' => '<script>alert("lost")</script> Reported by local officer October 25.']);
        (new WaiverStateService())->close((int)$this->gathering->id, self::ADMIN_MEMBER_ID);
        $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Waivers Lost');
        $this->assertResponseContains('&lt;script&gt;');
        $this->assertResponseNotContains('<script>alert("lost")');
        $loss = $this->getTableLocator()->get('Waivers.GatheringWaiverLosses')->find()->firstOrFail();
        $this->get('/waivers/gathering-waivers/view-loss/' . $loss->id);
        $this->assertResponseOk();
        $this->assertResponseContains('October 25');
        $this->assertResponseContains('Recorded');
    }

    public function testRecordingLossPreservesExistingUploads(): void
    {
        $waivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $upload = $waivers->saveOrFail($waivers->newEntity([
            'gathering_id' => $this->gathering->id, 'waiver_type_id' => $this->waiverTypeId,
            'is_exemption' => false, 'status' => 'active', 'retention_date' => '2033-01-01',
        ]));
        $original = $waivers->get($upload->id)->toArray();
        $this->postLoss();
        $this->assertResponseOk();
        $this->assertEquals($original, $waivers->get($upload->id)->toArray());
    }

    public function testAttestedRequirementCannotBeReportedLost(): void
    {
        $waivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waivers->saveOrFail($waivers->newEntity([
            'gathering_id' => $this->gathering->id, 'waiver_type_id' => $this->waiverTypeId,
            'is_exemption' => true, 'status' => 'active', 'retention_date' => '2033-01-01',
            'exemption_reason' => 'No participants',
        ]));
        $this->postLoss();
        $this->assertResponseCode(422);
        $this->assertStringContainsString('not needed', (string)$this->_response->getBody());
    }

    public function testDeputySeesLossOptionWithoutExemptionReasonsOnBothWizards(): void
    {
        $this->deputy();
        foreach (
            [
            '/waivers/gathering-waivers/upload?gathering_id=' . $this->gathering->id,
            '/waivers/gathering-waivers/mobile-upload/' . $this->gathering->id,
            ] as $url
        ) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertResponseContains('change->waiver-upload-wizard#setModeLost');
            $this->assertResponseContains('Notes (Required)');
        }
    }

    public function testGetCannotRecordLoss(): void
    {
        $this->get('/waivers/gathering-waivers/record-lost');
        $this->assertResponseCode(405);
    }
}
