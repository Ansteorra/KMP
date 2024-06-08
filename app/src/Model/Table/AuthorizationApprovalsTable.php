<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AuthorizationApprovals Model
 *
 * @property \App\Model\Table\AuthorizationsTable&\Cake\ORM\Association\BelongsTo $Authorizations
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 *
 * @method \App\Model\Entity\AuthorizationApproval newEmptyEntity()
 * @method \App\Model\Entity\AuthorizationApproval newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\AuthorizationApproval> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\AuthorizationApproval get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\AuthorizationApproval findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\AuthorizationApproval patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\AuthorizationApproval> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\AuthorizationApproval|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\AuthorizationApproval saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationApproval>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationApproval>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationApproval>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationApproval> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationApproval>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationApproval>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationApproval>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationApproval> deleteManyOrFail(iterable $entities, array $options = [])
 */
class AuthorizationApprovalsTable extends Table
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

        $this->setTable("authorization_approvals");
        $this->setDisplayField("id");
        $this->setPrimaryKey("id");

        $this->belongsTo("Authorizations", [
            "foreignKey" => "authorization_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("Approvers", [
            "className" => "Members",
            "foreignKey" => "approver_id",
            "joinType" => "LEFT",
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
        $validator
            ->integer("authorization_id")
            ->notEmptyString("authorization_id");

        $validator->integer("approver_id")->notEmptyString("approver_id");

        $validator
            ->scalar("authorization_token")
            ->maxLength("authorization_token", 255)
            ->requirePresence("authorization_token", "create")
            ->notEmptyString("authorization_token");

        $validator
            ->date("requested_on")
            ->requirePresence("requested_on", "create")
            ->notEmptyDate("requested_on");

        $validator->date("responded_on")->allowEmptyDate("responded_on");

        $validator->boolean("approved")->notEmptyString("approved");

        $validator
            ->scalar("approver_notes")
            ->maxLength("approver_notes", 255)
            ->allowEmptyString("approver_notes");

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
        $rules->add($rules->existsIn(["authorization_id"], "Authorizations"), [
            "errorField" => "authorization_id",
        ]);
        $rules->add($rules->existsIn(["approver_id"], "Approvers"), [
            "errorField" => "approver_id",
        ]);

        return $rules;
    }
}