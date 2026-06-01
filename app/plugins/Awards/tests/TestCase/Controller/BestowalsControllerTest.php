<?php

declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Services\BestowalCourtSlotService;

/**
 * BestowalsController integration tests.
 */
class BestowalsControllerTest extends HttpIntegrationTestCase
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
    public function testGridDataSortByGatheringNameAsc(): void
    {
        $this->get(
            '/awards/bestowals/grid-data?ignore_default=1&sort=gathering_name&direction=asc',
        );
        $this->assertResponseOk();
    }

    /**
     * @return void
     */
    public function testGridDataSortByMemberScaNameAsc(): void
    {
        $this->get(
            '/awards/bestowals/grid-data?ignore_default=1&sort=member_sca_name&direction=asc',
        );
        $this->assertResponseOk();
    }

    /**
     * @return void
     */
    public function testGridDataShowsRoamingCourtInCourtSlotColumn(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'roaming_court' => true,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]);
        $bestowals->saveOrFail($bestowal);

        $this->get('/awards/bestowals/grid-data?ignore_default=1');
        $this->assertResponseOk();
        $this->assertResponseContains(
            h((new BestowalCourtSlotService())->formatCourtSlotDisplay($bestowal)),
        );
    }
}
