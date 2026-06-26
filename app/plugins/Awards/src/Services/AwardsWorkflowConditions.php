<?php

declare(strict_types=1);

namespace Awards\Services;

use App\Services\WorkflowEngine\StateMachine\StateMachineHandler;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Awards\Model\Entity\Recommendation;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;

/**
 * Workflow condition evaluators for the Awards plugin.
 *
 * Each method accepts workflow context and config, returns bool.
 */
class AwardsWorkflowConditions
{
    use LocatorAwareTrait;
    use WorkflowContextAwareTrait;

    private Table $recommendationsTable;
    private Table $bestowalsTable;
    private StateMachineHandler $stateMachineHandler;

    public function __construct(?StateMachineHandler $stateMachineHandler = null)
    {
        $this->recommendationsTable = $this->fetchTable('Awards.Recommendations');
        $this->bestowalsTable = $this->fetchTable('Awards.Bestowals');
        $this->stateMachineHandler = $stateMachineHandler ?? new StateMachineHandler();
    }

    /**
     * Check if a state transition is allowed per the state machine configuration.
     *
     * @param array $context Current workflow context
     * @param array $config Config with currentState, targetState
     * @return bool
     */
    public function isValidTransition(array $context, array $config): bool
    {
        try {
            $currentState = $this->resolveValue($config['currentState'] ?? null, $context);
            $targetState = $this->resolveValue($config['targetState'] ?? null, $context);

            if (empty($currentState) || empty($targetState)) {
                return false;
            }

            $allStates = Recommendation::getStates();

            // Both states must be valid
            if (!in_array((string)$currentState, $allStates, true) || !in_array((string)$targetState, $allStates, true)) {
                return false;
            }

            // Check valid transitions from DB
            $validTargets = Recommendation::getValidTransitionsFrom((string)$currentState);

            $smConfig = ['transitions' => [(string)$currentState => $validTargets]];

            return $this->stateMachineHandler->validateTransition(
                (string)$currentState,
                (string)$targetState,
                $smConfig,
            );
        } catch (\Throwable $e) {
            Log::error('Condition IsValidTransition failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate that all required fields for a target state are present on the entity.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId, targetState
     * @return bool
     */
    public function hasRequiredFields(array $context, array $config): bool
    {
        try {
            $recommendationId = $this->resolveValue($config['recommendationId'] ?? null, $context);
            $targetState = $this->resolveValue($config['targetState'] ?? null, $context);

            if (empty($recommendationId) || empty($targetState)) {
                return false;
            }

            $recommendation = $this->recommendationsTable->get((int)$recommendationId);
            $entityData = $recommendation->toArray();

            $stateRules = Recommendation::getStateRules();
            $rules = $stateRules[(string)$targetState] ?? [];

            $requiredFields = $rules['Required'] ?? [];
            if (empty($requiredFields)) {
                return true;
            }

            $missing = $this->stateMachineHandler->validateRequiredFields($entityData, $requiredFields);

            return empty($missing);
        } catch (\Throwable $e) {
            Log::error('Condition HasRequiredFields failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if the target state requires a gathering_id to be set.
     *
     * @param array $context Current workflow context
     * @param array $config Config with targetState
     * @return bool True if the target state requires a gathering assignment
     */
    public function requiresGathering(array $context, array $config): bool
    {
        try {
            $targetState = $this->resolveValue($config['targetState'] ?? null, $context);

            if (empty($targetState)) {
                return false;
            }

            return Recommendation::supportsGatheringAssignmentForState((string)$targetState);
        } catch (\Throwable $e) {
            Log::error('Condition RequiresGathering failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if the target state requires a given date.
     *
     * @param array $context Current workflow context
     * @param array $config Config with targetState
     * @return bool True if the target state requires a given date
     */
    public function requiresGivenDate(array $context, array $config): bool
    {
        try {
            $targetState = $this->resolveValue($config['targetState'] ?? null, $context);

            if (empty($targetState)) {
                return false;
            }

            $stateRules = Recommendation::getStateRules();
            $rules = $stateRules[(string)$targetState] ?? [];
            $requiredFields = $rules['Required'] ?? [];

            return in_array('given', $requiredFields, true);
        } catch (\Throwable $e) {
            Log::error('Condition RequiresGivenDate failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check whether a recommendation is linked to an active bestowal.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId
     * @return bool
     */
    public function recommendationHasActiveBestowal(array $context, array $config): bool
    {
        try {
            $recommendationId = $this->resolveValue($config['recommendationId'] ?? null, $context);
            if (empty($recommendationId)) {
                return false;
            }

            $recommendation = $this->recommendationsTable->get((int)$recommendationId);
            if ($recommendation->bestowal_id === null) {
                return false;
            }

            $bestowal = $this->bestowalsTable->get((int)$recommendation->bestowal_id);

            return $bestowal->isActiveBestowal();
        } catch (\Throwable $e) {
            Log::error('Condition RecommendationHasActiveBestowal failed: ' . $e->getMessage());

            return false;
        }
    }
}
