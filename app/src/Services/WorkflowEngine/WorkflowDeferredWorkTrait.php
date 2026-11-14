<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Services\ServiceResult;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use RuntimeException;
use Throwable;

/**
 * Persisted queue waits and action attempts for DefaultWorkflowEngine.
 */
trait WorkflowDeferredWorkTrait
{
    /**
     * @inheritDoc
     */
    public function processDeferredWork(int $instanceId, string $nodeId, ?int $executionLogId = null): ServiceResult
    {
        $state = $this->beginExecution(false);
        try {
            // Infrastructure failures leave the wait intact for the queue's own retry.
            return $this->executeInTransaction(function () use ($instanceId, $nodeId, $executionLogId) {
                $instances = TableRegistry::getTableLocator()->get('WorkflowInstances');
                $instance = $instances->find()->where(['id' => $instanceId])->epilog('FOR UPDATE')->first();
                if ($instance === null || $instance->isTerminal()) {
                    return new ServiceResult(true, null, ['skipped' => true]);
                }
                $logs = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
                $conditions = ['workflow_instance_id' => $instanceId, 'node_id' => $nodeId];
                if ($executionLogId !== null) {
                    $conditions['id'] = $executionLogId;
                }
                $log = $logs->find()->where($conditions)->orderByDesc('id')->first();
                if (
                    $log === null || $log->status !== WorkflowExecutionLog::STATUS_WAITING
                    || !in_array($nodeId, $instance->active_nodes ?? [], true)
                ) {
                    return new ServiceResult(true, null, ['skipped' => true]);
                }

                $work = $log->output_data['_deferred'] ?? null;
                // Pre-upgrade async jobs contained action/config data. Never execute that payload.
                if ($work === null && $executionLogId === null && !empty($log->output_data['queued'])) {
                    $work = ['kind' => 'action'];
                }
                if (!in_array($work['kind'] ?? null, ['action', 'delay'], true)) {
                    throw new RuntimeException('Execution log does not describe deferred workflow work.');
                }
                if (!empty($work['dueAt']) && new DateTime($work['dueAt']) > DateTime::now()) {
                    $this->enqueueWorkflowWork($instance, $log, new DateTime($work['dueAt']));

                    return new ServiceResult(true, null, ['deferred' => true]);
                }

                $versions = TableRegistry::getTableLocator()->get('WorkflowVersions');
                $version = $versions->get($instance->workflow_version_id);
                $definition = $version->definition;
                $node = $definition['nodes'][$nodeId] ?? null;
                if (($node['type'] ?? null) !== ($work['kind'] === 'action' ? 'action' : 'delay')) {
                    throw new RuntimeException('Deferred work no longer matches the pinned workflow definition.');
                }
                $instance->status = WorkflowInstance::STATUS_RUNNING;
                if ($work['kind'] === 'action') {
                    $port = $this->executeDeferredAction($instance, $nodeId, $node, $log);
                    if ($port === null) {
                        $this->updateInstance($instance, []);

                        return new ServiceResult(true, null, ['instanceId' => $instanceId]);
                    }
                } else {
                    $port = 'default';
                    $context = $instance->context ?? [];
                    $context['nodes'][$nodeId] = ['status' => $port, 'decision' => $port];
                    $instance->context = $context;
                    $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
                    $log->completed_at = DateTime::now();
                    $this->saveLog($log);
                }

                $this->removeFromActiveNodes($instance, $nodeId);
                $this->hydrateInstanceEntityMetadata($instance, $definition);
                $this->updateInstance($instance, []);
                // A downstream failure must not roll back/retry a completed activity.
                $connection = $instances->getConnection();
                $connection->enableSavePoints();
                try {
                    $connection->transactional(function () use ($instance, $nodeId, $port, $definition): void {
                        $this->advanceToOutputs($instance, $nodeId, $port, $definition);
                    });
                } catch (Throwable $exception) {
                    $errorInfo = $instance->error_info ?? [];
                    $instance = $instances->get($instanceId);
                    $instance->status = WorkflowInstance::STATUS_FAILED;
                    $instance->completed_at = DateTime::now();
                    $instance->error_info = $errorInfo + ['error' => $exception->getMessage(), 'after_node' => $nodeId];
                }
                if (!$instance->isTerminal()) {
                    $activeNodes = $instance->active_nodes ?? [];
                    $waiting = $activeNodes !== [] && $logs->exists([
                        'workflow_instance_id' => $instanceId,
                        'node_id IN' => $activeNodes,
                        'status' => WorkflowExecutionLog::STATUS_WAITING,
                    ]);
                    $instance->status = $waiting ? WorkflowInstance::STATUS_WAITING : WorkflowInstance::STATUS_RUNNING;
                }
                $this->updateInstance($instance, []);

                return new ServiceResult(true, null, ['instanceId' => $instanceId]);
            }, 'processDeferredWork');
        } finally {
            $this->restoreExecution($state);
        }
    }

