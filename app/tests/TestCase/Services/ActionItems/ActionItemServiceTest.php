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
use RuntimeException;

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

    public function testCascadeFailureStillDispatchesCommittedCompletionEvent(): void
    {
        $starter = $this->makeBasicItem();
        $required = $this->makeRequiredItem(['sort_order' => 2]);
        $service = new class extends ActionItemService {
            /**
             * @var array<int, array{itemId: int, actorId: int|null}>
             */
            public array $completedEvents = [];

            protected function dispatchCompletedEvent(ActionItem $item, ?int $actorId): void
            {
                $this->completedEvents[] = [
                    'itemId' => (int)$item->id,
                    'actorId' => $actorId,
                ];
            }
        };

        $result = $service->complete((int)$starter->id, self::ADMIN_MEMBER_ID, null, false);

        $this->assertFalse($result->success);
        $this->assertSame(
            'This to-do requires additional information before it can be completed.',
            $result->reason,
        );
        $this->assertTrue(TableRegistry::getTableLocator()->get('ActionItems')->get($starter->id)->isCompleted());
        $this->assertTrue(TableRegistry::getTableLocator()->get('ActionItems')->get($required->id)->isOpen());
        $this->assertSame([
            [
                'itemId' => (int)$starter->id,
                'actorId' => self::ADMIN_MEMBER_ID,
            ],
        ], $service->completedEvents);
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

    public function testSynchronizeForReconcilesSnapshotsAndPreservesMatchingCompletion(): void
    {
        $service = new ActionItemService();
        $kept = $this->makeBasicItem([
            'title' => 'Old kept title',
            'source_ref' => 'kept_check',
            'is_gating' => true,
            'completion_config' => ['legacy' => true],
        ]);
        $completeResult = $service->complete((int)$kept->id, self::ADMIN_MEMBER_ID, 'Finished', false);
        $this->assertTrue($completeResult->success, (string)$completeResult->reason);
        $completedBefore = TableRegistry::getTableLocator()->get('ActionItems')->get($kept->id);
        $completedAt = $completedBefore->completed_at;
        $keptLogCount = TableRegistry::getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id' => (int)$kept->id])
            ->count();
        $removed = $this->makeBasicItem([
            'title' => 'Removed check',
            'source_ref' => 'removed_check',
        ]);

        $result = $service->synchronizeFor('Tests.RequiredOwner', 90001, [
            [
                'title' => 'Current kept title',
                'description' => 'Current instructions',
                'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
                'branch_id' => null,
                'is_gating' => false,
                'sort_order' => 5,
                'source_ref' => 'kept_check',
            ],
            [
                'title' => 'Brand new check',
                'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'is_gating' => true,
                'sort_order' => 6,
                'source_ref' => 'new_check',
            ],
        ], self::KINGDOM_BRANCH_ID, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame(1, $result->data['createdCount']);
        $this->assertSame(1, $result->data['updatedCount']);
        $this->assertSame(1, $result->data['cancelledCount']);
        $this->assertSame(0, $result->data['reopenedCount']);

        $reloadedKept = TableRegistry::getTableLocator()->get('ActionItems')->get($kept->id);
        $this->assertTrue($reloadedKept->isCompleted());
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$reloadedKept->completed_by);
        $this->assertEquals($completedAt, $reloadedKept->completed_at);
        $this->assertSame('Current kept title', $reloadedKept->title);
        $this->assertSame('Current instructions', $reloadedKept->description);
        $this->assertFalse((bool)$reloadedKept->is_gating);
        $this->assertNull($reloadedKept->completion_config);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$reloadedKept->assignee_lookup_id);
        $this->assertSame(
            $keptLogCount,
            TableRegistry::getTableLocator()->get('ActionItemLogs')->find()
                ->where(['action_item_id' => (int)$kept->id])
                ->count(),
            'Refreshing a matching snapshot must not add a lifecycle log.',
        );

        $reloadedRemoved = TableRegistry::getTableLocator()->get('ActionItems')->get($removed->id);
        $this->assertSame(ActionItem::STATUS_CANCELLED, $reloadedRemoved->status);
        $retirementLog = TableRegistry::getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id' => (int)$removed->id])
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame(ActionItemService::SYSTEM_DEFINITION_SYNC_CANCEL_NOTE, $retirementLog->note);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$retirementLog->created_by);

        $created = TableRegistry::getTableLocator()->get('ActionItems')->find()
            ->where([
                'entity_type' => 'Tests.RequiredOwner',
                'entity_id' => 90001,
                'source_ref' => 'new_check',
            ])
            ->firstOrFail();
        $this->assertTrue($created->isOpen());
    }

    public function testSynchronizeForOnlyReopensItemsCancelledByDefinitionSync(): void
    {
        $service = new ActionItemService();
        $returned = $this->makeBasicItem([
            'title' => 'Returned check',
            'source_ref' => 'returned_check',
        ]);
        $manual = $this->makeBasicItem([
            'title' => 'Manual check',
            'source_ref' => 'manual_check',
            'sort_order' => 1,
        ]);
        $manualResult = $service->cancel(
            (int)$manual->id,
            self::ADMIN_MEMBER_ID,
            'Cancelled deliberately by an administrator.',
            false,
        );
        $this->assertTrue($manualResult->success, (string)$manualResult->reason);

        $retireResult = $service->synchronizeFor('Tests.RequiredOwner', 90001, [], null, self::ADMIN_MEMBER_ID);
        $this->assertTrue($retireResult->success, (string)$retireResult->reason);
        $this->assertSame(1, $retireResult->data['cancelledCount']);

        $definitions = [
            [
                'title' => 'Returned check',
                'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'is_gating' => false,
                'sort_order' => 0,
                'source_ref' => 'returned_check',
            ],
            [
                'title' => 'Manual check',
                'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'is_gating' => false,
                'sort_order' => 1,
                'source_ref' => 'manual_check',
            ],
        ];
        $returnResult = $service->synchronizeFor(
            'Tests.RequiredOwner',
            90001,
            $definitions,
            null,
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($returnResult->success, (string)$returnResult->reason);
        $this->assertSame(1, $returnResult->data['reopenedCount']);
        $this->assertTrue(TableRegistry::getTableLocator()->get('ActionItems')->get($returned->id)->isOpen());
        $this->assertSame(
            ActionItem::STATUS_CANCELLED,
            TableRegistry::getTableLocator()->get('ActionItems')->get($manual->id)->status,
        );

        $logs = TableRegistry::getTableLocator()->get('ActionItemLogs');
        $logCount = $logs->find()->where(['action_item_id IN' => [$returned->id, $manual->id]])->count();
        $idempotentResult = $service->synchronizeFor(
            'Tests.RequiredOwner',
            90001,
            $definitions,
            null,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($idempotentResult->success, (string)$idempotentResult->reason);
        $this->assertSame(0, $idempotentResult->data['reopenedCount']);
        $this->assertSame(2, $idempotentResult->data['unchangedCount']);
        $this->assertSame(
            $logCount,
            $logs->find()->where(['action_item_id IN' => [$returned->id, $manual->id]])->count(),
        );
    }

    public function testSynchronizeForRejectsBlankAndDuplicateSourceReferencesBeforeWriting(): void
    {
        $service = new ActionItemService();
        $existing = $this->makeBasicItem(['source_ref' => 'existing_check']);
        $definition = [
            'title' => 'Duplicate check',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'source_ref' => 'duplicate_check',
        ];

        $duplicateResult = $service->synchronizeFor(
            'Tests.RequiredOwner',
            90001,
            [$definition, $definition],
        );
        $blankResult = $service->synchronizeFor('Tests.RequiredOwner', 90001, [[
            'title' => 'Blank key',
            'source_ref' => '   ',
        ]]);

        $this->assertFalse($duplicateResult->success);
        $this->assertStringContainsString('duplicated', (string)$duplicateResult->reason);
        $this->assertFalse($blankResult->success);
        $this->assertStringContainsString('stable source reference', (string)$blankResult->reason);
        $this->assertTrue(TableRegistry::getTableLocator()->get('ActionItems')->get($existing->id)->isOpen());
        $this->assertSame(
            0,
            TableRegistry::getTableLocator()->get('ActionItemLogs')->find()
                ->where(['action_item_id' => (int)$existing->id])
                ->count(),
        );
    }

    public function testSynchronizeForRunsRequiredFieldStateReconciliation(): void
    {
        $this->registerStaticRequirementProvider(false);
        $item = $this->makeRequiredItem([
            'status' => ActionItem::STATUS_COMPLETED,
            'completed_by' => self::ADMIN_MEMBER_ID,
            'source_ref' => 'required_check',
        ]);
        $service = new ActionItemService();

        $result = $service->synchronizeFor('Tests.RequiredOwner', 90001, [[
            'title' => 'Required field todo',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'is_gating' => true,
            'sort_order' => 1,
            'source_ref' => 'required_check',
            'completion_config' => $item->completion_config,
        ]]);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame(1, $result->data['requiredReopenedCount']);
        $this->assertTrue(TableRegistry::getTableLocator()->get('ActionItems')->get($item->id)->isOpen());
    }

    public function testSynchronizeForDefersAutoCompletionEventBeyondSyncTransaction(): void
    {
        $this->registerStaticRequirementProvider(true);
        $item = $this->makeRequiredItem([
            'source_ref' => 'required_check',
            'completion_config' => $this->autoCompletableRequirementConfig(),
        ]);
        $service = new class extends ActionItemService {
            /**
             * @var array<int, int|null>
             */
            public array $completedEventActors = [];

            protected function dispatchCompletedEvent(ActionItem $item, ?int $actorId): void
            {
                $this->completedEventActors[] = $actorId;
            }
        };

        $result = $service->synchronizeFor('Tests.RequiredOwner', 90001, [[
            'title' => 'Required field todo',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'is_gating' => true,
            'sort_order' => 1,
            'source_ref' => 'required_check',
            'completion_config' => $item->completion_config,
        ]], actorId: self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame(1, $result->data['requiredCompletedCount']);
        $completed = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($completed->isCompleted());
        $this->assertNull($completed->completed_by);
        $this->assertSame(
            [],
            $service->completedEventActors,
            'The fixture transaction has not committed, so the completion event must remain deferred.',
        );
    }

    public function testRequiredCompletionEventWaitsForOutermostCommit(): void
    {
        $this->registerStaticRequirementProvider(true);
        $item = $this->makeRequiredItem([
            'completion_config' => $this->autoCompletableRequirementConfig(),
        ]);
        $service = new class extends ActionItemService {
            /**
             * @var array<int>
             */
            public array $completedEventIds = [];

            protected function dispatchCompletedEvent(ActionItem $item, ?int $actorId): void
            {
                $this->completedEventIds[] = (int)$item->id;
            }
        };
        $actionItems = TableRegistry::getTableLocator()->get('ActionItems');

        try {
            $actionItems->getConnection()->transactional(function () use ($service, $item, $actionItems): void {
                $result = $service->syncRequiredFieldCompletionStates('Tests.RequiredOwner', 90001);
                $this->assertTrue($result->success, (string)$result->reason);
                $this->assertTrue($actionItems->get($item->id)->isCompleted());
                $this->assertSame([], $service->completedEventIds);

                throw new RuntimeException('Force the outer workflow synchronization to roll back.');
            });
            $this->fail('Expected the outer transaction to roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Force the outer workflow synchronization to roll back.',
                $exception->getMessage(),
            );
        }

        $this->assertTrue($actionItems->get($item->id)->isOpen());
        $this->assertSame([], $service->completedEventIds);
    }

    public function testCompleteDoesNotReviveCancelledItem(): void
    {
        $item = $this->makeBasicItem(['status' => ActionItem::STATUS_CANCELLED]);
        $service = new ActionItemService();

        $result = $service->complete((int)$item->id, self::ADMIN_MEMBER_ID, enforceEligibility: false);

        $this->assertFalse($result->success);
        $this->assertSame(
            ActionItem::STATUS_CANCELLED,
            TableRegistry::getTableLocator()->get('ActionItems')->get($item->id)->status,
        );
        $this->assertSame(
            0,
            TableRegistry::getTableLocator()->get('ActionItemLogs')->find()
                ->where(['action_item_id' => (int)$item->id])
                ->count(),
        );
    }

    public function testSynchronizeForRollsBackSnapshotChangesWhenRequiredFieldSyncFails(): void
    {
        $kept = $this->makeBasicItem([
            'title' => 'Original title',
            'source_ref' => 'kept_check',
        ]);
        $required = $this->makeRequiredItem(['source_ref' => 'required_check']);
        $service = new ActionItemService();

        $result = $service->synchronizeFor('Tests.RequiredOwner', 90001, [
            [
                'title' => 'Changed title',
                'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'is_gating' => false,
                'sort_order' => 0,
                'source_ref' => 'kept_check',
            ],
            [
                'title' => 'Required field todo',
                'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'is_gating' => true,
                'sort_order' => 1,
                'source_ref' => 'required_check',
                'completion_config' => $required->completion_config,
            ],
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('Original title', TableRegistry::getTableLocator()->get('ActionItems')->get($kept->id)->title);
        $this->assertSame(
            0,
            TableRegistry::getTableLocator()->get('ActionItemLogs')->find()
                ->where(['action_item_id IN' => [$kept->id, $required->id]])
                ->count(),
        );
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
