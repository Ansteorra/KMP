<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Model\Entity\WorkflowInstanceMigration;
use App\Model\Entity\WorkflowVersion;
use App\Services\ServiceResult;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Manages workflow version lifecycle: drafting, publishing, archiving,
 * instance migration, and version comparison.
 */
class DefaultWorkflowVersionManager implements WorkflowVersionManagerInterface
{
    /**
     * Create a new draft version for a workflow definition.
     *
     * @param int $definitionId Workflow definition to version
     * @param array $definition Workflow graph
     * @param array|null $canvasLayout Visual layout data
     * @param string|null $changeNotes Description of changes
     * @return \App\Services\ServiceResult
     */
    public function createDraft(int $definitionId, array $definition, ?array $canvasLayout = null, ?string $changeNotes = null): ServiceResult
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');

        $defEntity = $definitionsTable->find()->where(['id' => $definitionId])->first();
        if (!$defEntity) {
            return new ServiceResult(false, 'Workflow definition not found.');
        }

        $maxVersion = $versionsTable->find()
            ->where(['workflow_definition_id' => $definitionId])
            ->select(['max_version' => $versionsTable->find()->func()->max('version_number')])
            ->first();
        $nextVersion = ($maxVersion && $maxVersion->max_version) ? (int)$maxVersion->max_version + 1 : 1;

        $version = $versionsTable->newEntity([
            'workflow_definition_id' => $definitionId,
            'version_number' => $nextVersion,
            'definition' => $definition,
            'canvas_layout' => $canvasLayout,
            'status' => WorkflowVersion::STATUS_DRAFT,
            'change_notes' => $changeNotes,
        ]);

        if (!$versionsTable->save($version)) {
            return new ServiceResult(false, 'Failed to save draft version.');
        }

