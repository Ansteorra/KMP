<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalsStatesLog;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use InvalidArgumentException;

/**
 * Centralizes bestowal state-log creation for entity and bulk transitions.
 */
class BestowalStateLogService
{
    use LocatorAwareTrait;

    private Table $stateLogsTable;

    /**
     * @param \Cake\ORM\Table|null $stateLogsTable Optional injected state-log table.
     */
    public function __construct(?Table $stateLogsTable = null)
    {
        $this->stateLogsTable = $stateLogsTable ?? $this->fetchTable('Awards.BestowalsStatesLogs');
    }

    /**
     * Persist the state change represented by a dirty bestowal entity.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal with a dirty state field.
     * @return \Awards\Model\Entity\BestowalsStatesLog|null
     */
    public function logEntityStateChange(Bestowal $bestowal): ?BestowalsStatesLog
    {
        if (!$bestowal->isDirty('state') || (int)$bestowal->id <= 0) {
            return null;
        }

        return $this->logStateTransition(
            (int)$bestowal->id,
            (string)($bestowal->beforeState ?: 'New'),
            (string)$bestowal->state,
            $bestowal->beforeStatus !== null ? (string)$bestowal->beforeStatus : null,
            $bestowal->status !== null ? (string)$bestowal->status : null,
            $bestowal->modified_by !== null ? (int)$bestowal->modified_by : null,
        );
    }

    /**
     * Create a state-log entry when a transition changes the saved state/status pair.
     *
     * @param int $bestowalId Bestowal ID.
     * @param string $fromState Prior state name.
     * @param string $toState New state name.
     * @param string|null $fromStatus Prior status name.
     * @param string|null $toStatus New status name.
     * @param int|null $createdBy Actor ID.
     * @return \Awards\Model\Entity\BestowalsStatesLog|null
     */
    public function logStateTransition(
        int $bestowalId,
        string $fromState,
        string $toState,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?int $createdBy = null,
    ): ?BestowalsStatesLog {
        $resolvedFromStatus = $fromStatus ?: $this->inferStatusForState($fromState);
        $resolvedToStatus = $toStatus ?: $this->inferStatusForState($toState);

        if ($fromState === $toState && $resolvedFromStatus === $resolvedToStatus) {
            return null;
        }

        return $this->createLog(
            $bestowalId,
            $fromState,
            $toState,
            $resolvedFromStatus,
            $resolvedToStatus,
            $createdBy,
        );
    }

    /**
     * Create a single immutable bestowal state-log entry.
     *
     * @param int $bestowalId Bestowal ID.
     * @param string $fromState Prior state name.
     * @param string $toState New state name.
     * @param string $fromStatus Prior status name.
     * @param string $toStatus New status name.
     * @param int|null $createdBy Actor ID.
     * @return \Awards\Model\Entity\BestowalsStatesLog
     */
    public function createLog(
        int $bestowalId,
        string $fromState,
        string $toState,
        string $fromStatus,
        string $toStatus,
        ?int $createdBy = null,
    ): BestowalsStatesLog {
        if ($bestowalId <= 0) {
            throw new InvalidArgumentException('Bestowal ID must be greater than zero.');
        }

        $log = $this->stateLogsTable->newEmptyEntity();
        $log->bestowal_id = $bestowalId;
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
     * @param string $state Bestowal state name.
     * @return string
     */
    public function inferStatusForState(string $state): string
    {
        if ($state === 'New') {
            return 'New';
        }

        foreach (Bestowal::getStatuses() as $status => $states) {
            if (in_array($state, $states, true)) {
                return (string)$status;
            }
        }

        return 'Unknown';
    }
}
