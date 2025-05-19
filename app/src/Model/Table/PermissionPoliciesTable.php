<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * PermissionPolicies Model
 *
 * @property \App\Model\Table\PermissionsTable&\Cake\ORM\Association\BelongsTo $Permissions
 * @method \App\Model\Entity\PermissionPolicy newEmptyEntity()
 * @method \App\Model\Entity\PermissionPolicy newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\PermissionPolicy> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\PermissionPolicy get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\PermissionPolicy findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\PermissionPolicy patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\PermissionPolicy> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\PermissionPolicy|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\PermissionPolicy saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\PermissionPolicy>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PermissionPolicy>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PermissionPolicy>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PermissionPolicy> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PermissionPolicy>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PermissionPolicy>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PermissionPolicy>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PermissionPolicy> deleteManyOrFail(iterable $entities, array $options = [])
 */
class PermissionPoliciesTable extends BaseTable
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

        $this->setTable('permission_policies');
        $this->setDisplayField('policy_class');
        $this->setPrimaryKey('id');

        $this->belongsTo('Permissions', [
            'foreignKey' => 'permission_id',
            'joinType' => 'INNER',
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
            ->integer('permission_id')
            ->notEmptyString('permission_id');

        $validator
            ->scalar('policy_class')
            ->maxLength('policy_class', 255)
            ->requirePresence('policy_class', 'create')
            ->notEmptyString('policy_class');

        $validator
            ->scalar('policy_method')
            ->maxLength('policy_method', 255)
            ->requirePresence('policy_method', 'create')
            ->notEmptyString('policy_method');

        return $validator;
    }

    protected const CACHES_TO_CLEAR = [];
    protected const ID_CACHES_TO_CLEAR = [];
    protected const CACHE_GROUPS_TO_CLEAR = ['security'];

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['permission_id'], 'Permissions'), ['errorField' => 'permission_id']);

        return $rules;
    }
}
