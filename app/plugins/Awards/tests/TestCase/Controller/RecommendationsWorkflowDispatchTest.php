<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Controller\WorkflowDispatchTrait;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\BaseTestCase;
use ArrayAccess;
use Awards\Controller\RecommendationsController;
use Awards\Services\RecommendationTransitionService;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\TableRegistry;
use RuntimeException;

/**
 * Tests for workflow dispatch integration used by RecommendationsController.
 *
 * Uses a lightweight stub controller that mirrors the real controller's
 * workflow dispatch wiring. This avoids the full HTTP stack while testing
 * the trait integration, event payloads, and workflow-only dispatch helpers.
 */
class RecommendationsWorkflowDispatchTest extends BaseTestCase
{
    /**
     * Captured dispatch calls from the mocked TriggerDispatcher.
     *
     * @var array<int, array{event: string, data: array, triggeredBy: int|null}>
     */
    private array $dispatched = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->dispatched = [];
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Create a mock TriggerDispatcher that records calls.
     */
    private function buildMockDispatcher(): TriggerDispatcher
    {
        $mock = $this->createMock(TriggerDispatcher::class);
        $mock->method('dispatch')
            ->willReturnCallback(function (string $event, array $data, ?int $triggeredBy) {
                $this->dispatched[] = compact('event', 'data', 'triggeredBy');

                return ['workflow-started'];
            });

        return $mock;
    }

    /**
     * Create a mock TriggerDispatcher that throws on dispatch.
     */
    private function buildThrowingDispatcher(): TriggerDispatcher
    {
        $mock = $this->createMock(TriggerDispatcher::class);
        $mock->method('dispatch')
            ->willThrowException(new RuntimeException('Workflow engine offline'));

        return $mock;
    }

    /**
     * Create an active workflow definition so workflow-only dispatch can proceed.
     */
    private function activateWorkflow(string $slug): int
    {
        $definitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $versions = TableRegistry::getTableLocator()->get('WorkflowVersions');

        $def = $definitions->find()->where(['slug' => $slug])->first();

        if ($def && $def->is_active && $def->current_version_id) {
            return (int)$def->id;
        }

        if (!$def) {
            $def = $definitions->newEntity([
                'name' => 'Test Workflow - ' . $slug,
                'slug' => $slug,
                'description' => 'Activated for test',
                'trigger_type' => 'event',
                'is_active' => true,
            ]);
            $definitions->saveOrFail($def);
        }

        if (!$def->current_version_id) {
            $version = $versions->newEntity([
                'workflow_definition_id' => $def->id,
                'version_number' => 1,
                'status' => 'published',
                'definition' => json_encode(['nodes' => [], 'edges' => []]),
            ]);
            $versions->saveOrFail($version);

            $def->current_version_id = $version->id;
        }

        // Ensure the definition is active (may have been seeded as inactive)
        $def->is_active = true;
        $definitions->saveOrFail($def);

        return (int)$def->id;
    }

    /**
     * Deactivate all workflow definitions matching a slug.
     */
    private function deactivateWorkflow(string $slug): void
    {
        $definitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definitions->updateAll(['is_active' => false], ['slug' => $slug]);
    }

    /**
     * Get the first available award ID from the database.
     */
    private function getFirstAwardId(): int
    {
        $awards = TableRegistry::getTableLocator()->get('Awards.Awards');
        $award = $awards->find()->select(['id'])->first();

        return $award ? (int)$award->id : 1;
    }

