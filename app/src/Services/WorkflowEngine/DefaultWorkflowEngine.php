<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Model\Behavior\WorkflowTriggerBehavior;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Model\Entity\WorkflowTask;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\Conditions\CoreConditions;
use App\Services\WorkflowEngine\StateMachine\StateMachineHandler;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use Cake\Core\ContainerInterface;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Throwable;

/**
 * Default workflow execution engine.
 *
 * Executes workflow graphs by traversing nodes, invoking actions/conditions
 * from the registries, and managing instance lifecycle state.
 *
 * Transaction strategy: each public entry point (startWorkflow, resumeWorkflow,
 * cancelWorkflow, fireIntermediateApprovalActions) wraps its work in a single
 * database transaction via ConnectionManager::get('default')->transactional().
 * Recursive calls (e.g., subworkflow nodes calling startWorkflow, or child
 * completion calling resumeWorkflow) share the outer transaction instead of
 * opening a nested one. The $isInTransaction flag tracks this.
 *
 * Action nodes may invoke services (e.g., WarrantManager) that manage their own
 * internal transactions. CakePHP's Connection handles nested transactional()
 * calls via savepoints, so these are safe and will participate correctly in the
 * outer transaction's commit/rollback.
 *
 * When a workflow hits a WAITING state (approval gate, delay node, subworkflow),
 * the transaction commits at that point. A later resumeWorkflow() call starts a
 * fresh transaction.
 */
class DefaultWorkflowEngine implements WorkflowEngineInterface
{
    /**
     * Maximum node execution depth to prevent infinite recursion.
     */
    private const MAX_EXECUTION_DEPTH = 200;

    private ContainerInterface $container;

    /**
     * Tracks visited nodes during a single execution pass to detect cycles.
     * Reset at the start of each startWorkflow/resumeWorkflow call.
     *
     * @var array<string, bool>
     */
    private array $visitedNodes = [];

    /**
     * Current execution depth counter.
     */
    private int $executionDepth = 0;

    /**
     * Whether we are currently inside a database transaction.
     * Prevents nested transaction wrapping when methods like startWorkflow()
     * or resumeWorkflow() are called recursively (e.g., subworkflow nodes,
     * child completion callbacks).
     */
    private bool $isInTransaction = false;

    /**
     * Whether the current execution is ephemeral (in-memory, no persistence).
     * Set per-execution based on the workflow definition's execution_mode.
     */
    private bool $ephemeral = false;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
    /**
     * @inheritDoc
     */
    public function startWorkflow(
        string $workflowSlug,
        array $triggerData = [],
        ?int $startedBy = null,
        ?string $entityType = null,
        ?int $entityId = null,
    ): ServiceResult {
        // Reset cycle-detection state for this execution pass
        $this->visitedNodes = [];
        $this->executionDepth = 0;
        $this->ephemeral = false;

        return $this->executeInTransaction(function () use ($workflowSlug, $triggerData, $startedBy, $entityType, $entityId) {
            $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
            $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
            $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');

            // Find active definition with a published version
            $workflowDef = $definitionsTable->find()
                ->where([
                    'slug' => $workflowSlug,
                    'is_active' => true,
                    'current_version_id IS NOT' => null,
                    'deleted IS' => null,
                ])
                ->first();

            if (!$workflowDef) {
                return new ServiceResult(false, "No active workflow found for slug '{$workflowSlug}'.");
            }

            // Set ephemeral mode from definition
            $this->ephemeral = ($workflowDef->execution_mode ?? 'durable') === 'ephemeral';

            $version = $versionsTable->get($workflowDef->current_version_id);
            $definition = $version->definition;

            if (empty($definition['nodes'])) {
                return new ServiceResult(false, 'Workflow definition has no nodes.');
            }

            // Duplicate instance prevention — skip for ephemeral workflows and
            // when entity_id is unknown (e.g. entity created by the workflow itself).
            if (!$this->ephemeral && $entityId !== null) {
                $resolvedEntityType = $entityType ?? $workflowDef->entity_type;
                $duplicateConditions = [
                    'workflow_definition_id' => $workflowDef->id,
                    'status IN' => [WorkflowInstance::STATUS_RUNNING, WorkflowInstance::STATUS_WAITING],
                    'entity_id' => $entityId,
                ];
                if ($resolvedEntityType !== null) {
                    $duplicateConditions['entity_type'] = $resolvedEntityType;
                }
                $existingInstance = $instancesTable->find()
                    ->where($duplicateConditions)
                    ->first();

                if ($existingInstance) {
                    Log::warning(
                        "WorkflowEngine: Duplicate instance prevented for definition '{$workflowSlug}'"
                        . " entity_type={$resolvedEntityType} entity_id={$entityId}"
                        . " — existing instance #{$existingInstance->id} is '{$existingInstance->status}'."
                    );

                    return new ServiceResult(
                        false,
                        "A workflow instance is already active (#{$existingInstance->id}) for this entity.",
                        ['existingInstanceId' => $existingInstance->id],
                    );
                }
            }

            // Create instance — in-memory only for ephemeral
            $instance = $instancesTable->newEntity([
                'workflow_definition_id' => $workflowDef->id,
                'workflow_version_id' => $version->id,
                'entity_type' => $entityType ?? $workflowDef->entity_type,
                'entity_id' => $entityId,
                'status' => WorkflowInstance::STATUS_RUNNING,
                'context' => [
                    'trigger' => $triggerData,
                    'triggeredBy' => $startedBy,
                    'nodes' => [],
                    '_internal' => [],
                ],
                'active_nodes' => [],
                'started_by' => $startedBy,
                'started_at' => DateTime::now(),
            ]);

            if (!$this->ephemeral) {
                if (!$instancesTable->save($instance)) {
                    return new ServiceResult(false, 'Failed to create workflow instance.');
                }
            }

            // Find and execute trigger nodes
            $triggerNodes = $this->findNodesByType($definition, 'trigger');

            foreach ($triggerNodes as $triggerNodeId => $triggerNode) {
                // Log trigger node as completed (skip for ephemeral)
                $this->createExecutionLog($instance, $triggerNodeId, 'trigger', 1, $triggerData, $triggerData);

                // Store trigger output in context
                $context = $instance->context;
                $context['nodes'][$triggerNodeId] = ['result' => $triggerData];
                $instance->context = $context;

                // Follow trigger outputs
                $targets = $this->getNodeOutputTargets($definition, $triggerNodeId, 'default');
                foreach ($targets as $targetNodeId) {
                    $this->executeNode($instance, $targetNodeId, $definition);
                }
            }

            $this->hydrateInstanceEntityMetadata($instance, $definition);
            $this->updateInstance($instance, []);

            return new ServiceResult(true, null, [
                'instanceId' => $this->ephemeral ? null : $instance->id,
                'ephemeral' => $this->ephemeral,
                'workflowResult' => $instance->context['workflowResult'] ?? null,
            ]);
        }, 'startWorkflow');
    }

    /**
     * @inheritDoc
     */
    public function resumeWorkflow(
        int $instanceId,
        string $nodeId,
        string $outputPort,
        array $additionalData = [],
    ): ServiceResult {
        // Reset cycle-detection state for this execution pass
        $this->visitedNodes = [];
        $this->executionDepth = 0;

        return $this->executeInTransaction(function () use ($instanceId, $nodeId, $outputPort, $additionalData) {
            $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
            $instance = $instancesTable->get($instanceId, contain: ['WorkflowVersions']);

            if ($instance->status !== WorkflowInstance::STATUS_WAITING) {
                return new ServiceResult(false, "Instance {$instanceId} is not in waiting state.");
            }

            // Merge additional data into context
            $context = $instance->context ?? [];
            if (!empty($additionalData)) {
                $context['resumeData'] = $additionalData;
            }

            // Store approval output in nodes context so $.nodes.<nodeId>.* resolves
            if (!isset($context['nodes'])) {
                $context['nodes'] = [];
            }
            $context['nodes'][$nodeId] = [
                'status' => $outputPort,
                'approverId' => $additionalData['approverId'] ?? null,
                'comment' => $additionalData['comment'] ?? null,
                'rejectionComment' => $additionalData['comment'] ?? null,
                'decision' => $additionalData['decision'] ?? $outputPort,
            ];
            $instance->context = $context;

            $instance->status = WorkflowInstance::STATUS_RUNNING;
            $this->updateInstance($instance, []);

            $definition = $instance->workflow_version->definition;

            // Mark the waiting log as completed
            $logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
            $waitingLog = $logsTable->find()
                ->where([
                    'workflow_instance_id' => $instanceId,
                    'node_id' => $nodeId,
                    'status' => WorkflowExecutionLog::STATUS_WAITING,
                ])
                ->order(['id' => 'DESC'])
                ->first();

            if ($waitingLog) {
                $waitingLog->status = WorkflowExecutionLog::STATUS_COMPLETED;
                $waitingLog->completed_at = DateTime::now();
                $waitingLog->output_data = $additionalData;
                $logsTable->save($waitingLog);
            }

            // Remove node from active_nodes
            $activeNodes = $instance->active_nodes ?? [];
            $activeNodes = array_values(array_filter($activeNodes, fn($n) => $n !== $nodeId));
            $instance->active_nodes = $activeNodes;

            // Follow the specified output port
            $targets = $this->getNodeOutputTargets($definition, $nodeId, $outputPort);
            foreach ($targets as $targetNodeId) {
                $this->executeNode($instance, $targetNodeId, $definition);
            }

            $this->hydrateInstanceEntityMetadata($instance, $definition);
            $this->updateInstance($instance, []);

            return new ServiceResult(true, null, ['instanceId' => $instanceId]);
        }, 'resumeWorkflow', $instanceId);
    }

