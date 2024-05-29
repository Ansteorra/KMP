<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Authorizations Model
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\AuthorizationTypesTable&\Cake\ORM\Association\BelongsTo $AuthorizationTypes
 * @property \App\Model\Table\AuthorizationApprovalsTable&\Cake\ORM\Association\HasMany $AuthorizationApprovals
 *
 * @method \App\Model\Entity\Authorization newEmptyEntity()
 * @method \App\Model\Entity\Authorization newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Authorization> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Authorization get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Authorization findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Authorization patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Authorization> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Authorization|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Authorization saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Authorization>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Authorization>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Authorization>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Authorization> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Authorization>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Authorization>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Authorization>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Authorization> deleteManyOrFail(iterable $entities, array $options = [])
 */
class AuthorizationsTable extends Table
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

        $this->setTable("authorizations");
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
        $this->belongsTo("AuthorizationTypes", [
            "foreignKey" => "authorization_type_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("Revokers", [
            "className" => "Members",
            "foreignKey" => "revoker_id",
            "joinType" => "LEFT",
        ]);
        $this->hasMany("AuthorizationApprovals", [
            "foreignKey" => "authorization_id",
        ]);
        $this->hasMany("AuthorizationApprovals", [
            "foreignKey" => "authorization_id",
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator->integer("member_id")->notEmptyString("member_id");

        $validator
            ->integer("authorization_type_id")
            ->notEmptyString("authorization_type_id");

        $validator
            ->date("expires_on")
            ->requirePresence("expires_on", "create")
            ->notEmptyDate("expires_on");

        $validator->date("start_on")->allowEmptyDate("start_on");

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
        $rules->add($rules->existsIn(["member_id"], "Members"), [
            "errorField" => "member_id",
        ]);
        $rules->add(
            $rules->existsIn(["authorization_type_id"], "AuthorizationTypes"),
            ["errorField" => "authorization_type_id"],
        );

        return $rules;
    }
}
