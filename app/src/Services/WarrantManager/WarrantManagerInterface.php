<?php
declare(strict_types=1);

namespace App\Services\WarrantManager;

use App\Model\Entity\WarrantPeriod;
use App\Services\ServiceResult;
use Cake\I18n\DateTime;
use DateTimeInterface;

/**
 * Warrant Manager Interface
 *
 * Defines the contract for managing KMP warrant lifecycle: requests, approvals,
 * and lifecycle management for authorized roles/positions within branches.
 *
 * @see \App\Services\WarrantManager\DefaultWarrantManager Default implementation
 * @see \App\Services\WarrantManager\WarrantRequest Warrant request data structure
 * @see \App\Model\Entity\Warrant Warrant entity
 */
interface WarrantManagerInterface
{
    /**
     * Submit a batch of warrant requests for approval.
     *
     * @param string $request_name Name for the warrant roster
     * @param string $desc Description of the warrant requests
     * @param array<\App\Services\WarrantManager\WarrantRequest> $warrantRequests Array of warrant request objects
     * @param int|null $requestedBy Member ID of the user who initiated the request
     * @return \App\Services\ServiceResult Success with roster ID, or failure with errors
     */
    public function request($request_name, $desc, $warrantRequests, ?int $requestedBy = null): ServiceResult;

    /**
     * Decline an entire warrant roster and cancel all contained warrants.
     *
     * @param int $warrant_roster_id ID of the WarrantRoster to decline
     * @param int $rejecter_id ID of the member declining
     * @param string $reason Explanation for the decline
     * @return \App\Services\ServiceResult Success if declined, failure with errors
     */
    public function decline($warrant_roster_id, $rejecter_id, $reason): ServiceResult;

    /**
     * Cancel/revoke a specific warrant by ID.
     *
     * @param int $warrant_id ID of the warrant to cancel
     * @param string $reason Explanation for cancellation
     * @param int $rejecter_id ID of member cancelling
     * @param \Cake\I18n\DateTime $expiresOn When warrant should terminate
     * @return \App\Services\ServiceResult Always returns success
     */
    public function cancel($warrant_id, $reason, $rejecter_id, $expiresOn): ServiceResult;

    /**
     * Cancel all warrants associated with a specific entity.
     *
     * @param string $entityType Entity type (e.g., 'Branches', 'Activities')
     * @param int $entityId ID of the entity instance
     * @param string $reason Explanation for cancellation
     * @param int $rejecter_id ID of member cancelling
     * @param \Cake\I18n\DateTime $expiresOn When warrants should terminate
     * @return \App\Services\ServiceResult Always returns success
     */
    public function cancelByEntity($entityType, $entityId, $reason, $rejecter_id, $expiresOn): ServiceResult;

    /**
     * Decline a single warrant within a roster.
     *
     * @param int $warrant_id ID of the warrant to decline
     * @param string $reason Explanation for decline
     * @param int $rejecter_id ID of member declining
     * @return \App\Services\ServiceResult Success if declined, failure with errors
     */
    public function declineSingleWarrant($warrant_id, $reason, $rejecter_id): ServiceResult;

    /**
     * Activate warrants in an already-approved roster.
     *
     * Expects roster status=APPROVED and correct approval_count.
     * Activates pending warrants and expires overlapping ones.
     * Idempotent: returns success if no pending warrants remain.
     *
     * @param int $rosterId ID of the approved WarrantRoster
     * @param int $approverId ID of the member who approved
     * @return \App\Services\ServiceResult Success if warrants activated or already active
     */
    public function activateApprovedRoster(
        int $rosterId,
        int $approverId,
    ): ServiceResult;

    /**
     * Sync a workflow approval response to the roster's denormalized counter.
     *
     * Increments approval_count on the warrant_rosters table.
     * Dedup is handled by the workflow engine's approval manager.
     * Does NOT change roster status or activate warrants.
     *
     * @param int $rosterId ID of the WarrantRoster
     * @param int $approverId ID of the approving member
     * @param string|null $notes
     *   Optional approval notes (unused, kept for interface compat)
     * @param \DateTimeInterface|null $approvedOn
     *   When approval occurred (unused, kept for interface compat)
     * @return \App\Services\ServiceResult Success after counter increment
     */
    public function syncWorkflowApprovalToRoster(
        int $rosterId,
        int $approverId,
        ?string $notes = null,
        ?DateTimeInterface $approvedOn = null,
    ): ServiceResult;

    /**
     * Find or create a warrant period covering the specified date range.
     *
     * @param \Cake\I18n\DateTime $startOn Desired warrant start date
     * @param \Cake\I18n\DateTime|null $endOn Desired warrant end date
     * @return \App\Model\Entity\WarrantPeriod|null Matching period, or null if none found
     */
    public function getWarrantPeriod(DateTime $startOn, ?DateTime $endOn): ?WarrantPeriod;
}
