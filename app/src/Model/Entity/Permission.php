<?php

declare(strict_types=1);

namespace App\Model\Entity;

use InvalidArgumentException;

/**
 * Permission Entity - KMP RBAC Permission System
 *
 * Atomic unit of authorization defining what actions can be performed.
 * Supports scoping (Global, Branch Only, Branch and Children) and requirements
 * (membership, background check, min age, warrant).
 *
 * @property int $id Primary key
 * @property string $name Permission name
 * @property string $scoping_rule SCOPE_GLOBAL, SCOPE_BRANCH_ONLY, or SCOPE_BRANCH_AND_CHILDREN
 * @property bool $is_system System permission flag
 * @property bool $is_super_user Super user permission flag
 * @property bool $require_active_membership Membership requirement
 * @property bool $require_active_background_check Background check requirement
 * @property int $require_min_age Minimum age requirement
 * @property bool $requires_warrant Warrant requirement
 * @property \App\Model\Entity\Role[] $roles Roles with this permission
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

    protected function _setScopingRule($value)
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
