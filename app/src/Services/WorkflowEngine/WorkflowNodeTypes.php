<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Services\WorkflowRegistry\WorkflowActionRegistry;

/**
 * Canonical workflow node-type catalog shared by validation and execution.
 */
final class WorkflowNodeTypes
{
    public const SUPPORTED = [
        'trigger',
        'action',
        'condition',
        'approval',
        'fork',
        'join',
        'loop',
        'forEach',
        'delay',
        'subworkflow',
        'humanTask',
        'stateMachine',
        'end',
    ];

    public const WAITING = [
        'approval',
        'delay',
        'subworkflow',
        'humanTask',
    ];

    /**
     * Check whether the engine implements a node type.
     */
    public static function isSupported(string $type): bool
    {
        return in_array($type, self::SUPPORTED, true);
    }

    /**
     * Check whether a node type always suspends its workflow.
     */
    public static function isWaiting(string $type): bool
    {
        return in_array($type, self::WAITING, true);
    }

    /**
     * Check whether executing a node can suspend its workflow.
     */
    public static function requiresWaiting(array $node): bool
    {
        $type = (string)($node['type'] ?? '');
        if (self::isWaiting($type)) {
            return true;
        }
        if ($type !== 'action') {
            return false;
        }
        if (!empty($node['config']['isAsync'])) {
            return true;
        }

        $actionName = $node['config']['action'] ?? null;
        if (!is_string($actionName) || $actionName === '') {
            return false;
        }

        $actionConfig = WorkflowActionRegistry::getAction($actionName);

        return $actionConfig !== null && !empty($actionConfig['isAsync']);
    }
}
