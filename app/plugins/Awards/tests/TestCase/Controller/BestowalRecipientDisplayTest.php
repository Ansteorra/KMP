<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalNotificationVarsService;
use Awards\Services\CourtAgendaService;
use PHPUnit\Framework\Attributes\DataProvider;

/** Recipient names must survive recommendation approval without a member account. */
class BestowalRecipientDisplayTest extends HttpIntegrationTestCase
{
    private const RECIPIENT_NAME = 'Recipient display regression Élodie <Oak> & Reed';

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /** @return void */
    public function testRecipientWithoutAccountAppearsOnBestowalScreensAndExports(): void
    {
        $bestowal = $this->createBestowalFromApprovedRecommendation();
        foreach (['view', 'bestowal-todos', 'turbo-edit-form'] as $action) {
            $this->get('/awards/bestowals/' . $action . '/' . $bestowal->id);
            $this->assertResponseOk();
            $this->assertResponseContains(h(self::RECIPIENT_NAME));
            $this->assertResponseNotContains(self::RECIPIENT_NAME);
            $this->assertResponseNotContains('Unknown Member');
        }

        $query = [
            'ignore_default' => 1,
            'columns' => 'member_sca_name',
            'search' => 'Recipient display regression',
        ];
        foreach (['grid-data', 'gathering-bestowals-grid-data/' . $bestowal->gathering_id] as $action) {
            $url = '/awards/bestowals/' . $action . '?';
            $this->get($url . http_build_query($query));
            $this->assertResponseOk();
            $this->assertResponseContains(h(self::RECIPIENT_NAME));

            $this->get($url . http_build_query($query + ['export' => 'csv']));
            $this->assertResponseOk();
            $this->assertHeaderContains('Content-Disposition', '.csv');
            $this->assertResponseContains(self::RECIPIENT_NAME);
        }

        $vars = (new BestowalNotificationVarsService())->prepareCreatedVars((int)$bestowal->id);
        $this->assertTrue($vars['success'], $vars['error'] ?? 'Unable to prepare notification variables.');
        $this->assertSame(self::RECIPIENT_NAME, $vars['data']['memberScaName']);
    }

    /**
     * @param bool $roaming Whether to place the recipient in roaming court.
     * @return void
     */
    #[DataProvider('courtPlacementProvider')]
    public function testRecipientWithoutAccountAppearsBeforeAndAfterAgendaPlacementAndInPrint(bool $roaming): void
    {
        $bestowal = $this->createBestowalFromApprovedRecommendation();
        $gathering = $this->getTableLocator()->get('Gatherings')->get((int)$bestowal->gathering_id);
        $activity = $this->getTableLocator()->get('GatheringActivities')->find()->firstOrFail();
        $links = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        if (!$links->exists(['award_id' => $bestowal->award_id, 'gathering_activity_id' => $activity->id])) {
            $links->saveOrFail($links->newEntity([
                'award_id' => $bestowal->award_id,
                'gathering_activity_id' => $activity->id,
            ]));
        }
        $sessions = $this->getTableLocator()->get('GatheringScheduledActivities');
        $session = $sessions->saveOrFail($sessions->newEntity([
            'gathering_id' => $gathering->id,
            'gathering_activity_id' => $activity->id,
            'start_datetime' => $gathering->start_date->addHours(1),
            'end_datetime' => $gathering->start_date->addHours(2),
            'has_end_time' => true,
            'display_title' => 'Recipient Name Test Court',
            'is_other' => false,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $service = new CourtAgendaService();
        $agenda = $service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $viewModel = $service->buildAgendaViewModel((int)$agenda->id);
        $pending = array_values(array_filter(
            $viewModel['unscheduledBestowals'],
            static fn($row): bool => (int)$row['entity']->id === (int)$bestowal->id,
        ));
        $this->assertSame(self::RECIPIENT_NAME, $pending[0]['label']);
        $segment = $this->getTableLocator()->get('Awards.CourtAgendaSegments')->find()
            ->where(['gathering_scheduled_activity_id' => $session->id])->firstOrFail();
        $item = $roaming
            ? $service->moveToRoamingCourt(
                (int)$agenda->id,
                bestowalId: (int)$bestowal->id,
                actorId: self::ADMIN_MEMBER_ID,
            )
            : $service->addBestowalToSegment(
                (int)$agenda->id,
                (int)$bestowal->id,
                (int)$segment->id,
                self::ADMIN_MEMBER_ID,
            );

        $this->assertAgendaRecipientIsNamed((int)$agenda->id, (int)$gathering->id, (int)$item->court_agenda_segment_id);
    }

    /** @return array<string, array<bool>> */
    public static function courtPlacementProvider(): array
    {
        return ['scheduled session' => [false], 'roaming court' => [true]];
    }

    /**
     * @param int $agendaId Agenda ID.
     * @param int $gatheringId Gathering ID.
     * @param int $segmentId Selected court section.
     * @return void
     */
    private function assertAgendaRecipientIsNamed(int $agendaId, int $gatheringId, int $segmentId): void
    {
        $this->get('/awards/court-agendas/gathering/' . $gatheringId . '?segment_id=' . $segmentId);
        $this->assertResponseOk();
        $this->assertContains($segmentId, array_map(
            static fn($row): int => (int)$row['entity']->id,
            $this->viewVariable('segments'),
        ));
        $this->assertSame($segmentId, $this->viewVariable('selectedSegmentId'));
        $this->assertResponseContains('<h3 class="h5 mb-1">' . h(self::RECIPIENT_NAME) . '</h3>');
        $this->assertResponseContains('aria-label="Move ' . h(self::RECIPIENT_NAME) . ' up"');
        $this->assertResponseNotContains('Unknown recipient');
        $this->assertResponseNotContains(self::RECIPIENT_NAME);

        $this->get('/awards/court-agendas/print-agenda/' . $agendaId);
        $this->assertResponseOk();
        $this->assertResponseContains('<strong>' . h(self::RECIPIENT_NAME) . '</strong>');
        $this->assertResponseNotContains('Unknown recipient');
        $this->assertResponseNotContains(self::RECIPIENT_NAME);
    }

    /** @return \Awards\Model\Entity\Bestowal */
    private function createBestowalFromApprovedRecommendation(): Bestowal
    {
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $recommendation = $recommendations->saveOrFail($recommendations->newEntity([
            'member_id' => null,
            'member_sca_name' => self::RECIPIENT_NAME,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'requester_sca_name' => 'Test Submitter',
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'state' => 'King Approved',
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
            'person_to_notify' => '',
            'contact_email' => 'recipient@example.test',
            'reason' => 'Recipient display regression coverage.',
        ]));
        $result = (new BestowalCreationService())->createFromRecommendation(
            (int)$recommendation->id,
            self::ADMIN_MEMBER_ID,
            (int)$gathering->id,
        );
        $this->assertTrue($result['success'], $result['error'] ?? 'Unable to create bestowal.');
        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->get((int)$result['data']['bestowalId']);
        $this->assertNull($bestowal->member_id);
        $this->assertSame(self::RECIPIENT_NAME, $bestowal->member_sca_name);

        return $bestowal;
    }
}
