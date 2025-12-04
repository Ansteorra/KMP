<?php

declare(strict_types=1);

namespace Officers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Office Entity - Hierarchical office positions within departments
 *
 * Represents individual officer positions with hierarchical relationships, warrant
 * requirements, role assignments, and branch applicability constraints.
 *
 * @property int $id Primary key
 * @property string $name Office title (unique, required)
 * @property int|null $department_id Foreign key to parent department
 * @property bool $requires_warrant Warrant requirement flag
 * @property bool $required_office Organizational requirement flag
 * @property bool $only_one_per_branch Branch-level uniqueness constraint
 * @property bool $can_skip_report Reporting exemption permission
 * @property int|null $deputy_to_id Foreign key for deputy office relationships
 * @property int|null $reports_to_id Foreign key for reporting hierarchy
 * @property int|null $grants_role_id Foreign key to role granted upon assignment
 * @property int $term_length Duration in months for officer terms
 * @property string|null $applicable_branch_types Serialized array of applicable branch types
 * @property string $default_contact_address Default email contact for the office
 * @property \Cake\I18n\Date|null $deleted Soft deletion timestamp
 * @property \Cake\I18n\DateTime $created Record creation timestamp
 * @property \Cake\I18n\DateTime $modified Last modification timestamp
 * @property int|null $created_by User ID who created this record
 * @property int|null $modified_by User ID who last modified this record
 *
 * @property bool $is_deputy Virtual property indicating deputy office status
 * @property array $branch_types Virtual property for branch type array access
 * @property string|null $department_name Virtual property for grid display
 * @property string|null $reports_to_name Virtual property for grid display
 * @property string|null $deputy_to_name Virtual property for grid display
 * @property string|null $grants_role_name Virtual property for grid display
 *
 * @property \Officers\Model\Entity\Department $department Parent department
 * @property \App\Model\Entity\Role $grants_role Role granted upon assignment
 * @property \Officers\Model\Entity\Office $deputy_to Parent office for deputy relationships
 * @property \Officers\Model\Entity\Office $reports_to Parent office for reporting hierarchy
 * @property \Officers\Model\Entity\Office[] $deputies Child deputy offices
 * @property \Officers\Model\Entity\Office[] $direct_reports Child offices in reporting hierarchy
 * @property \Officers\Model\Entity\Officer[] $officers All officer assignments
 *
 * @see /docs/5.1-officers-plugin.md
 * @see \Officers\Model\Table\OfficesTable
 */
class Office extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'department_id' => true,
        'requires_warrant' => true,
        'can_skip_report' => true,
        'required_office' => true,
        'only_one_per_branch' => true,
        'deputy_to_id' => true,
        'reports_to_id' => true,
        'grants_role_id' => true,
        'term_length' => true,
        'deleted' => true,
        'department' => true,
        'officers' => true,
        'branch_types' => true,
        'default_contact_address' => true,
    ];

    /**
     * Set deputy_to_id and automatically set reports_to_id to maintain hierarchy.
     *
     * @param int|null $deputy_to_id The office ID this office is deputy to
     * @return int|null
     */
    protected function _setDeputyToId($deputy_to_id)
    {
        $this->reports_to_id = $deputy_to_id;
        return $deputy_to_id;
    }

    /**
     * Set reports_to_id and clear deputy_to_id to prevent conflicts.
     *
     * @param int|null $reports_to_id The office ID this office reports to
     * @return int|null
     */
    protected function _setReportsToId($reports_to_id)
    {
        $this->deputy_to_id = null;
        return $reports_to_id;
    }

    /**
     * Check if this office is a deputy to another office.
     *
     * @return bool
     */
    protected function _getIsDeputy(): bool
    {
        return $this->deputy_to_id !== null;
    }

    /**
     * Get branch types as an array from serialized storage.
     *
     * @return array
     */
    protected function _getBranchTypes(): array
    {
        if (empty($this->applicable_branch_types)) {
            return [];
        }
        $returnVals = explode(",", $this->applicable_branch_types);
        $returnVals = array_map(function ($branchType) {
            return ltrim(rtrim($branchType, "\""), "\"");
        }, $returnVals);
        return $returnVals;
    }

    /**
     * @return string|null
     */
    protected function _getDepartmentName(): ?string
    {
        return $this->department->name ?? null;
    }

    /**
     * @return string|null
     */
    protected function _getReportsToName(): ?string
    {
        return $this->reports_to->name ?? null;
    }

    /**
     * @return string|null
     */
    protected function _getDeputyToName(): ?string
    {
        return $this->deputy_to->name ?? null;
    }

    /**
     * @return string|null
     */
    protected function _getGrantsRoleName(): ?string
    {
        return $this->grants_role->name ?? null;
    }

    /**
     * Set branch types from array or string, serialized for storage.
     *
     * @param array|string $branchTypes Branch types to serialize
     * @return void
     */
    protected function _setBranchTypes($branchTypes): void
    {
        if (!is_array($branchTypes)) {
            if (empty($branchTypes)) {
                $branchTypes = [];
            } else {
                $branchTypes = [$branchTypes];
            }
        }
        $branchTypes = array_map(function ($branchType) {
            return "\"$branchType\"";
        }, $branchTypes);
        $this->applicable_branch_types = implode(",", $branchTypes);
    }
}
