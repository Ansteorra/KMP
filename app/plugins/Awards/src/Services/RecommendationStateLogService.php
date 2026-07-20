<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationsStatesLog;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use InvalidArgumentException;

/**
 * Centralizes recommendation state-log creation for entity and bulk transitions.
 */
class RecommendationStateLogService
{
    use LocatorAwareTrait;

    private Table $stateLogsTable;

    /**
     * @param \Cake\ORM\Table|null $stateLogsTable Optional injected state-log table.
     */
    public function __construct(?Table $stateLogsTable = null)
    {
        $this->stateLogsTable = $stateLogsTable ?? $this->fetchTable('Awards.RecommendationsStatesLogs');
    }

    /**
     * Persist the state change represented by a dirty recommendation entity.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation with a dirty state field.
     * @return \Awards\Model\Entity\RecommendationsStatesLog|null
     */
    public function logEntityStateChange(Recommendation $recommendation): ?RecommendationsStatesLog
    {
        if (!$recommendation->isDirty('state') || (int)$recommendation->id <= 0) {
            return null;
        }

        return $this->logStateTransition(
            (int)$recommendation->id,
            (string)($recommendation->beforeState ?: 'New'),
            (string)$recommendation->state,
            $recommendation->beforeStatus !== null ? (string)$recommendation->beforeStatus : null,
            $recommendation->status !== null ? (string)$recommendation->status : null,
            $recommendation->modified_by !== null ? (int)$recommendation->modified_by : null,
        );
    }

    /**
     * Create a state-log entry when a transition changes the saved state/status pair.
     *
     * @param int $recommendationId Recommendation ID.
     * @param string $fromState Prior state name.
     * @param string $toState New state name.
     * @param string|null $fromStatus Prior status name.
     * @param string|null $toStatus New status name.
     * @param int|null $createdBy Actor ID.
     * @return \Awards\Model\Entity\RecommendationsStatesLog|null
     */
    public function logStateTransition(
        int $recommendationId,
        string $fromState,
        string $toState,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?int $createdBy = null,
    ): ?RecommendationsStatesLog {
        $resolvedFromStatus = $fromStatus ?: $this->inferStatusForState($fromState);
        $resolvedToStatus = $toStatus ?: $this->inferStatusForState($toState);

        if ($fromState === $toState && $resolvedFromStatus === $resolvedToStatus) {
            return null;
        }

        return $this->createLog(
            $recommendationId,
            $fromState,
            $toState,
            $resolvedFromStatus,
            $resolvedToStatus,
            $createdBy,
        );
    }

    /**
     * Create a single immutable recommendation state-log entry.
     *
     * @param int $recommendationId Recommendation ID.
     * @param string $fromState Prior state name.
     * @param string $toState New state name.
     * @param string $fromStatus Prior status name.
     * @param string $toStatus New status name.
     * @param int|null $createdBy Actor ID.
     * @return \Awards\Model\Entity\RecommendationsStatesLog
     */
    public function createLog(
        int $recommendationId,
        string $fromState,
        string $toState,
        string $fromStatus,
        string $toStatus,
        ?int $createdBy = null,
    ): RecommendationsStatesLog {
        if ($recommendationId <= 0) {
            throw new InvalidArgumentException('Recommendation ID must be greater than zero.');
        }

        $log = $this->stateLogsTable->newEmptyEntity();
        $log->recommendation_id = $recommendationId;
        $log->from_state = $fromState;
        $log->to_state = $toState;
        $log->from_status = $fromStatus;
        $log->to_status = $toStatus;
        $log->created_by = $createdBy;

        return $this->stateLogsTable->saveOrFail($log);
    }

    /**
     * Infer the owning status name for a workflow state.
     *
     * @param string $state Recommendation state name.
     * @return string
     */
    public function inferStatusForState(string $state): string
    {
        if ($state === 'New') {
            return 'New';
        }

        foreach (Recommendation::getStatuses() as $status => $states) {
            if (in_array($state, $states, true)) {
                return (string)$status;
            }
        }

        return 'Unknown';
    }
}
