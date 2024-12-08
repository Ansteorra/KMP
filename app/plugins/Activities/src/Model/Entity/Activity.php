<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use App\KMP\PermissionsLoader;
use App\Model\Entity\Member;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;

/**
 * Activity Entity
 *
 * @property int $id
 * @property string $name
 * @property int $length
 * @property int $activity_group_id
 * @property int|null $minimum_age
 * @property int|null $maximum_age
 * @property int $num_required_authorizors
 * @property \Cake\I18n\Date|null $deleted
 *
 * @property \App\Model\Entity\ActivityGroup $activity_group
 * @property \App\Model\Entity\MemberActivity[] $member_activities
 * @property \App\Model\Entity\PendingAuthorization[] $pending_authorizations
 * @property \App\Model\Entity\Permission[] $permissions
 */
class Activity extends Entity
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
     * Get the current approvers for an activity
     *
     * @param int $activityId
     * @return SelectQuery
     */
    public function getApproversQuery()
    {

        if (!isset($this->permission_id)) {
            throw new \Exception("Permission ID not set");
        }
        return PermissionsLoader::getMembersWithPermissionsQuery($this->permission_id);
    }
}