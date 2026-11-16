<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Behavior\WorkflowTriggerBehavior;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Queue\Task\WorkflowResumeTask;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowEngine\WorkflowDefinitionValidator;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FactoryLocator;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\TableLocator;
use Cake\ORM\TableRegistry;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use Queue\Model\Entity\QueuedJob;
use RuntimeException;

/**
 * Trigger -> persisted queue job -> worker -> domain action regression tests.
 */
class DeferredWorkflowExecutionTest extends BaseTestCase
{
    private DefaultWorkflowEngine $engine;
    private ContainerInterface $container;
    private object $action;
    private array $registeredActions;
    private bool $savepointsEnabled;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savepointsEnabled = $this->connection->isSavePointsEnabled();
        $this->connection->enableSavePoints();
        DateTime::setTestNow(new DateTime('2026-11-01 12:00:00', 'UTC'));
        $this->registeredActions = WorkflowActionRegistry::getActionsBySource('DeferredTest');
        $this->action = new class {
            public array $calls = [];
            public ?Closure $handler = null;

            public function execute(array $context, array $config): array
            {
                $this->calls[] = ['context' => $context, 'config' => $config];

                return $this->handler ? ($this->handler)($context, $config) : ['value' => 'executed'];
            }
        };
        WorkflowActionRegistry::register('DeferredTest', [[
            'action' => 'DeferredTest.Execute',
            'label' => 'Deferred test action',
            'description' => 'Tests deferred domain operations',
            'inputSchema' => [],
            'outputSchema' => [],
            'serviceClass' => 'DeferredTestAction',
            'serviceMethod' => 'execute',
        ]]);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->container->method('has')->willReturnCallback(fn($id) => $id === 'DeferredTestAction');
        $this->container->method('get')->willReturnCallback(
            fn($id) => $id === WorkflowEngineInterface::class ? $this->engine : $this->action,
        );
        $this->engine = new DefaultWorkflowEngine($this->container);
    }

    protected function tearDown(): void
    {
        DateTime::setTestNow(null);
        WorkflowActionRegistry::register('DeferredTest', $this->registeredActions);
        if (!$this->savepointsEnabled) {
            $this->connection->disableSavePoints();
        }
        parent::tearDown();
    }

    public function testTimerResumesAfterRestartAndDoesNotExecuteTwice(): void
    {
        $instanceId = $this->dispatch([
            'wait' => $this->node('delay', ['duration' => '2h'], 'action'),
            'action' => $this->node('action', ['action' => 'DeferredTest.Execute'], 'end'),
        ]);
        $job = $this->jobs($instanceId)[0];
        $this->assertSame('2026-11-01 14:00:00', $job->notbefore->format('Y-m-d H:i:s'));
        $this->assertSame([], $this->action->calls);
        $this->assertSame('waiting', $this->instance($instanceId)->status);

        DateTime::setTestNow(new DateTime('2026-11-01 14:00:01', 'UTC'));
        $this->deliver($job);
        $this->deliver($job);

        $this->assertCount(1, $this->action->calls);
        $this->assertSame('completed', $this->instance($instanceId)->status);
        $this->assertSame('completed', $this->log($job->data['executionLogId'])->status);
    }

    public function testEarlyDeliveryPreservesTheTimer(): void
    {
        $instanceId = $this->dispatch(['wait' => $this->node('delay', ['duration' => '1h'], 'end')]);
        $job = $this->jobs($instanceId)[0];
        $this->deliver($job);
        $this->assertSame('waiting', $this->instance($instanceId)->status);
        $this->assertCount(2, $this->jobs($instanceId));
        $this->assertEquals($job->notbefore, $this->jobs($instanceId)[1]->notbefore);
    }

    public static function durations(): array
    {
        return [
            'seconds' => ['30s', 30],
            'numeric seconds' => [45, 45],
            'minutes' => ['30m', 1800],
            'hours' => ['2h', 7200],
            'days' => ['2d', 172800],
            'context' => [['type' => 'context', 'path' => 'trigger.duration'], 7200],
        ];
    }

    #[DataProvider('durations')]
    public function testDurationValuesAreResolvedAndPersisted(mixed $duration, int $seconds): void
    {
        $instanceId = $this->dispatch(['wait' => $this->node('delay', ['duration' => $duration], 'end')], ['duration' => '2h']);
        $this->assertSame(
            DateTime::now()->getTimestamp() + $seconds,
            $this->jobs($instanceId)[0]->notbefore->getTimestamp(),
        );
    }

    public function testInvalidDurationFailsWithoutLeavingAQueueJob(): void
    {
        $before = TableRegistry::getTableLocator()->get('Queue.QueuedJobs')->find()->count();
        $results = $this->dispatchResults(['wait' => $this->node('delay', ['duration' => 'tomorrow-ish'], 'end')]);
        $this->assertFalse($results[0]->isSuccess());
        $this->assertStringContainsString('Delay duration', $results[0]->reason);
        $this->assertSame($before, TableRegistry::getTableLocator()->get('Queue.QueuedJobs')->find()->count());
    }

    public function testAsyncActionRunsInWorkerAndPreservesItsOutputAndContext(): void
    {
        $this->action->handler = function (array $context, array $config): array {
            $this->assertTrue(WorkflowTriggerBehavior::$suppressTriggers);

            return ['value' => $config['memberId'], '_contextUpdates' => ['custom' => 'updated']];
        };
        $instanceId = $this->dispatch(['action' => $this->node('action', [
            'action' => 'DeferredTest.Execute', 'isAsync' => true,
            'params' => ['memberId' => '$.trigger.memberId'],
        ], 'end')], ['memberId' => self::ADMIN_MEMBER_ID]);
        $this->assertSame([], $this->action->calls);
        $job = $this->jobs($instanceId)[0];
        $this->assertSame(['instanceId', 'nodeId', 'executionLogId'], array_keys($job->data));
        $this->assertFalse($this->engine->resumeWorkflow($instanceId, 'action', 'default')->isSuccess());
        $this->deliver($job);
        $this->deliver($job);

        $instance = $this->instance($instanceId);
        $this->assertCount(1, $this->action->calls);
        $this->assertSame('completed', $instance->status);
        $this->assertSame(self::ADMIN_MEMBER_ID, $instance->context['nodes']['action']['result']['value']);
        $this->assertSame('updated', $instance->context['custom']);
        $this->assertFalse(WorkflowTriggerBehavior::$suppressTriggers);
    }

    public function testRegistryAsyncFlagAlsoDefersTheAction(): void
    {
        $actions = WorkflowActionRegistry::getActionsBySource('DeferredTest');
        $actions[0]['isAsync'] = true;
        WorkflowActionRegistry::register('DeferredTest', $actions);
        $instanceId = $this->dispatch(['action' => $this->node('action', ['action' => 'DeferredTest.Execute'], 'end')]);
        $this->assertSame([], $this->action->calls);
        $this->deliver($this->jobs($instanceId)[0]);
        $this->assertCount(1, $this->action->calls);
    }

    public function testLegacyAsyncJobUsesPinnedDefinitionInsteadOfItsPayload(): void
    {
        $instanceId = $this->dispatch(['action' => $this->node('action', [
            'action' => 'DeferredTest.Execute', 'isAsync' => true, 'params' => ['value' => 'pinned'],
        ], 'end')]);
        $job = $this->jobs($instanceId)[0];
        $log = $this->log($job->data['executionLogId']);
        $log->output_data = ['queued' => true, 'action' => 'DeferredTest.Execute'];
        TableRegistry::getTableLocator()->get('WorkflowExecutionLogs')->saveOrFail($log);
        $job->data = [
            'instanceId' => $instanceId, 'nodeId' => 'action',
            'additionalData' => ['asyncAction' => 'Untrusted.Action', 'nodeConfig' => ['value' => 'untrusted']],
        ];
        $this->deliver($job);
        $this->assertSame('pinned', $this->action->calls[0]['config']['value']);
    }

    public function testRetriesPersistAttemptsAndBackoffAcrossWorkers(): void
    {
        $this->action->handler = function (): array {
            if (count($this->action->calls) < 3) {
                throw new RuntimeException('Temporary outage');
            }

            return ['value' => 'recovered'];
        };
        $instanceId = $this->dispatch(['action' => $this->node('action', [
            'action' => 'DeferredTest.Execute', 'maxRetries' => 2,
        ], 'end')]);
        $this->assertSame([], $this->action->calls);
        $firstJob = $this->jobs($instanceId)[0];
        $this->deliver($firstJob);
        $this->assertSame('failed', $this->log($firstJob->data['executionLogId'])->status);
        $secondJob = $this->jobs($instanceId)[1];
        $this->assertSame(DateTime::now()->getTimestamp() + 1, $secondJob->notbefore->getTimestamp());
        $this->deliver($firstJob);
        $this->assertCount(1, $this->action->calls);
        DateTime::setTestNow(DateTime::now()->modify('+1 second'));
        $this->deliver($secondJob);
        $thirdJob = $this->jobs($instanceId)[2];
        $this->assertSame(DateTime::now()->getTimestamp() + 2, $thirdJob->notbefore->getTimestamp());
        DateTime::setTestNow(DateTime::now()->modify('+2 seconds'));
        $this->deliver($thirdJob);
        $this->assertSame('completed', $this->instance($instanceId)->status);
        $this->assertSame('recovered', $this->instance($instanceId)->context['nodes']['action']['result']['value']);
        $logs = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs')->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'action'])->orderByAsc('id')->all()->toList();
        $this->assertSame([1, 2, 3], array_column($logs, 'attempt_number'));
        $this->assertSame(['failed', 'failed', 'completed'], array_column($logs, 'status'));
    }

    public function testFailedActionDatabaseWritesRollBackBeforeRetryIsScheduled(): void
    {
        $settings = TableRegistry::getTableLocator()->get('AppSettings');
        $name = 'DeferredTest.' . uniqid();
        $this->action->handler = function () use ($settings, $name): array {
            $settings->saveOrFail($settings->newEntity(['name' => $name, 'value' => 'partial']));

            return ['success' => false, 'data' => [], 'error' => 'Try again', '_contextUpdates' => ['leaked' => true]];
        };
        $instanceId = $this->dispatch(['action' => $this->node('action', [
            'action' => 'DeferredTest.Execute', 'maxRetries' => 1,
        ], 'end')]);
        $this->deliver($this->jobs($instanceId)[0]);
        $this->assertFalse($settings->exists(['name' => $name]));
        $this->assertArrayNotHasKey('leaked', $this->instance($instanceId)->context);
        $this->assertSame('waiting', $this->instance($instanceId)->status);
        $this->assertCount(2, $this->jobs($instanceId));
    }

    public function testPostgresStatementFailureDoesNotPoisonRetryPersistence(): void
    {
        $this->action->handler = function (): array {
            ConnectionManager::get('default')->execute("SELECT CAST('not-an-integer' AS INTEGER)");

            return [];
        };
        $instanceId = $this->dispatch(['action' => $this->node('action', [
            'action' => 'DeferredTest.Execute', 'retryCount' => 1,
        ], 'end')]);
        $this->deliver($this->jobs($instanceId)[0]);
        $this->assertSame('waiting', $this->instance($instanceId)->status);
        $this->assertCount(2, $this->jobs($instanceId));
    }

    public static function errorRoutes(): array
    {
        return ['terminal failure' => [false], 'error output' => [true]];
    }

    #[DataProvider('errorRoutes')]
    public function testExhaustionStopsRetryingAndUsesTheConfiguredErrorRoute(bool $errorRoute): void
    {
        $this->action->handler = static fn(): array => ['success' => false, 'error' => 'Permanent failure'];
        $node = $this->node('action', ['action' => 'DeferredTest.Execute', 'maxRetries' => 1], 'end');
        if ($errorRoute) {
            $node['outputs'][] = ['port' => 'error', 'target' => 'end-error'];
        }
        $instanceId = $this->dispatch([
            'action' => $node,
            'end-error' => ['type' => 'end', 'config' => [], 'outputs' => []],
        ]);
        $this->deliver($this->jobs($instanceId)[0]);
        DateTime::setTestNow(DateTime::now()->modify('+1 second'));
        $this->deliver($this->jobs($instanceId)[1]);
        $this->assertCount(2, $this->action->calls);
        $this->assertCount(2, $this->jobs($instanceId));
        $this->assertSame($errorRoute ? 'completed' : 'failed', $this->instance($instanceId)->status);
        if (!$errorRoute) {
            $this->assertSame(2, $this->instance($instanceId)->error_info['attempts']);
        }
    }

    public function testDownstreamFailureDoesNotRetryTheSuccessfulActivity(): void
    {
        $instanceId = $this->dispatch([
            'action' => $this->node('action', ['action' => 'DeferredTest.Execute', 'maxRetries' => 2], 'broken'),
            'broken' => $this->node('action', ['action' => 'Missing.Action'], 'end'),
        ]);
        $job = $this->jobs($instanceId)[0];
        $this->deliver($job);
        $this->deliver($job);
        $this->assertCount(1, $this->action->calls);
        $this->assertCount(1, $this->jobs($instanceId));
        $this->assertSame('failed', $this->instance($instanceId)->status);
        $this->assertSame('completed', $this->log($job->data['executionLogId'])->status);
    }

    public static function deferredNodes(): array
    {
        return [
            'timer' => ['delay', ['duration' => '0s']],
            'async action' => ['action', ['action' => 'DeferredTest.Execute', 'isAsync' => true]],
            'retry' => ['action', ['action' => 'DeferredTest.Execute', 'maxRetries' => 1]],
        ];
    }

    #[DataProvider('deferredNodes')]
    public function testCancellationMakesQueuedWorkHarmless(string $type, array $config): void
    {
        $instanceId = $this->dispatch(['work' => $this->node($type, $config, 'end')]);
        $job = $this->jobs($instanceId)[0];
        $this->assertTrue($this->engine->cancelWorkflow($instanceId)->isSuccess());
        $this->deliver($job);
        $this->assertSame('cancelled', $this->instance($instanceId)->status);
        $this->assertSame([], $this->action->calls);
    }

    public function testMismatchedInstanceCannotConsumeAnotherWait(): void
    {
        $first = $this->dispatch(['work' => $this->node('delay', ['duration' => '0s'], 'end')]);
        $second = $this->dispatch(['work' => $this->node('delay', ['duration' => '0s'], 'end')]);
        $job = $this->jobs($first)[0];
        $job->data = array_replace($job->data, ['instanceId' => $second]);
        $this->deliver($job);
        $this->assertSame('waiting', $this->instance($first)->status);
        $this->assertSame('waiting', $this->instance($second)->status);
    }

    public function testFailedStartRollsBackItsTimerJob(): void
    {
        $jobs = TableRegistry::getTableLocator()->get('Queue.QueuedJobs');
        $before = $jobs->find()->count();
        $fork = $this->node('fork', [], 'wait');
        $fork['outputs'][] = ['port' => 'other', 'target' => 'broken'];
        $results = $this->dispatchResults([
            'fork' => $fork,
            'wait' => $this->node('delay', ['duration' => '1h'], 'end'),
            'broken' => $this->node('action', ['action' => 'Missing.Action'], 'end'),
        ]);
        $this->assertFalse($results[0]->isSuccess());
        $this->assertSame($before, $jobs->find()->count());
    }

    public function testStaleJobCannotConsumeALaterWaitAtTheSameNode(): void
    {
        $instanceId = $this->dispatch(['wait' => $this->node('delay', ['duration' => '0s'], 'end')]);
        $oldJob = $this->jobs($instanceId)[0];
        $this->deliver($oldJob);

        $instance = $this->instance($instanceId);
        $instance->status = 'waiting';
        $instance->active_nodes = ['wait'];
        $instance->completed_at = null;
        TableRegistry::getTableLocator()->get('WorkflowInstances')->saveOrFail($instance);
        $logs = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $newWait = $logs->newEntity([
            'workflow_instance_id' => $instanceId, 'node_id' => 'wait', 'node_type' => 'delay',
            'status' => 'waiting', 'attempt_number' => 1, 'started_at' => DateTime::now(),
            'output_data' => ['_deferred' => ['kind' => 'delay', 'dueAt' => DateTime::now()->format(DATE_ATOM)]],
        ]);
        $logs->saveOrFail($newWait);
        $this->deliver($oldJob);
        $this->assertSame('waiting', $this->instance($instanceId)->status);
        $this->assertSame('waiting', $this->log($newWait->id)->status);
    }

    public function testCancellingAnAlreadyScheduledRetryPreventsTheNextAttempt(): void
    {
        $this->action->handler = static fn(): array => ['success' => false, 'error' => 'Temporary outage'];
        $instanceId = $this->dispatch(['action' => $this->node('action', [
            'action' => 'DeferredTest.Execute', 'maxRetries' => 2,
        ], 'end')]);
        $this->deliver($this->jobs($instanceId)[0]);
        $this->assertTrue($this->engine->cancelWorkflow($instanceId)->isSuccess());
        DateTime::setTestNow(DateTime::now()->modify('+10 seconds'));
        $this->deliver($this->jobs($instanceId)[1]);
        $this->assertCount(1, $this->action->calls);
        $this->assertSame('cancelled', $this->instance($instanceId)->status);
    }

    public function testForEachRejectsRetryableDurableActionsBeforeExecutingThem(): void
    {
        $loop = $this->node('forEach', ['collection' => 'trigger.items'], 'action');
        $loop['outputs'][0]['port'] = 'iterate';
        $loop['outputs'][] = ['port' => 'complete', 'target' => 'end'];
        $nodes = [
            'loop' => $loop,
            'action' => $this->node('action', ['action' => 'DeferredTest.Execute', 'maxRetries' => 1], 'end'),
        ];
        $errors = (new WorkflowDefinitionValidator())->validate(['schemaVersion' => '1.0', 'nodes' => [
            'trigger' => $this->node('trigger', [], 'loop'),
        ] + $nodes + ['end' => ['type' => 'end']]]);
        $this->assertStringContainsString('cannot contain waiting node', implode(' ', $errors));
        $results = $this->dispatchResults($nodes, ['items' => [1, 2]]);
        $this->assertFalse($results[0]->isSuccess());
        $this->assertSame([], $this->action->calls);
    }

    public function testDeliveryUsesOnlyTheCurrentlyBoundTenantDatabase(): void
    {
        $instanceId = $this->dispatch(['action' => $this->node('action', [
            'action' => 'DeferredTest.Execute', 'isAsync' => true,
        ], 'end')]);
        $job = $this->jobs($instanceId)[0];
        $previousLocator = FactoryLocator::get('Table');
        $previousAlias = ConnectionManager::aliases()['default'];
        $config = ConnectionManager::getConfig('test');
        unset($config['name']);
        ConnectionManager::setConfig('workflow_other_tenant', $config);
        $otherConnection = ConnectionManager::get('workflow_other_tenant');
        // A separate PostgreSQL session has its own tenant-local instance relation.
        $otherConnection->execute('CREATE TEMP TABLE workflow_instances (LIKE public.workflow_instances)');
        try {
            ConnectionManager::dropAlias('default');
            ConnectionManager::alias('workflow_other_tenant', 'default');
            FactoryLocator::add('Table', new TableLocator());
            $this->deliver($job);
            $this->assertSame([], $this->action->calls);
        } finally {
            ConnectionManager::dropAlias('default');
            ConnectionManager::alias($previousAlias, 'default');
            FactoryLocator::add('Table', $previousLocator);
            ConnectionManager::drop('workflow_other_tenant');
        }
        $this->assertSame('waiting', $this->instance($instanceId)->status);
        $this->deliver($job);
        $this->assertCount(1, $this->action->calls);
        $this->assertSame('completed', $this->instance($instanceId)->status);
    }

    public function testDuplicateDeliveryDoesNotCompleteOtherActiveBranches(): void
    {
        $fork = $this->node('fork', [], 'first');
        $fork['outputs'][] = ['port' => 'other', 'target' => 'second'];
        $config = ['action' => 'DeferredTest.Execute', 'isAsync' => true];
        $instanceId = $this->dispatch([
            'fork' => $fork,
            'first' => $this->node('action', $config, 'end'),
            'second' => $this->node('action', $config, 'end-second'),
            'end-second' => ['type' => 'end', 'config' => [], 'outputs' => []],
        ]);
        [$first, $second] = $this->jobs($instanceId);
        $this->deliver($first);
        $this->deliver($first);
        $this->assertSame('waiting', $this->instance($instanceId)->status);
        $this->assertCount(1, $this->action->calls);
        $this->deliver($second);
        $this->assertCount(2, $this->action->calls);
        $this->assertSame('completed', $this->instance($instanceId)->status);
    }

    private function node(string $type, array $config, string $target): array
    {
        return ['type' => $type, 'config' => $config, 'outputs' => [['port' => 'default', 'target' => $target]]];
    }

    private function dispatch(array $nodes, array $data = []): int
    {
        $results = $this->dispatchResults($nodes, $data);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess(), $results[0]->reason ?? 'Dispatch failed');

        return $results[0]->data['instanceId'];
    }

    private function dispatchResults(array $nodes, array $data = []): array
    {
        $definitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $versions = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $slug = 'deferred-test-' . uniqid();
        $definition = $definitions->newEntity([
            'name' => $slug, 'slug' => $slug, 'trigger_type' => 'event', 'is_active' => true,
        ]);
        $definitions->saveOrFail($definition);
        $graph = ['schemaVersion' => '1.0', 'nodes' => [
            'trigger' => $this->node('trigger', ['event' => $slug], array_key_first($nodes)),
        ] + $nodes + ['end' => ['type' => 'end', 'config' => [], 'outputs' => []]]];
        $version = $versions->newEntity([
            'workflow_definition_id' => $definition->id, 'version_number' => 1,
            'status' => 'published', 'definition' => $graph,
        ]);
        $versions->saveOrFail($version);
        $definition->current_version_id = $version->id;
        $definitions->saveOrFail($definition);

        return (new TriggerDispatcher($this->engine))->dispatch($slug, $data, self::ADMIN_MEMBER_ID);
    }

    /**
     * @return list<\Queue\Model\Entity\QueuedJob>
     */
    private function jobs(int $instanceId): array
    {
        return array_values(array_filter(
            TableRegistry::getTableLocator()->get('Queue.QueuedJobs')->find()->orderByAsc('id')->all()->toList(),
            static fn($job) => ($job->data['instanceId'] ?? null) === $instanceId,
        ));
    }

    private function deliver(QueuedJob $job): void
    {
        // Every delivery uses a fresh engine, as it would after a worker restart.
        $this->engine = new DefaultWorkflowEngine($this->container);
        $task = new WorkflowResumeTask();
        $task->setContainer($this->container);
        $task->run($job->data, $job->id);
    }

    private function instance(int $instanceId): WorkflowInstance
    {
        return TableRegistry::getTableLocator()->get('WorkflowInstances')->get($instanceId);
    }

    private function log(int $logId): WorkflowExecutionLog
    {
        return TableRegistry::getTableLocator()->get('WorkflowExecutionLogs')->get($logId);
    }
}
