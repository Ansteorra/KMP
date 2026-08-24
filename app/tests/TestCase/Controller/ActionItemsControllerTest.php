<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\AppController;
use App\KMP\GridColumns\ActionItemsGridColumns;
use App\Model\Entity\ActionItem;
use App\Model\Entity\GridView;
use App\Services\ActionItems\ActionItemCompletionFormRegistry;
use App\Services\GridViewService;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalTodoCompletionFormProvider;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * ActionItemsController integration tests - the "My To-Dos" surface.
 */
class ActionItemsControllerTest extends HttpIntegrationTestCase
{
    /**
     * Persist an open, member-assigned action item.
     *
     * @param int $memberId The eligible member id
     * @param array<string, mixed> $overrides Field overrides
     * @return \App\Model\Entity\ActionItem
     */
    private function makeMemberItem(int $memberId, array $overrides = []): ActionItem
    {
        $table = TableRegistry::getTableLocator()->get('ActionItems');
        $data = array_merge([
            'entity_type' => 'Awards.Bestowals',
            'entity_id' => 999999,
            'title' => 'Scroll finished',
            'description' => 'Calligraphy complete',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => $memberId],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 1,
        ], $overrides);
        $entity = $table->newEntity($data);

        return $table->saveOrFail($entity);
    }

    private function makeBestowal(array $overrides = []): Bestowal
    {
        $award = TableRegistry::getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');

        return $bestowals->saveOrFail($bestowals->newEntity(array_merge([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Todo Recipient',
            'award_id' => $award->id,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ], $overrides)));
    }

    /**
     * Unauthenticated requests are redirected to login.
     *
     * @return void
     */
    public function testMyTasksRequiresAuthentication(): void
    {
        $this->get('/action-items/my-tasks');
        $this->assertResponseCode(302);
    }

    /**
     * The My To-Dos page renders the grid shell.
     *
     * @return void
     */
    public function testMyTasksRendersGridShell(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/my-tasks');

        $this->assertResponseOk();
        $this->assertResponseContains('My To-Dos');
        $this->assertResponseContains('action-items-grid');
        $this->assertResponseContains('todoCompleteModal');
    }

    /**
     * The grid data endpoint lists open items the member may act on.
     *
     * @return void
     */
    public function testMyTasksGridDataListsEligibleOpenItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $this->assertResponseContains('Scroll finished');
    }

    public function testMyTasksGridAllowsCustomViews(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $gridState = $this->viewVariable('gridState');
        $this->assertTrue($gridState['config']['canAddViews']);
        $this->assertSame(ActionItem::STATUS_OPEN, $gridState['filters']['active']['status_label']);
        $this->assertSame(
            ActionItemsGridColumns::MEMBER_SCOPE_ACTIONABLE,
            $gridState['filters']['active']['member_scope'],
        );
        $this->assertContains('status_label', $gridState['config']['lockedFilters']);
        $this->assertContains('member_scope', $gridState['config']['lockedFilters']);
        $this->assertFalse($gridState['filters']['available']['member_scope']['showInFilterMenu']);
        $this->assertResponseContains('data-action="click->grid-view#saveView"');
    }

    public function testCustomMyTasksViewPreservesOpenScopeFilters(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $mine = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'Inherited Open To-Do Mine',
            'entity_id' => 999991,
        ]);
        $other = $this->makeMemberItem(self::TEST_MEMBER_BRYCE_ID, [
            'title' => 'Inherited Open To-Do Other',
            'entity_id' => 999992,
        ]);
        $viewName = 'Custom Open To-Dos ' . uniqid();
        $gridView = $this->createCustomActionItemsView(
            self::TEST_MEMBER_AGATHA_ID,
            $viewName,
            ActionItem::STATUS_OPEN,
            ActionItemsGridColumns::MEMBER_SCOPE_ACTIONABLE,
        );
        $this->assertSame(
            ['status_label', 'member_scope'],
            array_column($gridView->getConfigArray()['filters'], 'field'),
        );

        $this->get('/action-items/my-tasks-data?' . http_build_query([
            'view_id' => $gridView->id,
            'search' => 'Inherited Open To-Do',
        ]));

        $this->assertResponseOk();
        $rows = array_values(iterator_to_array($this->viewVariable('data')));
        $rowIds = array_map(static fn($row): int => (int)$row->id, $rows);
        $this->assertContains((int)$mine->id, $rowIds);
        $this->assertNotContains((int)$other->id, $rowIds);
        $gridState = $this->viewVariable('gridState');
        $this->assertSame((int)$gridView->id, $gridState['view']['currentId']);
        $this->assertSame($viewName, $gridState['view']['currentName']);
        $this->assertSame([ActionItem::STATUS_OPEN], $gridState['filters']['active']['status_label']);
        $this->assertSame(
            [ActionItemsGridColumns::MEMBER_SCOPE_ACTIONABLE],
            $gridState['filters']['active']['member_scope'],
        );
        $this->assertContains('member_scope', $gridState['config']['lockedFilters']);
        $this->assertFalse($gridState['filters']['available']['member_scope']['showInFilterMenu']);
    }

    public function testCustomMyTasksViewPreservesCompletedScopeFilters(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $mine = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'Inherited Completed To-Do Mine',
            'entity_id' => 999993,
            'status' => ActionItem::STATUS_COMPLETED,
            'completed_by' => self::TEST_MEMBER_AGATHA_ID,
            'completed_at' => DateTime::now(),
        ]);
        $other = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'Inherited Completed To-Do Other',
            'entity_id' => 999994,
            'status' => ActionItem::STATUS_COMPLETED,
            'completed_by' => self::TEST_MEMBER_BRYCE_ID,
            'completed_at' => DateTime::now(),
        ]);
        $viewName = 'Custom Completed To-Dos ' . uniqid();
        $gridView = $this->createCustomActionItemsView(
            self::TEST_MEMBER_AGATHA_ID,
            $viewName,
            ActionItem::STATUS_COMPLETED,
            ActionItemsGridColumns::MEMBER_SCOPE_COMPLETED_BY_ME,
        );
        $this->assertSame(
            ['status_label', 'member_scope'],
            array_column($gridView->getConfigArray()['filters'], 'field'),
        );

        $this->get('/action-items/my-tasks-data?' . http_build_query([
            'view_id' => $gridView->id,
            'search' => 'Inherited Completed To-Do',
        ]));

        $this->assertResponseOk();
        $rows = array_values(iterator_to_array($this->viewVariable('data')));
        $rowIds = array_map(static fn($row): int => (int)$row->id, $rows);
        $this->assertContains((int)$mine->id, $rowIds);
        $this->assertNotContains((int)$other->id, $rowIds);
        $gridState = $this->viewVariable('gridState');
        $this->assertSame((int)$gridView->id, $gridState['view']['currentId']);
        $this->assertSame($viewName, $gridState['view']['currentName']);
        $this->assertSame([ActionItem::STATUS_COMPLETED], $gridState['filters']['active']['status_label']);
        $this->assertSame(
            [ActionItemsGridColumns::MEMBER_SCOPE_COMPLETED_BY_ME],
            $gridState['filters']['active']['member_scope'],
        );
        $this->assertContains('member_scope', $gridState['config']['lockedFilters']);
        $this->assertFalse($gridState['filters']['available']['member_scope']['showInFilterMenu']);
    }

    public function testUpdatingCustomMyTasksViewPreservesLockedScopeFilters(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $gridView = $this->createCustomActionItemsView(
            self::TEST_MEMBER_AGATHA_ID,
            'Immutable Open To-Dos ' . uniqid(),
            ActionItem::STATUS_OPEN,
            ActionItemsGridColumns::MEMBER_SCOPE_ACTIONABLE,
        );

        $updated = (new GridViewService())->updateView($gridView->id, [
            'config' => json_encode([
                'filters' => [
                    [
                        'field' => 'member_scope',
                        'operator' => 'in',
                        'value' => [ActionItemsGridColumns::MEMBER_SCOPE_COMPLETED_BY_ME],
                    ],
                    [
                        'field' => 'title',
                        'operator' => 'in',
                        'value' => ['User filter'],
                    ],
                ],
            ]),
        ], $member);

        $this->assertNotFalse($updated);
        $filters = array_column($updated->getConfigArray()['filters'], null, 'field');
        $this->assertSame([ActionItem::STATUS_OPEN], $filters['status_label']['value']);
        $this->assertTrue($filters['status_label']['locked']);
        $this->assertSame(
            [ActionItemsGridColumns::MEMBER_SCOPE_ACTIONABLE],
            $filters['member_scope']['value'],
        );
        $this->assertTrue($filters['member_scope']['locked']);
        $this->assertSame(['User filter'], $filters['title']['value']);

        $this->assertFalse((new GridViewService())->updateView($gridView->id, [
            'config' => 'null',
        ], $member));
    }

    public function testMyTasksGridProvidesDistinctScopedTodoFilterOptions(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $prefix = 'Scoped Filter ' . uniqid() . ' ';
        $alphaTitle = $prefix . 'Alpha';
        $betaTitle = $prefix . 'Beta';
        $alpha = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => $alphaTitle,
            'entity_id' => 910001,
        ]);
        $betaOne = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => $betaTitle,
            'entity_id' => 910002,
        ]);
        $betaTwo = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => $betaTitle,
            'entity_id' => 910003,
        ]);
        $otherMemberTitle = $prefix . 'Other Member';
        $this->makeMemberItem(self::TEST_MEMBER_BRYCE_ID, [
            'title' => $otherMemberTitle,
            'entity_id' => 910004,
        ]);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $titleOptions = array_values(array_filter(
            $this->viewVariable('filterOptions')['title'],
            static fn(array $option): bool => str_starts_with((string)$option['value'], $prefix),
        ));
        $this->assertSame([
            ['value' => $alphaTitle, 'label' => $alphaTitle],
            ['value' => $betaTitle, 'label' => $betaTitle],
        ], $titleOptions);
        $this->assertSame(
            $this->viewVariable('filterOptions')['title'],
            $this->viewVariable('gridState')['filters']['available']['title']['options'],
        );
        $this->assertNotContains($otherMemberTitle, array_column($titleOptions, 'value'));

        $this->get('/action-items/my-tasks-data?' . http_build_query([
            'filter' => ['title' => $betaTitle],
        ]));

        $this->assertResponseOk();
        $rowIds = $this->currentGridRowIds();
        $this->assertNotContains((int)$alpha->id, $rowIds);
        $this->assertContains((int)$betaOne->id, $rowIds);
        $this->assertContains((int)$betaTwo->id, $rowIds);
    }

    public function testMyTasksGridFiltersRequiredAndOptionalTodos(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $required = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'Required Filter ' . uniqid(),
            'entity_id' => 920001,
            'is_gating' => true,
        ]);
        $optional = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'Optional Filter ' . uniqid(),
            'entity_id' => 920002,
            'is_gating' => false,
        ]);

        $this->get('/action-items/my-tasks-data?' . http_build_query([
            'filter' => ['requirement' => '1'],
        ]));

        $this->assertResponseOk();
        $requiredRowIds = $this->currentGridRowIds();
        $this->assertContains((int)$required->id, $requiredRowIds);
        $this->assertNotContains((int)$optional->id, $requiredRowIds);

        $this->get('/action-items/my-tasks-data?' . http_build_query([
            'filter' => ['requirement' => '0'],
        ]));

        $this->assertResponseOk();
        $optionalRowIds = $this->currentGridRowIds();
        $this->assertNotContains((int)$required->id, $optionalRowIds);
        $this->assertContains((int)$optional->id, $optionalRowIds);
        $this->assertSame('0', $this->viewVariable('gridState')['filters']['active']['requirement']);
    }

    public function testMyTasksGridFiltersByGatheringAndNone(): void
    {
        $this->authenticateAsMember(self::ADMIN_MEMBER_ID);
        $assignedBestowal = $this->makeBestowal();
        $schedule = $this->makeScheduledCourtForAward((int)$assignedBestowal->award_id);
        $assignedBestowal->gathering_id = $schedule['gathering']->id;
        TableRegistry::getTableLocator()->get('Awards.Bestowals')->saveOrFail($assignedBestowal);
        $unassignedBestowal = $this->makeBestowal();

        $assigned = $this->makeMemberItem(self::ADMIN_MEMBER_ID, [
            'title' => 'Assigned Gathering Filter ' . uniqid(),
            'entity_id' => (int)$assignedBestowal->id,
        ]);
        $unassigned = $this->makeMemberItem(self::ADMIN_MEMBER_ID, [
            'title' => 'Unassigned Gathering Filter ' . uniqid(),
            'entity_id' => (int)$unassignedBestowal->id,
        ]);
        $generic = $this->makeMemberItem(self::ADMIN_MEMBER_ID, [
            'entity_type' => 'Members',
            'entity_id' => self::ADMIN_MEMBER_ID,
            'title' => 'Generic Gathering Filter ' . uniqid(),
        ]);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $gatheringOptions = $this->viewVariable('filterOptions')['gathering'];
        $this->assertContains([
            'value' => ActionItemsGridColumns::NO_GATHERING_FILTER_VALUE,
            'label' => 'None',
        ], $gatheringOptions);
        $this->assertContains([
            'value' => (string)$schedule['gathering']->id,
            'label' => (string)$schedule['gathering']->name,
        ], $gatheringOptions);
        $this->assertSame(
            $gatheringOptions,
            $this->viewVariable('gridState')['filters']['available']['gathering']['options'],
        );

        $this->configRequest(['headers' => ['Turbo-Frame' => 'action-items-grid-table']]);
        $this->get('/action-items/my-tasks-data?' . http_build_query([
            'filter' => ['gathering' => (string)$schedule['gathering']->id],
        ]));

        $this->assertResponseOk();
        $this->assertContains(
            ['value' => (string)$schedule['gathering']->id, 'label' => (string)$schedule['gathering']->name],
            $this->viewVariable('gridState')['filters']['available']['gathering']['options'],
        );
        $assignedRowIds = $this->currentGridRowIds();
        $this->assertContains((int)$assigned->id, $assignedRowIds);
        $this->assertNotContains((int)$unassigned->id, $assignedRowIds);
        $this->assertNotContains((int)$generic->id, $assignedRowIds);

        $this->get('/action-items/my-tasks-data?' . http_build_query([
            'filter' => ['gathering' => ActionItemsGridColumns::NO_GATHERING_FILTER_VALUE],
        ]));

        $this->assertResponseOk();
        $noneRowIds = $this->currentGridRowIds();
        $this->assertNotContains((int)$assigned->id, $noneRowIds);
        $this->assertContains((int)$unassigned->id, $noneRowIds);
        $this->assertContains((int)$generic->id, $noneRowIds);

        $this->get('/action-items/my-tasks-data?' . http_build_query([
            'filter' => ['gathering' => [
                (string)$schedule['gathering']->id,
                ActionItemsGridColumns::NO_GATHERING_FILTER_VALUE,
            ]],
        ]));

        $this->assertResponseOk();
        $combinedRowIds = $this->currentGridRowIds();
        $this->assertContains((int)$assigned->id, $combinedRowIds);
        $this->assertContains((int)$unassigned->id, $combinedRowIds);
        $this->assertContains((int)$generic->id, $combinedRowIds);
        $this->assertSame($combinedRowIds, array_values(array_unique($combinedRowIds)));
    }

    public function testMyTasksGridDataShowsOperationalBestowalDetails(): void
    {
        $this->authenticateAsMember(self::ADMIN_MEMBER_ID);
        $bestowal = $this->makeBestowal([
            'specialty' => 'Scribal Arts',
            'reason_summary' => 'Reason detail that should not appear',
            'noble_notes' => 'Noble note that should not appear',
        ]);
        $schedule = $this->makeScheduledCourtForAward((int)$bestowal->award_id);
        $bestowal->gathering_id = $schedule['gathering']->id;
        $bestowal->gathering_scheduled_activity_id = $schedule['scheduledActivity']->id;
        TableRegistry::getTableLocator()->get('Awards.Bestowals')->saveOrFail($bestowal);
        $this->makeMemberItem(self::ADMIN_MEMBER_ID, [
            'entity_id' => (int)$bestowal->id,
        ]);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $this->assertResponseContains('Specializations');
        $this->assertResponseContains('Scribal Arts');
        $this->assertResponseContains('Gathering Date');
        $this->assertResponseContains($schedule['gathering']->start_date->format('Y-m-d'));
        $this->assertResponseContains('Hosting Group');
        $this->assertResponseContains('Court Assigned');
        $this->assertResponseContains('Evening Court');
        $this->assertResponseContains(
            '/gatherings/view/' . $schedule['gathering']->public_id . '?tab=gathering-bestowals',
        );
        $this->assertResponseNotContains('Reason detail that should not appear');
        $this->assertResponseNotContains('Noble note that should not appear');
        $this->assertResponseNotContains('Linked Recommendation');

        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);
        $this->get('/action-items/mobile-data');
        $payload = json_decode((string)$this->_response->getBody(), true);
        $gatheringDetail = collection($payload['groups'][0]['details'] ?? [])
            ->firstMatch(['label' => 'Gathering']);
        $this->assertSame(
            '/gatherings/view/' . $schedule['gathering']->public_id . '?tab=gathering-bestowals',
            $gatheringDetail['url'] ?? null,
        );
    }

    public function testCompleteButtonsDoNotRenderCheckmarkIcons(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $this->assertResponseContains('>Complete</button>');
        $this->assertResponseNotContains('bi-check2');
    }

    public function testMyTasksGridDataIncludesProviderCompletionFormMetadata(): void
    {
        ActionItemCompletionFormRegistry::register(
            'AwardsBestowals',
            new BestowalTodoCompletionFormProvider(),
        );
        $this->authenticateAsMember(self::ADMIN_MEMBER_ID);
        $bestowal = $this->makeBestowal();
        $this->makeMemberItem(self::ADMIN_MEMBER_ID, [
            'entity_id' => (int)$bestowal->id,
            'title' => 'Event Scheduled',
            'source_ref' => 'event_scheduled',
            'completion_config' => [
                'required_fields' => [
                    [
                        'provider' => BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_GATHERING,
                        'field' => BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING,
                        'conditional_complete_on_assign' => true,
                    ],
                ],
            ],
        ]);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $this->assertCompletionFormMetadataContainsBestowalGatheringField((int)$bestowal->id);
    }

    public function testMyTasksGridDataIncludesEventScheduledFallbackCompletionFormMetadata(): void
    {
        ActionItemCompletionFormRegistry::register(
            'AwardsBestowals',
            new BestowalTodoCompletionFormProvider(),
        );
        $this->authenticateAsMember(self::ADMIN_MEMBER_ID);
        $bestowal = $this->makeBestowal();
        $this->makeMemberItem(self::ADMIN_MEMBER_ID, [
            'entity_id' => (int)$bestowal->id,
            'title' => 'Event Scheduled',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
        ]);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $this->assertCompletionFormMetadataContainsBestowalGatheringField((int)$bestowal->id);
    }

    public function testMyTasksGridDataIncludesAddedToAgendaCourtSlotCompletionFormMetadata(): void
    {
        ActionItemCompletionFormRegistry::register(
            'AwardsBestowals',
            new BestowalTodoCompletionFormProvider(),
        );
        $this->authenticateAsMember(self::ADMIN_MEMBER_ID);
        $bestowal = $this->makeBestowal();
        $schedule = $this->makeScheduledCourtForAward((int)$bestowal->award_id);
        $bestowal->gathering_id = $schedule['gathering']->id;
        TableRegistry::getTableLocator()->get('Awards.Bestowals')->saveOrFail($bestowal);
        $this->makeMemberItem(self::ADMIN_MEMBER_ID, [
            'entity_id' => (int)$bestowal->id,
            'title' => 'Event Scheduled',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
            'status' => ActionItem::STATUS_COMPLETED,
            'sort_order' => 10,
        ]);
        $this->makeMemberItem(self::ADMIN_MEMBER_ID, [
            'entity_id' => (int)$bestowal->id,
            'title' => 'Added to Agenda',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            'sort_order' => 20,
        ]);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $completionForm = $this->extractCompletionFormMetadata();
        $this->assertSame('Add Bestowal to Agenda', $completionForm['title'] ?? null);
        $this->assertSame('select', $completionForm['fields'][0]['type'] ?? null);
        $this->assertSame('gathering_scheduled_activity_id', $completionForm['fields'][0]['name'] ?? null);
        $this->assertArrayHasKey('roaming', $completionForm['fields'][0]['options'] ?? []);
        $this->assertArrayHasKey((string)$schedule['scheduledActivity']->id, $completionForm['fields'][0]['options'] ?? []);
    }

    private function assertCompletionFormMetadataContainsBestowalGatheringField(int $bestowalId): void
    {
        $completionForm = $this->extractCompletionFormMetadata();
        $this->assertSame('Schedule Bestowal Event', $completionForm['title'] ?? null);
        $this->assertSame('bestowal_gathering_id', $completionForm['fields'][0]['valueName'] ?? null);
        $this->assertSame(
            '/awards/bestowals/gatherings-for-bestowal-auto-complete/' . $bestowalId,
            $completionForm['fields'][0]['url'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCompletionFormMetadata(): array
    {
        $response = (string)$this->_response->getBody();
        $this->assertMatchesRegularExpression("/data-todo-completion-form='([^']+)'/", $response);
        preg_match("/data-todo-completion-form='([^']+)'/", $response, $matches);

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5), true);
    }

    /**
     * @param int $awardId Award primary key.
     * @return array{gathering: object, scheduledActivity: object}
     */
    private function makeScheduledCourtForAward(int $awardId): array
    {
        $suffix = uniqid('', true);
        $gatheringActivities = TableRegistry::getTableLocator()->get('GatheringActivities');
        $awardGatheringActivities = TableRegistry::getTableLocator()->get('Awards.AwardGatheringActivities');
        $gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $scheduledActivities = TableRegistry::getTableLocator()->get('GatheringScheduledActivities');
        $gatheringActivityLinks = TableRegistry::getTableLocator()->get('GatheringsGatheringActivities');

        $activity = $gatheringActivities->saveOrFail($gatheringActivities->newEntity([
            'name' => 'Todo Court Activity ' . $suffix,
        ]));
        $awardGatheringActivities->saveOrFail($awardGatheringActivities->newEntity([
            'award_id' => $awardId,
            'gathering_activity_id' => $activity->id,
        ]));

        $gatheringType = TableRegistry::getTableLocator()->get('GatheringTypes')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $gathering = $gatherings->saveOrFail($gatherings->newEntity([
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'gathering_type_id' => $gatheringType->id,
            'name' => 'Todo Court Gathering ' . $suffix,
            'start_date' => DateTime::now()->addDays(30),
            'end_date' => DateTime::now()->addDays(31),
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $gatheringActivityLinks->saveOrFail($gatheringActivityLinks->newEntity([
            'gathering_id' => $gathering->id,
            'gathering_activity_id' => $activity->id,
            'sort_order' => 1,
            'not_removable' => false,
        ]));
        $scheduledActivity = $scheduledActivities->saveOrFail($scheduledActivities->newEntity([
            'gathering_id' => $gathering->id,
            'gathering_activity_id' => $activity->id,
            'start_datetime' => DateTime::now()->addDays(30)->addHours(2),
            'has_end_time' => false,
            'display_title' => 'Evening Court',
            'description' => 'Court agenda test session.',
            'pre_register' => false,
            'is_other' => false,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));

        return compact('gathering', 'scheduledActivity');
    }

    /**
     * Mobile queue direct visits redirect to the Auth Card when there is nothing actionable.
     *
     * @return void
     */
    public function testMobileMyTasksRedirectsWhenNoOpenItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/mobile');

        $this->assertRedirectContains('/members/view-mobile-card');
    }

    /**
     * Mobile queue renders when the current member has an open to-do.
     *
     * @return void
     */
    public function testMobileMyTasksReturnsOkWhenOpenItemsExist(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/mobile');

        $this->assertResponseOk();
        $this->assertResponseContains('mobile-action-items');
        $this->assertResponseContains('My To-Dos');
        $this->assertResponseContains(
            'data-mobile-action-items-per-page-value="' . AppController::MOBILE_QUEUE_DEFAULT_PER_PAGE . '"',
        );
    }

    /**
     * Mobile data returns eligible open items as JSON.
     *
     * @return void
     */
    public function testMobileMyTasksDataReturnsEligibleItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        $this->get('/action-items/mobile-data');

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(1, $payload['openCount'] ?? null);
        $this->assertSame('Scroll finished', $payload['groups'][0]['items'][0]['title'] ?? null);
    }

    /**
     * Mobile data is paginated so large to-do queues do not render all cards at once.
     *
     * @return void
     */
    public function testMobileMyTasksDataPaginatesEligibleItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'First task',
            'entity_id' => 999991,
            'sort_order' => 1,
        ]);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'Second task',
            'entity_id' => 999992,
            'sort_order' => 2,
        ]);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'Third task',
            'entity_id' => 999993,
            'sort_order' => 3,
        ]);
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        $this->get('/action-items/mobile-data?per_page=2&page=1');

        $this->assertResponseOk();
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(3, $payload['openCount'] ?? null);
        $this->assertSame(1, $payload['pagination']['page'] ?? null);
        $this->assertSame(2, $payload['pagination']['perPage'] ?? null);
        $this->assertSame(3, $payload['pagination']['total'] ?? null);
        $this->assertSame(2, $payload['pagination']['pageCount'] ?? null);
        $this->assertTrue($payload['pagination']['hasNextPage'] ?? false);
        $this->assertCount(2, $payload['groups'] ?? []);
    }

    /**
     * Items assigned to someone else are not listed in the grid.
     *
     * @return void
     */
    public function testMyTasksGridDataHidesIneligibleItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $this->assertResponseNotContains('Scroll finished');
    }

    /**
     * The completed view lists to-dos the current member has completed.
     *
     * @return void
     */
    public function testCompletedViewListsMyCompletedItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $table = TableRegistry::getTableLocator()->get('ActionItems');
        $completed = $table->saveOrFail($table->newEntity([
            'entity_type' => 'Awards.Bestowals',
            'entity_id' => 999998,
            'title' => 'Regalia allotted',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_COMPLETED,
            'is_gating' => false,
            'sort_order' => 1,
            'completed_by' => self::TEST_MEMBER_AGATHA_ID,
            'completed_at' => DateTime::now(),
        ]));
        $this->assertNotEmpty($completed->id);

        $this->get('/action-items/my-tasks-data?view_id=sys-todos-completed');

        $this->assertResponseOk();
        $this->assertResponseContains('Regalia allotted');
    }

    /**
     * Another member's completed to-do is not listed in my completed view.
     *
     * @return void
     */
    public function testCompletedViewHidesOthersCompletedItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $table = TableRegistry::getTableLocator()->get('ActionItems');
        $table->saveOrFail($table->newEntity([
            'entity_type' => 'Awards.Bestowals',
            'entity_id' => 999997,
            'title' => 'Agatha completed scroll',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_COMPLETED,
            'is_gating' => false,
            'sort_order' => 1,
            'completed_by' => self::TEST_MEMBER_AGATHA_ID,
            'completed_at' => DateTime::now(),
        ]));

        $this->get('/action-items/my-tasks-data?view_id=sys-todos-completed');

        $this->assertResponseOk();
        $this->assertResponseNotContains('Agatha completed scroll');
    }

    /**
     * Completion is POST-only.
     *
     * @return void
     */
    public function testCompleteRejectsGet(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $item = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->get("/action-items/complete/{$item->id}");

        $this->assertResponseCode(405);
    }

    /**
     * A turbo-stream completion refreshes the grid and flips the item complete.
     *
     * @return void
     */
    public function testCompleteViaTurboStreamRefreshesGrid(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $item = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);
        $this->post('/action-items/complete', [
            'id' => $item->id,
            'page_context_url' => '/action-items/my-tasks',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('turbo-stream');
        $this->assertResponseContains('action-items-grid-table');
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isCompleted());
    }

    /**
     * An eligible member can complete their gated item.
     *
     * @return void
     */
    public function testEligibleMemberCanComplete(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $item = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post("/action-items/complete/{$item->id}");

        $this->assertRedirect();
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isCompleted());
    }

    /**
     * Mobile completion can return JSON so cards can be removed without a page reload.
     *
     * @return void
     */
    public function testMobileCompleteReturnsJson(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $item = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);
        $this->post("/action-items/complete/{$item->id}");

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($payload['success'] ?? false);
        $this->assertSame((int)$item->id, $payload['itemId'] ?? null);
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isCompleted());
    }

    /**
     * An ineligible member is forbidden from completing an item.
     *
     * @return void
     */
    public function testIneligibleMemberCannotComplete(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $item = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post("/action-items/complete/{$item->id}");

        $this->assertResponseCode(302);
        $this->assertRedirectContains('unauthorized');
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isOpen());
    }

    private function createCustomActionItemsView(
        int $memberId,
        string $name,
        string $status,
        string $memberScope,
    ): GridView {
        $currentUser = $this->getTableLocator()->get('Members')->get($memberId);
        $gridView = (new GridViewService())->createView([
            'grid_key' => 'Core.actionItems.myTasks',
            'name' => $name,
            'config' => json_encode([
                'filters' => [
                    [
                        'field' => 'status_label',
                        'operator' => 'in',
                        'value' => [$status],
                        'locked' => true,
                    ],
                    [
                        'field' => 'member_scope',
                        'operator' => 'in',
                        'value' => [$memberScope],
                        'locked' => true,
                    ],
                ],
                'sort' => [],
                'columns' => [],
                'pageSize' => 25,
            ]),
        ], $currentUser);
        $this->assertNotFalse($gridView, 'Failed to create custom My To-Dos grid view');

        return $gridView;
    }

    /**
     * @return array<int>
     */
    private function currentGridRowIds(): array
    {
        $rows = array_values(iterator_to_array($this->viewVariable('data')));

        return array_map(static fn($row): int => (int)$row->id, $rows);
    }
}
