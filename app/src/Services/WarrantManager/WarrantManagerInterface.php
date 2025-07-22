<?php

declare(strict_types=1);

namespace App\Services\WarrantManager;

use App\Model\Entity\WarrantPeriod;
use App\Services\ServiceResult;
use Cake\I18n\DateTime;

/**
 * Warrant Manager Interface
 * 
 * Defines the contract for managing KMP warrant lifecycle operations. Warrants in KMP represent 
 * authorized roles or positions that members can hold within branches and the broader organization.
 * This interface standardizes the workflow for warrant requests, approvals, and lifecycle management.
 * 
 * ## Warrant System Overview
 * 
 * The KMP warrant system implements a formal authorization process where:
 * - **Warrant Requests**: Collections of individual warrant applications grouped in WarrantRosters
 * - **Approval Process**: Multi-level approval system with configurable approval thresholds
 * - **Active Warrants**: Currently valid authorizations with start/end dates
 * - **Warrant Periods**: Administrative time frames that control warrant validity windows
 * 
 * ## Business Rules
 * 
 * 1. **Membership Validation**: Members must be warrantable and have active membership
 * 2. **Approval Requirements**: Configurable number of approvers required (default: 2)
 * 3. **Exclusive Warrants**: New warrants for same entity/member expire existing ones
 * 4. **Automatic Expiration**: Background process expires warrants past their end date
 * 5. **Dependency Management**: Declining warrants stops related entities (roles, activities)
 * 
 * ## State Management
 * 
 * Warrants follow this lifecycle:
 * ```
 * PENDING → CURRENT → EXPIRED/DEACTIVATED
 *     ↓
 * CANCELLED/DECLINED
 * ```
 * 
 * ## Security Considerations
 * 
 * - All operations require proper authorization through KMP's policy system
 * - Warrant operations are transactional to ensure data consistency
 * - Email notifications are queued for approved warrants
 * - Audit trail maintained through Notes system for declined/cancelled warrants
 * 
 * @see \App\Services\WarrantManager\DefaultWarrantManager Default implementation
 * @see \App\Services\WarrantManager\WarrantRequest Warrant request data structure
 * @see \App\Model\Entity\Warrant Individual warrant entity
 * @see \App\Model\Entity\WarrantRoster Group of warrant requests
 * @see \App\Model\Entity\WarrantPeriod Administrative time periods for warrants
 */
interface WarrantManagerInterface
{
    /**
     * Submit a batch of warrant requests for approval
     * 
     * Creates a WarrantRoster containing multiple WarrantRequest objects that will be processed
     * as a group through the approval workflow. All warrants in the roster must be approved
     * together or declined together.
     * 
     * Business Logic:
     * - Validates member warrantability and membership status
     * - Ensures warrant periods don't exceed membership expiration
     * - Creates pending warrants linked to the roster
     * - Uses database transactions for atomicity
     * 
     * @param string $request_name Human-readable name for the warrant roster
     * @param string $desc Description explaining the purpose of the warrant requests
     * @param WarrantRequest[] $warrantRequests Array of individual warrant request objects
     * 
     * @return ServiceResult Success with roster ID, or failure with validation errors
     * 
     * @example
     * ```php
     * $requests = [
     *     new WarrantRequest('Branch Officer', 'Branches', 5, $requesterId, $memberId),
     *     new WarrantRequest('Deputy Herald', 'Branches', 5, $requesterId, $memberId2)
     * ];
     * $result = $warrantManager->request('Q2 Officer Appointments', 'Quarterly appointments', $requests);
     * if ($result->success) {
     *     $rosterId = $result->data;
     * }
     * ```
     */
    public function request($request_name, $desc, $warrantRequests): ServiceResult;

    /**
     * Approve a warrant roster and activate all contained warrants
     * 
     * Records an approval vote for the specified warrant roster. When the roster reaches
     * the required number of approvals, all pending warrants become active and email
     * notifications are sent to recipients.
     * 
     * Business Logic:
     * - Validates roster exists and is in pending status
     * - Records approver and timestamp
     * - When approval threshold met: activates all warrants, expires conflicting warrants
     * - Adjusts start dates to current time if warrant period already started
     * - Queues email notifications for warrant recipients
     * 
     * @param int $warrant_roster_id ID of the WarrantRoster to approve
     * @param int $approver_id ID of the member providing approval
     * 
     * @return ServiceResult Success if approval recorded, failure with validation errors
     * 
     * @example
     * ```php
     * $result = $warrantManager->approve($rosterId, $approverId);
     * if ($result->success && $roster->hasRequiredApprovals()) {
     *     // All warrants in roster are now active
     * }
     * ```
     */
    public function approve($warrant_roster_id, $approver_id): ServiceResult;

