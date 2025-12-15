<?php

declare(strict_types=1);

namespace Officers\Services;

use Cake\I18n\DateTime;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;

/**
 * Service contract for officer lifecycle management operations.
 * 
 * Defines methods for officer assignment, release, and recalculation with
 * integrated warrant management, role assignment, and notification processing.
 * 
 * @package Officers\Services
 * @see \Officers\Services\DefaultOfficerManager for default implementation
 * @see /docs/5.1.1-officers-services.md for detailed documentation
 */
interface OfficerManagerInterface
{
    /**
     * Assign a member to an office position.
     * 
     * Handles office validation, member verification, warrant integration,
     * ActiveWindow temporal management, and hire notifications.
     *
     * @param int $officeId Office identifier for assignment
     * @param int $memberId Member identifier being assigned
     * @param int $branchId Branch identifier for organizational context
     * @param DateTime $startOn Assignment start date
     * @param DateTime|null $endOn Optional end date; derived from office term_length if null
     * @param string|null $deputyDescription Optional description for deputy positions
     * @param int $approverId Approver identifier for audit trail
     * @param string|null $emailAddress Optional email for officer record
     * @return ServiceResult Success with assignment data, or failure with reason
     */
    public function assign(
        int $officeId,
        int $memberId,
        int $branchId,
        DateTime $startOn,
        ?DateTime $endOn,
        ?string $deputyDescription,
        int $approverId,
        ?string $emailAddress
    ): ServiceResult;

    /**
     * Release a member from an office position.
     * 
     * Handles ActiveWindow termination, warrant cancellation for warrant-required
     * offices, and release notifications.
     *
     * @param int $officerId Officer record identifier
     * @param int $revokerId Administrator identifier for audit trail
     * @param DateTime $revokedOn Effective release date
     * @param string|null $revokedReason Optional reason for release
     * @return ServiceResult Success or failure with reason
     */
    public function release(
        int $officerId,
        int $revokerId,
        DateTime $revokedOn,
        ?string $revokedReason
    ): ServiceResult;

    /**
     * Recalculate reporting relationships and roles for all officers of an office.
     * 
     * Call when office deputy_to_id, reports_to_id, or grants_role_id changes.
     * Processes all current and upcoming officers with fail-fast error handling.
     *
     * @param int $officeId Office identifier for recalculation
     * @param int $updaterId Administrator identifier for audit trail
     * @return ServiceResult Success with counts (updated_count, current_count, upcoming_count),
     *                       or failure with specific member/office/branch identification
     */
    public function recalculateOfficersForOffice(
        int $officeId,
        int $updaterId
    ): ServiceResult;
}
