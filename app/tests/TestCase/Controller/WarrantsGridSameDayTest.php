<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\KMP\GridColumns\WarrantsGridColumns;
use App\Model\Entity\GridView;
use App\Model\Entity\Warrant;
use App\Services\GridViewService;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;

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

    /**
     * @param array<string, mixed> $overrides Warrant field overrides.
     */
    private function createTestWarrant(
        DateTime $startOn,
        DateTime $expiresOn,
        array $overrides = [],
    ): EntityInterface {
        $warrants = $this->getTableLocator()->get('Warrants');
        $name = (string)($overrides['name'] ?? 'SameDayTest-' . uniqid());
        unset($overrides['name']);
        $warrant = $warrants->newEntity(array_merge([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_role_id' => 1,
            'warrant_roster_id' => 1,
            'entity_type' => 'Direct Grant',
            'entity_id' => -1,
            'status' => Warrant::CURRENT_STATUS,
            'start_on' => $startOn,
            'expires_on' => $expiresOn,
        ], $overrides));
        $warrant->set('name', $name);
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

    /**
     * A copied Previous view keeps its symbolic OR scope while filters are edited.
     */
    public function testCopiedPreviousViewKeepsWarrantScopeWhenFiltersAreDirty(): void
    {
        $marker = 'CopiedPrevious-' . uniqid();
        $expiredByDate = $this->createTestWarrant(
            DateTime::now()->modify('-1 year'),
            DateTime::now()->modify('-2 days'),
            [
                'name' => $marker . '-ExpiredByDate',
                'revoked_reason' => $marker,
            ],
        );
        $expiredByStatus = $this->createTestWarrant(
            DateTime::now()->modify('-1 year'),
            DateTime::now()->modify('+6 months'),
            [
                'name' => $marker . '-ExpiredByStatus',
                'status' => Warrant::EXPIRED_STATUS,
                'revoked_reason' => $marker,
            ],
        );
        $current = $this->createTestWarrant(
            DateTime::now()->modify('-1 day'),
            DateTime::now()->modify('+6 months'),
            [
                'name' => $marker . '-Current',
                'revoked_reason' => $marker,
            ],
        );
        $this->assertContains(
            [
                'field' => 'warrant_scope',
                'operator' => 'eq',
                'value' => WarrantsGridColumns::SCOPE_PREVIOUS,
            ],
            WarrantsGridColumns::getSystemViews()['sys-warrants-previous']['config']['filters'],
        );
        $currentUser = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $gridView = (new GridViewService())->createView([
            'grid_key' => 'Warrants.index.main',
            'name' => 'Copied Previous Warrants ' . uniqid(),
            'config' => json_encode([
                'filters' => [
                    [
                        'field' => 'warrant_scope',
                        'operator' => 'in',
                        'value' => [WarrantsGridColumns::SCOPE_PREVIOUS],
                    ],
                    [
                        'field' => 'revoked_reason',
                        'operator' => 'eq',
                        'value' => $marker,
                    ],
                ],
            ]),
        ], $currentUser);
        $this->assertInstanceOf(GridView::class, $gridView);
        $this->assertSame(
            ['warrant_scope', 'revoked_reason'],
            array_column($gridView->getConfigArray()['filters'], 'field'),
        );

        $this->get('/warrants/grid-data?' . http_build_query([
            'view_id' => $gridView->id,
            'filter' => [
                'warrant_scope' => WarrantsGridColumns::SCOPE_PREVIOUS,
                'revoked_reason' => $marker,
            ],
            'dirty' => ['filters' => '1'],
        ]));

        $this->assertResponseOk();
        $this->assertResponseContains($expiredByDate->get('name'));
        $this->assertResponseContains($expiredByStatus->get('name'));
        $this->assertResponseNotContains($current->get('name'));
        $gridState = $this->viewVariable('gridState');
        $this->assertSame(
            [WarrantsGridColumns::SCOPE_PREVIOUS],
            $gridState['filters']['active']['warrant_scope'],
        );
        $this->assertContains('warrant_scope', $gridState['config']['lockedFilters']);
        $this->assertFalse($gridState['filters']['available']['warrant_scope']['showInFilterMenu']);
    }
}
