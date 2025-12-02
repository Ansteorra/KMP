<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Standardized result object for service layer operations.
 *
 * Provides consistent success/failure status, error messages, and optional data.
 * Used by WarrantManager, ActiveWindowManager, CsvExportService, etc.
 */
class ServiceResult
{
    /**
     * @var bool Whether the operation succeeded
     */
    public bool $success;

    /**
     * Human-readable error message (for failures) or informational message.
     *
     * @var string|null
     */
    public ?string $reason = null;

    /**
     * Optional data payload (entity IDs, entities, arrays, etc.).
     *
     * @var mixed
     */
    public $data = null;

    /**
     * @param bool $success Whether the operation succeeded
     * @param string|null $reason Optional explanation
     * @param mixed $data Optional data payload
     */
    public function __construct(bool $success, ?string $reason = null, $data = null)
    {
        $this->success = $success;
        if ($reason !== null) {
            $this->reason = $reason;
        }
        if ($data !== null) {
            $this->data = $data;
        }
    }

    /**
     * @return bool
        return $this->success;
    }

    /**
     * Get the data payload
     * 
     * Convenience method that returns the data property.
     * Consider checking isSuccess() before calling this method.
     * 
     * @return mixed The data payload or null
     * 
     * @example
     * ```php
     * if ($result->isSuccess()) {
     *     $id = $result->getData();
     * }
     * ```
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the error message
     * 
     * Convenience method that returns the reason property.
     * Typically used when isSuccess() is false to get the error message.
     * 
     * @return string|null The error message or null
     * 
     * @example
     * ```php
     * if (!$result->isSuccess()) {
     *     $error = $result->getError();
     *     $this->Flash->error($error);
     * }
     * ```
     */
    public function getError(): ?string
    {
        return $this->reason;
    }
}
