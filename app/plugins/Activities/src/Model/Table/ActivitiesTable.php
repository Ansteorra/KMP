<?php

declare(strict_types=1);

namespace Activities\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\TableRegistry;
use App\Model\Table\BaseTable;

/**
 * Manages Activity entities for the Activities Plugin.
 * 
 * Provides data access, validation, and authorization-related queries for activities.
 * Activities represent authorization types requiring member approval before participation.
 *
 * @property \Activities\Model\Table\ActivityGroupsTable&\Cake\ORM\Association\BelongsTo $ActivityGroups
 * @property \App\Model\Table\PermissionsTable&\Cake\ORM\Association\BelongsTo $Permissions
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsTo $Roles
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\HasMany $Authorizations
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\HasMany $CurrentAuthorizations
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\HasMany $PendingAuthorizations
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\HasMany $UpcomingAuthorizations
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\HasMany $PreviousAuthorizations
 * 
 * @method \Activities\Model\Entity\Activity newEmptyEntity()
 * @method \Activities\Model\Entity\Activity newEntity(array $data, array $options = [])
 * @method \Activities\Model\Entity\Activity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Activities\Model\Entity\Activity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * 
 * @see \Activities\Model\Entity\Activity Activity entity class
 * @see /docs/5.6.4-activity-entity-reference.md For comprehensive documentation
 */
class ActivitiesTable extends BaseTable
{
    /**
     * Initialize method - configures table associations and behaviors.
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable("activities_activities");
        $this->setDisplayField("name");
        $this->setPrimaryKey("id");

        $this->belongsTo("ActivityGroups", [
            "className" => "Activities.ActivityGroups",
            "foreignKey" => "activity_group_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("Roles", [
            "foreignKey" => "grants_role_id",
            "joinType" => "LEFT",
        ]);
        $this->hasMany("Authorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "activity_id",
        ]);
        $this->hasMany("CurrentAuthorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "activity_id",
            "finder" => "current",
        ]);
        $this->hasMany("PendingAuthorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "activity_id",
            "finder" => "pending",
        ]);
        $this->hasMany("UpcomingAuthorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "activity_id",
            "finder" => "upcoming",
        ]);
        $this->hasMany("PreviousAuthorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "activity_id",
            "finder" => "previous",
        ]);
        $this->belongsTo("Permissions", [
            "foreignKey" => "permission_id",
        ]);
        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar("name")
            ->maxLength("name", 255)
            ->requirePresence("name", "create")
            ->notEmptyString("name")
            ->add("name", "unique", [
                "rule" => "validateUnique",
                "provider" => "table",
            ]);

        $validator
            ->integer("term_length")
            ->requirePresence("term_length", "create")
            ->notEmptyString("term_length");

        $validator
            ->integer("activity_group_id")
            ->notEmptyString("activity_group_id");

        $validator->integer("minimum_age")->allowEmptyString("minimum_age");

        $validator->integer("maximum_age")->allowEmptyString("maximum_age");

        $validator
            ->integer("num_required_authorizors")
            ->notEmptyString("num_required_authorizors");

        $validator->date("deleted")->allowEmptyDate("deleted");

        return $validator;
    }

    /**
     * Returns a rules checker object for validating application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(["name"]), ["errorField" => "name"]);
        $rules->add(
            $rules->existsIn(
                ["activity_group_id"],
                "ActivityGroups",
            ),
            ["errorField" => "activity_group_id"],
        );

        return $rules;
    }

    /**
     * Check if user can authorize a specific activity.
     * 
     * Validates user has the required permission to authorize the specified activity.
     *
     * @param \App\Model\Entity\Member $user The user to check
     * @param int $activityId The activity ID to check
     * @return bool True if user can authorize the activity
     * @see /docs/5.6.4-activity-entity-reference.md For usage examples
     */
    public static function canAuthorizeActivity($user, int $activityId): bool
    {
        $permission = $user->getPermissionIDs();
        $activitiesTable = TableRegistry::getTableLocator()->get("Activities.Activities");
        $activity = $activitiesTable->find()->select("id")->where(["id" => $activityId, "permission_id IN" => $permission])->first();
        return $activity !== null;
    }

    /**
     * Check if user can authorize any activity (has authorization queue access).
     * 
     * Determines if user has permissions for any activities, enabling authorization
     * queue display and workflow navigation.
     *
     * @param \App\Model\Entity\Member $user The user to check
     * @return bool True if user can authorize any activities
     * @see /docs/5.6.4-activity-entity-reference.md For usage examples
     */
    public static function canAuhtorizeAnyActivity($user): bool
    {
        $permission = $user->getPermissionIDs();
        if (empty($permission)) {
            return false;
        }
        $activitiesTable = TableRegistry::getTableLocator()->get("Activities.Activities");
        $activityCount = $activitiesTable->find()->select("id")->where(["permission_id IN" => $permission])->count();
        return $activityCount > 0;
    }
}
