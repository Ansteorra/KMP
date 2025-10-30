<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service Layer Result Pattern Implementation
 * 
 * Standardized response object for all KMP service layer operations. Provides a consistent
 * way to return success/failure status, error messages, and optional data from service methods.
 * This pattern eliminates the need for exception-based error handling in many business logic
 * scenarios and provides clear, predictable return values.
 * 
 * ## Design Pattern Benefits
 * 
 * - **Explicit Error Handling**: Forces callers to check success status before using data
 * - **Consistent Interface**: All services return the same result structure
 * - **Rich Error Information**: Includes human-readable error messages for UI display
 * - **Flexible Data Return**: Can carry any type of result data (IDs, entities, arrays)
 * - **Reduced Exceptions**: Minimizes exception throwing for expected failure conditions
 * 
 * ## Usage Patterns
 * 
 * ### Basic Success/Failure
 * ```php
 * $result = $service->performOperation();
 * if ($result->success) {
 *     // Handle success case
 * } else {
 *     $this->Flash->error($result->reason);
 * }
 * ```
 * 
 * ### Success with Data
 * ```php
 * $result = $service->createEntity($data);
 * if ($result->success) {
 *     $newId = $result->data; // e.g., newly created entity ID
 *     return $this->redirect(['action' => 'view', $newId]);
 * }
 * ```
 * 
 * ### Chained Service Calls
 * ```php
 * $result1 = $serviceA->validate($input);
 * if (!$result1->success) {
 *     return $result1; // Pass failure up the chain
 * }
 * 
 * $result2 = $serviceB->process($result1->data);
 * return $result2;
 * ```
 * 
 * ## KMP Service Integration
 * 
 * Used by all major KMP services:
 * - **WarrantManager**: Warrant lifecycle operations
 * - **ActiveWindowManager**: Date-bounded entity management  
 * - **AuthorizationService**: Permission checking results
 * - **CsvExportService**: Export operation results
 * 
 * @see \App\Services\WarrantManager\WarrantManagerInterface Example service using this pattern
 * @see \App\Services\ActiveWindowManager\ActiveWindowManagerInterface Another service example
 */
class ServiceResult
{
    /**
     * Indicates whether the service operation completed successfully
     * 
     * - true: Operation succeeded, data (if any) is valid and safe to use
     * - false: Operation failed, reason contains error details, data should be ignored
     * 
     * @var bool
     */
    public bool $success;

    /**
     * Human-readable explanation of the result
     * 
     * For failures: Contains error message suitable for display to users
     * For success: Usually null, but may contain informational messages
     * 
     * @var string|null
     */
    public ?string $reason = null;

    /**
     * Optional data payload from the service operation
     * 
     * Common data types returned:
     * - Entity IDs for creation operations
     * - Entity objects for retrieval operations  
     * - Arrays for list/search operations
     * - Scalar values for calculation operations
     * - null for operations that don't return data
     * 
     * @var mixed
     */
    public $data = null;

    /**
     * Create a new service result
     * 
     * @param bool $success Whether the operation succeeded
     * @param string|null $reason Optional explanation (required for failures, optional for success)
     * @param mixed $data Optional data payload
     * 
     * @example
     * ```php
     * // Simple success
     * return new ServiceResult(true);
     * 
     * // Success with data
     * return new ServiceResult(true, null, $entity->id);
     * 
     * // Failure with explanation
     * return new ServiceResult(false, 'Invalid input data provided');
     * 
     * // Success with informational message
     * return new ServiceResult(true, 'Operation completed with warnings', $results);
     * ```
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
     * Check if the service operation was successful
     * 
     * Convenience method that returns the success property.
     * Provides a more fluent API for checking results.
     * 
     * @return bool True if operation succeeded, false otherwise
     * 
     * @example
     * ```php
     * if ($result->isSuccess()) {
     *     // Handle success
     * }
     * ```
     */
    public function isSuccess(): bool
    {
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