    /**
     * Run only this activity inside a savepoint; persist failures outside it.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance
     * @param string $nodeId Node ID
     * @param array $node Pinned action definition
     * @param \App\Model\Entity\WorkflowExecutionLog $log Current attempt
     * @return string|null Output port, or null when waiting/failed
     */
    private function executeDeferredAction(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        WorkflowExecutionLog $log,
    ): ?string {
        $context = $instance->context ?? [];
        $originalContext = $context;
        $result = null;
        $log->started_at = DateTime::now();
        $connection = ConnectionManager::get('default');
        $connection->enableSavePoints();
        try {
            $connection->transactional(function () use ($instance, $node, &$context, &$result): void {
                $result = $this->invokeWorkflowAction($instance, $node, $context);
                if (!$result['success']) {
                    throw new RuntimeException((string)($result['error'] ?? 'Action failed'));
                }
            });
        } catch (Throwable $exception) {
            $context = $originalContext;
            $result = array_replace($result ?? ['data' => []], [
                'success' => false, 'error' => $exception->getMessage(),
            ]);
            $log->status = WorkflowExecutionLog::STATUS_FAILED;
            $log->error_message = $exception->getMessage();
            $log->output_data = $result;
            $log->completed_at = DateTime::now();
            $this->saveLog($log);
            if ($log->attempt_number <= $this->actionRetryCount($node)) {
                $nextLog = $this->createExecutionLog(
                    $instance,
                    $nodeId,
                    'action',
                    $log->attempt_number + 1,
                    $log->input_data,
                );
                $seconds = min(3600, 2 ** min(12, $log->attempt_number - 1));
                $this->deferWorkflowWork($instance, $nextLog, 'action', DateTime::now()->modify("+{$seconds} seconds"));

                return null;
            }
            $context['nodes'][$nodeId] = ['result' => $result];
            $instance->context = $context;
            if ($this->nodeHasOutput($node, 'error')) {
                return 'error';
            }
            $instance->status = WorkflowInstance::STATUS_FAILED;
            $instance->completed_at = DateTime::now();
            $instance->error_info = [
                'failed_node' => $nodeId,
                'error' => $exception->getMessage(),
                'attempts' => $log->attempt_number,
            ];

            return null;
        }

        $context['nodes'][$nodeId] = ['result' => $result];
        $instance->context = $context;
        $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
        $log->output_data = $result;
        $log->completed_at = DateTime::now();
        $this->saveLog($log);

        return 'default';
    }

    /**
     * Persist the wait and its job in the same tenant transaction.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance
     * @param \App\Model\Entity\WorkflowExecutionLog $log Exact wait/attempt
     * @param string $kind Work kind
     * @param \Cake\I18n\DateTime $dueAt Earliest execution time
     * @return void
     */
    private function deferWorkflowWork(
        WorkflowInstance $instance,
        WorkflowExecutionLog $log,
        string $kind,
        DateTime $dueAt,
    ): void {
        $log->status = WorkflowExecutionLog::STATUS_WAITING;
        $log->output_data = ($log->output_data ?? []) + [
            '_deferred' => ['kind' => $kind, 'dueAt' => $dueAt->format(DATE_ATOM)],
        ];
        $this->saveLog($log);
        $instance->status = WorkflowInstance::STATUS_WAITING;
        $this->updateInstance($instance, []);
        $this->enqueueWorkflowWork($instance, $log, $dueAt);
    }

    /**
     * Queue identifiers only; worker inputs come from the pinned definition.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance
     * @param \App\Model\Entity\WorkflowExecutionLog $log Exact wait/attempt
     * @param \Cake\I18n\DateTime $dueAt Earliest execution time
     * @return void
     */
    private function enqueueWorkflowWork(WorkflowInstance $instance, WorkflowExecutionLog $log, DateTime $dueAt): void
    {
        $jobs = TableRegistry::getTableLocator()->get('Queue.QueuedJobs');
        if ($jobs->getConnection() !== ConnectionManager::get('default')) {
            throw new RuntimeException('Workflow jobs must use the workflow tenant database connection.');
        }
        $jobs->createJob('WorkflowResume', [
            'instanceId' => $instance->id,
            'nodeId' => $log->node_id,
            'executionLogId' => $log->id,
        ], ['notBefore' => $dueAt]);
    }

    /**
     * @param array $node Action node
     * @return int Number of retries after the first attempt
     */
    private function actionRetryCount(array $node): int
    {
        return max(0, (int)($node['config']['maxRetries'] ?? $node['config']['retryCount'] ?? 0));
    }

    /**
     * @param mixed $duration Resolved duration, in seconds or compact units
     * @return int Duration in seconds
     */
    private function delaySeconds(mixed $duration): int
    {
        if (!is_scalar($duration) || !preg_match('/^(\d+)\s*([smhdw]?)$/i', trim((string)$duration), $matches)) {
            throw new RuntimeException(
                'Delay duration must be non-negative seconds or a duration such as 30m, 1h, or 2d.',
            );
        }
        $units = ['' => 1, 's' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800];
        $seconds = (float)$matches[1] * $units[strtolower($matches[2])];
        if ($seconds > 315360000) {
            throw new RuntimeException('Delay duration cannot exceed ten years.');
        }

        return (int)$seconds;
    }
}
