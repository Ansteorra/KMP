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
 * Activities Model
 *
 * @property \App\Model\Table\ActivityGroupsTable&\Cake\ORM\Association\BelongsTo $ActivityGroups
 * @property \App\Model\Table\PermissionsTable&\Cake\ORM\Association\HasMany $Permissions
 *
 * @method \App\Model\Entity\Activity newEmptyEntity()
 * @method \App\Model\Entity\Activity newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Activity> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Activity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Activity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Activity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Activity> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Activity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Activity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Activity>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Activity>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Activity>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Activity> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Activity>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Activity>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Activity>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Activity> deleteManyOrFail(iterable $entities, array $options = [])
 */
class ActivitiesTable extends BaseTable
{
    /**
     * Initialize method
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
     * Returns a rules checker object that will be used for validating
     * application integrity.
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
     * Shortcut query to see if the user can authorize a specific activity
     */
    public static function canAuthorizeActivity($user, int $activityId): bool
    {
        $permission = $user->getPermissionIDs();
        $activitiesTable = TableRegistry::getTableLocator()->get("Activities.Activities");
        $activity = $activitiesTable->find()->select("id")->where(["id" => $activityId, "permission_id IN" => $permission])->first();
        return $activity !== null;
    }

    /**
     * Shortcut query to see if the user can authorize anything and there for may have an Auth Queue
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
