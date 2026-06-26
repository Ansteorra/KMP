<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;

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
    public function testGatheringAgendaRendersVisualPlanningBoard(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Projected Court Runtime');
        $this->assertResponseContains('data-controller="court-agenda-board"');
        $this->assertResponseContains('Printer Ready');
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

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $agenda = $this->getTableLocator()->get('Awards.CourtAgendas')
            ->find()
            ->where(['gathering_id' => $gathering->id])
            ->firstOrFail();

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);

        $this->assertResponseOk();
        $this->assertResponseContains('<table>');
        $this->assertResponseContains('Projected court runtime');
        $this->assertResponseContains('Print Agenda');
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @return void
     */
    private function createBestowalForGathering(int $gatheringId): void
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gatheringId,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 10,
            'herald_notes' => 'Speak clearly.',
        ]));
    }
}