    /**
     * Decline an entire warrant roster and cancel all contained warrants
     * 
     * Rejects a warrant roster and marks all contained pending warrants as cancelled.
     * This is typically used when the entire request set is inappropriate or invalid.
     * 
     * Business Logic:
     * - Validates roster exists and is in pending status  
     * - Marks all pending warrants as cancelled with provided reason
     * - Sets roster status to declined
     * - Creates audit note with decline reason
     * - Uses database transactions for atomicity
     * 
     * @param int $warrant_roster_id ID of the WarrantRoster to decline
     * @param int $rejecter_id ID of the member declining the roster
     * @param string $reason Human-readable explanation for the decline
     * 
     * @return ServiceResult Success if roster declined, failure with database errors
     * 
     * @example
     * ```php
     * $result = $warrantManager->decline($rosterId, $rejectorId, 'Insufficient qualifications');
     * // All warrants in roster are now cancelled
     * ```
     */
    public function decline($warrant_roster_id, $rejecter_id, $reason): ServiceResult;

    /**
     * Cancel/revoke a specific warrant by ID
     * 
     * Terminates an active warrant by setting its expiration date and recording the
     * reason for cancellation. Used when a specific warrant needs to be revoked
     * independent of its original roster.
     * 
     * Business Logic:
     * - Sets warrant expiration to specified date
     * - Records revocation reason and revoker
     * - If expiration is past, marks as deactivated immediately
     * - Does not affect other warrants in the same roster
     * 
     * @param int $warrant_id ID of the specific warrant to cancel
     * @param string $reason Human-readable explanation for cancellation
     * @param int $rejecter_id ID of the member cancelling the warrant
     * @param DateTime $expiresOn Date when the warrant should be terminated
     * 
     * @return ServiceResult Always returns success (handles missing warrants gracefully)
     * 
     * @example
     * ```php
     * $result = $warrantManager->cancel($warrantId, 'Officer resigned', $revokerId, new DateTime('+1 day'));
     * ```
     */
    public function cancel($warrant_id, $reason, $rejecter_id, $expiresOn): ServiceResult;

    /**
     * Cancel all warrants associated with a specific entity
     * 
     * Finds and cancels warrants linked to a particular entity (branch, activity, etc.).
     * Used when an organizational entity is dissolved or restructured and all related
     * warrants need to be terminated.
     * 
     * Business Logic:
     * - Searches for warrants matching entity type and ID
     * - Cancels first matching warrant (typically only one per entity)
     * - Gracefully handles cases where no warrants exist
     * 
     * @param string $entityType Type of entity (e.g., 'Branches', 'Activities')
     * @param int $entityId ID of the specific entity instance
     * @param string $reason Human-readable explanation for cancellation
     * @param int $rejecter_id ID of the member cancelling the warrants
     * @param DateTime $expiresOn Date when warrants should be terminated
     * 
     * @return ServiceResult Always returns success (handles missing warrants gracefully)
     * 
     * @example
     * ```php
     * $result = $warrantManager->cancelByEntity('Branches', $branchId, 'Branch dissolved', $revokerId, DateTime::now());
     * ```
     */
    public function cancelByEntity($entityType, $entityId, $reason, $rejecter_id, $expiresOn): ServiceResult;

    /**
     * Decline a single warrant within a roster
     * 
     * Rejects an individual warrant while potentially leaving others in the same roster
     * active. If all warrants in a roster are individually declined, the entire roster
     * is marked as declined.
     * 
     * Business Logic:
     * - Marks individual warrant as declined
     * - Stops related entities (member roles, activities) through ActiveWindowManager
     * - If no pending warrants remain in roster, marks entire roster as declined
     * - Creates audit notes for tracking
     * - Uses database transactions for consistency
     * 
     * @param int $warrant_id ID of the specific warrant to decline
     * @param string $reason Human-readable explanation for decline
     * @param int $rejecter_id ID of the member declining the warrant
     * 
     * @return ServiceResult Success if warrant declined, failure with database errors
     * 
     * @example
     * ```php
     * $result = $warrantManager->declineSingleWarrant($warrantId, 'Member not qualified', $rejectorId);
     * ```
     */
    public function declineSingleWarrant($warrant_id, $reason, $rejecter_id): ServiceResult;

    /**
     * Find or create a warrant period covering the specified date range
     * 
     * Locates an existing WarrantPeriod that encompasses the requested start and end dates,
     * adjusting the period boundaries if necessary. Warrant periods define the administrative
     * windows during which warrants can be active.
     * 
     * Business Logic:
     * - Searches for periods that overlap with requested dates
     * - Adjusts period start date if later than requested start
     * - Adjusts period end date if earlier than requested end  
     * - Returns null if no suitable period exists
     * 
     * @param DateTime $startOn Desired warrant start date
     * @param DateTime|null $endOn Desired warrant end date (null = use period end)
     * 
     * @return WarrantPeriod|null Matching/adjusted period, or null if none found
     * 
     * @example
     * ```php
     * $period = $warrantManager->getWarrantPeriod(
     *     new DateTime('2025-01-01'),
     *     new DateTime('2025-06-30')
     * );
     * if ($period) {
     *     // Use $period->start_date and $period->end_date for warrant
     * }
     * ```
     */
    public function getWarrantPeriod(DateTime $startOn, ?DateTime $endOn): ?WarrantPeriod;
}
