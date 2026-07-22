<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\ActionItem;
use App\Model\Entity\Member;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\KMP\GridColumns\BestowalsGridColumns;
use Awards\Model\Entity\Award;
use Awards\Model\Entity\Bestowal;

/**
 * Bestowal grid sorting, filtering, and Turbo-frame response coverage.
 */
class BestowalsGridSortingFilteringTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testRelationColumnsExposeSortingAndDropdownFiltering(): void
    {
        $columns = BestowalsGridColumns::getColumns();

        foreach (['member_sca_name', 'awards', 'gathering_name'] as $columnKey) {
            $this->assertTrue($columns[$columnKey]['sortable']);
            $this->assertTrue($columns[$columnKey]['filterable']);
            $this->assertSame('dropdown', $columns[$columnKey]['filterType']);
            $this->assertNotEmpty($columns[$columnKey]['filterOptionsSource']);
            $this->assertNotEmpty($columns[$columnKey]['filterQueryField']);
        }
        foreach (['award_type', 'award_group'] as $columnKey) {
            $this->assertFalse($columns[$columnKey]['defaultVisible']);
            $this->assertTrue($columns[$columnKey]['filterable']);
            $this->assertSame('dropdown', $columns[$columnKey]['filterType']);
            $this->assertNotEmpty($columns[$columnKey]['filterOptionsSource']);
            $this->assertNotEmpty($columns[$columnKey]['filterQueryField']);
        }

        $this->assertTrue($columns['todos_summary']['sortable']);
        $this->assertSame('open_todo_count', $columns['todos_summary']['queryField']);
        $this->assertSame(
            'navigate:/gatherings/view/:gathering_public_id?tab=gathering-bestowals',
            $columns['gathering_name']['clickAction'],
        );
    }

    public function testSystemViewsKeepSourceAvailableButHiddenByDefault(): void
    {
        $columns = BestowalsGridColumns::getColumns();
        $views = BestowalsGridColumns::getSystemViews();

        $this->assertArrayHasKey('source', $columns);
        $this->assertFalse($columns['source']['defaultVisible']);
        foreach ($views as $view) {
            $this->assertNotContains('source', $view['config']['columns']);
        }
    }

    public function testProtectedColumnsAreAvailableOnlyForAuthorizedFieldSets(): void
    {
        $original = BestowalsGridColumns::setProtectedFieldVisibility(false, false);

        try {
            $columns = BestowalsGridColumns::getColumns();
            $this->assertArrayNotHasKey('herald_notes_preview', $columns);
            $this->assertArrayNotHasKey('recommendation_reasons', $columns);

            BestowalsGridColumns::setProtectedFieldVisibility(true, false);
            $columns = BestowalsGridColumns::getColumns();
            $this->assertArrayHasKey('herald_notes_preview', $columns);
            $this->assertArrayNotHasKey('recommendation_reasons', $columns);

            BestowalsGridColumns::setProtectedFieldVisibility(false, true);
            $columns = BestowalsGridColumns::getColumns();
            $this->assertArrayHasKey('herald_notes_preview', $columns);
            $this->assertArrayHasKey('recommendation_reasons', $columns);
            foreach (BestowalsGridColumns::getSystemViews() as $view) {
                $this->assertContains('herald_notes_preview', $view['config']['columns']);
                $this->assertContains('recommendation_reasons', $view['config']['columns']);
            }
        } finally {
            BestowalsGridColumns::setProtectedFieldVisibility(
                $original['heraldNotes'],
                $original['crownFields'],
            );
        }
    }

    public function testRelationFiltersAndSortsUseJoinedGridFields(): void
    {
        $prefix = 'bestowal-grid-fields-' . uniqid();
        $members = [
            $this->createMember($prefix . '-aaa'),
            $this->createMember($prefix . '-zzz'),
        ];
        $awards = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id', 'abbreviation'])
            ->orderBy(['abbreviation' => 'ASC'])
            ->limit(2)
            ->all()
            ->toList();
        $gatherings = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'public_id', 'name'])
            ->orderBy(['name' => 'ASC'])
            ->limit(2)
            ->all()
            ->toList();

        $this->assertCount(2, $awards);
        $this->assertCount(2, $gatherings);

        $later = $this->createBestowal(
            (int)$members[1]->id,
            (int)$awards[1]->id,
            (int)$gatherings[1]->id,
        );
        $earlier = $this->createBestowal(
            (int)$members[0]->id,
            (int)$awards[0]->id,
            (int)$gatherings[0]->id,
        );

        $filterCases = [
            'member_sca_name' => (int)$members[0]->id,
            'awards' => (int)$awards[0]->id,
            'gathering_name' => (int)$gatherings[0]->id,
        ];
        foreach ($filterCases as $column => $value) {
            $this->get('/awards/bestowals/grid-data?' . http_build_query([
                'ignore_default' => 1,
                'search' => $prefix,
                'filter' => [$column => [$value]],
            ]));

            $this->assertResponseOk();
            $this->assertResponseContains('data-id="' . $earlier->id . '"');
            $this->assertResponseNotContains('data-id="' . $later->id . '"');
        }

        foreach (['member_sca_name', 'awards', 'gathering_name'] as $sortColumn) {
            $this->get('/awards/bestowals/grid-data?' . http_build_query([
                'ignore_default' => 1,
                'search' => $prefix,
                'sort' => $sortColumn,
                'direction' => 'asc',
            ]));

            $this->assertResponseOk();
            $body = (string)$this->_response->getBody();
            $this->assertLessThan(
                strpos($body, 'data-id="' . $later->id . '"'),
                strpos($body, 'data-id="' . $earlier->id . '"'),
            );
        }

        $this->get('/awards/bestowals/grid-data?' . http_build_query([
            'ignore_default' => 1,
            'columns' => 'id,gathering_name',
            'filter' => ['gathering_name' => [(int)$gatherings[0]->id]],
        ]));

        $this->assertResponseOk();
        $this->assertResponseContains(
            '/gatherings/view/' . $gatherings[0]->public_id . '?tab=gathering-bestowals',
        );
    }

    public function testMemberColumnFallsBackToStoredScaNameWithoutLinkedMember(): void
    {
        $recipientName = 'Unlinked Recipient ' . uniqid();
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->select(['id'])->firstOrFail();
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->select(['id'])->firstOrFail();
        $bestowal = $this->createBestowal(
            null,
            (int)$award->id,
            (int)$gathering->id,
            $recipientName,
        );

        $this->get('/awards/bestowals/grid-data?' . http_build_query([
            'ignore_default' => 1,
            'columns' => 'id,member_sca_name',
            'sort' => 'id',
            'direction' => 'desc',
        ]));

        $this->assertResponseOk();
        $this->assertResponseContains('data-id="' . $bestowal->id . '"');
        $this->assertResponseContains($recipientName);
    }

    public function testTodoSummarySortsByDisplayedOpenCount(): void
    {
        $prefix = 'bestowal-grid-todos-' . uniqid();
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->select(['id'])->firstOrFail();
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->select(['id'])->firstOrFail();
        $memberWithNone = $this->createMember($prefix . '-none');
        $memberWithTwo = $this->createMember($prefix . '-two');
        $none = $this->createBestowal((int)$memberWithNone->id, (int)$award->id, (int)$gathering->id);
        $two = $this->createBestowal((int)$memberWithTwo->id, (int)$award->id, (int)$gathering->id);
        $this->createTodo((int)$two->id, 'grid-sort-one');
        $this->createTodo((int)$two->id, 'grid-sort-two');

        $this->get('/awards/bestowals/grid-data?' . http_build_query([
            'ignore_default' => 1,
            'search' => $prefix,
            'sort' => 'todos_summary',
            'direction' => 'desc',
        ]));

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertLessThan(
            strpos($body, 'data-id="' . $none->id . '"'),
            strpos($body, 'data-id="' . $two->id . '"'),
        );
    }

    public function testAwardTypeAndGroupFiltersUseAwardTaxonomy(): void
    {
        $prefix = 'bestowal-grid-taxonomy-' . uniqid();
        $domains = $this->getTableLocator()->get('Awards.Domains')
            ->find()
            ->select(['id'])
            ->orderBy(['id' => 'ASC'])
            ->limit(2)
            ->all()
            ->toList();
        $branches = $this->getTableLocator()->get('Branches')
            ->find()
            ->select(['id'])
            ->orderBy(['id' => 'ASC'])
            ->limit(2)
            ->all()
            ->toList();
        $level = $this->getTableLocator()->get('Awards.Levels')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $this->assertCount(2, $domains);
        $this->assertCount(2, $branches);

        $matchingAward = $this->createAward(
            $prefix . '-matching',
            (int)$domains[0]->id,
            (int)$level->id,
            (int)$branches[0]->id,
        );
        $otherAward = $this->createAward(
            $prefix . '-other',
            (int)$domains[1]->id,
            (int)$level->id,
            (int)$branches[1]->id,
        );
        $matchingMember = $this->createMember($prefix . '-matching');
        $otherMember = $this->createMember($prefix . '-other');
        $matching = $this->createBestowal(
            (int)$matchingMember->id,
            (int)$matchingAward->id,
            (int)$gathering->id,
        );
        $other = $this->createBestowal(
            (int)$otherMember->id,
            (int)$otherAward->id,
            (int)$gathering->id,
        );

        foreach (
            [
                'award_type' => (int)$domains[0]->id,
                'award_group' => (int)$branches[0]->id,
            ] as $column => $value
        ) {
            $this->get('/awards/bestowals/grid-data?' . http_build_query([
                'ignore_default' => 1,
                'search' => $prefix,
                'filter' => [$column => [$value]],
            ]));

            $this->assertResponseOk();
            $this->assertResponseContains('data-id="' . $matching->id . '"');
            $this->assertResponseNotContains('data-id="' . $other->id . '"');
        }
    }

    public function testRemovingFinalFilterReturnsRequestedTableFrame(): void
    {
        $prefix = 'bestowal-grid-remove-filter-' . uniqid();
        $member = $this->createMember($prefix);
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->select(['id'])->firstOrFail();
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->select(['id'])->firstOrFail();
        $this->createBestowal((int)$member->id, (int)$award->id, (int)$gathering->id);

        $this->configRequest(['headers' => ['Turbo-Frame' => 'bestowals-grid-table']]);
        $this->get('/awards/bestowals/grid-data?' . http_build_query([
            'view_id' => 'sys-bestowals-active',
            'dirty' => ['filters' => 1],
            'search' => $prefix,
        ]));

        $this->assertResponseOk();
        $this->assertResponseContains('<turbo-frame');
        $this->assertResponseContains('id="bestowals-grid-table"');
        $this->assertResponseContains($prefix);
    }

    private function createMember(string $scaName): Member
    {
        $members = $this->getTableLocator()->get('Members');
        $member = $members->newEntity([
            'password' => 'VeryStrongPassword123!',
            'sca_name' => $scaName,
            'first_name' => 'Grid',
            'last_name' => 'Tester',
            'street_address' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'phone_number' => '',
            'email_address' => 'grid-' . uniqid() . '@example.test',
            'birth_month' => 1,
            'birth_year' => 1990,
        ]);

        return $members->saveOrFail($member);
    }

    private function createBestowal(
        ?int $memberId,
        int $awardId,
        int $gatheringId,
        ?string $memberScaName = null,
    ): Bestowal {
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => $memberId,
            'member_sca_name' => $memberScaName,
            'award_id' => $awardId,
            'gathering_id' => $gatheringId,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]);

        return $bestowals->saveOrFail($bestowal);
    }

    private function createAward(string $name, int $domainId, int $levelId, int $branchId): Award
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $award = $awards->newEntity([
            'name' => $name,
            'abbreviation' => 'AT-' . substr(sha1($name), 0, 12),
            'domain_id' => $domainId,
            'level_id' => $levelId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return $awards->saveOrFail($award);
    }

    private function createTodo(int $bestowalId, string $sourceRef): ActionItem
    {
        $actionItems = $this->getTableLocator()->get('ActionItems');
        $todo = $actionItems->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId,
            'title' => $sourceRef,
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => false,
            'sort_order' => 1,
            'source_ref' => $sourceRef,
        ]);

        return $actionItems->saveOrFail($todo);
    }
}