        return new ServiceResult(true, null, [
            'versionId' => $version->id,
            'versionNumber' => $version->version_number,
        ]);
    }

    /**
     * Update an existing draft version.
     *
     * @param int $versionId Draft version to update
     * @param array $definition Updated workflow graph
     * @param array|null $canvasLayout Visual layout data
     * @param string|null $changeNotes Description of changes
     * @return \App\Services\ServiceResult
     */
    public function updateDraft(int $versionId, array $definition, ?array $canvasLayout = null, ?string $changeNotes = null): ServiceResult
    {
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');

        $version = $versionsTable->get($versionId);
        if (!$version->isDraft()) {
            return new ServiceResult(false, 'Only draft versions can be updated.');
        }

        $versionsTable->patchEntity($version, [
            'definition' => $definition,
            'canvas_layout' => $canvasLayout,
            'change_notes' => $changeNotes,
        ]);

        if (!$versionsTable->save($version)) {
            return new ServiceResult(false, 'Failed to update draft version.');
        }

        return new ServiceResult(true, null, [
            'versionId' => $version->id,
            'versionNumber' => $version->version_number,
        ]);
    }

    /**
     * Publish a draft version within a transaction.
     *
     * Archives any existing published version, marks this version published,
     * and updates the definition's current_version_id.
     *
     * @param int $versionId Draft version to publish
     * @param int $publishedBy User ID publishing the version
     * @return \App\Services\ServiceResult
     */
    public function publish(int $versionId, int $publishedBy): ServiceResult
    {
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');

        $version = $versionsTable->get($versionId);
        if (!$version->isDraft()) {
            return new ServiceResult(false, 'Only draft versions can be published.');
        }

        $errors = $this->validateDefinition($version->definition);
        if (!empty($errors)) {
            return new ServiceResult(false, 'Definition validation failed: ' . implode('; ', $errors));
        }

        $result = ConnectionManager::get('default')->transactional(function () use ($versionsTable, $definitionsTable, $version, $versionId, $publishedBy) {
            // Archive any existing published version for this definition
            $currentPublished = $versionsTable->find()
                ->where([
                    'workflow_definition_id' => $version->workflow_definition_id,
                    'status' => WorkflowVersion::STATUS_PUBLISHED,
                ])
                ->first();
            if ($currentPublished) {
                $currentPublished->status = WorkflowVersion::STATUS_ARCHIVED;
                if (!$versionsTable->save($currentPublished)) {
                    return new ServiceResult(false, 'Failed to archive existing published version.');
                }
            }

            // Publish this version
            $version->status = WorkflowVersion::STATUS_PUBLISHED;
            $version->published_at = DateTime::now();
            $version->published_by = $publishedBy;
            if (!$versionsTable->save($version)) {
                return new ServiceResult(false, 'Failed to publish version.');
            }

            // Update definition to point to this version
            $defEntity = $definitionsTable->get($version->workflow_definition_id);
            $definitionsTable->patchEntity($defEntity, [
                'current_version_id' => $versionId,
                'is_active' => true,
            ]);
            if (!$definitionsTable->save($defEntity)) {
                return new ServiceResult(false, 'Failed to update workflow definition.');
            }

            return new ServiceResult(true, null, [
                'versionId' => $version->id,
                'versionNumber' => $version->version_number,
            ]);
        });

        return $result;
    }

    /**
     * Validate a workflow definition structure.
     *
     * @param array $definition Workflow graph to validate
     * @return string[] Array of error messages; empty if valid
     */
    protected function validateDefinition(array $definition): array
    {
        $errors = [];

        if (empty($definition['nodes']) || !is_array($definition['nodes'])) {
            $errors[] = 'Definition must contain a non-empty "nodes" array.';

            return $errors;
        }

        $nodes = $definition['nodes'];
        $nodeKeys = array_keys($nodes);

        // Exactly one trigger node
        $triggerNodes = array_filter($nodes, fn($node) => ($node['type'] ?? '') === 'trigger');
        if (count($triggerNodes) !== 1) {
            $errors[] = 'Definition must contain exactly one trigger node.';
        }

        // At least one end node
        $endNodes = array_filter($nodes, fn($node) => ($node['type'] ?? '') === 'end');
        if (count($endNodes) < 1) {
            $errors[] = 'Definition must contain at least one end node.';
        }

        // All output targets reference existing node keys
        foreach ($nodes as $key => $node) {
            $outputs = $node['outputs'] ?? [];
            foreach ($outputs as $output) {
                $target = $output['target'] ?? $output;
                if (is_string($target) && !in_array($target, $nodeKeys, true)) {
                    $errors[] = "Node '{$key}' references non-existent target '{$target}'.";
                }
            }
        }

        // No orphan nodes - every non-trigger node must be reachable from trigger
        $triggerKey = !empty($triggerNodes) ? array_key_first($triggerNodes) : null;
        if ($triggerKey !== null) {
            $reachable = $this->findReachableNodes($triggerKey, $nodes);
            foreach ($nodeKeys as $key) {
                if ($key !== $triggerKey && !in_array($key, $reachable, true)) {
                    $errors[] = "Node '{$key}' is not reachable from the trigger node.";
                }
            }
        }

        // Loop nodes must have maxIterations set
        $loopNodes = array_filter($nodes, fn($node) => ($node['type'] ?? '') === 'loop');
        foreach ($loopNodes as $key => $node) {
            if (empty($node['config']['maxIterations'])) {
                $errors[] = "Loop node '{$key}' must have maxIterations set.";
            }
        }

        // ForEach nodes must have a collection path configured
        $forEachNodes = array_filter($nodes, fn($node) => ($node['type'] ?? '') === 'forEach');
        foreach ($forEachNodes as $key => $node) {
            if (empty($node['config']['collection'])) {
                $errors[] = "ForEach node '{$key}' must have a collection path configured.";
            }
        }

        // Cycle detection: find back-edges via DFS (loops are allowed via 'continue' port)
        if ($triggerKey !== null) {
            $cycles = $this->detectCycles($triggerKey, $nodes);
            foreach ($cycles as $cycle) {
                $errors[] = "Cycle detected in graph: " . implode(' -> ', $cycle) . ".";
            }
        }

        // Validate action and condition node required params (only when config is present)
        foreach ($nodes as $key => $node) {
            $type = $node['type'] ?? '';

            if ($type === 'action' && isset($node['config']['action'])) {
                $actionName = $node['config']['action'];

                $actionConfig = WorkflowActionRegistry::getAction($actionName);
                if (!$actionConfig) {
                    $errors[] = "Action node '{$key}' references unknown action '{$actionName}'.";
                    continue;
                }

                $inputSchema = $actionConfig['inputSchema'] ?? [];
                $params = $node['config']['params'] ?? [];

                foreach ($inputSchema as $paramKey => $paramMeta) {
                    if ($this->isSchemaFieldHidden($paramMeta)) {
                        continue;
                    }

                    if (!empty($paramMeta['required']) && empty($params[$paramKey]) && !isset($node['config'][$paramKey])) {
                        $errors[] = "Action node '{$key}' ({$actionName}): required parameter '{$paramKey}' is not configured.";
                    }
                }
            }

            if ($type === 'condition' && isset($node['config']['condition'])) {
                $conditionName = $node['config']['condition'];
                if (!str_starts_with($conditionName, 'Core.')) {
                    $condConfig = WorkflowConditionRegistry::getCondition($conditionName);
                    if (!$condConfig) {
                        $errors[] = "Condition node '{$key}' references unknown condition '{$conditionName}'.";
                        continue;
                    }

                    $inputSchema = $condConfig['inputSchema'] ?? [];
                    $params = $node['config']['params'] ?? [];

                    foreach ($inputSchema as $paramKey => $paramMeta) {
                        if ($this->isSchemaFieldHidden($paramMeta)) {
                            continue;
                        }

                        if (!empty($paramMeta['required']) && empty($params[$paramKey]) && !isset($node['config'][$paramKey])) {
                            $errors[] = "Condition node '{$key}' ({$conditionName}): required parameter '{$paramKey}' is not configured.";
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Check whether a schema field is internal-only and should be skipped by user-facing validation.
     *
     * @param array $paramMeta Schema metadata
     * @return bool
     */
    private function isSchemaFieldHidden(array $paramMeta): bool
    {
        return ($paramMeta['hidden'] ?? false) === true || ($paramMeta['visible'] ?? true) === false;
    }

    /**
     * Find all nodes reachable from a starting node via BFS.
     *
     * @param string $startKey Starting node key
     * @param array $nodes All nodes in the definition
     * @return string[] Keys of reachable nodes
     */
    private function findReachableNodes(string $startKey, array $nodes): array
    {
        $visited = [];
        $queue = [$startKey];

        while (!empty($queue)) {
            $current = array_shift($queue);
            if (in_array($current, $visited, true)) {
                continue;
            }
            $visited[] = $current;

            $outputs = $nodes[$current]['outputs'] ?? [];
            foreach ($outputs as $output) {
                $target = $output['target'] ?? $output;
                if (is_string($target) && !in_array($target, $visited, true)) {
                    $queue[] = $target;
                }
            }
        }

        return $visited;
    }

    /**
     * Detect cycles in the workflow graph using DFS.
     * Loop nodes with 'continue' port back-edges are excluded
     * since they are bounded by maxIterations.
     *
     * @param string $startKey Starting node key
     * @param array $nodes All nodes in the definition
     * @return array<array<string>> Each element is an array of node keys forming a cycle
     */
    private function detectCycles(string $startKey, array $nodes): array
    {
        $cycles = [];
        $visited = [];
        $stack = [];

        $this->dfsDetectCycles($startKey, $nodes, $visited, $stack, $cycles);

        return $cycles;
    }

    /**
     * Recursive DFS helper for cycle detection.
     */
    private function dfsDetectCycles(string $nodeKey, array $nodes, array &$visited, array &$stack, array &$cycles): void
    {
        $visited[$nodeKey] = true;
        $stack[$nodeKey] = true;

        $outputs = $nodes[$nodeKey]['outputs'] ?? [];
        $nodeType = $nodes[$nodeKey]['type'] ?? '';

        foreach ($outputs as $output) {
            $target = $output['target'] ?? $output;
            $port = $output['port'] ?? 'default';

            if (!is_string($target) || !isset($nodes[$target])) {
                continue;
            }

            // Loop 'continue' port deliberately cycles back — skip it
            if ($nodeType === 'loop' && $port === 'continue') {
                continue;
            }

            if (isset($stack[$target])) {
                // Found a cycle — extract the path
                $cyclePath = [];
                $inCycle = false;
                foreach (array_keys($stack) as $key) {
                    if ($key === $target) {
                        $inCycle = true;
                    }
                    if ($inCycle) {
                        $cyclePath[] = $key;
                    }
                }
                $cyclePath[] = $target;
                $cycles[] = $cyclePath;
            } elseif (!isset($visited[$target])) {
                $this->dfsDetectCycles($target, $nodes, $visited, $stack, $cycles);
            }
        }

        unset($stack[$nodeKey]);
    }

    /**
     * Archive a version. If it is the current published version,
     * also clears the definition's current_version_id.
     *
     * @param int $versionId Version to archive
     * @return \App\Services\ServiceResult
     */
    public function archive(int $versionId): ServiceResult
    {
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');

        $version = $versionsTable->get($versionId);

        if ($version->isPublished()) {
            $defEntity = $definitionsTable->get($version->workflow_definition_id);
            if ((int)$defEntity->current_version_id === $versionId) {
                $definitionsTable->patchEntity($defEntity, [
                    'current_version_id' => null,
                    'is_active' => false,
                ]);
                if (!$definitionsTable->save($defEntity)) {
                    return new ServiceResult(false, 'Failed to update workflow definition.');
                }
            }
        }

        $version->status = WorkflowVersion::STATUS_ARCHIVED;
        if (!$versionsTable->save($version)) {
            return new ServiceResult(false, 'Failed to archive version.');
        }

        return new ServiceResult(true);
    }

    /**
     * Get the currently published version for a definition.
     *
     * @param int $definitionId Workflow definition ID
     * @return \App\Model\Entity\WorkflowVersion|null
     */
    public function getCurrentVersion(int $definitionId): ?WorkflowVersion
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');

        $defEntity = $definitionsTable->find()
            ->where(['WorkflowDefinitions.id' => $definitionId])
            ->contain(['CurrentVersion'])
            ->first();

        if (!$defEntity) {
            return null;
        }

        return $defEntity->current_version;
    }

    /**
     * Get all versions for a definition, ordered newest first.
     *
     * @param int $definitionId Workflow definition ID
     * @return \App\Model\Entity\WorkflowVersion[]
     */
    public function getVersionHistory(int $definitionId): array
    {
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');

        return $versionsTable->find()
            ->where(['workflow_definition_id' => $definitionId])
            ->orderBy(['version_number' => 'DESC'])
            ->all()
            ->toArray();
    }

    /**
     * Migrate a running instance to a target version.
     *
     * Auto-generates node mapping if not provided by matching node keys.
     * Creates an audit record of the migration.
     *
     * @param int $instanceId Instance to migrate
     * @param int $targetVersionId Target version (must be published)
     * @param int $migratedBy User performing the migration
     * @param array|null $nodeMapping Explicit old-key => new-key mapping
     * @return \App\Services\ServiceResult
     */
    public function migrateInstance(int $instanceId, int $targetVersionId, int $migratedBy, ?array $nodeMapping = null): ServiceResult
    {
        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $migrationsTable = TableRegistry::getTableLocator()->get('WorkflowInstanceMigrations');

        $instance = $instancesTable->get($instanceId);
        if ($instance->isTerminal()) {
            return new ServiceResult(false, 'Cannot migrate a terminal instance.');
        }

        $targetVersion = $versionsTable->get($targetVersionId);
        if (!$targetVersion->isPublished()) {
            return new ServiceResult(false, 'Target version must be published.');
        }

        $oldVersionId = $instance->workflow_version_id;
        $oldVersion = $versionsTable->get($oldVersionId);

        // Auto-generate node mapping if not provided
        if ($nodeMapping === null) {
            $oldNodes = array_keys($oldVersion->definition['nodes'] ?? []);
            $newNodes = array_keys($targetVersion->definition['nodes'] ?? []);
            $nodeMapping = [];
            foreach ($oldNodes as $key) {
                if (in_array($key, $newNodes, true)) {
                    $nodeMapping[$key] = $key;
                }
            }
        }

        // Verify all active nodes can be mapped
        $activeNodes = $instance->active_nodes ?? [];
        foreach ($activeNodes as $activeNode) {
            $nodeKey = is_array($activeNode) ? ($activeNode['node_key'] ?? $activeNode['key'] ?? null) : $activeNode;
            if ($nodeKey !== null && !isset($nodeMapping[$nodeKey])) {
                return new ServiceResult(false, "Active node '{$nodeKey}' cannot be mapped to the target version.");
            }
        }

        $result = ConnectionManager::get('default')->transactional(function () use ($instancesTable, $migrationsTable, $instance, $targetVersionId, $oldVersionId, $nodeMapping, $migratedBy, $activeNodes) {
            // Remap active nodes
            $remappedNodes = [];
            foreach ($activeNodes as $activeNode) {
                if (is_array($activeNode)) {
                    $key = $activeNode['node_key'] ?? $activeNode['key'] ?? null;
                    if ($key !== null && isset($nodeMapping[$key])) {
                        $activeNode['node_key'] = $nodeMapping[$key];
                        if (isset($activeNode['key'])) {
                            $activeNode['key'] = $nodeMapping[$key];
                        }
                    }
                    $remappedNodes[] = $activeNode;
                } else {
                    $remappedNodes[] = $nodeMapping[$activeNode] ?? $activeNode;
                }
            }

            // Update instance
            $instancesTable->patchEntity($instance, [
                'workflow_version_id' => $targetVersionId,
                'active_nodes' => $remappedNodes,
            ]);
            if (!$instancesTable->save($instance)) {
                return new ServiceResult(false, 'Failed to update workflow instance.');
            }

            // Create migration audit record
            $migration = $migrationsTable->newEntity([
                'workflow_instance_id' => $instance->id,
                'from_version_id' => $oldVersionId,
                'to_version_id' => $targetVersionId,
                'migration_type' => WorkflowInstanceMigration::MIGRATION_TYPE_MANUAL,
                'node_mapping' => $nodeMapping,
                'migrated_by' => $migratedBy,
            ]);
            if (!$migrationsTable->save($migration)) {
                return new ServiceResult(false, 'Failed to create migration record.');
            }

            return new ServiceResult(true, null, [
                'instanceId' => $instance->id,
                'migrationId' => $migration->id,
            ]);
        });

        return $result;
    }

    /**
     * Compare two versions and return structural differences.
     *
     * @param int $versionId1 First version ID
     * @param int $versionId2 Second version ID
     * @return array{added: array, removed: array, modified: array}
     */
    public function compareVersions(int $versionId1, int $versionId2): array
    {
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');

        $version1 = $versionsTable->get($versionId1);
        $version2 = $versionsTable->get($versionId2);

        $nodes1 = $version1->definition['nodes'] ?? [];
        $nodes2 = $version2->definition['nodes'] ?? [];

        $keys1 = array_keys($nodes1);
        $keys2 = array_keys($nodes2);

        $added = [];
        $removed = [];
        $modified = [];

        // Nodes in v2 but not v1
        foreach (array_diff($keys2, $keys1) as $key) {
            $added[$key] = $nodes2[$key];
        }

        // Nodes in v1 but not v2
        foreach (array_diff($keys1, $keys2) as $key) {
            $removed[$key] = $nodes1[$key];
        }

        // Nodes in both but with different config
        foreach (array_intersect($keys1, $keys2) as $key) {
            if ($nodes1[$key] !== $nodes2[$key]) {
                $modified[$key] = [
                    'old' => $nodes1[$key],
                    'new' => $nodes2[$key],
                ];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
        ];
    }
}
