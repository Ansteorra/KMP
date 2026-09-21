<?php
declare(strict_types=1);

namespace Activities\Test\TestCase\Services;

use Activities\Services\ActivitiesWorkflowActions;
use Activities\Services\AuthorizationManagerInterface;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\ORM\TableRegistry;
use RuntimeException;

class AuthorizationWorkflowResilienceTest extends BaseTestCase
{
    private ActivitiesWorkflowActions $actions;
    private $mockAuthManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAuthManager = $this->createMock(AuthorizationManagerInterface::class);
        $this->actions = new ActivitiesWorkflowActions($this->mockAuthManager);
    }

    public function testEngineDoesNotNotifyOrCompleteAfterActivationFailure(): void
    {
        $this->mockAuthManager->method('activate')->willReturn(new ServiceResult(false, 'Activation failed'));
        $actions = $this->getMockBuilder(ActivitiesWorkflowActions::class)
            ->setConstructorArgs([$this->mockAuthManager])->onlyMethods(['notifyRequester'])->getMock();
        $actions->expects($this->never())->method('notifyRequester');
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($actions);
        WorkflowActionRegistry::register('ActivationRegression', [
            ['action' => 'ActivationRegression.Activate', 'label' => 'Activate', 'description' => 'Activation regression', 'serviceClass' => ActivitiesWorkflowActions::class,
                'serviceMethod' => 'activateAuthorization', 'inputSchema' => [], 'outputSchema' => []],
            ['action' => 'ActivationRegression.Notify', 'label' => 'Notify', 'description' => 'Notification regression', 'serviceClass' => ActivitiesWorkflowActions::class,
                'serviceMethod' => 'notifyRequester', 'inputSchema' => [], 'outputSchema' => []],
        ]);
        try {
            $definitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
            $versions = TableRegistry::getTableLocator()->get('WorkflowVersions');
            $definition = $definitions->newEntity([
                'name' => 'Activation regression', 'slug' => 'activation-regression-' . uniqid(),
                'trigger_type' => 'event', 'entity_type' => 'Activities.Authorizations', 'is_active' => true,
            ]);
            $definitions->saveOrFail($definition);
            $version = $versions->newEntity([
                'workflow_definition_id' => $definition->id, 'version_number' => 1, 'status' => 'published',
                'definition' => ['startNode' => 'trigger', 'nodes' => [
                    'trigger' => ['type' => 'trigger', 'config' => [
                        'event' => 'ActivationRegression.Requested', 'entityIdField' => 'authorizationId',
                    ], 'outputs' => [['port' => 'next', 'target' => 'activate']]],
                    'activate' => ['type' => 'action', 'config' => [
                        'action' => 'ActivationRegression.Activate', 'params' => [
                            'authorizationId' => '$.nodes.validate-request.result.authorizationId',
                            'approverId' => self::ADMIN_MEMBER_ID,
                        ],
                    ], 'outputs' => [['port' => 'next', 'target' => 'notify']]],
                    'notify' => ['type' => 'action', 'config' => ['action' => 'ActivationRegression.Notify'],
                        'outputs' => [['port' => 'next', 'target' => 'end']]],
                    'end' => ['type' => 'end', 'config' => [], 'outputs' => []],
                ]],
            ]);
            $versions->saveOrFail($version);
            $definition->current_version_id = $version->id;
            $definitions->saveOrFail($definition);
            $engine = new DefaultWorkflowEngine($container);
            $results = $engine->dispatchTrigger('ActivationRegression.Requested', ['authorizationId' => 42], self::ADMIN_MEMBER_ID);
            $this->assertCount(1, $results);
            $this->assertFalse($results[0]->success);
            $this->assertStringContainsString('Activation failed', $results[0]->reason);
        } finally {
            WorkflowActionRegistry::unregister('ActivationRegression');
        }
    }

    public function testActivationUsesTriggerWhenLegacyNodeResultIsMissing(): void
    {
        $this->mockAuthManager->expects($this->once())->method('activate')
            ->with(42, self::ADMIN_MEMBER_ID)->willReturn(new ServiceResult(true));
        $result = $this->actions->activateAuthorization([
            'trigger' => ['authorizationId' => 42],
            'resumeData' => ['approverId' => self::ADMIN_MEMBER_ID],
        ], ['authorizationId' => null]);
        $this->assertTrue($result['activated']);
    }

    public function testActivationPrefersPersistedEntityOverLegacyParameter(): void
    {
        $instances = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $version = TableRegistry::getTableLocator()->get('WorkflowVersions')->find()->firstOrFail();
        $instance = $instances->newEntity([
            'workflow_definition_id' => $version->workflow_definition_id,
            'workflow_version_id' => $version->id,
            'entity_type' => 'Activities.Authorizations', 'entity_id' => 42,
            'status' => 'waiting', 'context' => [], 'active_nodes' => [],
        ]);
        $instances->saveOrFail($instance);
        $this->mockAuthManager->expects($this->once())->method('activate')
            ->with(42, self::ADMIN_MEMBER_ID)->willReturn(new ServiceResult(true));
        $this->actions->activateAuthorization(['instanceId' => $instance->id], [
            'authorizationId' => 99, 'approverId' => self::ADMIN_MEMBER_ID,
        ]);
    }

    public function testConflictingEntityAndTriggerFailBeforeActivation(): void
    {
        $instances = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $version = TableRegistry::getTableLocator()->get('WorkflowVersions')->find()->firstOrFail();
        $instance = $instances->newEntity([
            'workflow_definition_id' => $version->workflow_definition_id,
            'workflow_version_id' => $version->id,
            'entity_type' => 'Activities.Authorizations', 'entity_id' => 42,
            'status' => 'waiting', 'context' => [], 'active_nodes' => [],
        ]);
        $instances->saveOrFail($instance);
        $this->mockAuthManager->expects($this->never())->method('activate');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('IDs disagree');
        $this->actions->activateAuthorization([
            'instanceId' => $instance->id, 'trigger' => ['authorizationId' => 43],
        ], ['authorizationId' => null]);
    }

    public function testMissingReferencesFailBeforeActivation(): void
    {
        $this->mockAuthManager->expects($this->never())->method('activate');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no valid authorization ID');
        $this->actions->activateAuthorization([], ['authorizationId' => null]);
    }

    public function testActivationFailureIsNotReturnedAsSuccess(): void
    {
        $this->mockAuthManager->method('activate')->willReturn(new ServiceResult(false, 'Cannot start active window'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot start active window');
        $this->actions->activateAuthorization([], ['authorizationId' => 42, 'approverId' => self::ADMIN_MEMBER_ID]);
    }

    public function testDenialUsesTriggerWhenLegacyResultIsMissing(): void
    {
        $table = TableRegistry::getTableLocator()->get('Activities.Authorizations');
        $activity = TableRegistry::getTableLocator()->get('Activities.Activities')->find()->firstOrFail();
        $auth = $table->newEmptyEntity();
        $auth->member_id = self::TEST_MEMBER_EIRIK_ID;
        $auth->activity_id = $activity->id;
        $auth->status = 'Pending';
        $table->saveOrFail($auth);
        $result = $this->actions->handleDenial([
            'trigger' => ['authorizationId' => $auth->id],
            'resumeData' => ['approverId' => self::ADMIN_MEMBER_ID, 'comment' => 'Reviewed denial'],
        ], ['authorizationId' => null]);
        $this->assertTrue($result['denied']);
        $saved = $table->get($auth->id);
        $this->assertSame('Denied', $saved->status);
        $this->assertSame(self::ADMIN_MEMBER_ID, $saved->revoker_id);
        $this->assertSame('Reviewed denial', $saved->revoked_reason);
    }

    public function testRequestReturnsTheCreatedIdWithoutLookingUpAnotherRequest(): void
    {
        $this->mockAuthManager->method('request')->willReturn(new ServiceResult(true, null, ['authorizationId' => 42]));
        $result = $this->actions->createAuthorizationRequest([], [
            'memberId' => self::TEST_MEMBER_EIRIK_ID, 'activityId' => 1, 'approverId' => self::ADMIN_MEMBER_ID,
        ]);
        $this->assertSame(42, $result['authorizationId']);
    }

    public function testFailedCreationStopsTheWorkflow(): void
    {
        $this->mockAuthManager->method('request')->willReturn(new ServiceResult(false, 'Existing pending request'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Existing pending request');
        $this->actions->createAuthorizationRequest([], [
            'memberId' => self::TEST_MEMBER_EIRIK_ID, 'activityId' => 1, 'approverId' => self::ADMIN_MEMBER_ID,
        ]);
    }
}
