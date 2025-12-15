<?php

declare(strict_types=1);

namespace Activities\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Activities\Model\Entity\Authorization;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;
use App\Model\Table\BaseTable;

/**
 * Manages Authorization entities for member activity participation workflows.
 * 
 * Provides data access, temporal lifecycle management, and status validation for
 * authorization requests. Implements ActiveWindow behavior for automatic expiration
 * processing and temporal queries.
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\MemberRolesTable&\Cake\ORM\Association\BelongsTo $MemberRoles
 * @property \Activities\Model\Table\ActivitiesTable&\Cake\ORM\Association\BelongsTo $Activities
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $RevokedBy
 * @property \Activities\Model\Table\AuthorizationApprovalsTable&\Cake\ORM\Association\HasMany $AuthorizationApprovals
 * @property \Activities\Model\Table\AuthorizationApprovalsTable&\Cake\ORM\Association\HasOne $CurrentPendingApprovals
 * 
 * @method \Activities\Model\Entity\Authorization newEmptyEntity()
 * @method \Activities\Model\Entity\Authorization newEntity(array $data, array $options = [])
 * @method \Activities\Model\Entity\Authorization get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Activities\Model\Entity\Authorization|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * 
 * @see \Activities\Model\Entity\Authorization Authorization entity class
 * @see /docs/5.6.7-authorization-entity-reference.md For comprehensive documentation
 */
class AuthorizationsTable extends BaseTable
{
    /**
     * Initialize method - configures associations, behaviors, and automated status management.
     * 
     * Runs daily status check to transition expired authorizations to EXPIRED status.
     *
     * @param array<string, mixed> $config The configuration for the Table
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable("activities_authorizations");
        $this->setDisplayField("id");
        $this->setPrimaryKey("id");

        $this->belongsTo("Members", [
            "foreignKey" => "member_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("MemberRoles", [
            "foreignKey" => "granted_member_role_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("Activities", [
            "className" => "Activities.Activities",
            "foreignKey" => "activity_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("RevokedBy", [
            "className" => "Members",
            "foreignKey" => "revoker_id",
            "joinType" => "LEFT",
            "propertyName" => "revoked_by",
        ]);

        $this->hasMany("AuthorizationApprovals", [
            "className" => "Activities.AuthorizationApprovals",
            "foreignKey" => "authorization_id",
        ]);
        $this->hasOne("CurrentPendingApprovals", [
            "className" => "Activities.AuthorizationApprovals",
            "conditions" => ["CurrentPendingApprovals.responded_on IS" => null],
            "foreignKey" => "authorization_id",
        ]);
        $this->addBehavior("ActiveWindow");

        $lastExpCheck = new DateTime(StaticHelpers::getAppSetting("Activities.NextStatusCheck", DateTime::now()->subDays(1)->toDateString()), null, true);
        if ($lastExpCheck->isPast()) {
            $this->checkStatus();
            StaticHelpers::setAppSetting("Activities.NextStatusCheck", DateTime::now()->addDays(1)->toDateString(), null, true);
        }
    }

    /**
     * Batch update expired authorizations to EXPIRED status.
     * 
     * Called automatically during table initialization when NextStatusCheck is past.
     * Transitions APPROVED and PENDING authorizations past their expires_on date.
     *
     * @return void
     */
    protected function checkStatus(): void
    {
        $this->updateAll(
            ["status" => Authorization::EXPIRED_STATUS],
            ["expires_on <=" => DateTime::now(), 'status IN' => [Authorization::APPROVED_STATUS, Authorization::PENDING_STATUS]]
        );
    }

    /**
     * Default validation rules for authorization data integrity.
     *
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return \Cake\Validation\Validator Configured validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator->integer("member_id")->notEmptyString("member_id");

        $validator
            ->integer("activity_id")
            ->notEmptyString("activity_id");

        $validator
            ->date("expires_on")
            ->requirePresence("expires_on", "create")
            ->notEmptyDate("expires_on");

        $validator->date("start_on")->allowEmptyDate("start_on");

        return $validator;
    }

    /**
     * Build rules ensuring referential integrity for member and activity associations.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules checker
     * @return \Cake\ORM\RulesChecker Configured rules
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(["member_id"], "Members"), [
            "errorField" => "member_id",
        ]);
        $rules->add(
            $rules->existsIn(["activity_id"], "Activities"),
            ["errorField" => "activity_id"],
        );

        return $rules;
    }


    /**
     * Custom finder for pending authorization requests.
     * 
     * Filters to authorizations with PENDING status for approval workflow processing.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The base query
     * @return \Cake\ORM\Query\SelectQuery Modified query filtered for pending status
     */
    public function findPending(SelectQuery $query): SelectQuery
    {
        $query = $query->where([$this->getAlias() . '.status' => Authorization::PENDING_STATUS]);
        return $query;
    }
}
