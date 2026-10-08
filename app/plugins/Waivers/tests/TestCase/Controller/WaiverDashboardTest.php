<?php
declare(strict_types=1);

namespace Waivers\Test\TestCase\Controller;

use App\Model\Entity\Gathering;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;
use DOMDocument;
use DOMXPath;
use Waivers\Services\WaiverDashboardService;
use Waivers\Services\WaiverMobileService;

/**
 * End-date compliance and authorized mobile selection regressions.
 */
class WaiverDashboardTest extends HttpIntegrationTestCase
{
    private int $activityId;
    private array $waiverTypeIds;
    private int $gatheringTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
        DateTime::setTestNow(new DateTime('2026-10-07 12:00:00', 'UTC'));

        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Dashboard regression activity']));
        $this->activityId = (int)$activity->id;
        $gatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $this->gatheringTypeId = (int)$gatheringTypes->saveOrFail($gatheringTypes->newEntity([
            'name' => 'Dashboard regression type', 'clonable' => false, 'color' => '#0d6efd',
        ]))->id;
        $types = $this->getTableLocator()->get('Waivers.WaiverTypes');
        $requirements = $this->getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $this->waiverTypeIds = [];
        foreach (['Dashboard adult waiver', 'Dashboard minor waiver'] as $name) {
            $type = $types->saveOrFail($types->newEntity([
                'name' => $name,
                'is_active' => true,
                'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
            ]));
            $this->waiverTypeIds[] = (int)$type->id;
            $requirements->saveOrFail($requirements->newEntity([
                'gathering_activity_id' => $this->activityId,
                'waiver_type_id' => $type->id,
            ]));
        }
    }

    protected function tearDown(): void
    {
        DateTime::setTestNow(null);
        parent::tearDown();
    }

    private function gathering(string $name, string $end, int $branchId = self::TEST_BRANCH_LOCAL_ID): Gathering
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $endDate = new DateTime($end, 'UTC');

        return $gatherings->saveOrFail($gatherings->newEntity([
            'name' => $name,
            'branch_id' => $branchId,
            'gathering_type_id' => $this->gatheringTypeId,
            'description' => 'Waiver regression gathering',
            'location' => 'Test location',
            'start_date' => $endDate->subDays(2),
            'end_date' => $endDate,
            'created_by' => self::ADMIN_MEMBER_ID,
            'gathering_activities' => ['_ids' => [$this->activityId]],
        ], [
            'associated' => ['GatheringActivities'],
            'accessibleFields' => ['gathering_activities' => true],
        ]));
    }

    private function waiver(Gathering $gathering, int $typeId, array $overrides = []): void
    {
        $waivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waivers->saveOrFail($waivers->newEntity($overrides + [
            'gathering_id' => $gathering->id,
            'waiver_type_id' => $typeId,
            'status' => 'active',
            'is_exemption' => true,
            'exemption_reason' => 'No participants',
            'retention_date' => '2033-10-07',
            'created_by' => self::ADMIN_MEMBER_ID,
        ], ['accessibleFields' => ['deleted' => true]]));
    }

    private function ids(array $gatherings): array
    {
        return array_map(static fn($gathering) => (int)$gathering->id, $gatherings);
    }

    public function testBucketsUseEndDateAndIncludeOldPartialGatherings(): void
    {
        $old = $this->gathering('Old partial', '2026-06-01 10:00:00');
        $this->waiver($old, $this->waiverTypeIds[0]);
        $day31 = $this->gathering('Day 31', '2026-09-06 23:59:59');
        $day30 = $this->gathering('Day 30', '2026-09-07 00:00:00');
        $endedToday = $this->gathering('Ended today', '2026-10-07 11:00:00');
        $ongoing = $this->gathering('Ongoing', '2026-10-07 13:00:00');
        $future30 = $this->gathering('Future boundary', '2026-11-06 23:59:59');
        $future31 = $this->gathering('Future outside', '2026-11-07 00:00:00');
        $otherBranch = $this->gathering('Other branch', '2026-09-01 10:00:00', self::TEST_BRANCH_STARGATE_ID);

        $service = new WaiverDashboardService();
        $buckets = $service->getGatheringsWithIncompleteWaivers([self::TEST_BRANCH_LOCAL_ID], 30);
        $this->assertContains((int)$old->id, $this->ids($buckets['missing']));
        $this->assertContains((int)$day31->id, $this->ids($buckets['missing']));
        $this->assertContains((int)$day30->id, $this->ids($buckets['due']));
        $this->assertContains((int)$endedToday->id, $this->ids($buckets['due']));
        $this->assertContains((int)$ongoing->id, $this->ids($buckets['upcoming']));
        $this->assertContains((int)$future30->id, $this->ids($buckets['upcoming']));
        $allIds = array_merge(...array_values(array_map($this->ids(...), $buckets)));
        $this->assertNotContains((int)$future31->id, $allIds);
        $this->assertNotContains((int)$otherBranch->id, $allIds);
        $this->assertCount(count(array_unique($allIds)), $allIds, 'Buckets must not overlap');
        $partial = array_values(array_filter($buckets['missing'], fn($g) => $g->id === $old->id))[0];
        $this->assertSame(1, $partial->missing_waiver_count);
        $this->assertSame(['Dashboard minor waiver'], $partial->missing_waiver_names);
        $this->assertSame(['missing' => [], 'due' => [], 'upcoming' => []], $service->getGatheringsWithIncompleteWaivers([], 30));
    }

    public function testComplianceExcludesClosedCancelledDeletedAndSatisfiedRequirements(): void
    {
        $partial = $this->gathering('Partial compliant type', '2026-08-01 10:00:00');
        // Duplicate uploads satisfy only one type; declined/deleted waivers do not satisfy the other.
        $this->waiver($partial, $this->waiverTypeIds[0]);
        $this->waiver($partial, $this->waiverTypeIds[0]);
        $this->waiver($partial, $this->waiverTypeIds[1], ['declined_at' => DateTime::now()]);
        $this->waiver($partial, $this->waiverTypeIds[1], ['deleted' => DateTime::now()]);
        $complete = $this->gathering('Complete', '2026-08-02 10:00:00');
        foreach ($this->waiverTypeIds as $id) {
            $this->waiver($complete, $id);
        }
        $closed = $this->gathering('Closed', '2026-08-03 10:00:00');
        $closures = $this->getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $closures->saveOrFail($closures->newEntity([
            'gathering_id' => $closed->id, 'closed_at' => DateTime::now(), 'closed_by' => self::ADMIN_MEMBER_ID,
        ]));
        $cancelled = $this->gathering('Cancelled', '2026-08-04 10:00:00');
        $deleted = $this->gathering('Deleted', '2026-08-05 10:00:00');
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gatherings->updateAll(['cancelled_at' => DateTime::now()], ['id' => $cancelled->id]);
        $gatherings->updateAll(['deleted' => DateTime::now()], ['id' => $deleted->id]);

        $service = new WaiverDashboardService();
        $buckets = $service->getGatheringsWithIncompleteWaivers([self::TEST_BRANCH_LOCAL_ID], 30);
        foreach ([$complete, $closed, $cancelled, $deleted] as $excluded) {
            $this->assertNotContains((int)$excluded->id, $this->ids($buckets['missing']));
        }
        $issues = $service->getBranchesWithIssues([self::TEST_BRANCH_LOCAL_ID], $buckets['missing']);
        $this->assertCount(1, $issues);
        $this->assertSame(count($buckets['missing']), $issues[0]['gathering_count']);
        $this->assertSame(array_sum(array_map(fn($g) => $g->missing_waiver_count, $buckets['missing'])), $issues[0]['total_missing_waivers']);
        $this->assertSame($this->ids($buckets['missing']), $this->ids($issues[0]['gatherings']));
        $stats = $service->getDashboardStatistics([self::TEST_BRANCH_LOCAL_ID], $buckets);
        $this->assertSame($issues[0]['gathering_count'], $stats['gatheringsMissingCount']);
    }

    public function testDashboardRendersDueSectionsAndActionableBranchDialog(): void
    {
        $pastDue = $this->gathering('Past due modal fixture', '2026-08-01 10:00:00');
        $this->waiver($pastDue, $this->waiverTypeIds[0]);
        $this->gathering('Due fixture', '2026-09-07 23:59:59');
        $this->gathering('Future fixture', '2026-11-06 23:59:59');
        $this->get('/waivers/gathering-waivers/dashboard');
        $this->assertResponseOk();
        $this->assertContains((int)$pastDue->id, $this->ids($this->viewVariable('gatheringsMissingWaivers')));
        $document = new DOMDocument();
        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            $document->loadHTML((string)$this->_response->getBody());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//*[@id="collapse-due-waivers"]//th[contains(., "Days Until Start")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="collapse-upcoming"]//th[contains(., "Days Until Start")]')->length);
        $modalId = 'branch-past-due-' . self::TEST_BRANCH_LOCAL_ID;
        $this->assertSame(1, $xpath->query('//button[@data-bs-target="#' . $modalId . '"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="' . $modalId . '"][@aria-labelledby="' . str_replace('branch-past-due-', 'branch-past-due-title-', $modalId) . '"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//*[@id="' . $modalId . '"]//li[text()="Dashboard minor waiver"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//*[@id="' . $modalId . '"]//a[contains(@href, "gathering_id=' . $pastDue->id . '")]')->length);
    }

    public function testMobileSortsOldestEndFirstAndPreservesBranchAndStewardAccess(): void
    {
        $future = $this->gathering('Mobile future', '2026-10-12 10:00:00');
        $old = $this->gathering('Mobile old', '2026-06-01 10:00:00');
        $current = $this->gathering('Mobile current', '2026-10-07 13:00:00');
        $denied = $this->gathering('Mobile other branch', '2026-05-01 10:00:00', self::TEST_BRANCH_STARGATE_ID);
        $service = new WaiverMobileService();
        $authorized = $service->getAuthorizedGatherings([self::TEST_BRANCH_LOCAL_ID], []);
        $ids = $this->ids($authorized);
        $this->assertSame([(int)$old->id, (int)$current->id, (int)$future->id], array_values(array_intersect($ids, [(int)$future->id, (int)$old->id, (int)$current->id])));
        $this->assertNotContains((int)$denied->id, $ids);
        $this->assertSame([(int)$denied->id], $this->ids($service->getAuthorizedGatherings([], [(int)$denied->id])));
        $this->assertSame([], $service->getAuthorizedGatherings([], []));
    }
}
