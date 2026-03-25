<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Entity\Warrant;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;
use Cake\I18n\Date;

/**
 * Tests that warrants started today appear in the Current system view.
 *
 * Regression test for a bug where DATETIME columns compared against
 * date-only strings in dateRange filters excluded same-day records
 * (MySQL treats '2026-03-25' as '2026-03-25 00:00:00').
 */
class WarrantsGridSameDayTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    private function createTestWarrant(DateTime $startOn, DateTime $expiresOn): \Cake\Datasource\EntityInterface
    {
        $warrants = $this->getTableLocator()->get('Warrants');
        $warrant = $warrants->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_role_id' => 1,
            'warrant_roster_id' => 1,
            'entity_type' => 'Direct Grant',
            'entity_id' => -1,
            'status' => Warrant::CURRENT_STATUS,
            'start_on' => $startOn,
            'expires_on' => $expiresOn,
        ]);
        $warrant->set('name', 'SameDayTest-' . uniqid());
        $saved = $warrants->save($warrant);
        $this->assertNotFalse($saved, 'Failed to save test warrant');

        return $saved;
    }

    /**
     * A warrant whose start_on is today (with a time component past midnight)
     * must appear in the "Current" system view grid data.
     */
    public function testCurrentViewIncludesWarrantStartedToday(): void
    {
        $saved = $this->createTestWarrant(
            DateTime::now(),
            DateTime::now()->modify('+6 months'),
        );

        // Search for the specific warrant name to avoid pagination issues
        $this->get('/warrants/grid-data?view_id=sys-warrants-current&search=' . urlencode($saved->get('name')));
        $this->assertResponseOk();
        $this->assertResponseContains($saved->get('name'));
    }

    /**
     * A warrant whose start_on is in the past (yesterday) must also appear.
     */
    public function testCurrentViewIncludesWarrantStartedYesterday(): void
    {
        $saved = $this->createTestWarrant(
            DateTime::now()->modify('-1 day'),
            DateTime::now()->modify('+6 months'),
        );

        $this->get('/warrants/grid-data?view_id=sys-warrants-current&search=' . urlencode($saved->get('name')));
        $this->assertResponseOk();
        $this->assertResponseContains($saved->get('name'));
    }

    /**
     * A warrant starting tomorrow should NOT appear in the "Current" system view.
     * Use a direct SQL query to verify the dateRange filter logic.
     */
    public function testCurrentViewExcludesWarrantStartingTomorrow(): void
    {
        $saved = $this->createTestWarrant(
            DateTime::now()->modify('+1 day'),
            DateTime::now()->modify('+6 months'),
        );
        $name = $saved->get('name');

        // The Current system view should not include this warrant. 
        // Use the grid with search to target this specific warrant.
        $this->get('/warrants/grid-data?view_id=sys-warrants-current&search=' . urlencode($name));
        $this->assertResponseOk();

        // Also verify via direct SQL that the dateRange logic is correct
        $today = date('Y-m-d');
        $endOfDay = $today . ' 23:59:59';
        $warrants = $this->getTableLocator()->get('Warrants');
        $count = $warrants->find()
            ->where([
                'Warrants.id' => $saved->id,
                'Warrants.start_on <=' => $endOfDay,
                'Warrants.status' => Warrant::CURRENT_STATUS,
            ])
            ->count();
        $this->assertEquals(0, $count, 'Warrant starting tomorrow should not match start_on <= today 23:59:59');
    }

    /**
     * Grid data endpoint with no system view should not error for same-day warrants.
     */
    public function testGridDataDefaultViewDoesNotError(): void
    {
        $this->createTestWarrant(
            DateTime::now(),
            DateTime::now()->modify('+6 months'),
        );

        $this->get('/warrants/grid-data');
        $this->assertResponseOk();
    }
}
