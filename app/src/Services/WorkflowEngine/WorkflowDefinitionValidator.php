<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;

/**
 * Shared workflow definition graph validator for seeds, publishing, and CI checks.
 */
class WorkflowDefinitionValidator
{
    /**
     * @param array $definition Workflow graph to validate
     * @return array<string>
     */
    public function validate(array $definition): array
    {
        $errors = [];

        if (($definition['schemaVersion'] ?? null) !== '1.0') {
            $errors[] = 'Definition must declare schemaVersion "1.0".';
        }

        if (empty($definition['nodes']) || !is_array($definition['nodes'])) {
            $errors[] = 'Definition must contain a non-empty "nodes" array.';

            return $errors;
        }

        $nodes = $definition['nodes'];
        $nodeKeys = array_keys($nodes);

        $triggerNodes = array_filter($nodes, fn($node) => ($node['type'] ?? '') === 'trigger');
        if (count($triggerNodes) !== 1) {
            $errors[] = 'Definition must contain exactly one trigger node.';
        }

        $endNodes = array_filter($nodes, fn($node) => ($node['type'] ?? '') === 'end');
        if (count($endNodes) < 1) {
            $errors[] = 'Definition must contain at least one end node.';
        }

        foreach ($nodes as $key => $node) {
            $type = $node['type'] ?? null;
            if (!is_string($type) || !WorkflowNodeTypes::isSupported($type)) {
                $errors[] = "Node '{$key}' has unsupported type '" . (is_scalar($type) ? (string)$type : '') . "'.";
            }

            $outputs = $node['outputs'] ?? [];
            if (!is_array($outputs)) {
                $errors[] = "Node '{$key}' outputs must be an array.";
                continue;
            }

            foreach ($outputs as $output) {
                $target = $output['target'] ?? $output;
                if (is_string($target) && !in_array($target, $nodeKeys, true)) {
                    $errors[] = "Node '{$key}' references non-existent target '{$target}'.";
                }
            }
        }

        $triggerKey = !empty($triggerNodes) ? array_key_first($triggerNodes) : null;
        if ($triggerKey !== null) {
            $reachable = $this->findReachableNodes($triggerKey, $nodes);
            foreach ($nodeKeys as $key) {
                if ($key !== $triggerKey && !in_array($key, $reachable, true)) {
                    $errors[] = "Node '{$key}' is not reachable from the trigger node.";
                }
            }

            foreach ($this->detectCycles($triggerKey, $nodes) as $cycle) {
                $errors[] = 'Cycle detected in graph: ' . implode(' -> ', $cycle) . '.';
            }
        }

        foreach (array_filter($nodes, fn($node) => ($node['type'] ?? '') === 'loop') as $key => $node) {
            if (empty($node['config']['maxIterations'])) {
                $errors[] = "Loop node '{$key}' must have maxIterations set.";
            }
        }

        foreach (array_filter($nodes, fn($node) => ($node['type'] ?? '') === 'forEach') as $key => $node) {
            if (empty($node['config']['collection'])) {
                $errors[] = "ForEach node '{$key}' must have a collection path configured.";
            }
            foreach ($this->findWaitingForEachDescendants((string)$key, $node, $nodes) as $descendant => $type) {
                $errors[] = "ForEach node '{$key}' iterate path cannot contain waiting node "
                    . "'{$descendant}' of type '{$type}'.";
            }
        }

        foreach ($nodes as $key => $node) {
            $this->validateActionOrConditionNode((string)$key, (array)$node, $errors);
        }

        return $errors;
    }

    /**
     * Find async/waiting nodes reachable from a forEach iterate output.
     *
     * @param string $nodeKey ForEach node key
     * @param array $node ForEach node definition
     * @param array $nodes Workflow nodes
     * @return array<string, string> Node ID to node type
     */
    private function findWaitingForEachDescendants(string $nodeKey, array $node, array $nodes): array
    {
        $waiting = [];
        foreach (($node['outputs'] ?? []) as $output) {
            if (!is_array($output) || ($output['port'] ?? 'default') !== 'iterate') {
                continue;
            }
            $target = $output['target'] ?? null;
            if (!is_string($target) || !isset($nodes[$target])) {
                continue;
            }

            foreach ($this->findReachableNodes($target, $nodes) as $descendant) {
                if ($descendant === $nodeKey) {
                    continue;
                }
                $descendantNode = (array)($nodes[$descendant] ?? []);
                $type = (string)($descendantNode['type'] ?? '');
                if (WorkflowNodeTypes::requiresWaiting($descendantNode)) {
                    $waiting[$descendant] = $type;
                }
            }
        }

        return $waiting;
    }

