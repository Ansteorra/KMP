<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\GatheringScheduledActivity;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Cake\I18n\DateTime;

/**
 * CourtAgendasControllerTest
 */
class CourtAgendasControllerTest extends HttpIntegrationTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /**
     * @return void
     */
    public function testGatheringAgendaRendersPerCourtBuilder(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);
        $this->importBestowalsForGathering((int)$gathering->id);

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Projected Agenda Runtime');
        $this->assertResponseContains('Court Activities');
        $this->assertResponseContains('Build one court at a time');
        $this->assertResponseContains('data-controller="court-agenda-board"');
        $this->assertResponseContains('Back to Gathering');
        $this->assertResponseContains('Printer Ready');
        $this->assertResponseContains('Remove from Agenda');
        $this->assertResponseNotContains('No linked scheduled activity');
    }

    /**
     * @return void
     */
    public function testGatheringAgendaShowsCourtLanesFromScheduleWithoutImport(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date,
        );

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Court Session');
        $this->assertResponseNotContains('No court activities are available yet.');
    }

    /**
     * @return void
     */
    public function testPrintAgendaRendersPrinterReadyFormat(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);

        $this->assertResponseOk();
        $this->assertResponseContains('<table>');
        $this->assertResponseContains('Projected court runtime');
        $this->assertResponseContains('Print Agenda');
    }

    /**
     * @return void
     */
    public function testRemoveItemEndpointClearsBestowalCourtAssignment(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $item = $this->getTableLocator()->get('Awards.CourtAgendaItems')
            ->find()
            ->contain(['CourtAgendaSegments'])
            ->where(['CourtAgendaSegments.court_agenda_id' => $agenda->id])
            ->where(['bestowal_id IS NOT' => null])
            ->firstOrFail();

        $this->post('/awards/court-agendas/remove-item', [
            'item_id' => (int)$item->id,
            'return_segment_id' => (int)$item->court_agenda_segment_id,
        ]);

        $this->assertRedirectContains('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertFalse($this->getTableLocator()->get('Awards.CourtAgendaItems')->exists([
            'id' => (int)$item->id,
        ]));
        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->get((int)$item->bestowal_id);
        $this->assertNull($bestowal->gathering_scheduled_activity_id);
        $this->assertFalse((bool)$bestowal->roaming_court);
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @return \Awards\Model\Entity\CourtAgenda
     */
    private function importBestowalsForGathering(int $gatheringId)
    {
        $this->get('/awards/court-agendas/gathering/' . $gatheringId);
        $this->assertResponseOk();
        $agenda = $this->getTableLocator()->get('Awards.CourtAgendas')
            ->find()
            ->where(['gathering_id' => $gatheringId])
            ->firstOrFail();

        $this->post('/awards/court-agendas/import/' . $agenda->id);
        $this->assertRedirect(['controller' => 'CourtAgendas', 'action' => 'gathering', $gatheringId]);

        return $agenda;
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @return void
     */
    private function createBestowalForGathering(int $gatheringId): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date'])
            ->where(['id' => $gatheringId])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $scheduledActivity = $this->createScheduledActivityForAward(
            $gatheringId,
            (int)$award->id,
            $gathering->start_date,
        );

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gatheringId,
            'gathering_scheduled_activity_id' => $scheduledActivity->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 10,
            'herald_notes' => 'Speak clearly.',
        ]));
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @param int $awardId Award ID.
     * @param \Cake\I18n\DateTime $startDate Gathering start date.
     * @return \App\Model\Entity\GatheringScheduledActivity
     */
    private function createScheduledActivityForAward(
        int $gatheringId,
        int $awardId,
        DateTime $startDate,
    ): GatheringScheduledActivity {
        $activity = $this->getTableLocator()->get('GatheringActivities')
            ->find()
            ->firstOrFail();
        $awardActivities = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        if (!$awardActivities->exists(['award_id' => $awardId, 'gathering_activity_id' => $activity->id])) {
            $awardActivities->saveOrFail($awardActivities->newEntity([
                'award_id' => $awardId,
                'gathering_activity_id' => $activity->id,
            ]));
        }

        $scheduledActivities = $this->getTableLocator()->get('GatheringScheduledActivities');
        $scheduledActivity = $scheduledActivities->newEntity([
            'gathering_id' => $gatheringId,
            'gathering_activity_id' => $activity->id,
            'start_datetime' => (clone $startDate)->modify('+1 hour'),
            'end_datetime' => (clone $startDate)->modify('+2 hours'),
            'has_end_time' => true,
            'display_title' => 'Court Session',
            'description' => 'Court Session description.',
            'pre_register' => false,
            'is_other' => false,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);

        return $scheduledActivities->saveOrFail($scheduledActivity);
    }
}
