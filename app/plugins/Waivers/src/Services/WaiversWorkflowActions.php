<?php

declare(strict_types=1);

namespace Waivers\Services;

use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Workflow action implementations for waiver lifecycle operations.
 *
 * Delegates state transitions to WaiverStateService to avoid duplicating business logic.
 */
class WaiversWorkflowActions
{
    use WorkflowContextAwareTrait;
    use LocatorAwareTrait;

    private WaiverStateService $waiverService;

    public function __construct(?WaiverStateService $waiverService = null)
    {
        $this->waiverService = $waiverService ?? new WaiverStateService();
    }

    /**
     * Mark gathering waiver collection as ready to close.
     *
     * @param array $context Current workflow context
     * @param array $config Config with gatheringId, markedBy
     * @return array Output with success boolean
     */
    public function markReadyToClose(array $context, array $config): array
    {
        try {
            $gatheringId = (int)$this->resolveValue($config['gatheringId'], $context);
            $markedBy = (int)$this->resolveValue($config['markedBy'], $context);

            $result = $this->waiverService->markReadyToClose($gatheringId, $markedBy);

            if (!$result->success) {
                Log::warning('Workflow MarkReadyToClose: ' . $result->reason);
                return ['success' => false, 'error' => $result->reason];
            }

            return ['success' => true, 'data' => ['gatheringId' => $gatheringId]];
        } catch (\Throwable $e) {
            Log::error('Workflow MarkReadyToClose failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Close a gathering's waiver collection.
     *
     * @param array $context Current workflow context
     * @param array $config Config with gatheringId, closedBy
     * @return array Output with success boolean
     */
    public function closeWaiverCollection(array $context, array $config): array
    {
        try {
            $gatheringId = (int)$this->resolveValue($config['gatheringId'], $context);
            $closedBy = (int)$this->resolveValue($config['closedBy'], $context);

            $result = $this->waiverService->close($gatheringId, $closedBy);

            if (!$result->success) {
                Log::warning('Workflow CloseWaiverCollection: ' . $result->reason);
                return ['success' => false, 'error' => $result->reason];
            }

            return ['success' => true, 'data' => ['gatheringId' => $gatheringId]];
        } catch (\Throwable $e) {
            Log::error('Workflow CloseWaiverCollection failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reopen a closed waiver collection.
     *
     * @param array $context Current workflow context
     * @param array $config Config with gatheringId
     * @return array Output with success boolean
     */
    public function reopenWaiverCollection(array $context, array $config): array
    {
        try {
            $gatheringId = (int)$this->resolveValue($config['gatheringId'], $context);

            $result = $this->waiverService->reopen($gatheringId);

            if (!$result->success) {
                Log::warning('Workflow ReopenWaiverCollection: ' . $result->reason);
                return ['success' => false, 'error' => $result->reason];
            }

            return ['success' => true, 'data' => ['gatheringId' => $gatheringId]];
        } catch (\Throwable $e) {
            Log::error('Workflow ReopenWaiverCollection failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Decline an individual gathering activity waiver.
     *
     * @param array $context Current workflow context
     * @param array $config Config with waiverId, declineReason, declinedBy
     * @return array Output with success boolean
     */
    public function declineWaiver(array $context, array $config): array
    {
        try {
            $waiverId = (int)$this->resolveValue($config['waiverId'], $context);
            $declineReason = (string)$this->resolveValue($config['declineReason'] ?? '', $context);
            $declinedBy = (int)$this->resolveValue($config['declinedBy'], $context);

            $result = $this->waiverService->decline($waiverId, $declineReason, $declinedBy);

            if (!$result->success) {
                Log::warning('Workflow DeclineWaiver: ' . $result->reason);
                return ['success' => false, 'error' => $result->reason];
            }

            return ['success' => true, 'data' => ['waiverId' => $waiverId]];
        } catch (\Throwable $e) {
            Log::error('Workflow DeclineWaiver failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Remove ready-to-close status from a gathering.
     *
     * @param array $context Current workflow context
     * @param array $config Config with gatheringId
     * @return array Output with success boolean
     */
    public function unmarkReadyToClose(array $context, array $config): array
    {
        try {
            $gatheringId = (int)$this->resolveValue($config['gatheringId'], $context);

            $result = $this->waiverService->unmarkReadyToClose($gatheringId);

            if (!$result->success) {
                Log::warning('Workflow UnmarkReadyToClose: ' . $result->reason);
                return ['success' => false, 'error' => $result->reason];
            }

            return ['success' => true, 'data' => ['gatheringId' => $gatheringId]];
        } catch (\Throwable $e) {
            Log::error('Workflow UnmarkReadyToClose failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
