<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use RuntimeException;

/**
 * Thrown when an optimistic lock version conflict is detected
 * during concurrent approval modification.
 */
class OptimisticLockException extends RuntimeException
{
}
