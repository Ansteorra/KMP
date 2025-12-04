<?php

declare(strict_types=1);

namespace Officers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\ActiveWindowBaseEntity;

/**
 * Officer Entity - Officer assignments with temporal management
 *
 * Represents individual officer assignments extending ActiveWindowBaseEntity for
 * automatic temporal status transitions, warrant integration, and hierarchical reporting.
 *
 * @property int $id Primary key
 * @property int $member_id Foreign key to assigned member
 * @property int $branch_id Foreign key to branch
 * @property int $office_id Foreign key to office
 * @property int|null $granted_member_role_id Foreign key to granted role
 * @property \Cake\I18n\Date|null $expires_on Assignment expiration date
 * @property \Cake\I18n\Date|null $start_on Assignment start date
 * @property string $status Assignment status (new, current, upcoming, expired, revoked)
 * @property string|null $deputy_description Custom description for deputy assignments
 * @property string|null $revoked_reason Reason for revocation
 * @property int|null $revoker_id User who revoked
 * @property int|null $deputy_to_branch_id Foreign key for cross-branch deputy
 * @property int|null $deputy_to_office_id Foreign key for deputy office
 * @property string $email_address Officer contact email
 * @property \Cake\I18n\DateTime $created Record creation timestamp
 * @property \Cake\I18n\DateTime $modified Last modification timestamp
 *
 * @property string $warrant_state Virtual: Active, Pending, Missing, Not Required
 * @property bool $is_editable Virtual: whether assignment can be edited
 * @property string $reports_to_list Virtual: formatted reporting hierarchy
 * @property array $effective_reports_to_currently Virtual: skip-aware hierarchy traversal
 *
 * @property \App\Model\Entity\Member $member
 * @property \App\Model\Entity\Branch $branch
 * @property \Officers\Model\Entity\Office $office
 * @property \App\Model\Entity\Role $granted_member_role
 * @property \App\Model\Entity\Warrant $current_warrant
 * @property \App\Model\Entity\Warrant[] $pending_warrants
 * @property \Officers\Model\Entity\Officer[] $reports_to_currently
 * @property \Officers\Model\Entity\Officer[] $deputy_to_currently
 *
 * @see /docs/5.1-officers-plugin.md
 * @see \Officers\Model\Table\OfficersTable
 */
class Officer extends ActiveWindowBaseEntity
{
    /**
     * Type identification fields for ActiveWindow behavior.
     * Composite key enables temporal management per office per branch.
     *
     * @var array
     */
    public array $typeIdField = ['office_id', 'branch_id'];

    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'member_id' => true,
        'branch_id' => true,
        'office_id' => true,
        'granted_member_role_id' => true,
        'expires_on' => true,
        'start_on' => true,
        'status' => true,
        'revoked_reason' => true,
        'revoker_id' => true,
        'approver_id' => true,
        'approval_date' => true,
        'reports_to_office_id' => true,
        'reports_to_branch_id' => true,
        'deputy_to_office_id' => true,
        'deputy_to_branch_id' => true,
        'member' => true,
        'branch' => true,
        'office' => true,
        'deputy_description' => true,
        'email_address' => true,
    ];

    /**
     * Calculate warrant state based on office requirements and active warrants.
     *
     * @param mixed $value Unused
     * @return string One of: Active, Pending, Missing, Not Required, Can Not Calculate
     */
    protected function _getWarrantState($value)
    {
        if ($this->office == null) {
            return "Can Not Calculate";
        }
        if ($this->current_warrant != null && $this->current_warrant->expires_on != null) {
            return "Active";
        }
        if ($this->pending_warrants != null && count($this->pending_warrants) > 0) {
            return "Pending";
        }
        if ($this->office->requires_warrant == true) {
            return "Missing";
        }
        return "Not Required";
    }

    /**
     * Determine if assignment can be edited (deputy positions or those with email).
     *
     * @return bool
     */
    protected function _getIsEditable()
    {
        if ($this->office->is_deputy == true) {
            return true;
        }
        if ($this->email_address !== null && $this->email_address !== "") {
            return true;
        }
        return false;
    }

    /**
     * Get formatted string of officers this position reports to with mailto links.
     *
     * Uses skip-aware hierarchy traversal when intermediate offices are vacant.
     *
     * @return string "Society", "Not Filled", or comma-separated list with links
     */
    public function _getReportsToList()
    {
        if ($this->reports_to_office_id == null && $this->deputy_to_office_id == null) {
            return "Society";
        }

        $effectiveReports = $this->effective_reports_to_currently;

        if (empty($effectiveReports) && empty($this->deputy_to_currently)) {
            return "Not Filled";
        }

        $reportsTo = [];

        if (!empty($effectiveReports)) {
            foreach ($effectiveReports as $report) {
                if ($report->email_address !== null && $report->email_address !== "") {
                    $reportsTo[] = "<a href='mailto:{$report->email_address}'>{$report->member->sca_name}</a>";
                } else {
                    $reportsTo[] = $report->member->sca_name;
                }
            }
        }

        if (!empty($this->deputy_to_currently)) {
            foreach ($this->deputy_to_currently as $report) {
                if ($report->email_address !== null && $report->email_address !== "") {
                    $reportsTo[] = "<a href='mailto:{$report->email_address}'>{$report->member->sca_name}</a>";
                } else {
                    $reportsTo[] = $report->member->sca_name;
                }
            }
        }

        $reportsTo = array_unique($reportsTo);
        if (count($reportsTo) > 0) {
            return implode(", ", $reportsTo);
        }
        return "Not Filled";
    }

    /**
     * Resolve effective officers this assignment reports to using skip-aware hierarchy.
     *
     * Traverses reporting chain honoring can_skip_report flag when offices are vacant.
     *
     * @return array<\Officers\Model\Entity\Officer>
     */
    protected function _getEffectiveReportsToCurrently(): array
    {
        if (empty($this->reports_to_office_id)) {
            return [];
        }

        $officersTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Officers.Officers');
        return $officersTable->findEffectiveReportsTo($this);
    }
}
