<?php
declare(strict_types=1);

namespace Officers\Services;

use App\Services\ServiceResult;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;

/**
 * Service contract for shared officer release and recalculation operations.
 *
 * Officer hire now runs exclusively through workflow definitions. This interface
 * remains for shared release and recalculation logic reused by controllers and
 * workflow actions.
 *
 * @package Officers\Services
 * @see \Officers\Services\DefaultOfficerManager for default implementation
 * @see /docs/5.1.1-officers-services.md for detailed documentation
 */
interface OfficerManagerInterface
{
    /**
     * Release a member from an office position.
     *
     * Handles ActiveWindow termination, warrant cancellation for warrant-required
     * offices, and release notifications.
     *
     * @param int $officerId Officer record identifier
     * @param int $revokerId Administrator identifier for audit trail
     * @param \Cake\I18n\DateTime $revokedOn Effective release date
     * @param string|null $revokedReason Optional reason for release
     * @param string $releaseStatus Status to apply when ending the assignment
     * @return \App\Services\ServiceResult Success or failure with reason
     */
    public function release(
        int $officerId,
        int $revokerId,
        DateTime $revokedOn,
        ?string $revokedReason,
        string $releaseStatus = Officer::RELEASED_STATUS,
    ): ServiceResult;

    /**
     * Recalculate reporting relationships and roles for all officers of an office.
     *
     * Call when office deputy_to_id, reports_to_id, or grants_role_id changes.
     * Processes all current and upcoming officers with fail-fast error handling.
     *
     * @param int $officeId Office identifier for recalculation
     * @param int $updaterId Administrator identifier for audit trail
     * @return \App\Services\ServiceResult Success with counts (updated_count, current_count, upcoming_count),
     *                       or failure with specific member/office/branch identification
     */
    public function recalculateOfficersForOffice(
        int $officeId,
        int $updaterId,
    ): ServiceResult;
}
