<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine\StateMachine;

use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Handles configurable state machine transitions with dual status/state tracking,
 * field rules, and audit logging. Designed for use by the workflow engine's
 * stateMachine node type but generic enough for any entity with state tracking.
 */
class StateMachineHandler
{
    /**
     * Check whether a transition from one state to another is allowed.
     *
     * @param string $currentState The entity's current state.
     * @param string $targetState The desired target state.
     * @param array $config The state machine configuration containing 'transitions'.
     * @return bool
     */
    public function validateTransition(string $currentState, string $targetState, array $config): bool
    {
        $transitions = $config['transitions'] ?? [];

        if (!isset($transitions[$currentState])) {
            return false;
        }

        return in_array($targetState, $transitions[$currentState], true);
    }

    /**
     * Determine the status category that contains the given state.
     *
     * @param string $state The state to look up.
     * @param array $statuses Map of status => [states].
     * @return string|null The status category, or null if not found.
     */
    public function resolveStatus(string $state, array $statuses): ?string
    {
        foreach ($statuses as $statusName => $states) {
            if (in_array($state, $states, true)) {
                return $statusName;
            }
        }

        return null;
    }

    /**
     * Apply "Set" rules to entity data, auto-populating fields for the target state.
     *
     * @param array $entityData The current entity data (key-value pairs).
     * @param array $rules The state rules containing optional 'set' key.
     * @return array The modified entity data.
     */
    public function applySetRules(array $entityData, array $rules): array
    {
        $setRules = $rules['set'] ?? [];

        foreach ($setRules as $field => $value) {
            $entityData[$field] = $value;
        }

        return $entityData;
    }

    /**
     * Validate that all required fields for a state transition are present and non-empty.
     *
     * @param array $entityData The entity data to check.
     * @param array $requiredFields List of field names that must be present.
     * @return array List of missing/empty field names. Empty array means valid.
     */
    public function validateRequiredFields(array $entityData, array $requiredFields): array
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $entityData) || $entityData[$field] === null || $entityData[$field] === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Create an audit log entry for a state transition.
     *
     * @param array $auditConfig The audit log configuration with 'table' and 'fields' keys.
     * @param string $fromState Previous state.
     * @param string $toState New state.
     * @param string $fromStatus Previous status category.
     * @param string $toStatus New status category.
     * @param int $entityId The entity being transitioned.
     * @param int|null $userId The user performing the transition.
     * @return void
     */
    public function createAuditLog(
        array $auditConfig,
        string $fromState,
        string $toState,
        string $fromStatus,
        string $toStatus,
        int $entityId,
        ?int $userId = null,
    ): void {
        $tableName = $auditConfig['table'] ?? null;
        if (!$tableName) {
            Log::warning('StateMachineHandler: No audit log table configured, skipping audit.');

            return;
        }

        $fieldMap = $auditConfig['fields'] ?? [];

        $data = [
            ($fieldMap['from_state'] ?? 'from_state') => $fromState,
            ($fieldMap['to_state'] ?? 'to_state') => $toState,
            ($fieldMap['from_status'] ?? 'from_status') => $fromStatus,
            ($fieldMap['to_status'] ?? 'to_status') => $toStatus,
            'entity_id' => $entityId,
            'created_by' => $userId,
            'created' => DateTime::now(),
        ];

        try {
            $table = TableRegistry::getTableLocator()->get($tableName);
            $entity = $table->newEntity($data, ['validate' => false]);
            $table->save($entity);
        } catch (\Throwable $e) {
            Log::error("StateMachineHandler: Failed to write audit log to '{$tableName}': {$e->getMessage()}");
        }
    }

    /**
     * Execute a full state transition: validate, apply rules, resolve status.
     *
     * @param array $entityData Current entity data.
     * @param string $currentState The current state value.
     * @param string $targetState The requested target state.
     * @param array $config Full state machine configuration.
     * @return array{success: bool, entityData?: array, newStatus?: string, error?: string, missingFields?: array}
     */
    public function executeTransition(
        array $entityData,
        string $currentState,
        string $targetState,
        array $config,
    ): array {
        // 1. Validate transition is allowed
        if (!$this->validateTransition($currentState, $targetState, $config)) {
            return [
                'success' => false,
                'error' => "Transition from '{$currentState}' to '{$targetState}' is not allowed.",
            ];
        }

        // 2. Get state rules for the target state
        $stateRules = $config['stateRules'][$targetState] ?? [];

        // 3. Apply "set" rules (auto-populate fields)
        $entityData = $this->applySetRules($entityData, $stateRules);

        // 4. Validate required fields
        $requiredFields = $stateRules['required'] ?? [];
        $missingFields = $this->validateRequiredFields($entityData, $requiredFields);
        if (!empty($missingFields)) {
            return [
                'success' => false,
                'error' => 'Missing required fields for state \'' . $targetState . '\': ' . implode(', ', $missingFields),
                'missingFields' => $missingFields,
            ];
        }

        // 5. Resolve new status
        $statuses = $config['statuses'] ?? [];
        $newStatus = $this->resolveStatus($targetState, $statuses);

        // 6. Update entity state/status fields
        $stateField = $config['stateField'] ?? 'state';
        $statusField = $config['statusField'] ?? 'status';
        $entityData[$stateField] = $targetState;
        if ($newStatus !== null) {
            $entityData[$statusField] = $newStatus;
        }

        return [
            'success' => true,
            'entityData' => $entityData,
            'newStatus' => $newStatus,
        ];
    }
}
