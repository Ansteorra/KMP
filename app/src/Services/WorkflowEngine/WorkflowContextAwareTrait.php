<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Services\WorkflowEngine\Conditions\CoreConditions;

/**
 * Shared context-resolution logic for workflow action and condition classes.
 *
 * Provides resolveValue() which dereferences '$.' context paths via CoreConditions.
 */
trait WorkflowContextAwareTrait
{
    /**
     * Resolve a config value from context if it starts with '$.'.
     *
     * @param mixed $value Raw value or context path (e.g. '$.trigger.memberId')
     * @param array $context Current workflow context
     * @return mixed Resolved value
     */
    protected function resolveValue(mixed $value, array $context): mixed
    {
        if (is_string($value) && str_starts_with($value, '$.')) {
            return CoreConditions::resolveFieldPath($context, $value);
        }

        return $value;
    }
}
