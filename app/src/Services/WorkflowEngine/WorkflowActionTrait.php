<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use Cake\I18n\DateTime;
use Cake\Log\Log;
use Throwable;

/**
 * Shared helpers for workflow action providers.
 */
trait WorkflowActionTrait
{
    /**
     * Run action code and return a standard ActionResult envelope.
     *
     * @param string $name Action name for logging
     * @param callable $fn Action implementation
     * @return array{success: bool, data: array, error: string|null}
     */
    protected function guard(string $name, callable $fn): array
    {
        try {
            $data = $fn();

            return ActionResult::success(is_array($data) ? $data : ['value' => $data])->toArray();
        } catch (Throwable $e) {
            Log::error("Workflow {$name} failed: " . $e->getMessage());

            return ActionResult::failure($e->getMessage())->toArray();
        }
    }

    /**
     * Normalize date-like action input values.
     *
     * @param mixed $value DateTime, string, or null
     * @param string|null $default Default date/time expression for null input
     * @return \Cake\I18n\DateTime|null
     */
    protected function coerceDateTime(mixed $value, ?string $default = null): ?DateTime
    {
        if ($value === null) {
            return $default === null ? null : new DateTime($default);
        }

        if ($value instanceof DateTime) {
            return $value;
        }

        return new DateTime((string)$value);
    }
}
