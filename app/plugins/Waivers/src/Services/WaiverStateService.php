<?php
declare(strict_types=1);

namespace Waivers\Services;

use App\Services\ServiceResult;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Waiver State Service
 *
 * Manages waiver collection lifecycle state transitions for gatherings:
 * closing, reopening, marking ready-to-close, unmarking, and declining waivers.
 */
class WaiverStateService
{
    /**
     * Close waiver collection for a gathering.
     *
     * @param int $gatheringId Gathering ID
     * @param int $closedBy User ID performing the close
     * @return \App\Services\ServiceResult
     */
    public function close(int $gatheringId, int $closedBy): ServiceResult
    {
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $existing = $GatheringWaiverClosures->getClosureForGathering($gatheringId);

        if ($existing && $existing->isClosed()) {
            return new ServiceResult(false, __('Waiver collection is already closed for this gathering.'));
        }

        if ($existing) {
            $existing->closed_at = DateTime::now();
            $existing->closed_by = $closedBy;

            if ($GatheringWaiverClosures->save($existing)) {
                return new ServiceResult(true, __('Waiver collection has been closed.'));
            }

            return new ServiceResult(false, __('Unable to close waiver collection. Please try again.'));
        }

        $closure = $GatheringWaiverClosures->newEntity([
            'gathering_id' => $gatheringId,
            'closed_at' => DateTime::now(),
            'closed_by' => $closedBy,
        ]);

        if ($GatheringWaiverClosures->save($closure)) {
            return new ServiceResult(true, __('Waiver collection has been closed.'));
        }

        return new ServiceResult(false, __('Unable to close waiver collection. Please try again.'));
    }

    /**
     * Reopen waiver collection for a gathering.
     *
     * @param int $gatheringId Gathering ID
     * @return \App\Services\ServiceResult
     */
    public function reopen(int $gatheringId): ServiceResult
    {
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $existing = $GatheringWaiverClosures->getClosureForGathering($gatheringId);

        if (!$existing) {
            return new ServiceResult(false, __('Waiver collection is already open for this gathering.'));
        }

        if ($GatheringWaiverClosures->delete($existing)) {
            return new ServiceResult(true, __('Waiver collection has been reopened.'));
        }

        return new ServiceResult(false, __('Unable to reopen waiver collection. Please try again.'));
    }

    /**
     * Mark a gathering as ready for waiver secretary to close.
     *
     * @param int $gatheringId Gathering ID
     * @param int $markedBy User ID performing the action
     * @return \App\Services\ServiceResult
     */
    public function markReadyToClose(int $gatheringId, int $markedBy): ServiceResult
    {
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $existing = $GatheringWaiverClosures->getClosureForGathering($gatheringId);

        if ($existing && $existing->isClosed()) {
            return new ServiceResult(false, __('Waiver collection is already closed for this gathering.'));
        }

        if ($existing && $existing->isReadyToClose()) {
            return new ServiceResult(false, __('This gathering is already marked as ready to close.'));
        }

        $closure = $GatheringWaiverClosures->markReadyToClose($gatheringId, $markedBy);

        if ($closure) {
            return new ServiceResult(true, __('Gathering marked as ready for waiver secretary review.'));
        }

        return new ServiceResult(false, __('Unable to mark gathering as ready. Please try again.'));
    }

    /**
     * Unmark a gathering as ready to close (reverts ready status).
     *
     * @param int $gatheringId Gathering ID
     * @return \App\Services\ServiceResult
     */
    public function unmarkReadyToClose(int $gatheringId): ServiceResult
    {
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $existing = $GatheringWaiverClosures->getClosureForGathering($gatheringId);

        if ($existing && $existing->isClosed()) {
            return new ServiceResult(false, __('Cannot unmark - waiver collection is already closed by the waiver secretary.'));
        }

        if (!$existing || !$existing->isReadyToClose()) {
            return new ServiceResult(false, __('This gathering is not marked as ready to close.'));
        }

        if ($GatheringWaiverClosures->unmarkReadyToClose($gatheringId)) {
            return new ServiceResult(true, __('Gathering is no longer marked as ready to close.'));
        }

        return new ServiceResult(false, __('Unable to unmark gathering. Please try again.'));
    }

    /**
     * Decline/reject an invalid waiver.
     *
     * @param int $waiverId Waiver ID
     * @param string $declineReason Reason for declining
     * @param int $declinedBy User ID performing the decline
     * @return \App\Services\ServiceResult
     */
    public function decline(int $waiverId, string $declineReason, int $declinedBy): ServiceResult
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $gatheringWaiver = $GatheringWaivers->get($waiverId, [
            'contain' => ['Gatherings', 'WaiverTypes'],
        ]);

        if (!$gatheringWaiver->can_be_declined) {
            if ($gatheringWaiver->is_declined) {
                return new ServiceResult(false, __('This waiver has already been declined.'));
            }
            if ($gatheringWaiver->status === 'expired' || $gatheringWaiver->status === 'deleted') {
                return new ServiceResult(false, __('Expired or deleted waivers cannot be declined.'));
            }

            return new ServiceResult(false, __('This waiver can no longer be declined. Waivers can only be declined within 30 days of upload.'));
        }

        if (empty($declineReason)) {
            return new ServiceResult(false, __('Please provide a reason for declining this waiver.'));
        }

        $gatheringWaiver->declined_at = new DateTime();
        $gatheringWaiver->declined_by = $declinedBy;
        $gatheringWaiver->decline_reason = $declineReason;
        $gatheringWaiver->status = 'declined';

        if ($GatheringWaivers->save($gatheringWaiver)) {
            Log::info('Waiver declined', [
                'waiver_id' => $gatheringWaiver->id,
                'gathering_id' => $gatheringWaiver->gathering_id,
                'declined_by' => $declinedBy,
                'decline_reason' => $declineReason,
            ]);

            return new ServiceResult(true, __('The waiver has been declined.'));
        }

        return new ServiceResult(false, __('The waiver could not be declined. Please, try again.'));
    }
}
