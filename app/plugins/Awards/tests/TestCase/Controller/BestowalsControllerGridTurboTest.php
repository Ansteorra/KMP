<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\ActionItem;
use App\Model\Entity\Member;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Cake\Core\ContainerInterface as CakeContainerInterface;
use Cake\ORM\TableRegistry;
use Closure;

/**
 * Turbo stream grid row sync for BestowalsController.
 */
class BestowalsControllerGridTurboTest extends HttpIntegrationTestCase
{
    private $workflowDefinitions;

    private $workflowVersions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();

        $locator = TableRegistry::getTableLocator();
        $this->workflowDefinitions = $locator->get('WorkflowDefinitions');
        $this->workflowVersions = $locator->get('WorkflowVersions');

        $this->mockServiceClean(CakeContainerInterface::class, function () {
            return $this->createMock(CakeContainerInterface::class);
        });
    }

    public function testEditFromGridReturnsRowReplaceStreamOnMainIndex(): void
    {
        $this->ensureActiveWorkflow('awards-bestowal-update');
        $bestowal = $this->createExistingBestowal();
        $this->createOpenBestowalTodo((int)$bestowal->id);

        $this->mockServiceClean(TriggerDispatcher::class, function () use ($bestowal) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use ($bestowal): array {
                    if ($event === 'Awards.BestowalUpdateRequested') {
                        $this->assertSame((int)$bestowal->id, $context['bestowalId']);

                        return [$this->successfulWorkflowDispatchResult([
                            'bestowalId' => (int)$bestowal->id,
                        ])];
                    }

                    return [];
                });

            return $mock;
        });

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/awards/bestowals/edit/' . $bestowal->id, [
            'page_context_url' => '/awards/bestowals',
            'note' => 'Grid turbo row sync test',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains(
            '<turbo-stream action="replace" target="bestowals-grid-row-' . $bestowal->id . '"',
        );
        $this->assertResponseContains('data-grid-view-target="rowCheckbox"');
        $this->assertResponseContains('aria-label=');
        $this->assertResponseContains('data-bulk-todo-options=');
        $this->assertResponseNotContains('target="bestowals-grid-table"');
    }

    public function testEditFromGridFallsBackToTableRefreshOutsideMainIndex(): void
    {
        $this->ensureActiveWorkflow('awards-bestowal-update');
        $bestowal = $this->createExistingBestowal();

        $this->mockServiceClean(TriggerDispatcher::class, function () use ($bestowal) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturnCallback(function (string $event) use ($bestowal): array {
                    if ($event === 'Awards.BestowalUpdateRequested') {
                        return [$this->successfulWorkflowDispatchResult([
                            'bestowalId' => (int)$bestowal->id,
                        ])];
                    }

                    return [];
                });

            return $mock;
        });

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/awards/bestowals/edit/' . $bestowal->id, [
            'page_context_url' => '/awards/bestowals/table/draft',
            'note' => 'Outside main index',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('<turbo-stream action="replace" target="bestowals-grid-table"');
    }

    public function testActiveSystemViewExcludesClosedBestowals(): void
    {
        $activeMemberName = 'bestowal-active-filter-active-' . uniqid();
        $closedMemberName = 'bestowal-active-filter-closed-' . uniqid();
        $activeMember = $this->createGridMember($activeMemberName);
        $closedMember = $this->createGridMember($closedMemberName);

        $this->createBestowalForMember((int)$activeMember->id, 'Created', 'Planning');
        $this->createBestowalForMember((int)$closedMember->id, 'Given', 'Closed');

        $url = '/awards/bestowals/grid-data?' . http_build_query([
            'view_id' => 'sys-bestowals-active',
            'search' => 'bestowal-active-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Active Bestowals');
        $this->assertResponseContains($activeMemberName);
        $this->assertResponseNotContains($closedMemberName);
    }

    public function testCompletedSystemViewShowsGivenBestowalsOnly(): void
    {
        $givenMemberName = 'bestowal-completed-filter-given-' . uniqid();
        $activeMemberName = 'bestowal-completed-filter-active-' . uniqid();
        $givenMember = $this->createGridMember($givenMemberName);
        $activeMember = $this->createGridMember($activeMemberName);

        $this->createBestowalForMember((int)$givenMember->id, 'Given', 'Closed');
        $this->createBestowalForMember((int)$activeMember->id, 'Created', 'Planning');

        $url = '/awards/bestowals/grid-data?' . http_build_query([
            'view_id' => 'sys-bestowals-completed',
            'search' => 'bestowal-completed-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Completed');
        $this->assertResponseContains($givenMemberName);
        $this->assertResponseNotContains($activeMemberName);
    }

    private function createExistingBestowal(): Bestowal
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
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]);
        $bestowals->saveOrFail($bestowal);

        return $bestowal;
    }

    private function createGridMember(string $scaName): Member
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

        $savedMember = $members->saveOrFail($member);
        $this->assertInstanceOf(Member::class, $savedMember);

        return $savedMember;
    }

    private function createBestowalForMember(int $memberId, string $state, string $status): Bestowal
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $lifecycleStatus = $state === 'Given'
            ? Bestowal::LIFECYCLE_GIVEN
            : Bestowal::LIFECYCLE_OPEN;

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => $memberId,
            'award_id' => $award->id,
            'lifecycle_status' => $lifecycleStatus,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]);
        $bestowals->saveOrFail($bestowal);

        return $bestowal;
    }

    /**
     * @param string $class Class name
     * @param \Closure $factory Factory
     * @return void
     */
    private function mockServiceClean(string $class, Closure $factory): void
    {
        if ($class !== TriggerDispatcher::class) {
            $this->mockService($class, $factory);

            return;
        }

        $dispatcher = $factory();
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->method('dispatchTrigger')
            ->willReturnCallback(
                fn(string $eventName, array $eventData = [], ?int $triggeredBy = null): array => $dispatcher->dispatch(
                    $eventName,
                    $eventData,
                    $triggeredBy,
                ),
            );
        $this->mockService(WorkflowEngineInterface::class, static fn() => $engine);
    }

    private function ensureActiveWorkflow(string $slug): void
    {
        $definition = $this->workflowDefinitions->find()->where(['slug' => $slug])->first();

        if (!$definition) {
            $definition = $this->workflowDefinitions->newEntity([
                'name' => "Test {$slug}",
                'slug' => $slug,
                'description' => "Test workflow for {$slug}",
                'trigger_type' => 'event',
                'is_active' => true,
                'created_by' => self::ADMIN_MEMBER_ID,
                'modified_by' => self::ADMIN_MEMBER_ID,
            ]);
            $this->workflowDefinitions->saveOrFail($definition);
        }

        if (!$definition->current_version_id) {
            $version = $this->workflowVersions->newEntity([
                'workflow_definition_id' => $definition->id,
                'version_number' => 1,
                'status' => 'published',
                'definition' => ['nodes' => [], 'edges' => []],
                'created_by' => self::ADMIN_MEMBER_ID,
                'modified_by' => self::ADMIN_MEMBER_ID,
            ]);
            $this->workflowVersions->saveOrFail($version);
            $definition->current_version_id = $version->id;
        }

        $definition->is_active = true;
        $this->workflowDefinitions->saveOrFail($definition);
    }

    private function createOpenBestowalTodo(int $bestowalId): ActionItem
    {
        $table = TableRegistry::getTableLocator()->get('ActionItems');

        return $table->saveOrFail($table->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId,
            'title' => 'Event Scheduled',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 1,
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
        ]));
    }

    /**
     * @param array<string, mixed> $data Workflow result data
     * @return \App\Services\ServiceResult
     */
    private function successfulWorkflowDispatchResult(array $data): ServiceResult
    {
        return new ServiceResult(true, null, [
            'workflowResult' => [
                'success' => true,
                'data' => $data,
            ],
        ]);
    }
}
