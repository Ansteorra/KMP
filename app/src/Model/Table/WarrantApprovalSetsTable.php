<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * WarrantApprovalSets Model
 *
 * @property \App\Model\Table\WarrantApprovalsTable&\Cake\ORM\Association\HasMany $WarrantApprovals
 * @property \App\Model\Table\WarrantsTable&\Cake\ORM\Association\HasMany $Warrants
 *
 * @method \App\Model\Entity\WarrantApprovalSet newEmptyEntity()
 * @method \App\Model\Entity\WarrantApprovalSet newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\WarrantApprovalSet> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\WarrantApprovalSet get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\WarrantApprovalSet findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\WarrantApprovalSet patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\WarrantApprovalSet> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\WarrantApprovalSet|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\WarrantApprovalSet saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\WarrantApprovalSet>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WarrantApprovalSet>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WarrantApprovalSet>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WarrantApprovalSet> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WarrantApprovalSet>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WarrantApprovalSet>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WarrantApprovalSet>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WarrantApprovalSet> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class WarrantApprovalSetsTable extends Table
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

        $this->setTable('warrant_approval_sets');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('WarrantApprovals', [
            'foreignKey' => 'warrant_approval_set_id',
        ]);
        $this->hasMany('Warrants', [
            'foreignKey' => 'warrant_approval_set_id',
        ]);

        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('description')
            ->maxLength('description', 255)
            ->requirePresence('description', 'create')
            ->notEmptyString('description');

        $validator
            ->dateTime('planned_expires_on')
            ->requirePresence('planned_expires_on', 'create')
            ->notEmptyDateTime('planned_expires_on');

        $validator
            ->dateTime('planned_start_on')
            ->requirePresence('planned_start_on', 'create')
            ->notEmptyDateTime('planned_start_on');

        $validator
            ->integer('approvals_required')
            ->requirePresence('approvals_required', 'create')
            ->notEmptyString('approvals_required');

        $validator
            ->integer('approval_count')
            ->allowEmptyString('approval_count');

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        return $validator;
    }
}