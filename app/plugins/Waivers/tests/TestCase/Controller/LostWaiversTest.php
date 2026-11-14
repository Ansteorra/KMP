<?php
declare(strict_types=1);

namespace Waivers\Test\TestCase\Controller;

use AddRecordLostWaiversPermission;
use App\Model\Entity\Gathering;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use DOMDocument;
use DOMXPath;
use Migrations\Db\Adapter\PostgresAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
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

    private function deputy(bool $summaryAccess = false): void
    {
        $roles = $this->getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity(['name' => 'Test Loss Reporting']));
        $permission = $this->getTableLocator()->get('Permissions')->find()
            ->where(['name' => 'Can Record Lost Waivers'])->firstOrFail();
        $this->connection->execute(
            'INSERT INTO roles_permissions (role_id, permission_id, created, created_by) VALUES (?, ?, NOW(), ?)',
            [$role->id, $permission->id, self::ADMIN_MEMBER_ID],
        );
        if ($summaryAccess) {
            $management = $this->getTableLocator()->get('Permissions')->find()
                ->where(['name' => 'Waiver Management'])->firstOrFail();
            $this->connection->execute(
                'INSERT INTO roles_permissions (role_id, permission_id, created, created_by) VALUES (?, ?, NOW(), ?)',
                [$role->id, $management->id, self::ADMIN_MEMBER_ID],
            );
        }
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
        $this->postLoss(['notes' => str_repeat('x', 10001)]);
        $this->assertResponseCode(422);
        $this->postLoss();
        $this->assertResponseOk();
        $this->postLoss(['notes' => 'Replacement notes']);
        $this->assertResponseCode(422);
        $losses = $this->getTableLocator()->get('Waivers.GatheringWaiverLosses')->find();
        $this->assertSame(1, $losses->count());
        $this->assertStringContainsString('October 25', $losses->firstOrFail()->notes);
    }

    public function testClosedCollectionAllowsLossAndPreservesClosure(): void
    {
        (new WaiverStateService())->close((int)$this->gathering->id, self::ADMIN_MEMBER_ID);
        $closures = $this->getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $original = $closures->getClosureForGathering((int)$this->gathering->id)->toArray();
        $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertResponseContains('Report/Mark Lost');
        $this->postLoss();
        $this->assertResponseOk();
        $this->assertEquals($original, $closures->getClosureForGathering((int)$this->gathering->id)->toArray());
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

    public function testLossSatisfiesItsRequirementWithoutInflatingCountsAndPreservesPartialUpload(): void
    {
        $this->postLoss();
        $fileService = new WaiverFileService();
        $type = $this->getTableLocator()->get('Waivers.WaiverTypes')->get($this->waiverTypeId);
        $summary = $fileService->getWaiverStatusSummary((int)$this->gathering->id, [$type])[0];
        $this->assertTrue($summary['loss_recorded']);
        $this->assertSame(0, $summary['uploaded_count']);
        $this->assertSame([], $summary['attestation_reasons']);
        $buckets = (new WaiverDashboardService())->getGatheringsWithIncompleteWaivers([self::TEST_BRANCH_LOCAL_ID], 30);
        foreach ($buckets['missing'] as $gathering) {
            if ($gathering->id === $this->gathering->id) {
                $this->assertNotContains($type->name, $gathering->missing_waiver_names);
            }
        }

        $waivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $upload = $waivers->saveOrFail($waivers->newEntity([
            'gathering_id' => $this->gathering->id, 'waiver_type_id' => $this->waiverTypeId,
            'is_exemption' => false, 'status' => 'active', 'retention_date' => '2033-01-01',
        ]));
        $this->assertNotNull($waivers->get($upload->id));
        $this->assertSame(1, $fileService->getWaiverStatusSummary((int)$this->gathering->id, [$type])[0]['uploaded_count']);
    }

    public function testAuthorizedCloseExecutesWorkflowWithoutPriorReadyMarker(): void
    {
        $this->postLoss();
        $this->assertResponseOk();
        $closures = $this->getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $this->assertNull($closures->getClosureForGathering((int)$this->gathering->id));

        $this->post('/waivers/gathering-waivers/close/' . $this->gathering->id);
        $this->assertRedirect();
        $this->assertTrue($closures->isGatheringClosed((int)$this->gathering->id));
        $dashboard = new WaiverDashboardService();
        $branches = [self::TEST_BRANCH_LOCAL_ID];
        $this->assertNotContains((int)$this->gathering->id, array_map(
            static fn($g) => (int)$g->id,
            $dashboard->getGatheringsWithIncompleteWaivers($branches, 30)['missing'],
        ));
        $this->assertNotContains((int)$this->gathering->id, array_map(
            static fn($g) => (int)$g->id,
            $dashboard->getNeedingWaiversData($branches, [])['gatherings'],
        ));
    }

    public function testLossRemainsVisibleAfterClosureAndNotesAreEscaped(): void
    {
        $this->postLoss(['notes' => '<script>alert("lost")</script> Reported by local officer October 25.']);
        (new WaiverStateService())->close((int)$this->gathering->id, self::ADMIN_MEMBER_ID);
        $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertResponseNotContains('Loss Report History');
        $this->assertResponseNotContains('id="waiver-loss-reports-heading"');
        $this->assertResponseContains('&lt;script&gt;');
        $this->assertResponseNotContains('<script>alert("lost")');
        $loss = $this->getTableLocator()->get('Waivers.GatheringWaiverLosses')->find()->firstOrFail();
        $this->get('/waivers/gathering-waivers/view-loss/' . $loss->id);
        $this->assertRedirectContains('/waivers/gathering-waivers/view?');
        $this->get('/waivers/gathering-waivers/view?gathering_id=' . $this->gathering->id
            . '&waiver_type_id=' . $this->waiverTypeId);
        $this->assertResponseOk();
        $this->assertResponseContains('October 25');
        $this->assertResponseContains('Recorded');
    }

    public static function reportedStates(): array
    {
        return ['uploaded' => [false, false], 'declined' => [true, false], 'exempt' => [false, true]];
    }

    #[DataProvider('reportedStates')]
    public function testRecordingLossPreservesExistingFilesAndRecords(bool $declined, bool $exempt): void
    {
        $waivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $upload = $waivers->saveOrFail($waivers->newEntity([
            'gathering_id' => $this->gathering->id, 'waiver_type_id' => $this->waiverTypeId,
            'is_exemption' => $exempt, 'status' => 'active',
            'exemption_reason' => $exempt ? 'No participants' : null,
            'declined_at' => $declined ? '2026-01-02 10:00:00' : null,
            'declined_by' => $declined ? self::ADMIN_MEMBER_ID : null,
            'decline_reason' => $declined ? 'Illegible signature' : null,
            'retention_date' => '2033-01-01',
        ]));
        $documents = $this->getTableLocator()->get('Documents');
        $relativePath = 'loss-regression-' . uniqid() . '.pdf';
        $content = '%PDF-1.4 loss regression paper trail';
        $document = $documents->saveOrFail($documents->newEntity([
            'entity_type' => 'Waivers.GatheringWaivers', 'entity_id' => $upload->id,
            'original_filename' => 'waiver.pdf', 'stored_filename' => $relativePath,
            'file_path' => $relativePath, 'mime_type' => 'application/pdf',
            'file_size' => strlen($content), 'checksum' => hash('sha256', $content),
            'storage_adapter' => 'local', 'uploaded_by' => self::ADMIN_MEMBER_ID,
        ]));
        $upload->document_id = $document->id;
        $waivers->saveOrFail($upload);
        $filePath = rtrim((string)Configure::read('Documents.storage.local.path', WWW_ROOT . '../images/uploaded'), '/')
            . '/' . $relativePath;
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, $content);
        try {
            $original = $waivers->get($upload->id)->toArray();
            $originalDocument = $documents->get($document->id)->toArray();
            $this->postLoss();
            $this->assertResponseOk();
            $this->assertEquals($original, $waivers->get($upload->id)->toArray());
            $this->assertEquals($originalDocument, $documents->get($document->id)->toArray());
            $this->assertSame($content, file_get_contents($filePath));
            $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
            $this->assertResponseOk();
            $this->assertGridShowsLost();
            $this->get('/waivers/gathering-waivers/view/' . $upload->id);
            $this->assertResponseOk();
            $this->assertResponseContains('Waiver Details — Lost');
            $this->assertResponseContains('Existing uploaded files remain available for the paper trail.');
            $this->assertResponseContains('/waivers/gathering-waivers/inline-pdf/' . $upload->id);
            $this->assertResponseContains('/waivers/gathering-waivers/download/' . $upload->id);
            $this->get('/waivers/gathering-waivers/download/' . $upload->id);
            $this->assertResponseOk();
            $this->assertContentType('application/pdf');
            $this->assertSame($content, (string)$this->_response->getBody());
            $losses = $this->getTableLocator()->get('Waivers.GatheringWaiverLosses');
            $loss = $losses->find()->where(['gathering_id' => $this->gathering->id])->firstOrFail();
            $this->post('/waivers/gathering-waivers/reverse-loss/' . $loss->id, [
                'reversed_by' => self::TEST_MEMBER_AGATHA_ID,
            ]);
            $this->assertRedirectContains('/waivers/gathering-waivers/view?');
            $this->assertSame(self::ADMIN_MEMBER_ID, $losses->get($loss->id)->reversed_by);
            $this->assertEquals($original, $waivers->get($upload->id)->toArray());
            $this->assertEquals($originalDocument, $documents->get($document->id)->toArray());
            $this->get('/waivers/gathering-waivers/view/' . $upload->id);
            $this->assertResponseOk();
            $this->assertResponseNotContains('Waiver Details — Lost');
            $this->assertResponseContains('Lost status reversed');
            $this->assertResponseContains('/waivers/gathering-waivers/inline-pdf/' . $upload->id);
            $this->assertResponseContains('/waivers/gathering-waivers/download/' . $upload->id);
            $this->get('/waivers/gathering-waivers/download/' . $upload->id);
            $this->assertResponseOk();
            $this->assertSame($content, (string)$this->_response->getBody());
            $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
            $this->assertResponseOk();
            $this->assertSummaryUploadedCount($declined || $exempt ? 0 : 1);
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function testAttestedRequirementCanBeReportedLostWithoutChangingExemption(): void
    {
        $waivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $exemption = $waivers->saveOrFail($waivers->newEntity([
            'gathering_id' => $this->gathering->id, 'waiver_type_id' => $this->waiverTypeId,
            'is_exemption' => true, 'status' => 'active', 'retention_date' => '2033-01-01',
            'exemption_reason' => 'No participants',
        ]));
        $original = $waivers->get($exemption->id)->toArray();
        $this->postLoss();
        $this->assertResponseOk();
        $this->assertEquals($original, $waivers->get($exemption->id)->toArray());
    }

    public function testDeputySeesLossActionOnlyOnGatheringSummary(): void
    {
        $this->deputy(true);
        $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Report/Mark Lost');
        $this->assertResponseContains('aria-labelledby="report-lost-waiver-title"');
        $this->assertResponseContains('Notes (Required)');
        $this->assertResponseContains('Loss regression waiver');
        $this->assertLossActionsAreInGrid();
        foreach (
            [
            '/waivers/gathering-waivers/upload?gathering_id=' . $this->gathering->id,
            '/waivers/gathering-waivers/mobile-upload/' . $this->gathering->id,
            ] as $url
        ) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertResponseNotContains('change->waiver-upload-wizard#setModeLost');
            $this->assertResponseNotContains('record-lost-url');
            $this->assertResponseNotContains('Report/Mark Lost');
        }
    }

    private function assertLossActionsAreInGrid(): void
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML((string)$this->_response->getBody());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $buttons = '//button[@data-bs-target="#report-lost-waiver-modal"]';
        $gridButtons = '//td[contains(@class, "actions")]' . $buttons;
        $this->assertGreaterThan(0, $xpath->query($buttons)->length);
        $this->assertSame($xpath->query($buttons)->length, $xpath->query($gridButtons)->length);
        $this->assertSame(0, $xpath->query('//*[@id="waiver-summary-actions"]')->length);
        $this->assertGreaterThan(0, $xpath->query(
            $gridButtons . '[@data-waiver-loss-type-id="' . $this->waiverTypeId . '"]',
        )->length);
    }

    private function assertGridShowsLost(): void
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML((string)$this->_response->getBody());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//*[@id="waiver-loss-reports-heading"]')->length);
        $card = $xpath->query('//div[h6[text()="Loss regression waiver"]]')->item(0);
        $this->assertNotNull($card);
        $this->assertSame('Lost', trim($xpath->query('p', $card)->item(0)->textContent));
        $this->assertSame(0, $xpath->query('.//*[contains(@class, "display-4")]', $card)->length);
        $this->assertStringNotContainsString('waivers uploaded', $card->textContent);
        $rows = $xpath->query('//tbody/tr[td[1]/strong[text()="Loss regression waiver"]]');
        $this->assertSame(1, $rows->length, 'A declined upload must not also produce a synthetic Lost row.');
        foreach ($rows as $row) {
            $this->assertSame('Lost', trim($xpath->query('td[2]', $row)->item(0)->textContent));
            $this->assertStringContainsString('October 25, 2026', $xpath->query('td[3]', $row)->item(0)->textContent);
            $this->assertSame(0, $xpath->query('.//button[@data-bs-target="#report-lost-waiver-modal"]', $row)->length);
        }
    }

    private function assertSummaryUploadedCount(int $expectedCount): void
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML((string)$this->_response->getBody());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $card = $xpath->query('//div[h6[text()="Loss regression waiver"]]')->item(0);
        $this->assertNotNull($card);
        $this->assertSame((string)$expectedCount, trim($xpath->query('p', $card)->item(0)->textContent));
        $this->assertStringContainsString('waivers uploaded', $card->textContent);
        $this->assertStringNotContainsString('Lost', $card->textContent);
    }

    public function testPendingLossShowsLostAndLinksToItsDetail(): void
    {
        $this->postLoss();
        $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertGridShowsLost();
        $this->assertResponseContains('View waiver');
        $loss = $this->getTableLocator()->get('Waivers.GatheringWaiverLosses')->find()
            ->where(['gathering_id' => $this->gathering->id])->firstOrFail();
        $this->get('/waivers/gathering-waivers/view-loss/' . $loss->id);
        $this->assertRedirectContains('/waivers/gathering-waivers/view?');
        $this->get('/waivers/gathering-waivers/view?gathering_id=' . $this->gathering->id
            . '&waiver_type_id=' . $this->waiverTypeId);
        $this->assertResponseOk();
        $this->assertResponseContains('Lost Report');
        $this->assertResponseContains('October 25, 2026');
    }

    public static function submittedGridStates(): array
    {
        return ['uploaded' => [false, false], 'declined' => [true, false], 'exempt' => [false, true]];
    }

    #[DataProvider('submittedGridStates')]
    public function testSubmittedRowsHaveLossActionRegardlessOfState(bool $declined, bool $exempt): void
    {
        $waivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $waivers->saveOrFail($waivers->newEntity([
            'gathering_id' => $this->gathering->id, 'waiver_type_id' => $this->waiverTypeId,
            'status' => 'active', 'is_exemption' => $exempt, 'retention_date' => '2033-01-01',
            'declined_at' => $declined ? '2026-01-02 10:00:00' : null,
            'decline_reason' => $declined ? 'Illegible copy' : null,
            'exemption_reason' => $exempt ? 'No participants' : null,
        ]));
        $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertLossActionsAreInGrid();
        $this->postLoss();
        $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertGridShowsLost();
        $this->get('/waivers/gathering-waivers/view/' . $waiver->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Waiver Details — Lost');
        $this->assertResponseContains('id="waiver-loss-notice-heading"');
        $this->assertResponseContains('October 25, 2026');
        if ($declined) {
            $this->assertResponseContains('Illegible copy');
        }
        if ($exempt) {
            $this->assertResponseContains('No participants');
        }
    }

    public function testSummaryFormReturnsToSummaryWithResult(): void
    {
        $this->deputy(true);
        $this->post('/waivers/gathering-waivers/record-lost', [
            'gathering_id' => $this->gathering->id, 'waiver_type_id' => $this->waiverTypeId,
            'notes' => 'Local officer reported the loss on October 25.',
        ]);
        $this->assertRedirect('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertFlashMessage('Waivers Lost recorded. Existing files and collection status have been preserved.');
    }

    public function testPreviouslySubmittedTypeRemainsEligibleWithoutCurrentRequirement(): void
    {
        $waivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waivers->saveOrFail($waivers->newEntity([
            'gathering_id' => $this->gathering->id, 'waiver_type_id' => $this->waiverTypeId,
            'is_exemption' => true, 'status' => 'active', 'retention_date' => '2033-01-01',
            'exemption_reason' => 'No participants',
        ]));
        $this->getTableLocator()->get('Waivers.GatheringActivityWaivers')
            ->deleteAll(['waiver_type_id' => $this->waiverTypeId]);
        $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Report/Mark Lost');
        $this->assertResponseContains('data-waiver-loss-type-id="' . $this->waiverTypeId . '"');
        $this->postLoss();
        $this->assertResponseOk();
    }

    public function testSummaryHidesLossActionWithoutPermission(): void
    {
        $this->deputy(true);
        $permission = $this->getTableLocator()->get('Permissions')->find()
            ->where(['name' => 'Can Record Lost Waivers'])->firstOrFail();
        $this->getTableLocator()->get('RolesPermissions')->deleteAll(['permission_id' => $permission->id]);
        Cache::clearGroup('security');
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertResponseNotContains('Report/Mark Lost');
        $this->assertResponseNotContains('id="report-lost-waiver-modal"');
    }

    public function testGetCannotRecordLoss(): void
    {
        $this->get('/waivers/gathering-waivers/record-lost');
        $this->assertResponseCode(405);
    }

    public function testReversalKeepsHistoryAndAllowsAnotherLossWithoutChangingClosure(): void
    {
        $this->postLoss();
        $losses = $this->getTableLocator()->get('Waivers.GatheringWaiverLosses');
        $original = $losses->find()->where(['gathering_id' => $this->gathering->id])->firstOrFail();
        (new WaiverStateService())->close((int)$this->gathering->id, self::ADMIN_MEMBER_ID);
        $closures = $this->getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $closure = $closures->getClosureForGathering((int)$this->gathering->id)->toArray();
        $this->deputy(true);
        $this->post('/waivers/gathering-waivers/reverse-loss/' . $original->id);
        $this->assertRedirectContains('/waivers/gathering-waivers/view?');
        $reversed = $losses->get($original->id);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $reversed->reversed_by);
        $this->assertNotNull($reversed->reversed_at);
        $this->assertSame($original->notes, $reversed->notes);
        $this->assertSame($original->created_by, $reversed->created_by);
        $this->assertEquals($original->created, $reversed->created);
        $this->assertEquals($closure, $closures->getClosureForGathering((int)$this->gathering->id)->toArray());
        $type = $this->getTableLocator()->get('Waivers.WaiverTypes')->get($this->waiverTypeId);
        $this->assertFalse((new WaiverFileService())->getWaiverStatusSummary(
            (int)$this->gathering->id,
            [$type],
        )[0]['loss_recorded']);
        $this->get('/waivers/gathering-waivers/view?gathering_id=' . $this->gathering->id
            . '&waiver_type_id=' . $this->waiverTypeId);
        $this->assertResponseOk();
        $this->assertResponseNotContains('Waiver Details — Lost');
        $this->assertResponseContains('Lost Report');
        $this->assertResponseContains('Lost status reversed');
        $this->assertResponseContains('October 25, 2026');
        $this->assertResponseNotContains('Reverse Lost Status');
        $this->postLoss(['notes' => 'A new loss reported on October 25, 2026.']);
        $this->assertResponseOk();
        $this->assertSame(2, $losses->find()->where(['gathering_id' => $this->gathering->id])->count());
        $this->assertSame(1, $losses->find()->where([
            'gathering_id' => $this->gathering->id, 'reversed_at IS' => null,
        ])->count());
        $this->post('/waivers/gathering-waivers/reverse-loss/' . $original->id);
        $this->assertFlashMessage('This loss report has already been reversed.');
        $this->assertEquals($reversed->reversed_at, $losses->get($original->id)->reversed_at);
        $this->get('/waivers/gathering-waivers?gathering_id=' . $this->gathering->id);
        $this->assertResponseOk();
        $this->assertGridShowsLost();
    }

    public function testReversalRequiresLossPermissionAndBranchScope(): void
    {
        $this->postLoss();
        $losses = $this->getTableLocator()->get('Waivers.GatheringWaiverLosses');
        $loss = $losses->find()->where(['gathering_id' => $this->gathering->id])->firstOrFail();
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']]);
        $this->post('/waivers/gathering-waivers/reverse-loss/' . $loss->id);
        $this->assertResponseCode(403);
        $this->assertNull($losses->get($loss->id)->reversed_at);
        $this->deputy();
        $this->connection->execute('UPDATE gatherings SET branch_id = ? WHERE id = ?', [
            self::TEST_BRANCH_STARGATE_ID, $this->gathering->id,
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']]);
        $this->post('/waivers/gathering-waivers/reverse-loss/' . $loss->id);
        $this->assertResponseCode(403);
        $this->assertNull($losses->get($loss->id)->reversed_at);
    }

    public function testGetCannotReverseLoss(): void
    {
        $this->postLoss();
        $loss = $this->getTableLocator()->get('Waivers.GatheringWaiverLosses')->find()->firstOrFail();
        $this->get('/waivers/gathering-waivers/reverse-loss/' . $loss->id);
        $this->assertResponseCode(405);
    }
}
