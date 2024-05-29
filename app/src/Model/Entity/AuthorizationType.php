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
 * @property int $authorization_groups_id
 * @property int|null $minimum_age
 * @property int|null $maximum_age
 * @property int $num_required_authorizors
 * @property \Cake\I18n\Date|null $deleted
 *
 * @property \App\Model\Entity\AuthorizationGroup $authorization_group
 * @property \App\Model\Entity\MemberAuthorizationType[] $member_authorization_types
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
        "name" => true,
        "length" => true,
        "authorization_groups_id" => true,
        "minimum_age" => true,
        "maximum_age" => true,
        "num_required_authorizors" => true,
        "deleted" => true,
        "authorization_group" => true,
        "member_authorization_types" => true,
        "pending_authorizations" => true,
        "permissions" => true,
        "grants_role_id" => true,
        "role" => true,
        "num_required_renewers" => true,
    ];
}