    /**
     * Create a recommendation in the database for state transition tests.
     */
    private function createTestRecommendation(string $state = 'Submitted'): int
    {
        $table = TableRegistry::getTableLocator()->get('Awards.Recommendations');
        $rec = $table->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->getFirstAwardId(),
            'reason' => 'Test recommendation for workflow',
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@test.com',
            'status' => 'In Progress',
            'state' => $state,
            'state_date' => DateTime::now(),
            'not_found' => false,
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
            'person_to_notify' => '',
            'branch_id' => self::KINGDOM_BRANCH_ID,
        ]);
        $table->saveOrFail($rec);

        return (int)$rec->id;
    }

    /**
     * Build a stub object that uses WorkflowDispatchTrait with a mock request.
     */
    private function buildTraitStub(?int $identityId = null, ?int $branchId = null): object
    {
        $branchId = $branchId ?? self::KINGDOM_BRANCH_ID;

        return new class ($identityId, $branchId) {
            use WorkflowDispatchTrait;
            use LocatorAwareTrait;

            public object $request;

            public function __construct(?int $identityId, ?int $branchId)
            {
                $identity = $identityId !== null
                    ? new class ($identityId, $branchId) implements ArrayAccess {
                        private int $id;
                        private array $data;

                        public function __construct(int $id, ?int $branchId)
                        {
                            $this->id = $id;
                            $this->data = ['id' => $id, 'branch_id' => $branchId];
                        }

                        public function getIdentifier(): int
                        {
                            return $this->id;
                        }

                        public function offsetExists(mixed $offset): bool
                        {
                            return isset($this->data[$offset]);
                        }

                        public function offsetGet(mixed $offset): mixed
                        {
                            return $this->data[$offset] ?? null;
                        }

                        public function offsetSet(mixed $offset, mixed $value): void
                        {
                            $this->data[$offset] = $value;
                        }

                        public function offsetUnset(mixed $offset): void
                        {
                            unset($this->data[$offset]);
                        }
                    }
                    : null;

                $this->request = new class ($identity) {
                    private $identity;

                    public function __construct($identity)
                    {
                        $this->identity = $identity;
                    }

                    public function getAttribute(string $name): mixed
                    {
                        return $name === 'identity' ? $this->identity : null;
                    }
                };
            }

            public function callDispatchWorkflowOrFail(
                TriggerDispatcher $dispatcher,
                string $slug,
                string $trigger,
                array $ctx,
            ): array {
                return $this->dispatchWorkflowOrFail($dispatcher, $slug, $trigger, $ctx);
            }

            public function callDispatchWorkflowEvent(
                TriggerDispatcher $dispatcher,
                string $trigger,
                array $ctx,
            ): void {
                $this->dispatchWorkflowEvent($dispatcher, $trigger, $ctx);
            }
        };
    }

    // ── 1. Workflow-only dispatch fails when no workflow is active ───

    public function testDispatchWorkflowOrFailThrowsWhenNoWorkflow(): void
    {
        $this->deactivateWorkflow('awards-recommendation-submitted');
        $stub = $this->buildTraitStub(self::ADMIN_MEMBER_ID);
        $dispatcher = $this->buildMockDispatcher();

        try {
            $stub->callDispatchWorkflowOrFail(
                $dispatcher,
                'awards-recommendation-submitted',
                'Awards.RecommendationCreateRequested',
                ['data' => ['award_id' => 1]],
            );
            $this->fail('Expected workflow-only dispatch to throw when inactive.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'awards-recommendation-submitted',
                $exception->getMessage(),
            );
        }

        $this->assertEmpty($this->dispatched, 'No workflow dispatch when no active definition');
    }

    // ── 2. Workflow path dispatches when workflow is active ──────────

    public function testDispatchWorkflowOrFailDispatchesWhenWorkflowActive(): void
    {
        $this->activateWorkflow('awards-recommendation-submitted');
        $dispatcher = $this->buildMockDispatcher();
        $stub = $this->buildTraitStub(self::ADMIN_MEMBER_ID);

        $stub->callDispatchWorkflowOrFail(
            $dispatcher,
            'awards-recommendation-submitted',
            'Awards.RecommendationCreateRequested',
            [
                'data' => [
                    'award_id' => 1,
                    'reason' => 'Test recommendation',
                ],
                'submissionMode' => 'authenticated',
            ],
        );

        $this->assertCount(1, $this->dispatched);
        $this->assertEquals('Awards.RecommendationCreateRequested', $this->dispatched[0]['event']);
        $this->assertEquals(1, $this->dispatched[0]['data']['data']['award_id']);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $this->dispatched[0]['triggeredBy']);
    }

    public function testDispatchWorkflowEventFiresRecommendationUpdate(): void
    {
        $dispatcher = $this->buildMockDispatcher();
        $recId = $this->createTestRecommendation();
        $stub = $this->buildTraitStub(self::ADMIN_MEMBER_ID);

        $stub->callDispatchWorkflowEvent(
            $dispatcher,
            'Awards.RecommendationUpdateRequested',
            [
                'recommendationId' => $recId,
                'data' => ['reason' => 'Updated reason'],
                'actorId' => self::ADMIN_MEMBER_ID,
            ],
        );

        $this->assertCount(1, $this->dispatched);
        $call = $this->dispatched[0];
        $this->assertEquals('Awards.RecommendationUpdateRequested', $call['event']);
        $this->assertEquals($recId, $call['data']['recommendationId']);
        $this->assertEquals('Updated reason', $call['data']['data']['reason']);
    }

    public function testDispatchWorkflowEventSwallowsExceptions(): void
    {
        $dispatcher = $this->buildThrowingDispatcher();
        $stub = $this->buildTraitStub(self::ADMIN_MEMBER_ID);

        // Should not throw even though dispatcher raises an exception
        $stub->callDispatchWorkflowEvent(
            $dispatcher,
            'Awards.RecommendationUpdateRequested',
            ['recommendationId' => 1, 'data' => ['reason' => 'Updated reason']],
        );

        $this->assertTrue(true, 'dispatchWorkflowEvent should swallow exceptions');
    }

    public function testDispatchWorkflowOrFailPassesActorId(): void
    {
        $this->activateWorkflow('awards-recommendation-submitted');
        $dispatcher = $this->buildMockDispatcher();
        $actorId = self::TEST_MEMBER_AGATHA_ID;
        $stub = $this->buildTraitStub($actorId);

        $stub->callDispatchWorkflowOrFail(
            $dispatcher,
            'awards-recommendation-submitted',
            'Awards.RecommendationCreateRequested',
            ['data' => ['award_id' => 1]],
        );

        $this->assertEquals($actorId, $this->dispatched[0]['triggeredBy']);
    }

    // ── 7. Bulk state service integration produces correct state ─────

    public function testBulkUpdateStatesSucceeds(): void
    {
        $recId = $this->createTestRecommendation('Submitted');
        $table = TableRegistry::getTableLocator()->get('Awards.Recommendations');
        $transitionService = new RecommendationTransitionService();

        $result = $transitionService->transitionMany(
            $table,
            [
                'recommendationIds' => [(string)$recId],
                'newState' => 'In Consideration',
                'gathering_id' => null,
                'given' => null,
                'note' => null,
                'close_reason' => null,
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], 'Bulk state update should succeed');

        $updated = $table->get($recId);
        $this->assertEquals('In Consideration', $updated->state);
        $this->assertEquals('In Progress', $updated->status);
    }

    // ── 8. Controller uses WorkflowDispatchTrait ────────────────────

    public function testRecommendationsControllerUsesWorkflowDispatchTrait(): void
    {
        $uses = class_uses(RecommendationsController::class);
        $this->assertArrayHasKey(
            'App\Controller\WorkflowDispatchTrait',
            $uses,
            'RecommendationsController must use WorkflowDispatchTrait',
        );
    }

    // ── 10. Workflow trigger names match provider registration ───────

    public function testWorkflowTriggerNamesMatchProvider(): void
    {
        $expectedTriggers = [
            'Awards.RecommendationCreateRequested',
            'Awards.RecommendationUpdateRequested',
            'Awards.RecommendationsGroupRequested',
            'Awards.RecommendationsUngroupRequested',
            'Awards.RecommendationRemoveFromGroupRequested',
            'Awards.RecommendationDeleteRequested',
        ];

        // Verify the controller source references each expected trigger
        $controllerSource = file_get_contents(
            dirname(__DIR__, 3) . '/src/Controller/RecommendationsController.php',
        );

        foreach ($expectedTriggers as $trigger) {
            $this->assertStringContainsString(
                $trigger,
                $controllerSource,
                "Controller must reference trigger '{$trigger}'",
            );
        }
    }
}
