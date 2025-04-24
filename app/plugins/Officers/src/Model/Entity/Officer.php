<?php

declare(strict_types=1);

namespace Officers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\ActiveWindowBaseEntity;

/**
 * Officer Entity
 *
 * @property int $id
 * @property int $member_id
 * @property int $branch_id
 * @property int $office_id
 * @property int|null $granted_member_role_id
 * @property \Cake\I18n\Date|null $expires_on
 * @property \Cake\I18n\Date|null $start_on
 * @property string $status
 * @property string|null $revoked_reason
 * @property int|null $revoker_id
 *
 * @property \App\Model\Entity\Member $member
 * @property \App\Model\Entity\Branch $branch
 * @property \App\Model\Entity\Office $office
 */
class Officer extends ActiveWindowBaseEntity
{

    public array $typeIdField = ['office_id', 'branch_id'];
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
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
        'member' => true,
        'branch' => true,
        'office' => true,
        'deputy_description' => true,
        'email_address' => true,
    ];

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

    public function _getReportsToList()
    {
        if ($this->reports_to_office_id == null && $this->deputy_to_office_id == null) {
            return "Society";
        }
        if ($this->reports_to_currently == null && $this->deputy_to_currently == null) {
            return "Not Filled";
        }
        $reportsTo = [];
        if (!empty($this->reports_to_currently)) {

            foreach ($this->reports_to_currently as $report) {
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
        // Remove duplicates
        $reportsTo = array_unique($reportsTo);
        if (count($reportsTo) > 0) {
            return implode(", ", $reportsTo);
        }
        return "Not Filled";
    }
}