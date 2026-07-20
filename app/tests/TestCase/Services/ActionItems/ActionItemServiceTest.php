<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\ActionItems;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemCompletionForm;
use App\Services\ActionItems\ActionItemCompletionFormProviderInterface;
use App\Services\ActionItems\ActionItemCompletionFormRegistry;
use App\Services\ActionItems\ActionItemService;
use App\Services\ServiceResult;
use App\Test\TestCase\BaseTestCase;
use Cake\Cache\Cache;
use Cake\ORM\TableRegistry;

class ActionItemServiceTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ActionItemCompletionFormRegistry::clear();
    }

    protected function tearDown(): void
    {
        ActionItemCompletionFormRegistry::clear();
        parent::tearDown();
    }

    public function testCompleteStillEnforcesRequiredFieldWhenEligibilityBypassed(): void
    {
        ActionItemCompletionFormRegistry::register(
            'test',
            new class implements ActionItemCompletionFormProviderInterface {
                public function canHandle(ActionItem $item): bool
                {
                    return $item->entity_type === 'Tests.RequiredOwner';
                }

                public function buildForm(ActionItem $item, KmpIdentityInterface $user): ?ActionItemCompletionForm
                {
                    return null;
                }

                public function applySubmission(
                    ActionItem $item,
                    array $data,
                    int $actorId,
                    KmpIdentityInterface $user,
                ): ServiceResult {
                    return new ServiceResult(true);
                }

                public function validateCompletion(ActionItem $item): ServiceResult
                {
                    return new ServiceResult(false, 'Required field missing.');
                }
            },
        );
        $item = $this->makeRequiredItem();
        $service = new ActionItemService();

        $result = $service->complete((int)$item->id, self::ADMIN_MEMBER_ID, null, false);

        $this->assertFalse($result->success);
        $this->assertSame('Required field missing.', $result->reason);
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isOpen());
    }

    public function testAutoCompleteSatisfiedRequirementsClosesSystemClosableItem(): void
    {
        $this->registerStaticRequirementProvider(true);
        $item = $this->makeRequiredItem([
            'completion_config' => $this->autoCompletableRequirementConfig(),
        ]);
        $service = new ActionItemService();

        $result = $service->autoCompleteSatisfiedRequirements('Tests.RequiredOwner', 90001);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['completedCount'] ?? null);
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isCompleted());
        $this->assertNull($reloaded->completed_by);
        $log = TableRegistry::getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id' => (int)$item->id])
            ->firstOrFail();
        $this->assertSame(ActionItem::STATUS_OPEN, $log->from_status);
        $this->assertSame(ActionItem::STATUS_COMPLETED, $log->to_status);
        $this->assertSame(ActionItemService::SYSTEM_AUTO_COMPLETION_NOTE, $log->note);
        $this->assertNull($log->created_by);
    }

    public function testAutoCompleteSatisfiedRequirementsLeavesUnmetItemOpen(): void
    {
        $this->registerStaticRequirementProvider(false);
        $item = $this->makeRequiredItem([
            'completion_config' => $this->autoCompletableRequirementConfig(),
        ]);
        $service = new ActionItemService();

        $result = $service->autoCompleteSatisfiedRequirements('Tests.RequiredOwner', 90001);

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->data['completedCount'] ?? null);
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isOpen());
    }

    public function testAutoCompleteSatisfiedRequirementsRequiresOptIn(): void
    {
        $this->registerStaticRequirementProvider(true);
        $item = $this->makeRequiredItem();
        $service = new ActionItemService();

        $result = $service->autoCompleteSatisfiedRequirements('Tests.RequiredOwner', 90001);

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->data['completedCount'] ?? null);
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isOpen());
    }

    public function testSyncRequiredFieldCompletionStatesReopensCompletedItemWhenRequirementCleared(): void
    {
        $this->registerStaticRequirementProvider(false);
        $item = $this->makeRequiredItem([
            'status' => ActionItem::STATUS_COMPLETED,
            'completed_by' => self::ADMIN_MEMBER_ID,
        ]);
        $service = new ActionItemService();

        $result = $service->syncRequiredFieldCompletionStates('Tests.RequiredOwner', 90001);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['reopenedCount'] ?? null);
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isOpen());
        $this->assertNull($reloaded->completed_by);
        $log = TableRegistry::getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id' => (int)$item->id])
            ->firstOrFail();
        $this->assertSame(ActionItem::STATUS_COMPLETED, $log->from_status);
        $this->assertSame(ActionItem::STATUS_OPEN, $log->to_status);
        $this->assertSame(ActionItemService::SYSTEM_REQUIREMENT_REOPEN_NOTE, $log->note);
        $this->assertNull($log->created_by);
    }

    public function testCompletingItemCascadesSatisfiedSystemClosableRequirements(): void
    {
        $this->registerStaticRequirementProvider(true);
        $starter = $this->makeBasicItem();
        $autoClosable = $this->makeRequiredItem([
            'completion_config' => $this->autoCompletableRequirementConfig(),
            'sort_order' => 2,
        ]);
        $service = new ActionItemService();

        $result = $service->complete((int)$starter->id, self::ADMIN_MEMBER_ID, null, false);

        $this->assertTrue($result->success);
        $reloadedStarter = TableRegistry::getTableLocator()->get('ActionItems')->get($starter->id);
        $this->assertTrue($reloadedStarter->isCompleted());
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$reloadedStarter->completed_by);
        $reloadedAutoClosable = TableRegistry::getTableLocator()->get('ActionItems')->get($autoClosable->id);
        $this->assertTrue($reloadedAutoClosable->isCompleted());
        $this->assertNull($reloadedAutoClosable->completed_by);
    }

    public function testRoleAssignedItemsAreScopedToActionItemBranch(): void
    {
        $role = $this->createScopedRoleForMember(self::TEST_MEMBER_AGATHA_ID, self::TEST_BRANCH_STARGATE_ID);
        $stargateItem = $this->makeBasicItem([
            'title' => 'Stargate scheduling',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_ROLE,
            'assignee_config' => ['role_id' => (int)$role->id],
            'branch_id' => self::TEST_BRANCH_STARGATE_ID,
            'source_ref' => 'stargate-scheduling',
        ]);
        $kingdomItem = $this->makeBasicItem([
            'title' => 'Kingdom scheduling',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_ROLE,
            'assignee_config' => ['role_id' => (int)$role->id],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'source_ref' => 'kingdom-scheduling',
        ]);
        $service = new ActionItemService();

        $items = $service->getOpenItemsForMember(self::TEST_MEMBER_AGATHA_ID, 'Tests.RequiredOwner');
        $itemIds = array_map(static fn(ActionItem $item): int => (int)$item->id, $items);

        $this->assertContains((int)$stargateItem->id, $itemIds);
        $this->assertNotContains((int)$kingdomItem->id, $itemIds);
    }

    /**
     * @param bool $satisfied Whether provider validation succeeds.
     * @return void
     */
    private function registerStaticRequirementProvider(bool $satisfied): void
    {
        ActionItemCompletionFormRegistry::register(
            'test',
            new class ($satisfied) implements ActionItemCompletionFormProviderInterface {
                public function __construct(private readonly bool $satisfied)
                {
                }

                public function canHandle(ActionItem $item): bool
                {
                    return $item->entity_type === 'Tests.RequiredOwner';
                }

                public function buildForm(ActionItem $item, KmpIdentityInterface $user): ?ActionItemCompletionForm
                {
                    return null;
                }

                public function applySubmission(
                    ActionItem $item,
                    array $data,
                    int $actorId,
                    KmpIdentityInterface $user,
                ): ServiceResult {
                    return new ServiceResult(true);
                }

                public function validateCompletion(ActionItem $item): ServiceResult
                {
                    return new ServiceResult($this->satisfied, $this->satisfied ? null : 'Required field missing.');
                }
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function autoCompletableRequirementConfig(): array
    {
        return [
            ActionItem::COMPLETION_CONFIG_AUTO_COMPLETE => true,
            'required_fields' => [
                [
                    'provider' => 'Tests.Required',
                    'field' => 'required_value',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides Field overrides.
     * @return \App\Model\Entity\ActionItem
     */
    private function makeRequiredItem(array $overrides = []): ActionItem
    {
        $table = TableRegistry::getTableLocator()->get('ActionItems');

        return $table->saveOrFail($table->newEntity(array_merge([
            'entity_type' => 'Tests.RequiredOwner',
            'entity_id' => 90001,
            'title' => 'Required field todo',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 1,
            'completion_config' => [
                'required_fields' => [
                    [
                        'provider' => 'Tests.Required',
                        'field' => 'required_value',
                    ],
                ],
            ],
        ], $overrides)));
    }

    /**
     * @param array<string, mixed> $overrides Field overrides.
     * @return \App\Model\Entity\ActionItem
     */
    private function makeBasicItem(array $overrides = []): ActionItem
    {
        $table = TableRegistry::getTableLocator()->get('ActionItems');

        return $table->saveOrFail($table->newEntity(array_merge([
            'entity_type' => 'Tests.RequiredOwner',
            'entity_id' => 90001,
            'title' => 'Basic todo',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => false,
            'sort_order' => 0,
        ], $overrides)));
    }

    private function createScopedRoleForMember(int $memberId, int $branchId)
    {
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity([
            'name' => 'Scoped Scheduler ' . uniqid(),
        ]));
        TableRegistry::getTableLocator()->get('MemberRoles')->saveOrFail(
            TableRegistry::getTableLocator()->get('MemberRoles')->newEntity([
                'member_id' => $memberId,
                'role_id' => (int)$role->id,
                'branch_id' => $branchId,
                'start_on' => date('Y-m-d', strtotime('-1 day')),
                'approver_id' => self::ADMIN_MEMBER_ID,
            ]),
        );
        Cache::clear('member_permissions');

        return $role;
    }
}