    /**
     * @inheritDoc
     */
    public function cancelWorkflow(int $instanceId, ?string $reason = null): ServiceResult
    {
        return $this->executeInTransaction(function () use ($instanceId, $reason) {
            $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
            $instance = $instancesTable->get($instanceId);

            if ($instance->isTerminal()) {
                return new ServiceResult(false, "Instance {$instanceId} is already in terminal state '{$instance->status}'.");
            }

            $instance->status = WorkflowInstance::STATUS_CANCELLED;
            $instance->completed_at = DateTime::now();
            if ($reason) {
                $errorInfo = $instance->error_info ?? [];
                $errorInfo['cancellation_reason'] = $reason;
                $instance->error_info = $errorInfo;
            }

            if (!$instancesTable->save($instance)) {
                return new ServiceResult(false, 'Failed to save cancelled instance.');
            }

            // Cancel any pending approvals
            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
            $pendingApprovals = $approvalsTable->find()
                ->where([
                    'workflow_instance_id' => $instanceId,
                    'status' => WorkflowApproval::STATUS_PENDING,
                ])
                ->all();

            foreach ($pendingApprovals as $approval) {
                $approval->status = WorkflowApproval::STATUS_CANCELLED;
                $approvalsTable->save($approval);
            }

            return new ServiceResult(true, null, ['instanceId' => $instanceId]);
        }, 'cancelWorkflow', $instanceId);
    }

    /**
     * @inheritDoc
     */
    public function getInstanceState(int $instanceId): ?array
    {
        try {
            $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
            $instance = $instancesTable->get($instanceId, contain: [
                'WorkflowDefinitions',
                'WorkflowVersions',
                'WorkflowExecutionLogs',
                'WorkflowApprovals',
            ]);

            return [
                'id' => $instance->id,
                'status' => $instance->status,
                'context' => $instance->context,
                'active_nodes' => $instance->active_nodes,
                'error_info' => $instance->error_info,
                'started_at' => $instance->started_at,
                'completed_at' => $instance->completed_at,
                'definition_name' => $instance->workflow_definition->name ?? null,
                'version_number' => $instance->workflow_version->version_number ?? null,
                'execution_logs' => array_map(fn($log) => [
                    'node_id' => $log->node_id,
                    'node_type' => $log->node_type,
                    'status' => $log->status,
                    'started_at' => $log->started_at,
                    'completed_at' => $log->completed_at,
                    'error_message' => $log->error_message,
                ], $instance->workflow_execution_logs ?? []),
                'approvals' => array_map(fn($a) => [
                    'node_id' => $a->node_id,
                    'status' => $a->status,
                    'required_count' => $a->required_count,
                    'approved_count' => $a->approved_count,
                    'rejected_count' => $a->rejected_count,
                ], $instance->workflow_approvals ?? []),
            ];
        } catch (Throwable $e) {
            Log::error("WorkflowEngine::getInstanceState failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function dispatchTrigger(
        string $eventName,
        array $eventData = [],
        ?int $triggeredBy = null,
    ): array {
        $results = [];

        try {
            $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');

            $activeDefinitions = $definitionsTable->find()
                ->where([
                    'is_active' => true,
                    'current_version_id IS NOT' => null,
                    'deleted IS' => null,
                ])
                ->contain(['CurrentVersion'])
                ->all();

            foreach ($activeDefinitions as $def) {
                $version = $def->current_version;
                if (!$version || empty($version->definition['nodes'])) {
                    continue;
                }

                $definition = $version->definition;
                $triggerNodes = $this->findNodesByType($definition, 'trigger');

                foreach ($triggerNodes as $triggerNode) {
                    $triggerEvent = $triggerNode['config']['event'] ?? $triggerNode['config']['eventName'] ?? null;
                    if ($triggerEvent === $eventName) {
                        // Resolve entity ID from trigger config's entityIdField
                        $entityIdField = $triggerNode['config']['entityIdField'] ?? null;
                        $entityId = $entityIdField ? ($eventData[$entityIdField] ?? null) : null;

                        $result = $this->startWorkflow(
                            $def->slug,
                            $eventData,
                            $triggeredBy,
                            null, // entityType from definition
                            $entityId ? (int)$entityId : null,
                        );
                        $results[] = $result;
                        break; // Only start once per definition
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error("WorkflowEngine::dispatchTrigger failed: {$e->getMessage()}");
            $results[] = new ServiceResult(false, "Trigger dispatch error: {$e->getMessage()}");
        }

        return $results;
    }

    /**
     * Execute a single workflow node and advance to connected outputs.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance The workflow instance
     * @param string $nodeId The node ID to execute
     * @param array $definition The workflow definition graph
     * @return void
     */
    protected function executeNode(WorkflowInstance $instance, string $nodeId, array $definition): void
    {
        // Cycle detection: check depth limit
        $this->executionDepth++;
        if ($this->executionDepth > self::MAX_EXECUTION_DEPTH) {
            $msg = "Workflow execution exceeded max depth (" . self::MAX_EXECUTION_DEPTH
                . ") at node '{$nodeId}'. Possible cycle in workflow graph.";
            Log::error("WorkflowEngine: {$msg}");
            $instance->status = WorkflowInstance::STATUS_FAILED;
            $instance->completed_at = DateTime::now();
            $instance->error_info = ['failed_node' => $nodeId, 'error' => $msg];
            $this->updateInstance($instance, []);

            throw new \RuntimeException($msg);
        }

        // Cycle detection: visited-node set (skip for join/loop which are legitimately revisited)
        $node = $definition['nodes'][$nodeId] ?? null;
        $nodeType = $node['type'] ?? 'unknown';

        $allowRevisit = in_array($nodeType, ['join', 'loop'], true);
        if (!$allowRevisit && isset($this->visitedNodes[$nodeId])) {
            $msg = "Cycle detected: node '{$nodeId}' was already visited in this execution path.";
            Log::error("WorkflowEngine: {$msg}");
            $instance->status = WorkflowInstance::STATUS_FAILED;
            $instance->completed_at = DateTime::now();
            $instance->error_info = ['failed_node' => $nodeId, 'error' => $msg];
            $this->updateInstance($instance, []);

            throw new \RuntimeException($msg);
        }
        $this->visitedNodes[$nodeId] = true;

        $logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');

        if (!$node) {
            Log::error("WorkflowEngine: Node '{$nodeId}' not found in definition.");

            return;
        }

        $nodeConfig = $node['config'] ?? [];

        // Add to active nodes
        $activeNodes = $instance->active_nodes ?? [];
        if (!in_array($nodeId, $activeNodes, true)) {
            $activeNodes[] = $nodeId;
            $instance->active_nodes = $activeNodes;
        }

        // Ephemeral workflows cannot use async node types
        if ($this->ephemeral && in_array($nodeType, ['approval', 'humanTask', 'delay', 'subworkflow'], true)) {
            throw new \RuntimeException(
                "Ephemeral workflow cannot execute async node type '{$nodeType}' at node '{$nodeId}'. "
                . "Change the workflow's execution_mode to 'durable' to use {$nodeType} nodes."
            );
        }

        // Retry logic: determine max attempts from node config
        $maxRetries = (int)($nodeConfig['maxRetries'] ?? $nodeConfig['retryCount'] ?? 0);
        $maxAttempts = $maxRetries + 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // Create execution log for this attempt (null in ephemeral mode)
            $log = $this->createExecutionLog(
                $instance,
                $nodeId,
                $nodeType,
                $attempt,
                $this->resolveInputData($instance->context ?? [], $nodeConfig),
            );

            try {
                switch ($nodeType) {
                    case 'action':
                        $this->executeActionNode($instance, $nodeId, $node, $log, $definition);
                        break;

                    case 'condition':
                        $this->executeConditionNode($instance, $nodeId, $node, $log, $definition);
                        break;

                    case 'approval':
                        $this->executeApprovalNode($instance, $nodeId, $node, $log);
                        break;

                    case 'fork':
                        $this->executeForkNode($instance, $nodeId, $log, $definition);
                        break;

                    case 'join':
                        $this->executeJoinNode($instance, $nodeId, $node, $log, $definition);
                        break;

                    case 'loop':
                        $this->executeLoopNode($instance, $nodeId, $node, $log, $definition);
                        break;

                    case 'forEach':
                        $this->executeForEachNode($instance, $nodeId, $node, $log, $definition);
                        break;

                    case 'delay':
                        $this->executeDelayNode($instance, $nodeId, $node, $log);
                        break;

                    case 'end':
                        $this->executeEndNode($instance, $nodeId, $node, $log);
                        break;

                    case 'subworkflow':
                        $this->executeSubworkflowNode($instance, $nodeId, $node, $log);
                        break;

                    case 'stateMachine':
                        $this->executeStateMachineNode($instance, $nodeId, $node, $log, $definition);
                        break;

                    case 'humanTask':
                        $this->executeHumanTaskNode($instance, $nodeId, $node, $log);
                        break;

                    default:
                        if ($log) {
                            $log->status = WorkflowExecutionLog::STATUS_FAILED;
                            $log->error_message = "Unknown node type: {$nodeType}";
                            $log->completed_at = DateTime::now();
                            $this->saveLog($log);
                        }
                        break;
                }

                // Success — break out of retry loop
                return;
            } catch (Throwable $e) {
                $lastException = $e;
                if ($log) {
                    $log->status = WorkflowExecutionLog::STATUS_FAILED;
                    $log->error_message = $e->getMessage();
                    $log->completed_at = DateTime::now();
                    $this->saveLog($log);
                }

                if ($attempt < $maxAttempts) {
                    // Exponential backoff: 1s, 2s, 4s, ...
                    $backoffSeconds = (int)pow(2, $attempt - 1);
                    Log::warning(
                        "WorkflowEngine: Node '{$nodeId}' attempt {$attempt}/{$maxAttempts} failed, "
                        . "retrying in {$backoffSeconds}s: {$e->getMessage()}"
                    );
                    sleep($backoffSeconds);
                }
            }
        }

        // All attempts exhausted — mark instance as failed
        Log::error("WorkflowEngine: Node '{$nodeId}' failed after {$maxAttempts} attempt(s): {$lastException->getMessage()}");

        $instance->status = WorkflowInstance::STATUS_FAILED;
        $instance->completed_at = DateTime::now();
        $instance->error_info = [
            'failed_node' => $nodeId,
            'error' => $lastException->getMessage(),
            'attempts' => $maxAttempts,
        ];
        $this->updateInstance($instance, []);

        throw $lastException;
    }

    /**
     * Execute an action node via the WorkflowActionRegistry.
     */
    protected function executeActionNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
        array $definition,
    ): void {
        $actionName = $node['config']['action'] ?? null;

        if (!$actionName) {
            throw new \RuntimeException("Action node '{$nodeId}' has no action configured.");
        }

        $actionConfig = WorkflowActionRegistry::getAction($actionName);
        if (!$actionConfig) {
            throw new \RuntimeException("Action '{$actionName}' not found in registry.");
        }

        // Async execution: queue the action instead of running inline
        $isAsync = !empty($node['config']['isAsync']) || !empty($actionConfig['isAsync']);
        if ($isAsync) {
            if ($log) {
                $log->status = WorkflowExecutionLog::STATUS_WAITING;
                $log->output_data = ['queued' => true, 'action' => $actionName];
                $this->saveLog($log);
            }

            $instance->status = WorkflowInstance::STATUS_WAITING;
            $this->updateInstance($instance, []);

            $queuedJobsTable = TableRegistry::getTableLocator()->get('Queue.QueuedJobs');
            $queuedJobsTable->createJob('WorkflowResume', [
                'instanceId' => $instance->id,
                'nodeId' => $nodeId,
                'outputPort' => 'default',
                'additionalData' => [
                    'asyncAction' => $actionName,
                    'nodeConfig' => $node['config'] ?? [],
                ],
            ]);

            Log::info("WorkflowEngine: Async action '{$actionName}' queued for node '{$nodeId}' instance #{$instance->id}.");

            return;
        }

        $serviceClass = $actionConfig['serviceClass'];
        $serviceMethod = $actionConfig['serviceMethod'];

        if (!$this->container->has($serviceClass)) {
            throw new \RuntimeException(
                "Workflow action service '{$serviceClass}' is not registered in the DI container. "
                . "Register it in Application::services() or your plugin's services() method."
            );
        }
        $service = $this->container->get($serviceClass);
        $context = $instance->context ?? [];
        $context['instanceId'] = $instance->id;

        // Resolve and merge config.params into top-level config so actions can read params directly
        $nodeConfig = $node['config'] ?? [];
        if (!empty($nodeConfig['params']) && is_array($nodeConfig['params'])) {
            $resolvedParams = [];
            foreach ($nodeConfig['params'] as $key => $paramValue) {
                $resolvedParams[$key] = $this->resolveParamValue($paramValue, $context);
            }
            $nodeConfig = array_merge($nodeConfig, $resolvedParams);
        }

        $result = $this->executeActionService($service, $serviceMethod, $context, $nodeConfig);
        $result = $this->applyActionContextUpdates($context, $result);

        // Store result in context
        $context['nodes'][$nodeId] = ['result' => $result];
        $instance->context = $context;
        $this->hydrateInstanceEntityMetadata($instance, $definition);

        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
            $log->output_data = $result;
            $log->completed_at = DateTime::now();
            $this->saveLog($log);
        }

        $this->removeFromActiveNodes($instance, $nodeId);
        $this->advanceToOutputs($instance, $nodeId, 'default', $definition);
    }

    /**
     * Execute a condition node and follow the matching output port.
     */
    protected function executeConditionNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
        array $definition,
    ): void {
        $conditionName = $node['config']['condition'] ?? $node['config']['evaluator'] ?? null;
        $context = $instance->context ?? [];
        $context['instanceId'] = $instance->id;
        $result = false;

        if ($conditionName) {
            $conditionConfig = WorkflowConditionRegistry::getCondition($conditionName);
            if ($conditionConfig) {
                $evaluatorClass = $conditionConfig['evaluatorClass'];
                $evaluatorMethod = $conditionConfig['evaluatorMethod'];
                if (!$this->container->has($evaluatorClass)) {
                    throw new \RuntimeException(
                        "Workflow condition evaluator '{$evaluatorClass}' is not registered in the DI container. "
                        . "Register it in Application::services() or your plugin's services() method."
                    );
                }
                $evaluator = $this->container->get($evaluatorClass);
                // Resolve and merge config.params into top-level config so evaluators can read params directly
                $nodeConfig = $node['config'] ?? [];
                if (isset($nodeConfig['expectedValue'])) {
                    $nodeConfig['expectedValue'] = $this->resolveParamValue($nodeConfig['expectedValue'], $context);
                }
                if (!empty($nodeConfig['params']) && is_array($nodeConfig['params'])) {
                    $resolvedParams = [];
                    foreach ($nodeConfig['params'] as $key => $paramValue) {
                        $resolvedParams[$key] = $this->resolveParamValue($paramValue, $context);
                    }
                    $nodeConfig = array_merge($nodeConfig, $resolvedParams);
                }
                $result = (bool)$evaluator->{$evaluatorMethod}($context, $nodeConfig);
            } else {
                throw new \RuntimeException("Condition '{$conditionName}' not found in registry.");
            }
        } else {
            // Handle inline expression evaluation
            $evaluator = new CoreConditions();
            $result = $evaluator->evaluateExpression($context, $node['config']);
        }

        $outputPort = $result ? 'true' : 'false';

        $context['nodes'][$nodeId] = ['result' => $result, 'port' => $outputPort];
        $instance->context = $context;

        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
            $log->output_data = ['result' => $result, 'port' => $outputPort];
            $log->completed_at = DateTime::now();
            $this->saveLog($log);
        }

        $this->removeFromActiveNodes($instance, $nodeId);
        $this->advanceToOutputs($instance, $nodeId, $outputPort, $definition);
    }

