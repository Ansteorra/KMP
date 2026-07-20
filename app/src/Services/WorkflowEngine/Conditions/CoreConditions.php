<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine\Conditions;

use App\Services\WorkflowEngine\ExpressionEvaluator;
use Cake\ORM\TableRegistry;

/**
 * Core workflow condition evaluators: field comparisons, permission checks, and expressions.
 */
class CoreConditions
{
    private ExpressionEvaluator $expressionEvaluator;

    public function __construct(?ExpressionEvaluator $expressionEvaluator = null)
    {
        $this->expressionEvaluator = $expressionEvaluator ?? new ExpressionEvaluator();
    }
    /**
     * Check if a context field equals a specific value.
     *
     * @param array $context Current workflow context
     * @param array $config Config with 'field' (path) and 'value' (expected)
     * @return bool
     */
    public function fieldEquals(array $context, array $config): bool
    {
        $resolved = self::resolveFieldPath($context, $config['field']);

        // Use loose comparison for mixed types (int vs string)
        return $resolved == $config['value'];
    }

    /**
     * Check if a context field has a non-empty value.
     *
     * @param array $context Current workflow context
     * @param array $config Config with 'field' (path)
     * @return bool
     */
    public function fieldNotEmpty(array $context, array $config): bool
    {
        $resolved = self::resolveFieldPath($context, $config['field']);

        return !empty($resolved);
    }

    /**
     * Check if a numeric field exceeds a threshold.
     *
     * @param array $context Current workflow context
     * @param array $config Config with 'field' (path) and 'value' (threshold)
     * @return bool
     */
    public function fieldGreaterThan(array $context, array $config): bool
    {
        $resolved = self::resolveFieldPath($context, $config['field']);

        if (!is_numeric($resolved) || !is_numeric($config['value'])) {
            return false;
        }

        return (float)$resolved > (float)$config['value'];
    }

    /**
     * Check if a member has a specific permission.
     *
     * @param array $context Current workflow context
     * @param array $config Config with 'memberId' and 'permission'
     * @return bool
     */
    public function memberHasPermission(array $context, array $config): bool
    {
        try {
            $memberId = $config['memberId'];
            $permission = $config['permission'];

            $membersTable = TableRegistry::getTableLocator()->get('Members');
            $member = $membersTable->get($memberId, contain: ['Roles.Permissions']);

            foreach ($member->roles as $role) {
                foreach ($role->permissions as $perm) {
                    if ($perm->name === $permission) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Evaluate a simple expression against the workflow context.
     *
     * Supports basic comparisons: field == value, field != value,
     * field > value, field < value, field >= value, field <= value.
     *
     * @param array $context Current workflow context
     * @param array $config Config with 'expression' string
     * @return bool
     */
    public function evaluateExpression(array $context, array $config): bool
    {
        $expression = trim($config['expression'] ?? '');
        if (empty($expression)) {
            return false;
        }

        // Match: field operator value (with optional expression-evaluated right side)
        $operators = ['>=', '<=', '!=', '==', '>', '<'];
        foreach ($operators as $op) {
            $parts = explode($op, $expression, 2);
            if (count($parts) === 2) {
                $field = trim($parts[0]);
                $rawRight = trim($parts[1]);

                // Resolve left side from context
                $resolved = self::resolveFieldPath($context, $field);

                // Resolve right side: use ExpressionEvaluator for '=' prefixed values
                if (str_starts_with($rawRight, '=')) {
                    $value = $this->expressionEvaluator->evaluate(substr($rawRight, 1), $context);
                } else {
                    $value = trim($rawRight, " \t\n\r\0\x0B\"'");
                }

                return self::compare($resolved, $op, $value);
            }
        }

        // If no operator found, treat as boolean field check
        $resolved = self::resolveFieldPath($context, $expression);

        return !empty($resolved);
    }

    /**
     * Resolve a dot-separated field path from context.
     *
     * Supports paths like 'entity.field_name', '$.entity.field_name',
     * or simple field names.
     *
     * @param array $context The workflow context array
     * @param string $path Dot-separated path to resolve
     * @return mixed The resolved value or null if not found
     */
    public static function resolveFieldPath(array $context, string $path): mixed
    {
        // Strip leading $. prefix if present
        if (str_starts_with($path, '$.')) {
            $path = substr($path, 2);
        }

        $segments = explode('.', $path);
        $current = $context;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (is_object($current) && isset($current->{$segment})) {
                $current = $current->{$segment};
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Compare two values with a given operator.
     *
     * @param mixed $left Left operand
     * @param string $operator Comparison operator
     * @param mixed $right Right operand
     * @return bool
     */
    private static function compare(mixed $left, string $operator, mixed $right): bool
    {
        // Cast to numeric if both sides are numeric
        if (is_numeric($left) && is_numeric($right)) {
            $left = (float)$left;
            $right = (float)$right;
        }

        return match ($operator) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '<' => $left < $right,
            '>=' => $left >= $right,
            '<=' => $left <= $right,
            default => false,
        };
    }
}
