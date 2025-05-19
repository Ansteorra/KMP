<?php
declare(strict_types=1);

namespace App\Model\Entity;

use InvalidArgumentException;

/**
 * Permission Entity
 *
 * @property int $id
 * @property string $name
 * @property int|null $activity_id
 * @property bool $require_active_membership
 * @property bool $require_active_background_check
 * @property bool $require_min_age
 * @property bool $is_system
 * @property bool $is_super_user
 *
 * @property \App\Model\Entity\Activity $activity
 * @property \App\Model\Entity\Role[] $roles
 */
class Permission extends BaseEntity
{
    public const SCOPE_GLOBAL = 'Global'; //No Scope limitations
    public const SCOPE_BRANCH_ONLY = 'Branch Only';
    public const SCOPE_BRANCH_AND_CHILDREN = 'Branch and Children'; //Can Login

    //scoping rules as an array for dropdowns
    public const SCOPING_RULES = [
        self::SCOPE_GLOBAL => self::SCOPE_GLOBAL,
        self::SCOPE_BRANCH_ONLY => self::SCOPE_BRANCH_ONLY,
        self::SCOPE_BRANCH_AND_CHILDREN => self::SCOPE_BRANCH_AND_CHILDREN,
    ];

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
        'require_active_membership' => true,
        'require_active_background_check' => true,
        'require_min_age' => true,
        'is_system' => true,
        'is_super_user' => true,
        'requires_warrant' => true,
        'scoping_rule' => true,
        'roles' => true,
    ];

    protected function _setScopeing_rule($value)
    {
        //the status must be one of the constants defined in this class
        switch ($value) {
            case self::SCOPE_GLOBAL:
            case self::SCOPE_BRANCH_ONLY:
            case self::SCOPE_BRANCH_AND_CHILDREN:
                return $value;
            default:
                throw new InvalidArgumentException('Invalid Scoping Rule');
        }
    }
}
