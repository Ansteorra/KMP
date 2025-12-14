<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use App\KMP\PermissionsLoader;
use App\Model\Entity\Member;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use App\Model\Entity\BaseEntity;

/**
 * Activity Entity - Authorization Type Definition
 *
 * Represents an activity authorization type within KMP requiring member authorization for participation.
 * Activities define approval workflows, age restrictions, and permission requirements.
 *
 * Comprehensive documentation available in `/docs/5.6.4-activity-entity-reference.md`.
 *
 * @property int $id Primary key
 * @property string $name Unique activity identifier
 * @property int $term_length Authorization validity in days
 * @property int $activity_group_id Foreign key to ActivityGroup
 * @property int|null $minimum_age Minimum age requirement
 * @property int|null $maximum_age Maximum age requirement
 * @property int $num_required_authorizors Required approvers for new requests
 * @property int $num_required_renewers Required approvers for renewals
 * @property int|null $permission_id RBAC permission for approver discovery
 * @property int|null $grants_role_id Role auto-granted on authorization approval
 * @property \Cake\I18n\DateTime|null $deleted Soft deletion timestamp
 *
 * @see \Activities\Model\Entity\ActivityGroup Parent categorization entity
 * @see \Activities\Model\Entity\Authorization Child authorization records
 * @see \App\Model\Entity\Permission RBAC permission integration
 * @see \App\Model\Entity\Role Role granting functionality
 *
 * @package Activities\Model\Entity
 * @since KMP 1.0
 */
class Activity extends BaseEntity
{
    /**
     * Mass Assignment Security Configuration
     *
     * Defines which fields can be safely mass assigned via newEntity() or patchEntity().
     * Accessible fields allow administrator configuration while protecting timestamps and identifiers.
     *
     * Protected fields: id, created, created_by, modified, modified_by
     * See `/docs/5.6.5-activity-security-patterns.md` for comprehensive security documentation.
     *
     * @var array<string, bool> Field accessibility configuration
     * @since KMP 1.0
     */
    protected array $_accessible = [
        "name" => true,
        "term_length" => true,
        "activity_group_id" => true,
        "minimum_age" => true,
        "maximum_age" => true,
        "num_required_authorizors" => true,
        "deleted" => true,
        "activity_group" => true,
        "member_activities" => true,
        "pending_authorizations" => true,
        "permission_id" => true,
        "grants_role_id" => true,
        "role" => true,
        "num_required_renewers" => true,
    ];

    /**
     * Get activity_group_name virtual property
     *
     * Returns the associated activity group name for grid display.
     *
     * @return string|null
     */
    protected function _getActivityGroupName(): ?string
    {
        if (isset($this->activity_group) && $this->activity_group) {
            return $this->activity_group->name;
        }

        return null;
    }

    /**
     * Get grants_role_name virtual property
     *
     * Returns the granted role name or "None" if no role is assigned.
     *
     * @return string
     */
    protected function _getGrantsRoleName(): string
    {
        if (isset($this->role) && $this->role) {
            return $this->role->name;
        }

        return 'None';
    }

    /**
     * Get Approvers Query for Activity Authorization
     *
     * Returns a query of members who have permission to authorize this activity within
     * the specified organizational branch. Uses PermissionsLoader for optimized permission-based
     * member discovery with branch scoping.
     *
     * Comprehensive documentation: See `/docs/5.6.5-activity-security-patterns.md` for
     * approvers discovery patterns, error handling, and usage examples.
     *
     * @param int $branch_id Branch scope for approver identification
     * @return \Cake\ORM\Query\SelectQuery Query object for eligible approvers
     * @throws \Exception When permission_id is not configured for activity
     *
     * @see \App\KMP\PermissionsLoader::getMembersWithPermissionsQuery() Core permission query
     */
    public function getApproversQuery(Int $branch_id)
    {
        // Validate that activity has permission configuration
        if (!isset($this->permission_id)) {
            throw new \Exception("Permission ID not set");
        }

        // Use PermissionsLoader for optimized permission-based member query
        return PermissionsLoader::getMembersWithPermissionsQuery($this->permission_id, $branch_id);
    }
}