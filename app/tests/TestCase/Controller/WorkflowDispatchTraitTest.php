<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\WorkflowDispatchTrait;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\BaseTestCase;
use Authentication\IdentityInterface;
use Cake\Http\ServerRequest;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\TableRegistry;
use RuntimeException;

/**
 * Tests for workflow-only controller dispatch helpers.
 */
class WorkflowDispatchTraitTest extends BaseTestCase
{
    private $defTable;
    private $versionsTable;
    private $subject;

    private function createSubjectWithBranch(?int $branchId, int $memberId = 42): object
    {
        $identity = $this->createMock(IdentityInterface::class);
        $identity->method('getIdentifier')->willReturn($memberId);
        $identity->method('offsetGet')->willReturnCallback(function ($offset) use ($branchId) {
            return match ($offset) {
                'branch_id' => $branchId,
                default => null,
            };
        });
        $identity->method('offsetExists')->willReturnCallback(function ($offset) use ($branchId) {
            return $offset === 'branch_id' && $branchId !== null;
        });

        $request = new ServerRequest(['url' => '/test']);
        $request = $request->withAttribute('identity', $identity);

        return new class ($request) {
            use LocatorAwareTrait;
            use WorkflowDispatchTrait {
                dispatchWorkflowOrFail as public;
                dispatchWorkflowEvent as public;
                resolveKingdomId as public;
                resolveKingdomIdFromBranch as public;
            }

            public ServerRequest $request;

            public function __construct(ServerRequest $request)
            {
                $this->request = $request;
            }
        };
    }