    /**
     * Execute an approval node — creates approval record, sets instance to WAITING.
     */
    protected function executeApprovalNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
    ): void {
        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

        $config = $node['config'] ?? [];

        // Build approver_config from top-level config keys
        $approverConfig = $config['approverConfig'] ?? [];
        if (empty($approverConfig)) {
            // Map approverValue (set by UI) to the correct key based on approverType
            $approverType = $config['approverType'] ?? '';
            $approverValue = $config['approverValue'] ?? '';
            if (!empty($approverValue)) {
                $typeKeyMap = [
                    'permission' => 'permission',
                    'role' => 'role',
                    'member' => 'member_id',
                ];
                $mappedKey = $typeKeyMap[$approverType] ?? null;
                if ($mappedKey) {
                    $approverConfig[$mappedKey] = $approverValue;
                }
            }

            if (!empty($config['permission'])) {
                $approverConfig['permission'] = $config['permission'];
            }
            if (!empty($config['role'])) {
                $approverConfig['role'] = $config['role'];
            }
            if (!empty($config['member_id'])) {
                $approverConfig['member_id'] = $config['member_id'];
            }
            // Policy approver type fields
            if (!empty($config['policyClass'])) {
                $approverConfig['policyClass'] = $config['policyClass'];
            }
            if (!empty($config['policyAction'])) {
                $approverConfig['policyAction'] = $config['policyAction'];
            }
            if (!empty($config['entityTable'])) {
                $approverConfig['entityTable'] = $config['entityTable'];
            }
            if (!empty($config['entityIdKey'])) {
                $approverConfig['entityIdKey'] = $config['entityIdKey'];
            }
            // Dynamic resolver fields (flat config backward compat)
            if (!empty($config['resolverService'])) {
                $approverConfig['service'] = $config['resolverService'];
            }
            if (!empty($config['resolverMethod'])) {
                $approverConfig['method'] = $config['resolverMethod'];
            }
            // For dynamic type, preserve any remaining custom keys
            if (($config['approverType'] ?? '') === 'dynamic') {
                $standardKeys = ['approverType', 'approverConfig', 'approverValue', 'resolverService', 'resolverMethod',
                    'permission', 'role', 'member_id', 'policyClass', 'policyAction', 'entityTable', 'entityIdKey',
                    'requiredCount', 'serialPickNext', 'allowParallel', 'allowComments', 'deadline'];
                foreach ($config as $key => $value) {
                    if (!in_array($key, $standardKeys) && !isset($approverConfig[$key])) {
                        $approverConfig[$key] = $value;
                    }
                }
            }
        }

        // Resolve context references ($.) in approverConfig values
        $instanceContext = $instance->context ?? [];
        foreach ($approverConfig as $key => $value) {
            if (is_string($value) && str_starts_with($value, '$.')) {
                $approverConfig[$key] = $this->resolveParamValue($value, $instanceContext);
            }
        }

        // Preserve serial_pick_next flag from config into approverConfig
        if (!empty($config['serialPickNext'])) {
            $approverConfig['serial_pick_next'] = true;
        }

        // Preserve commentWarning from config into approverConfig
        if (!empty($config['commentWarning'])) {
            $approverConfig['comment_warning'] = $config['commentWarning'];
        }

        // Resolve requiredCount (may be int, or {type: "app_setting", key: "..."})
        $requiredCount = $this->resolveRequiredCount($config['requiredCount'] ?? 1, $instance->context ?? []);

        // Resolve initial approver from config (e.g., "$.trigger.approverId")
        $initialApproverId = null;
        if (!empty($config['initialApproverId'])) {
            $resolved = $this->resolveParamValue($config['initialApproverId'], $instanceContext);
            if ($resolved !== null) {
                $initialApproverId = (int)$resolved;
                $approverConfig['current_approver_id'] = $initialApproverId;
            }
        }

        // Parse deadline duration (e.g., "14d", "24h", "7d") into a future DateTime
        $deadline = null;
        if (!empty($config['deadline'])) {
            $deadline = $this->parseDeadline($config['deadline']);
        }

        $approval = $approvalsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => $nodeId,
            'execution_log_id' => $log?->id,
            'approver_type' => $config['approverType'] ?? WorkflowApproval::APPROVER_TYPE_PERMISSION,
            'approver_config' => $approverConfig,
            'current_approver_id' => $initialApproverId,
            'required_count' => $requiredCount,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => !empty($config['parallel'] ?? $config['allowParallel'] ?? false),
            'deadline' => $deadline,
            'escalation_config' => $config['escalationConfig'] ?? null,
            'approval_token' => \App\KMP\StaticHelpers::generateToken(32),
        ]);
        if ($approval->getErrors()) {
            Log::error('Approval entity validation errors: ' . json_encode($approval->getErrors()));
        }
        if (!$approvalsTable->save($approval)) {
            Log::error('Failed to save workflow approval for node ' . $nodeId . ': ' . json_encode($approval->getErrors()));
        }

        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_WAITING;
            $this->saveLog($log);
        }

        $instance->status = WorkflowInstance::STATUS_WAITING;
        $this->updateInstance($instance, []);
    }

    /**
     * Execute a humanTask node — creates a task record and pauses the workflow.
     *
     * The workflow remains in WAITING status until completeHumanTask() is
     * called with the submitted form data.
     */
    protected function executeHumanTaskNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
    ): void {
        $tasksTable = TableRegistry::getTableLocator()->get('WorkflowTasks');

        $config = $node['config'] ?? [];
        $instanceContext = $instance->context ?? [];

        // Resolve assignTo — may be a $.path or a literal member ID
        $assignedTo = null;
        if (!empty($config['assignTo'])) {
            $resolved = $this->resolveParamValue($config['assignTo'], $instanceContext);
            if ($resolved !== null) {
                $assignedTo = (int)$resolved;
            }
        }

        // Resolve assignByRole — permission name for role-based assignment
        $assignedByRole = $config['assignByRole'] ?? null;

        // Resolve task title
        $taskTitle = $config['taskTitle'] ?? null;
        if (is_string($taskTitle) && str_starts_with($taskTitle, '$.')) {
            $taskTitle = $this->resolveParamValue($taskTitle, $instanceContext);
        }

        // Build form definition from config
        $formFields = $config['formFields'] ?? [];

        // Parse due date
        $dueDate = null;
        if (!empty($config['dueDate'])) {
            $dueDateValue = $config['dueDate'];
            // Support "=$.now + 7 days" style expressions
            if (is_string($dueDateValue) && preg_match('/^\s*=?\s*\$\.now\s*\+\s*(\d+)\s*(days?|hours?|minutes?)\s*$/i', $dueDateValue, $matches)) {
                $amount = (int)$matches[1];
                $unit = strtolower($matches[2]);
                // Normalise singular
                if (!str_ends_with($unit, 's')) {
                    $unit .= 's';
                }
                $dueDate = DateTime::now()->modify("+{$amount} {$unit}");
            } elseif (is_string($dueDateValue)) {
                // Try as a duration string (e.g. "7d")
                $dueDate = $this->parseDeadline($dueDateValue);
            }
        }

        $task = $tasksTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => $nodeId,
            'assigned_to' => $assignedTo,
            'assigned_by_role' => $assignedByRole,
            'task_title' => $taskTitle,
            'form_definition' => $formFields,
            'form_data' => null,
            'status' => WorkflowTask::STATUS_PENDING,
            'due_date' => $dueDate,
        ]);

        if ($task->getErrors()) {
            Log::error('WorkflowTask entity validation errors: ' . json_encode($task->getErrors()));
        }
        if (!$tasksTable->save($task)) {
            Log::error('Failed to save workflow task for node ' . $nodeId . ': ' . json_encode($task->getErrors()));
        }

        // Store task ID in context for later reference
        $context = $instance->context ?? [];
        if (!isset($context['nodes'])) {
            $context['nodes'] = [];
        }
        $context['nodes'][$nodeId] = [
            'taskId' => $task->id,
            'status' => 'pending',
        ];
        $instance->context = $context;

        // Set log to WAITING
        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_WAITING;
            $log->output_data = ['taskId' => $task->id];
            $this->saveLog($log);
        }

        // Pause workflow
        $instance->status = WorkflowInstance::STATUS_WAITING;
        $this->updateInstance($instance, []);
    }

    /**
     * Complete a human task and resume the workflow.
     *
     * Validates required form fields, saves form data to the task record,
     * merges values into the workflow context via contextMapping, and
     * resumes the workflow from the humanTask node's default output.
     *
     * @param int $taskId The workflow_tasks.id to complete
     * @param array $formData Submitted form field values
     * @param int $completedBy Member ID of the user completing the task
     * @return \App\Services\ServiceResult
     */
    public function completeHumanTask(int $taskId, array $formData, int $completedBy): ServiceResult
    {
        return $this->executeInTransaction(function () use ($taskId, $formData, $completedBy) {
            $tasksTable = TableRegistry::getTableLocator()->get('WorkflowTasks');

            $task = $tasksTable->find()
                ->where(['WorkflowTasks.id' => $taskId])
                ->first();

            if (!$task) {
                return new ServiceResult(false, "Task #{$taskId} not found.");
            }

            if ($task->status !== WorkflowTask::STATUS_PENDING) {
                return new ServiceResult(false, "Task #{$taskId} is no longer pending (status: {$task->status}).");
            }

            // Check if the task has expired
            if ($task->due_date !== null && $task->due_date->isPast()) {
                $task->status = WorkflowTask::STATUS_EXPIRED;
                $tasksTable->save($task);

                return new ServiceResult(false, "Task #{$taskId} has expired.");
            }

            // Validate required form fields
            $formDefinition = $task->form_definition ?? [];
            $missingFields = [];
            foreach ($formDefinition as $field) {
                $fieldName = $field['name'] ?? null;
                $required = $field['required'] ?? false;
                if ($required && $fieldName) {
                    if (!array_key_exists($fieldName, $formData) || $formData[$fieldName] === null || $formData[$fieldName] === '') {
                        $missingFields[] = $fieldName;
                    }
                }
            }
            if (!empty($missingFields)) {
                return new ServiceResult(false, 'Missing required fields: ' . implode(', ', $missingFields));
            }

            // Save completion data
            $task->form_data = $formData;
            $task->status = WorkflowTask::STATUS_COMPLETED;
            $task->completed_at = DateTime::now();
            $task->completed_by = $completedBy;
            $tasksTable->save($task);

            // Load the workflow instance to merge context
            $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
            $instance = $instancesTable->get($task->workflow_instance_id, contain: ['WorkflowVersions']);
            $definition = $instance->workflow_version->definition;

            // Apply contextMapping from the node config
            $nodeConfig = $definition['nodes'][$task->node_id]['config'] ?? [];
            $contextMapping = $nodeConfig['contextMapping'] ?? [];

            $context = $instance->context ?? [];

            foreach ($contextMapping as $formField => $contextPath) {
                $value = $formData[$formField] ?? null;
                if ($contextPath && $value !== null) {
                    $this->setContextValue($context, $contextPath, $value);
                }
            }

            $instance->context = $context;
            $this->updateInstance($instance, []);

            // Resume the workflow from the humanTask node
            return $this->resumeWorkflow(
                $task->workflow_instance_id,
                $task->node_id,
                'default',
                ['taskId' => $taskId, 'formData' => $formData, 'completedBy' => $completedBy],
            );
        }, 'completeHumanTask');
    }

    /**
     * Cancel a pending human task without resuming the workflow.
     *
     * @param int $taskId The workflow_tasks.id to cancel
     * @param string|null $reason Optional cancellation reason
     * @return \App\Services\ServiceResult
     */
    public function cancelHumanTask(int $taskId, ?string $reason = null): ServiceResult
    {
        $tasksTable = TableRegistry::getTableLocator()->get('WorkflowTasks');

        $task = $tasksTable->find()
            ->where(['WorkflowTasks.id' => $taskId])
            ->first();

        if (!$task) {
            return new ServiceResult(false, "Task #{$taskId} not found.");
        }

        if ($task->status !== WorkflowTask::STATUS_PENDING) {
            return new ServiceResult(false, "Task #{$taskId} is no longer pending.");
        }

        $task->status = WorkflowTask::STATUS_CANCELLED;
        $tasksTable->save($task);

        return new ServiceResult(true, null, ['taskId' => $taskId]);
    }

    /**
     * Execute a fork node — marks complete and executes all output targets.
     */
    protected function executeForkNode(
        WorkflowInstance $instance,
        string $nodeId,
        ?WorkflowExecutionLog $log,
        array $definition,
    ): void {
        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
            $log->completed_at = DateTime::now();
            $this->saveLog($log);
        }

        $this->removeFromActiveNodes($instance, $nodeId);

        // Execute all output targets (parallel paths)
        $allTargets = $this->getAllOutputTargets($definition, $nodeId);
        foreach ($allTargets as $targetNodeId) {
            $this->executeNode($instance, $targetNodeId, $definition);
        }
    }

    /**
     * Execute a join node — waits for all input paths before advancing.
     */
    protected function executeJoinNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
        array $definition,
    ): void {
        $context = $instance->context ?? [];

        // Track join state
        $joinKey = "_internal.joinState.{$nodeId}";
        $joinState = $context['_internal']['joinState'][$nodeId] ?? [];
        $completedInputs = $joinState['completedInputs'] ?? [];

        // Find which edges lead into this join node
        $expectedInputs = $this->getNodeInputSources($definition, $nodeId);
        $completedInputs = array_unique(array_merge(
            $completedInputs,
            [$this->findIncomingSource($definition, $nodeId, $instance, $completedInputs)],
        ));

        $context['_internal']['joinState'][$nodeId] = ['completedInputs' => $completedInputs];
        $instance->context = $context;

        if (count($completedInputs) >= count($expectedInputs)) {
            // All inputs completed — advance
            if ($log) {
                $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
                $log->completed_at = DateTime::now();
                $this->saveLog($log);
            }

            $this->removeFromActiveNodes($instance, $nodeId);
            $this->advanceToOutputs($instance, $nodeId, 'default', $definition);
        } else {
            // Still waiting for other paths
            if ($log) {
                $log->status = WorkflowExecutionLog::STATUS_WAITING;
                $this->saveLog($log);
            }
        }
    }

    /**
     * Execute a loop node — iterates until max count or exit condition.
     */
    protected function executeLoopNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
        array $definition,
    ): void {
        $context = $instance->context ?? [];
        $config = $node['config'] ?? [];

        $maxIterations = (int)($config['maxIterations'] ?? 10);
        $iterationKey = "_internal.loopState.{$nodeId}.iteration";
        $currentIteration = $context['_internal']['loopState'][$nodeId]['iteration'] ?? 0;
        $currentIteration++;

        $context['_internal']['loopState'][$nodeId] = ['iteration' => $currentIteration];
        $instance->context = $context;

        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
            $log->output_data = ['iteration' => $currentIteration, 'maxIterations' => $maxIterations];
            $log->completed_at = DateTime::now();
            $this->saveLog($log);
        }

        $this->removeFromActiveNodes($instance, $nodeId);

        // Check exit condition
        $shouldExit = $currentIteration >= $maxIterations;
        if (!$shouldExit && !empty($config['exitCondition'])) {
            $evaluator = new CoreConditions();
            $shouldExit = $evaluator->evaluateExpression($context, ['expression' => $config['exitCondition']]);
        }

        $outputPort = $shouldExit ? 'exit' : 'continue';
        $this->advanceToOutputs($instance, $nodeId, $outputPort, $definition);
    }

    /**
     * Execute a forEach node — iterates over a collection, executing child nodes per item.
     *
     * Output ports: 'iterate' (per item), 'complete' (after all), 'error' (on failure).
     */
    protected function executeForEachNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
        array $definition,
    ): void {
        $context = $instance->context ?? [];
        $context['instanceId'] = $instance->id;
        $config = $node['config'] ?? [];

        $collectionPath = $config['collection'] ?? '';
        $itemVariable = $config['itemVariable'] ?? 'currentItem';
        $indexVariable = $config['indexVariable'] ?? 'currentIndex';
        $continueOnError = !empty($config['continueOnError']);

        // Resolve collection from context
        $collection = $collectionPath ? $this->resolveContextValue($context, $collectionPath) : null;

        if (!is_array($collection)) {
            $collection = [];
        }

        // Collect all descendant node IDs reachable from iterate targets
        // so we can clear their visited state between iterations
        $iterateTargets = $this->getNodeOutputTargets($definition, $nodeId, 'iterate');
        $iterateDescendants = [];
        foreach ($iterateTargets as $targetId) {
            $this->collectDescendants($definition, $targetId, $iterateDescendants);
        }

        $processed = 0;
        $errors = [];
        $results = [];

        foreach ($collection as $index => $item) {
            // Clear visited state for iterate descendants so they can execute again
            foreach ($iterateDescendants as $descendantId => $_) {
                unset($this->visitedNodes[$descendantId]);
            }

            // Set iteration variables in context
            $context[$itemVariable] = $item;
            $context[$indexVariable] = $index;
            $instance->context = $context;

            try {
                // Execute child nodes connected to 'iterate' output port
                foreach ($iterateTargets as $targetNodeId) {
                    $this->executeNode($instance, $targetNodeId, $definition);
                }

                // Capture any result stored by child nodes for this iteration
                $iterationResult = $instance->context['nodes'][$nodeId . '_iteration'] ?? null;
                $results[] = $iterationResult;
                $processed++;

                // Re-read context after child execution (children may have mutated it)
                $context = $instance->context ?? [];
            } catch (Throwable $e) {
                $errors[] = [
                    'index' => $index,
                    'message' => $e->getMessage(),
                ];

                // Clean up failed iterate descendants from active_nodes
                foreach ($iterateDescendants as $descId => $_) {
                    $this->removeFromActiveNodes($instance, $descId);
                }

                if (!$continueOnError) {
                    // Store partial results, log failure, and fire error port
                    $context = $instance->context ?? [];
                    $context['forEach'][$nodeId] = [
                        'processed' => $processed,
                        'errors' => $errors,
                        'results' => $results,
                    ];
                    $instance->context = $context;

                    // Restore instance to running so error port can continue execution
                    $instance->status = WorkflowInstance::STATUS_RUNNING;
                    $instance->completed_at = null;
                    $instance->error_info = null;
                    $this->updateInstance($instance, []);

                    if ($log) {
                        $log->status = WorkflowExecutionLog::STATUS_FAILED;
                        $log->error_message = "forEach failed at index {$index}: " . $e->getMessage();
                        $log->output_data = $context['forEach'][$nodeId];
                        $log->completed_at = DateTime::now();
                        $this->saveLog($log);
                    }

                    $this->removeFromActiveNodes($instance, $nodeId);

                    // Clear visited state for error port descendants
                    $errorTargets = $this->getNodeOutputTargets($definition, $nodeId, 'error');
                    foreach ($errorTargets as $errTargetId) {
                        $errDescendants = [];
                        $this->collectDescendants($definition, $errTargetId, $errDescendants);
                        foreach ($errDescendants as $descId => $_) {
                            unset($this->visitedNodes[$descId]);
                        }
                    }

                    $this->advanceToOutputs($instance, $nodeId, 'error', $definition);

                    return;
                }

                // continueOnError: restore instance status and keep going
                $instance->status = WorkflowInstance::STATUS_RUNNING;
                $instance->completed_at = null;
                $instance->error_info = null;
                $this->updateInstance($instance, []);
                $context = $instance->context ?? [];
                $results[] = null;
                $processed++;
            }
        }

        // Clean up iteration variables from context
        unset($context[$itemVariable], $context[$indexVariable]);

        // Store aggregated results
        $context['forEach'][$nodeId] = [
            'processed' => $processed,
            'errors' => $errors,
            'results' => $results,
        ];
        $instance->context = $context;

        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
            $log->output_data = $context['forEach'][$nodeId];
            $log->completed_at = DateTime::now();
            $this->saveLog($log);
        }

        $this->removeFromActiveNodes($instance, $nodeId);

        // Clear visited state for complete port descendants (may overlap with iterate descendants)
        $completeTargets = $this->getNodeOutputTargets($definition, $nodeId, 'complete');
        foreach ($completeTargets as $targetId) {
            $descendants = [];
            $this->collectDescendants($definition, $targetId, $descendants);
            foreach ($descendants as $descId => $_) {
                unset($this->visitedNodes[$descId]);
            }
        }

        $this->advanceToOutputs($instance, $nodeId, 'complete', $definition);
    }

    /**
     * Collect all descendant node IDs reachable from a starting node.
     */
    protected function collectDescendants(array $definition, string $nodeId, array &$collected): void
    {
        if (isset($collected[$nodeId])) {
            return;
        }
        $collected[$nodeId] = true;

        $targets = $this->getAllOutputTargets($definition, $nodeId);
        foreach ($targets as $targetId) {
            $this->collectDescendants($definition, $targetId, $collected);
        }
    }

    /**
     * Execute a delay node — sets instance to WAITING for later resumption.
     */
    protected function executeDelayNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
    ): void {
        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_WAITING;
            $log->output_data = ['delayConfig' => $node['config'] ?? []];
            $this->saveLog($log);
        }

        $instance->status = WorkflowInstance::STATUS_WAITING;
        $this->updateInstance($instance, []);
    }

    /**
     * Execute an end node — completes this path, finishes instance if no active nodes remain.
     * If this instance is a child workflow, resumes the parent instance.
     */
    protected function executeEndNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
    ): void {
        $context = $instance->context ?? [];
        $resultConfig = $node['config']['result'] ?? null;
        if ($resultConfig !== null) {
            $context['workflowResult'] = $this->resolveParamValue($resultConfig, $context, $resultConfig);
            $instance->context = $context;
        }

        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
            $log->output_data = $context['workflowResult'] ?? null;
            $log->completed_at = DateTime::now();
            $this->saveLog($log);
        }

        $this->removeFromActiveNodes($instance, $nodeId);

        // If no more active nodes, complete the instance
        $activeNodes = $instance->active_nodes ?? [];
        if (empty($activeNodes)) {
            $instance->status = WorkflowInstance::STATUS_COMPLETED;
            $instance->completed_at = DateTime::now();
            $this->updateInstance($instance, []);

            // Subworkflow completion callback: resume parent if this is a child instance
            $context = $instance->context ?? [];
            $parentInstanceId = $context['_internal']['parentInstanceId'] ?? null;
            $parentNodeId = $context['_internal']['parentNodeId'] ?? null;

            if ($parentInstanceId !== null && $parentNodeId !== null) {
                Log::info(
                    "WorkflowEngine: Child instance #{$instance->id} completed, "
                    . "resuming parent instance #{$parentInstanceId} at node '{$parentNodeId}'."
                );

                $this->resumeWorkflow(
                    (int)$parentInstanceId,
                    $parentNodeId,
                    'default',
                    ['childResult' => $context['nodes'] ?? [], 'childInstanceId' => $instance->id],
                );
            }
        }
    }

    /**
     * Execute a subworkflow node — starts a child workflow, sets instance to WAITING.
     * Passes parent instance/node info so the child can resume the parent on completion.
     */
    protected function executeSubworkflowNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
    ): void {
        $config = $node['config'] ?? [];
        $childSlug = $config['workflowSlug'] ?? null;

        if (!$childSlug) {
            throw new \RuntimeException("Subworkflow node '{$nodeId}' has no workflowSlug configured.");
        }

        $childResult = $this->startWorkflow(
            $childSlug,
            $instance->context['trigger'] ?? [],
            $instance->started_by,
            $instance->entity_type,
            $instance->entity_id,
        );

        // Store parent info in the child instance's context so it can call back
        $childInstanceId = $childResult->data['instanceId'] ?? null;
        if ($childInstanceId !== null) {
            $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
            $childInstance = $instancesTable->get($childInstanceId);
            $childContext = $childInstance->context ?? [];
            $childContext['_internal']['parentInstanceId'] = $instance->id;
            $childContext['_internal']['parentNodeId'] = $nodeId;
            $childInstance->context = $childContext;
            $instancesTable->save($childInstance);
        }

        $context = $instance->context ?? [];
        $context['nodes'][$nodeId] = [
            'result' => $childResult->data,
            'childInstanceId' => $childInstanceId,
        ];
        $instance->context = $context;

        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_WAITING;
            $log->output_data = $childResult->data;
            $this->saveLog($log);
        }

        $instance->status = WorkflowInstance::STATUS_WAITING;
        $this->updateInstance($instance, []);
    }

    /**
     * Execute a state machine node — validates and applies a state transition
     * with configurable rules, status resolution, and audit logging.
     */
    protected function executeStateMachineNode(
        WorkflowInstance $instance,
        string $nodeId,
        array $node,
        ?WorkflowExecutionLog $log,
        array $definition,
    ): void {
        $context = $instance->context ?? [];
        $config = $node['config'] ?? [];

        $handler = $this->container->has(StateMachineHandler::class)
            ? $this->container->get(StateMachineHandler::class)
            : new StateMachineHandler();

        // Read current and target states from context
        $stateField = $config['stateField'] ?? 'state';
        $statusField = $config['statusField'] ?? 'status';

        $targetState = $this->resolveParamValue(
            $config['targetState'] ?? '$.trigger.targetState',
            $context,
        );
        $currentState = $this->resolveParamValue(
            $config['currentState'] ?? '$.trigger.currentState',
            $context,
        );

        if (!$targetState || !$currentState) {
            if ($log) {
                $log->status = WorkflowExecutionLog::STATUS_FAILED;
                $log->error_message = 'State machine node requires both currentState and targetState.';
                $log->completed_at = DateTime::now();
                $this->saveLog($log);
            }

            $this->removeFromActiveNodes($instance, $nodeId);
            $this->advanceToOutputs($instance, $nodeId, 'on_invalid', $definition);

            return;
        }

        // Build entity data from context
        $entityData = $context['trigger']['entityData'] ?? $context['trigger'] ?? [];

        // Execute the transition
        $result = $handler->executeTransition($entityData, (string)$currentState, (string)$targetState, $config);

        if (!$result['success']) {
            $invalidOutputData = [
                'success' => false,
                'error' => $result['error'],
                'missingFields' => $result['missingFields'] ?? [],
            ];
            if ($log) {
                $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
                $log->output_data = $invalidOutputData;
                $log->completed_at = DateTime::now();
                $this->saveLog($log);
            }

            $context['nodes'][$nodeId] = [
                'result' => $invalidOutputData,
                'port' => 'on_invalid',
            ];
            $instance->context = $context;

            $this->removeFromActiveNodes($instance, $nodeId);
            $this->advanceToOutputs($instance, $nodeId, 'on_invalid', $definition);

            return;
        }

        // Resolve statuses for audit log
        $statuses = $config['statuses'] ?? [];
        $fromStatus = $handler->resolveStatus((string)$currentState, $statuses) ?? '';
        $toStatus = $result['newStatus'] ?? '';

        // Create audit log entry if configured
        $auditConfig = $config['auditLog'] ?? [];
        if (!empty($auditConfig['table'])) {
            $entityId = (int)($context['trigger']['entity_id'] ?? $context['trigger']['id'] ?? 0);
            $userId = $instance->started_by;
            $handler->createAuditLog(
                $auditConfig,
                (string)$currentState,
                (string)$targetState,
                $fromStatus,
                $toStatus,
                $entityId,
                $userId,
            );
        }

        // Store result in context
        $outputData = [
            'success' => true,
            'fromState' => $currentState,
            'toState' => $targetState,
            'fromStatus' => $fromStatus,
            'toStatus' => $toStatus,
            'entityData' => $result['entityData'],
        ];

        $context['nodes'][$nodeId] = ['result' => $outputData];
        $context['trigger']['entityData'] = $result['entityData'];
        $instance->context = $context;

        if ($log) {
            $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
            $log->output_data = $outputData;
            $log->completed_at = DateTime::now();
            $this->saveLog($log);
        }

        $this->removeFromActiveNodes($instance, $nodeId);

        // Fire state-specific port, then generic on_transition
        $statePort = 'on_enter_' . strtolower(str_replace(' ', '_', (string)$targetState));
        $this->advanceToOutputs($instance, $nodeId, $statePort, $definition);
        $this->advanceToOutputs($instance, $nodeId, 'on_transition', $definition);
    }

    // -------------------------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------------------------

    /**
     * Resolve a context value using a dot-path (e.g., '$.trigger.officer.id').
     *
     * @param array $context The workflow context
     * @param string $path Dot-separated path, optionally prefixed with '$.'
     * @return mixed
     */
    protected function resolveContextValue(array $context, string $path): mixed
    {
        return CoreConditions::resolveFieldPath($context, $path);
    }

    /**
     * Resolve a parameter value that may be a plain scalar, a $.path string,
     * or a value descriptor object {type: '...', ...}.
     *
     * @param mixed $value The raw parameter value from workflow config
     * @param array $context The workflow instance context
     * @param mixed $default Fallback if resolution fails
     * @return mixed The resolved value
     */
    protected function resolveParamValue(mixed $value, array $context, mixed $default = null): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        // Plain scalar (not a string starting with $.)
        if (is_scalar($value) && !(is_string($value) && str_starts_with($value, '$.'))) {
            return $value;
        }

        // Shorthand: string starting with $. is a context path
        if (is_string($value) && str_starts_with($value, '$.')) {
            $resolved = $this->resolveContextValue($context, $value);

            return $resolved ?? $default;
        }

        // Value descriptor object
        if (is_array($value)) {
            $type = $value['type'] ?? null;

            // Plain key-value object (no 'type' descriptor) — resolve each value individually
            if ($type === null) {
                $resolved = [];
                foreach ($value as $k => $v) {
                    $resolved[$k] = $this->resolveParamValue($v, $context, $v);
                }

                return $resolved;
            }

            switch ($type) {
                case 'fixed':
                    return $value['value'] ?? $default;

                case 'context':
                    $path = $value['path'] ?? '';
                    if ($path) {
                        $resolved = $this->resolveContextValue($context, $path);

                        return $resolved ?? ($value['default'] ?? $default);
                    }

                    return $value['default'] ?? $default;

                case 'app_setting':
                    $key = $value['key'] ?? '';
                    if ($key) {
                        $settingsTable = TableRegistry::getTableLocator()->get('AppSettings');
                        $setting = $settingsTable->find()->where(['name' => $key])->first();
                        if ($setting) {
                            return $setting->value;
                        }
                    }

                    return $value['default'] ?? $default;

                default:
                    Log::warning("WorkflowEngine: Unknown value resolution type '{$type}'");

                    return $default;
            }
        }

        return $default;
    }

    /**
     * Resolve a required count value that may be an integer or a config object.
     *
     * Delegates to resolveParamValue() for universal resolution, then ensures
     * the result is an integer >= 1.
     */
    protected function resolveRequiredCount(mixed $value, array $context): int
    {
        $resolved = $this->resolveParamValue($value, $context, 1);

        return max(1, (int)$resolved);
    }

    /**
     * Parse a deadline duration string (e.g., "14d", "24h", "7d") into a future DateTime.
     */
    protected function parseDeadline(string $deadline): ?DateTime
    {
        if (preg_match('/^(\d+)([dhm])$/i', $deadline, $matches)) {
            $amount = (int)$matches[1];
            $unit = strtolower($matches[2]);
            $now = DateTime::now();

            switch ($unit) {
                case 'd':
                    return $now->modify("+{$amount} days");
                case 'h':
                    return $now->modify("+{$amount} hours");
                case 'm':
                    return $now->modify("+{$amount} minutes");
            }
        }

        // Try parsing as a standard datetime string
        try {
            return new DateTime($deadline);
        } catch (\Exception $e) {
            Log::warning("Could not parse deadline: {$deadline}");
            return null;
        }
    }

    /**
     * Set a value in the context at the given dot-path.
     *
     * @param array &$context The workflow context (by reference)
     * @param string $path Dot-separated path
     * @param mixed $value The value to set
     * @return void
     */
    protected function setContextValue(array &$context, string $path, mixed $value): void
    {
        if (str_starts_with($path, '$.')) {
            $path = substr($path, 2);
        }

        $segments = explode('.', $path);
        $current = &$context;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Get target node IDs for a given node's output port.
     *
     * @param array $definition The workflow definition
     * @param string $nodeId Source node ID
     * @param string $port Output port name
     * @return array<string> Target node IDs
     */
    protected function getNodeOutputTargets(array $definition, string $nodeId, string $port): array
    {
        $targets = [];

        // Check top-level edges array (if present)
        $edges = $definition['edges'] ?? [];
        foreach ($edges as $edge) {
            $edgeSource = $edge['source'] ?? $edge['from'] ?? null;
            $edgePort = $edge['sourcePort'] ?? $edge['port'] ?? 'default';
            $edgeTarget = $edge['target'] ?? $edge['to'] ?? null;

            if ($edgeSource === $nodeId && $this->portsMatch($edgePort, $port) && $edgeTarget !== null) {
                $targets[] = $edgeTarget;
            }
        }

        // Check per-node outputs (primary format used by designer/seed data)
        $node = $definition['nodes'][$nodeId] ?? null;
        if ($node && !empty($node['outputs'])) {
            foreach ($node['outputs'] as $output) {
                $outputPort = $output['port'] ?? 'default';
                $outputTarget = $output['target'] ?? null;

                if ($this->portsMatch($outputPort, $port) && $outputTarget !== null) {
                    $targets[] = $outputTarget;
                }
            }
        }

        return array_unique($targets);
    }

    /**
     * Check if two port names are equivalent.
     * Treats "next" and "default" as equivalent for regular action/trigger outputs.
     */
    protected function portsMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        // Normalize output-N ports (legacy designer naming) to 'default'
        if (preg_match('/^output-\d+$/', $a)) {
            $a = 'default';
        }
        if (preg_match('/^output-\d+$/', $b)) {
            $b = 'default';
        }
        $defaultAliases = ['default', 'next'];
        return in_array($a, $defaultAliases) && in_array($b, $defaultAliases);
    }

    /**
     * Get all output targets for a node regardless of port.
     *
     * @param array $definition The workflow definition
     * @param string $nodeId Source node ID
     * @return array<string> Target node IDs
     */
    protected function getAllOutputTargets(array $definition, string $nodeId): array
    {
        $targets = [];

        // Check top-level edges
        $edges = $definition['edges'] ?? [];
        foreach ($edges as $edge) {
            $edgeSource = $edge['source'] ?? $edge['from'] ?? null;
            $edgeTarget = $edge['target'] ?? $edge['to'] ?? null;

            if ($edgeSource === $nodeId && $edgeTarget !== null) {
                $targets[] = $edgeTarget;
            }
        }

        // Check per-node outputs
        $node = $definition['nodes'][$nodeId] ?? null;
        if ($node && !empty($node['outputs'])) {
            foreach ($node['outputs'] as $output) {
                $outputTarget = $output['target'] ?? null;
                if ($outputTarget !== null) {
                    $targets[] = $outputTarget;
                }
            }
        }

        return array_unique($targets);
    }

    /**
     * Save instance changes to the database.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance The instance to save
     * @param array $changes Additional field changes to apply
     * @return void
     */
    protected function updateInstance(WorkflowInstance $instance, array $changes): void
    {
        foreach ($changes as $field => $value) {
            $instance->{$field} = $value;
        }

        if ($this->ephemeral) {
            return;
        }

        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instancesTable->save($instance);
    }

    /**
     * Persist the workflow entity_id once it becomes available in context.
     *
     * Some durable workflows create the underlying entity inside an action node,
     * after the workflow instance has already been inserted. When the trigger
     * node declares an entityIdField, scan the accumulated context for that key
     * and promote it onto the workflow instance as soon as it appears.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Current workflow instance
     * @param array $definition Workflow definition graph
     * @return void
     */
    protected function hydrateInstanceEntityMetadata(WorkflowInstance $instance, array $definition): void
    {
        if ($this->ephemeral || $instance->entity_id !== null) {
            return;
        }

        $entityIdField = $this->getTriggerEntityIdField($definition);
        if ($entityIdField === null) {
            return;
        }

        $context = $instance->context ?? [];
        $resolvedEntityId = $this->findContextValueByKey($context, $entityIdField);
        if (!is_numeric($resolvedEntityId)) {
            return;
        }

        $resolvedEntityId = (int)$resolvedEntityId;
        $instance->entity_id = $resolvedEntityId;

        if (!isset($context['trigger']) || !is_array($context['trigger'])) {
            $context['trigger'] = [];
        }
        if (($context['trigger'][$entityIdField] ?? null) === null) {
            $context['trigger'][$entityIdField] = $resolvedEntityId;
            $instance->context = $context;
        }
    }

    /**
     * Resolve the trigger-configured entity ID field name for a workflow.
     *
     * @param array $definition Workflow definition graph
     * @return string|null
     */
    protected function getTriggerEntityIdField(array $definition): ?string
    {
        foreach ($this->findNodesByType($definition, 'trigger') as $triggerNode) {
            $entityIdField = $triggerNode['config']['entityIdField'] ?? null;
            if (is_string($entityIdField) && $entityIdField !== '') {
                return $entityIdField;
            }
        }

        return null;
    }

    /**
     * Recursively find the first non-empty value for a key in workflow context.
     *
     * @param mixed $value Context value to inspect
     * @param string $targetKey Key to locate
     * @return mixed
     */
    protected function findContextValueByKey(mixed $value, string $targetKey): mixed
    {
        if (!is_array($value)) {
            return null;
        }

        if (array_key_exists($targetKey, $value) && $value[$targetKey] !== null && $value[$targetKey] !== '') {
            return $value[$targetKey];
        }

        foreach ($value as $nestedValue) {
            $resolvedValue = $this->findContextValueByKey($nestedValue, $targetKey);
            if ($resolvedValue !== null && $resolvedValue !== '') {
                return $resolvedValue;
            }
        }

        return null;
    }

    /**
     * Create an execution log entry. Returns null for ephemeral workflows.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Current instance
     * @param string $nodeId Node identifier
     * @param string $nodeType Node type
     * @param int $attempt Attempt number
     * @param array|null $inputData Input data for the node
     * @param array|null $outputData Output data (for completed-on-create logs like triggers)
     * @return \App\Model\Entity\WorkflowExecutionLog|null
     */
    protected function createExecutionLog(
        WorkflowInstance $instance,
        string $nodeId,
        string $nodeType,
        int $attempt,
        ?array $inputData = null,
        ?array $outputData = null,
    ): ?WorkflowExecutionLog {
        if ($this->ephemeral) {
            return null;
        }

        $logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $data = [
            'workflow_instance_id' => $instance->id,
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'attempt_number' => $attempt,
            'status' => $outputData !== null
                ? WorkflowExecutionLog::STATUS_COMPLETED
                : WorkflowExecutionLog::STATUS_RUNNING,
            'input_data' => $inputData,
            'started_at' => DateTime::now(),
        ];
        if ($outputData !== null) {
            $data['output_data'] = $outputData;
            $data['completed_at'] = DateTime::now();
        }
        $log = $logsTable->newEntity($data);
        $logsTable->save($log);

        return $log;
    }

    /**
     * Save an execution log update. No-op if log is null (ephemeral mode).
     */
    protected function saveLog(?WorkflowExecutionLog $log): void
    {
        if ($log === null) {
            return;
        }
        $logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $logsTable->save($log);
    }

    /**
     * Find all nodes of a given type in the definition.
     *
     * @param array $definition The workflow definition
     * @param string $type Node type to find
     * @return array Matching nodes keyed by node ID
     */
    protected function findNodesByType(array $definition, string $type): array
    {
        $matches = [];

        foreach ($definition['nodes'] as $nodeId => $node) {
            if (($node['type'] ?? '') === $type) {
                $matches[$nodeId] = $node;
            }
        }

        return $matches;
    }

    /**
     * Remove a node from the instance's active_nodes list.
     */
    protected function removeFromActiveNodes(WorkflowInstance $instance, string $nodeId): void
    {
        $activeNodes = $instance->active_nodes ?? [];
        $activeNodes = array_values(array_filter($activeNodes, fn($n) => $n !== $nodeId));
        $instance->active_nodes = $activeNodes;
    }

    /**
     * Advance execution to all targets of a node's output port.
     */
    protected function advanceToOutputs(
        WorkflowInstance $instance,
        string $nodeId,
        string $port,
        array $definition,
    ): void {
        $targets = $this->getNodeOutputTargets($definition, $nodeId, $port);
        foreach ($targets as $targetNodeId) {
            $this->executeNode($instance, $targetNodeId, $definition);
        }
    }

    /**
     * Resolve input data for a node from context using configured mappings.
     *
     * @param array $context The workflow context
     * @param array $nodeConfig The node's configuration
     * @return array Resolved input data
     */
    protected function resolveInputData(array $context, array $nodeConfig): array
    {
        $inputMappings = $nodeConfig['inputMappings'] ?? [];
        $resolved = [];

        foreach ($inputMappings as $key => $path) {
            if (is_string($path)) {
                $resolved[$key] = $this->resolveContextValue($context, $path);
            } else {
                $resolved[$key] = $path;
            }
        }

        return $resolved;
    }

    /**
     * Get all source node IDs that have edges leading into the given node.
     *
     * @param array $definition The workflow definition
     * @param string $nodeId The target node ID
     * @return array<string> Source node IDs
     */
    protected function getNodeInputSources(array $definition, string $nodeId): array
    {
        $sources = [];
        $edges = $definition['edges'] ?? [];

        foreach ($edges as $edge) {
            $edgeTarget = $edge['target'] ?? $edge['to'] ?? null;
            $edgeSource = $edge['source'] ?? $edge['from'] ?? null;

            if ($edgeTarget === $nodeId && $edgeSource !== null) {
                $sources[] = $edgeSource;
            }
        }

        return array_unique($sources);
    }

    /**
     * Find which source node most recently completed execution leading into a join.
     *
     * @param array $definition The workflow definition
     * @param string $joinNodeId The join node ID
     * @param \App\Model\Entity\WorkflowInstance $instance The workflow instance
     * @return string The most recently completed source node ID
     */
    protected function findIncomingSource(
        array $definition,
        string $joinNodeId,
        WorkflowInstance $instance,
        array $alreadyCompletedInputs = [],
    ): string {
        $logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $inputSources = $this->getNodeInputSources($definition, $joinNodeId);

        // Find the most recently completed source node
        $completedLogs = $logsTable->find()
            ->where([
                'workflow_instance_id' => $instance->id,
                'node_id IN' => $inputSources,
                'status' => WorkflowExecutionLog::STATUS_COMPLETED,
            ])
            ->order(['completed_at' => 'DESC', 'id' => 'DESC'])
            ->all();

        $latestSource = null;
        foreach ($completedLogs as $completedLog) {
            $latestSource ??= $completedLog->node_id;
            if (!in_array($completedLog->node_id, $alreadyCompletedInputs, true)) {
                return $completedLog->node_id;
            }
        }

        return $latestSource ?? ($inputSources[0] ?? 'unknown');
    }

    /**
     * @inheritDoc
     */
    public function fireIntermediateApprovalActions(
        int $instanceId,
        string $nodeId,
        array $approvalData,
        string $outputPort = 'on_each_approval',
    ): ServiceResult {
        $this->visitedNodes = [];
        $this->executionDepth = 0;

        return $this->executeInTransaction(function () use ($instanceId, $nodeId, $approvalData, $outputPort) {
                $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
                $instance = $instancesTable->get($instanceId, contain: ['WorkflowVersions']);

                if ($instance->status !== WorkflowInstance::STATUS_WAITING) {
                    return new ServiceResult(false, "Instance {$instanceId} is not in waiting state.");
                }

                $definition = $instance->workflow_version->definition;

                // Load current approval to get progress counts
                $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
                $approval = $approvalsTable->find()
                    ->where([
                        'workflow_instance_id' => $instanceId,
                        'node_id' => $nodeId,
                        'status' => WorkflowApproval::STATUS_PENDING,
                    ])
                    ->first();

                // Inject approval progress into context for intermediate action nodes.
                // Use values from approvalData when present; fall back to DB approval record.
                $context = $instance->context ?? [];
                if (!isset($context['nodes'])) {
                    $context['nodes'] = [];
                }
                $context['nodes'][$nodeId] = [
                    'approvedCount' => $approvalData['approvedCount'] ?? ($approval ? $approval->approved_count : 0),
                    'requiredCount' => $approvalData['requiredCount'] ?? ($approval ? $approval->required_count : 1),
                    'approverId' => $approvalData['approverId'] ?? null,
                    'nextApproverId' => $approvalData['nextApproverId'] ?? null,
                    'approvalChain' => $approvalData['approvalChain'] ?? ($approval ? ($approval->approver_config['approval_chain'] ?? []) : []),
                    'decision' => $approvalData['decision'] ?? 'approve',
                    'comment' => $approvalData['comment'] ?? null,
                ];
                $instance->context = $context;

                // Get targets from the specified output port
                $targets = $this->getNodeOutputTargets($definition, $nodeId, $outputPort);

                if (empty($targets)) {
                    // No intermediate actions configured — just save context and return
                    $this->updateInstance($instance, []);

                    return new ServiceResult(true, null, ['instanceId' => $instanceId, 'intermediateActionsRun' => 0]);
                }

                // Execute each target action node directly (synchronous, non-finalizing)
                $logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
                $actionsRun = 0;

                foreach ($targets as $targetNodeId) {
                    $targetNode = $definition['nodes'][$targetNodeId] ?? null;
                    if (!$targetNode) {
                        Log::warning("WorkflowEngine: Intermediate action target '{$targetNodeId}' not found in definition.");
                        continue;
                    }

                    $targetType = $targetNode['type'] ?? 'unknown';
                    if ($targetType !== 'action') {
                        Log::warning("WorkflowEngine: Intermediate action target '{$targetNodeId}' is type '{$targetType}', expected 'action'.");
                        continue;
                    }

                    $actionName = $targetNode['config']['action'] ?? null;
                    if (!$actionName) {
                        Log::warning("WorkflowEngine: Intermediate action node '{$targetNodeId}' has no action configured.");
                        continue;
                    }

                    $actionConfig = WorkflowActionRegistry::getAction($actionName);
                    if (!$actionConfig) {
                        Log::warning("WorkflowEngine: Action '{$actionName}' not found in registry for intermediate execution.");
                        continue;
                    }

                    // Create execution log for this intermediate action
                    $log = $logsTable->newEntity([
                        'workflow_instance_id' => $instance->id,
                        'node_id' => $targetNodeId,
                        'node_type' => 'action',
                        'attempt_number' => 1,
                        'status' => WorkflowExecutionLog::STATUS_RUNNING,
                        'input_data' => $this->resolveInputData($instance->context ?? [], $targetNode['config'] ?? []),
                        'started_at' => DateTime::now(),
                    ]);
                    $logsTable->save($log);

                    try {
                        $serviceClass = $actionConfig['serviceClass'];
                        $serviceMethod = $actionConfig['serviceMethod'];

                        if (!$this->container->has($serviceClass)) {
                            throw new \RuntimeException(
                                "Workflow action service '{$serviceClass}' is not registered in the DI container. "
                                . "Register it in Application::services() or your plugin's services() method."
                            );
                        }
                        $service = $this->container->get($serviceClass);

                        // Resolve params from context
                        $nodeConfig = $targetNode['config'] ?? [];
                        if (!empty($nodeConfig['params']) && is_array($nodeConfig['params'])) {
                            $resolvedParams = [];
                            foreach ($nodeConfig['params'] as $key => $paramValue) {
                                $resolvedParams[$key] = $this->resolveParamValue($paramValue, $instance->context ?? []);
                            }
                            $nodeConfig = array_merge($nodeConfig, $resolvedParams);
                        }

                        $context = $instance->context ?? [];
                        $result = $this->executeActionService($service, $serviceMethod, $context, $nodeConfig);
                        $result = $this->applyActionContextUpdates($context, $result);
                        $context['nodes'][$targetNodeId] = ['result' => $result];
                        $instance->context = $context;

                        $log->status = WorkflowExecutionLog::STATUS_COMPLETED;
                        $log->output_data = $result;
                        $log->completed_at = DateTime::now();
                        $logsTable->save($log);
                        $actionsRun++;
                    } catch (Throwable $e) {
                        Log::error("WorkflowEngine: Intermediate action '{$actionName}' on node '{$targetNodeId}' failed: {$e->getMessage()}");
                        $log->status = WorkflowExecutionLog::STATUS_FAILED;
                        $log->error_message = $e->getMessage();
                        $log->completed_at = DateTime::now();
                        $logsTable->save($log);
                    }
                }

                // Save updated context; instance remains WAITING
                $this->updateInstance($instance, []);

                return new ServiceResult(true, null, [
                    'instanceId' => $instanceId,
                    'intermediateActionsRun' => $actionsRun,
                ]);
        }, 'fireIntermediateApprovalActions', $instanceId);
    }

    // -------------------------------------------------------------------------
    // Transaction management
    // -------------------------------------------------------------------------

    /**
     * Execute a workflow action service while suppressing model-trigger loops.
     *
     * Workflow actions often save the same entities that can emit workflow
     * triggers. Suppression prevents recursive duplicate workflows while the
     * workflow engine is already applying an intentional state transition.
     *
     * @param object $service Workflow action service instance.
     * @param string $serviceMethod Method to invoke.
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $nodeConfig Resolved node config.
     * @return mixed
     */
    private function executeActionService(
        object $service,
        string $serviceMethod,
        array $context,
        array $nodeConfig,
    ): mixed {
        $wasSuppressing = WorkflowTriggerBehavior::$suppressTriggers;
        WorkflowTriggerBehavior::$suppressTriggers = true;

        try {
            return $service->{$serviceMethod}($context, $nodeConfig);
        } finally {
            WorkflowTriggerBehavior::$suppressTriggers = $wasSuppressing;
        }
    }

    /**
     * Apply action-requested context updates and remove private payload data from output.
     *
     * @param array $context Workflow context, updated by reference
     * @param mixed $result Raw action result
     * @return mixed Public action result
     */
    private function applyActionContextUpdates(array &$context, mixed $result): mixed
    {
        if (!is_array($result) || !isset($result['_contextUpdates']) || !is_array($result['_contextUpdates'])) {
            return $result;
        }

        $context = array_replace_recursive($context, $result['_contextUpdates']);
        unset($result['_contextUpdates']);

        return $result;
    }

    /**
     * Execute a callable inside a single database transaction, avoiding nesting.
     *
     * When already inside a transaction (e.g., subworkflow calling startWorkflow,
     * or child completion calling resumeWorkflow), the callable runs directly
     * without a new transactional() wrapper — all work shares the outer transaction.
     *
     * CakePHP supports nested transactional() calls via savepoints, so services
     * invoked by action nodes (e.g., WarrantManager) that open their own
     * transactional() blocks are safe and participate in the outer transaction.
     *
     * On failure at the top level the transaction is rolled back automatically.
     * If $existingInstanceId is provided, the instance is marked FAILED in a
     * separate post-rollback save so the database reflects the terminal state.
     *
     * @param callable $work The work to execute inside the transaction
     * @param string $methodName Method name for error logging
     * @param int|null $existingInstanceId Instance ID to mark FAILED on rollback
     * @return \App\Services\ServiceResult
     */
    private function executeInTransaction(
        callable $work,
        string $methodName,
        ?int $existingInstanceId = null,
    ): ServiceResult {
        // Ephemeral workflows skip DB transactions — domain actions handle their own
        if ($this->ephemeral) {
            try {
                return $work();
            } catch (Throwable $e) {
                Log::error("WorkflowEngine::{$methodName} (ephemeral) failed: {$e->getMessage()}");

                return new ServiceResult(false, "Failed to {$methodName}: {$e->getMessage()}");
            }
        }

        $alreadyInTransaction = $this->isInTransaction;
        $this->isInTransaction = true;

        try {
            if ($alreadyInTransaction) {
                // Already inside a transaction — execute directly.
                return $work();
            }

            $connection = ConnectionManager::get('default');
            $result = $connection->transactional($work);
            $this->isInTransaction = false;

            return $result;
        } catch (Throwable $e) {
            if (!$alreadyInTransaction) {
                $this->isInTransaction = false;
            }

            Log::error("WorkflowEngine::{$methodName} failed: {$e->getMessage()}");

            if ($alreadyInTransaction) {
                // Re-throw so the outer transaction can handle the rollback.
                throw $e;
            }

            // Transaction was rolled back. Mark instance FAILED in a separate save
            // so the database reflects the terminal state.
            if ($existingInstanceId !== null) {
                $this->markInstanceFailed($existingInstanceId, $e->getMessage());
            }

            return new ServiceResult(false, "Failed to {$methodName}: {$e->getMessage()}");
        }
    }

    /**
     * Mark a workflow instance as FAILED after a transaction rollback.
     *
     * Runs outside any transaction so the FAILED status persists even though
     * the main transaction was rolled back.
     *
     * @param int $instanceId The instance to mark
     * @param string $errorMessage The error that caused the failure
     * @return void
     */
    private function markInstanceFailed(int $instanceId, string $errorMessage): void
    {
        try {
            $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
            $instance = $instancesTable->get($instanceId);

            if (!$instance->isTerminal()) {
                $instance->status = WorkflowInstance::STATUS_FAILED;
                $instance->completed_at = DateTime::now();
                $instance->error_info = [
                    'error' => $errorMessage,
                    'rolled_back' => true,
                ];
                $instancesTable->save($instance);
            }
        } catch (Throwable $inner) {
            Log::error(
                "WorkflowEngine: Failed to mark instance #{$instanceId} as FAILED "
                . "after rollback: {$inner->getMessage()}"
            );
        }
    }
}
