<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * AuthorizationType Entity
 *
 * @property int $id
 * @property string $name
 * @property int $length
 * @property int $martial_groups_id
 * @property int|null $minimum_age
 * @property int|null $maximum_age
 * @property int $num_required_authorizors
 *
 * @property \App\Model\Entity\MartialGroup $martial_group
 * @property \App\Model\Entity\MemberAuthorizationType[] $Member_authorization_types
 * @property \App\Model\Entity\PendingAuthorization[] $pending_authorizations
 * @property \App\Model\Entity\Permission[] $permissions
 */
class AuthorizationType extends Entity
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
        'length' => true,
        'martial_groups_id' => true,
        'minimum_age' => true,
        'maximum_age' => true,
        'num_required_authorizors' => true,
        'martial_group' => true,
        'Member_authorization_types' => true,
        'pending_authorizations' => true,
        'permissions' => true,
    ];
}