    private function createAnonymousSubject(): object
    {
        $request = new ServerRequest(['url' => '/test']);

        return new class ($request) {
            use LocatorAwareTrait;
            use WorkflowDispatchTrait {
                dispatchWorkflowOrFail as public;
                dispatchWorkflowEvent as public;
                resolveKingdomId as public;
            }

            public ServerRequest $request;

            public function __construct(ServerRequest $request)
            {
                $this->request = $request;
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $this->versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $this->subject = $this->createSubjectWithBranch(self::TEST_BRANCH_STARGATE_ID);
    }

    private function createActiveWorkflow(string $slug, ?int $kingdomId = null): int
    {
        $def = $this->defTable->newEntity([
            'name' => 'Test: ' . $slug,
            'slug' => $slug,
            'trigger_type' => 'manual',
            'is_active' => true,
            'kingdom_id' => $kingdomId,
        ]);
        $this->defTable->saveOrFail($def);

        $version = $this->versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger1' => [
                        'type' => 'trigger',
                        'config' => [],
                        'outputs' => [['port' => 'default', 'target' => 'end1']],
                    ],
                    'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                ],
            ],
            'status' => 'published',
        ]);
        $this->versionsTable->saveOrFail($version);

        $def->current_version_id = $version->id;
        $this->defTable->saveOrFail($def);

        return $def->id;
    }

    private function createInactiveWorkflow(string $slug, ?int $kingdomId = null): int
    {
        $def = $this->defTable->newEntity([
            'name' => 'Inactive: ' . $slug,
            'slug' => $slug,
            'trigger_type' => 'manual',
            'is_active' => false,
            'kingdom_id' => $kingdomId,
        ]);
        $this->defTable->saveOrFail($def);

        return $def->id;
    }

    private function createWorkflowWithoutVersion(string $slug, ?int $kingdomId = null): int
    {
        $def = $this->defTable->newEntity([
            'name' => 'No version: ' . $slug,
            'slug' => $slug,
            'trigger_type' => 'manual',
            'is_active' => true,
            'current_version_id' => null,
            'kingdom_id' => $kingdomId,
        ]);
        $this->defTable->saveOrFail($def);

        return $def->id;
    }

    public function testDispatchWorkflowOrFailThrowsWhenNoWorkflowDefined(): void
    {
        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->expectException(RuntimeException::class);
        $this->subject->dispatchWorkflowOrFail($dispatcher, 'missing-' . uniqid(), 'Test.Event', []);
    }

    public function testDispatchWorkflowOrFailThrowsWhenWorkflowIsInactive(): void
    {
        $slug = 'inactive-' . uniqid();
        $this->createInactiveWorkflow($slug);

        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->expectException(RuntimeException::class);
        $this->subject->dispatchWorkflowOrFail($dispatcher, $slug, 'Test.Event', []);
    }

    public function testDispatchWorkflowOrFailThrowsWhenDefinitionHasNoCurrentVersion(): void
    {
        $slug = 'no-version-' . uniqid();
        $this->createWorkflowWithoutVersion($slug);

        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->expectException(RuntimeException::class);
        $this->subject->dispatchWorkflowOrFail($dispatcher, $slug, 'Test.Event', []);
    }

    public function testDispatchWorkflowOrFailCallsDispatcherWhenWorkflowIsActive(): void
    {
        $slug = 'active-' . uniqid();
        $this->createActiveWorkflow($slug);

        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with('Test.Event', $this->callback(function ($ctx) {
                return $ctx['foo'] === 'bar' && array_key_exists('kingdom_id', $ctx);
            }), 42)
            ->willReturn(['result1']);

        $result = $this->subject->dispatchWorkflowOrFail($dispatcher, $slug, 'Test.Event', ['foo' => 'bar']);

        $this->assertSame(['result1'], $result);
    }

    public function testDispatchWorkflowOrFailThrowsWhenActiveDefinitionStartsNoWorkflow(): void
    {
        $slug = 'active-no-match-' . uniqid();
        $this->createActiveWorkflow($slug);

        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with('Test.Event', $this->anything(), 42)
            ->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->subject->dispatchWorkflowOrFail($dispatcher, $slug, 'Test.Event', []);
    }

    public function testDispatchWorkflowOrFailPassesTriggeredByFromIdentity(): void
    {
        $slug = 'identity-' . uniqid();
        $this->createActiveWorkflow($slug);

        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->anything(), $this->anything(), $this->equalTo(42))
            ->willReturn(['result']);

        $this->subject->dispatchWorkflowOrFail($dispatcher, $slug, 'Test.Identity', []);
    }

    public function testDispatchWorkflowOrFailHandlesNullIdentity(): void
    {
        $slug = 'null-identity-' . uniqid();
        $this->createActiveWorkflow($slug);
        $subject = $this->createAnonymousSubject();

        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with('Test.NullId', $this->callback(function ($ctx) {
                return $ctx['kingdom_id'] === null;
            }), null)
            ->willReturn(['result']);

        $subject->dispatchWorkflowOrFail($dispatcher, $slug, 'Test.NullId', []);
    }

    public function testDispatchWorkflowEventCallsDispatcher(): void
    {
        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with('Activities.AuthorizationRevoked', $this->callback(function ($ctx) {
                return $ctx['id'] === 99 && array_key_exists('kingdom_id', $ctx);
            }), 42);

        $this->subject->dispatchWorkflowEvent($dispatcher, 'Activities.AuthorizationRevoked', ['id' => 99]);
    }

    public function testDispatchWorkflowEventDoesNotThrowWhenDispatchFails(): void
    {
        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('Boom'));

        $this->subject->dispatchWorkflowEvent($dispatcher, 'Test.Failure', ['data' => 'value']);

        $this->assertTrue(true, 'No exception should propagate');
    }

    public function testDispatchWorkflowEventHandlesNullIdentity(): void
    {
        $subject = $this->createAnonymousSubject();

        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with('Test.NullIdentity', $this->callback(function ($ctx) {
                return $ctx['kingdom_id'] === null;
            }), null);

        $subject->dispatchWorkflowEvent($dispatcher, 'Test.NullIdentity', []);
    }

    public function testResolveKingdomIdReturnsKingdomForLocalBranch(): void
    {
        $subject = $this->createSubjectWithBranch(self::TEST_BRANCH_STARGATE_ID);

        $this->assertSame(self::KINGDOM_BRANCH_ID, $subject->resolveKingdomId());
    }

    public function testResolveKingdomIdReturnsKingdomForKingdomBranch(): void
    {
        $subject = $this->createSubjectWithBranch(self::KINGDOM_BRANCH_ID);

        $this->assertSame(self::KINGDOM_BRANCH_ID, $subject->resolveKingdomId());
    }

    public function testResolveKingdomIdReturnsNullForAnonymous(): void
    {
        $subject = $this->createAnonymousSubject();

        $this->assertNull($subject->resolveKingdomId());
    }

    public function testResolveKingdomIdUsesContextBranchForAnonymous(): void
    {
        $subject = $this->createAnonymousSubject();

        $kingdomId = $subject->resolveKingdomId([
            'data' => ['branch_id' => self::TEST_BRANCH_STARGATE_ID],
        ]);

        $this->assertSame(self::KINGDOM_BRANCH_ID, $kingdomId);
    }

    public function testResolveKingdomIdUsesMemberPublicIdForAnonymous(): void
    {
        $subject = $this->createAnonymousSubject();
        $member = TableRegistry::getTableLocator()
            ->get('Members')
            ->get(self::TEST_MEMBER_AGATHA_ID, select: ['public_id']);

        $kingdomId = $subject->resolveKingdomId([
            'data' => ['member_public_id' => $member->public_id],
        ]);

        $this->assertSame(self::KINGDOM_BRANCH_ID, $kingdomId);
    }

    public function testResolveKingdomIdFromBranchForRegion(): void
    {
        $subject = $this->createSubjectWithBranch(self::TEST_BRANCH_CENTRAL_REGION_ID);

        $this->assertSame(
            self::KINGDOM_BRANCH_ID,
            $subject->resolveKingdomIdFromBranch(self::TEST_BRANCH_CENTRAL_REGION_ID),
        );
    }
}