    /**
     * @param string $key Node key
     * @param array $node Node definition
     * @param array<string> $errors Errors accumulator
     * @return void
     */
    private function validateActionOrConditionNode(string $key, array $node, array &$errors): void
    {
        $type = $node['type'] ?? '';

        if ($type === 'action' && isset($node['config']['action'])) {
            $actionName = $node['config']['action'];
            $actionConfig = WorkflowActionRegistry::getAction($actionName);
            if (!$actionConfig) {
                $errors[] = "Action node '{$key}' references unknown action '{$actionName}'.";

                return;
            }

            $this->validateRequiredParams(
                "Action node '{$key}' ({$actionName})",
                $actionConfig['inputSchema'] ?? [],
                $node['config']['params'] ?? [],
                $node['config'] ?? [],
                $errors,
            );
        }

        if ($type === 'condition' && isset($node['config']['condition'])) {
            $conditionName = $node['config']['condition'];
            if (str_starts_with($conditionName, 'Core.')) {
                return;
            }

            $conditionConfig = WorkflowConditionRegistry::getCondition($conditionName);
            if (!$conditionConfig) {
                $errors[] = "Condition node '{$key}' references unknown condition '{$conditionName}'.";

                return;
            }

            $this->validateRequiredParams(
                "Condition node '{$key}' ({$conditionName})",
                $conditionConfig['inputSchema'] ?? [],
                $node['config']['params'] ?? [],
                $node['config'] ?? [],
                $errors,
            );
        }
    }

    /**
     * @param string $prefix Error prefix
     * @param array $inputSchema Registry input schema
     * @param array $params Node params
     * @param array $config Node config
     * @param array<string> $errors Errors accumulator
     * @return void
     */
    private function validateRequiredParams(
        string $prefix,
        array $inputSchema,
        array $params,
        array $config,
        array &$errors,
    ): void {
        foreach ($inputSchema as $paramKey => $paramMeta) {
            if ($this->isSchemaFieldHidden((array)$paramMeta)) {
                continue;
            }

            if (!empty($paramMeta['required']) && empty($params[$paramKey]) && !isset($config[$paramKey])) {
                $errors[] = "{$prefix}: required parameter '{$paramKey}' is not configured.";
            }
        }
    }

    /**
     * @param array $paramMeta Schema metadata
     * @return bool
     */
    private function isSchemaFieldHidden(array $paramMeta): bool
    {
        return ($paramMeta['hidden'] ?? false) === true || ($paramMeta['visible'] ?? true) === false;
    }

    /**
     * @param string $startKey Starting node key
     * @param array $nodes Workflow nodes
     * @return array<string>
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

            foreach (($nodes[$current]['outputs'] ?? []) as $output) {
                $target = $output['target'] ?? $output;
                if (is_string($target) && !in_array($target, $visited, true)) {
                    $queue[] = $target;
                }
            }
        }

        return $visited;
    }

    /**
     * @param string $startKey Starting node key
     * @param array $nodes Workflow nodes
     * @return array<array<string>>
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
     * @param string $nodeKey Node key
     * @param array $nodes Workflow nodes
     * @param array<string, bool> $visited Visited map
     * @param array<string, bool> $stack Recursion stack
     * @param array<array<string>> $cycles Cycle accumulator
     * @return void
     */
    private function dfsDetectCycles(
        string $nodeKey,
        array $nodes,
        array &$visited,
        array &$stack,
        array &$cycles,
    ): void {
        $visited[$nodeKey] = true;
        $stack[$nodeKey] = true;

        $nodeType = $nodes[$nodeKey]['type'] ?? '';
        foreach (($nodes[$nodeKey]['outputs'] ?? []) as $output) {
            $target = $output['target'] ?? $output;
            $port = $output['port'] ?? 'default';

            if (!is_string($target) || !isset($nodes[$target])) {
                continue;
            }

            if ($nodeType === 'loop' && $port === 'continue') {
                continue;
            }

            if (isset($stack[$target])) {
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
}
