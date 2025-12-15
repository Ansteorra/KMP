<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * ActivityGroup Entity
 *
 * Represents a logical grouping of related activities, providing categorical organization
 * for activities within the Activities plugin. Extends BaseEntity to inherit audit trail
 * functionality and branch authorization integration.
 *
 * **Key Responsibilities:**
 * - Provide logical categorization for related activities
 * - Support administrative organization of activities
 * - Enable activity discovery through group-based navigation
 * - Maintain audit trail through inherited BaseEntity behaviors
 *
 * **Database Fields:**
 * - `id`: Primary key identifier
 * - `name`: Unique display name for the group
 * - Inherits audit fields: created, modified, created_by, modified_by
 *
 * **Relationships:**
 * - hasMany Activities: One group contains multiple activities
 *
 * **Mass Assignment:** Only `name` field is accessible via newEntity()/patchEntity()
 *
 * For detailed documentation including usage examples, relationships, validation rules,
 * and integration patterns, see `/docs/5.6.6-activity-groups-entity-reference.md`.
 *
 * @property int $id Primary key identifier
 * @property string $name Display name for the activity group
 *
 * @see \Activities\Model\Table\ActivityGroupsTable ActivityGroup data management
 * @see \Activities\Model\Entity\Activity Activities in this group
 * @see \App\Model\Entity\BaseEntity Audit trail and branch scoping
 */
class ActivityGroup extends BaseEntity
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
    ];
}