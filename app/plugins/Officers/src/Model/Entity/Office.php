<?php

declare(strict_types=1);

namespace Officers\Model\Entity;

use Cake\ORM\Entity;

/**
 * Office Entity
 *
 * @property int $id
 * @property string $name
 * @property int|null $department_id
 * @property bool $requires_warrant
 * @property bool $obly_one_per_branch
 * @property int|null $deputy_to_id
 * @property int|null $grants_role_id
 * @property int $length
 * @property \Cake\I18n\Date|null $deleted
 *
 * @property \App\Model\Entity\Department $department
 * @property \App\Model\Entity\Officer[] $officers
 */
class Office extends Entity
{
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
    ];

    //update setter so that if the deputy_to_id is set it replaces the reports_to_id as well
    protected function _setDeputyToId($deputy_to_id)
    {
        $this->reports_to_id = $deputy_to_id;
        return $deputy_to_id;
    }
    //update setter so that if the reports_to_id is set but the deputy_to_id is set then the reports_to_id is not updated
    protected function _setReportsToId($reports_to_id)
    {
        if ($this->deputy_to_id === null || $this->deputy_to_id === $reports_to_id) {
            return $reports_to_id;
        }
        return $this->deputy_to_id;
    }

    protected function _getIsDeputy(): bool
    {
        return $this->deputy_to_id !== null;
    }

    protected function _getBranchTypes(): array
    {
        if (empty($this->applicable_branch_types)) {
            return [];
        }
        $returnVals = explode(",", $this->applicable_branch_types);
        //remove quotes around each branch type
        $returnVals = array_map(function ($branchType) {
            return trim($branchType, "\"");
        }, $returnVals);
        return $returnVals;
    }
    protected function _setBranchTypes($branchTypes): void
    {
        //if branch types is not an array then make it an array
        if (!is_array($branchTypes)) {
            //if branch types is an empty string then make it an empty array
            if (empty($branchTypes)) {
                $branchTypes = [];
            } else {
                $branchTypes = [$branchTypes];
            }
        }
        //add quotes around each branch type
        $branchTypes = array_map(function ($branchType) {
            return "\"$branchType\"";
        }, $branchTypes);
        $this->applicable_branch_types = implode(",", $branchTypes);
    }
}