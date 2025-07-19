<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * ActivityGroup Entity
 * 
 * Represents a logical grouping of related activities within the KMP Activities Plugin.
 * Activity groups provide organizational structure for activities, allowing administrators
 * to categorize activities by type, department, skill level, or other logical groupings.
 * 
 * This entity extends BaseEntity to inherit standard audit trail functionality and
 * branch authorization integration. Activity groups serve as containers for activities,
 * enabling hierarchical organization and simplified management of related activities.
 * 
 * ## Database Schema
 * - `id` (int): Primary key identifier
 * - `name` (string): Display name for the activity group
 * - Inherits audit fields from BaseEntity: created, modified, created_by, modified_by
 * 
 * ## Entity Relationships
 * - **hasMany Activities**: One activity group can contain multiple activities
 * - Groups provide categorical organization without restricting activity functionality
 * 
 * ## Business Logic
 * Activity groups serve as organizational containers that:
 * - Provide logical categorization for related activities
 * - Enable simplified navigation and discovery of activities
 * - Support administrative organization without affecting authorization workflows
 * - Allow for future functionality like group-level permissions or bulk operations
 * 
 * ## Security Architecture
 * Activity groups inherit security features from BaseEntity:
 * - Mass assignment protection limiting accessible fields to `name` only
 * - Audit trail integration for change tracking and accountability
 * - Branch authorization compatibility through BaseEntity inheritance
 * 
 * ## Usage Examples
 * 
 * ### Creating Activity Groups
 * ```php
 * // Create a new activity group for combat activities
 * $combatGroup = $activityGroupsTable->newEntity([
 *     'name' => 'Combat Activities'
 * ]);
 * $activityGroupsTable->save($combatGroup);
 * 
 * // Create a group for administrative activities
 * $adminGroup = $activityGroupsTable->newEntity([
 *     'name' => 'Administrative Activities'
 * ]);
 * $activityGroupsTable->save($adminGroup);
 * ```
 * 
 * ### Organizing Activities by Group
 * ```php
 * // Assign activities to groups during creation
 * $activity = $activitiesTable->newEntity([
 *     'name' => 'Heavy Weapons Combat',
 *     'activity_group_id' => $combatGroup->id,
 *     'description' => 'Authorization for heavy weapons combat activities'
 * ]);
 * 
 * // Query activities by group
 * $combatActivities = $activitiesTable->find()
 *     ->where(['activity_group_id' => $combatGroup->id])
 *     ->all();
 * ```
 * 
 * ### Administrative Management
 * ```php
 * // Get all groups with activity counts
 * $groupsWithCounts = $activityGroupsTable->find()
 *     ->contain(['Activities'])
 *     ->map(function($group) {
 *         return [
 *             'group' => $group,
 *             'activity_count' => count($group->activities)
 *         ];
 *     });
 * 
 * // Reorganize activities between groups
 * $activitiesToMove = $activitiesTable->find()
 *     ->where(['activity_group_id' => $oldGroup->id])
 *     ->all();
 * 
 * foreach ($activitiesToMove as $activity) {
 *     $activity->activity_group_id = $newGroup->id;
 *     $activitiesTable->save($activity);
 * }
 * ```
 * 
 * ## Integration Points
 * - **Activities Table**: ActivityGroup entities are referenced by Activity entities
 * - **Administrative Interface**: Used in activity management for organization
 * - **Navigation System**: May be used for hierarchical navigation structures
 * - **Reporting System**: Enables group-based activity reporting and analytics
 * 
 * ## Performance Considerations
 * - Simple entity structure minimizes database overhead
 * - Group-based queries enable efficient activity filtering
 * - Audit trail inheritance provides accountability without performance impact
 * 
 * @property int $id Primary key identifier
 * @property string $name Display name for the activity group
 * 
 * @see \Activities\Model\Table\ActivityGroupsTable Activity groups table class
 * @see \Activities\Model\Entity\Activity Activity entity that references groups
 * @see \App\Model\Entity\BaseEntity Base entity with audit trail functionality
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
