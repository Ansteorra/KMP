<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AuthorizationTypes Model
 *
 * @property \App\Model\Table\MartialGroupsTable&\Cake\ORM\Association\BelongsTo $MartialGroups
 * @property \App\Model\Table\MemberAuthorizationTypesTable&\Cake\ORM\Association\HasMany $MemberAuthorizationTypes
 * @property \App\Model\Table\PendingAuthorizationsTable&\Cake\ORM\Association\HasMany $PendingAuthorizations
 * @property \App\Model\Table\PermissionsTable&\Cake\ORM\Association\HasMany $Permissions
 *
 * @method \App\Model\Entity\AuthorizationType newEmptyEntity()
 * @method \App\Model\Entity\AuthorizationType newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\AuthorizationType> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\AuthorizationType get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\AuthorizationType findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\AuthorizationType patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\AuthorizationType> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\AuthorizationType|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\AuthorizationType saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationType>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationType>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationType>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationType> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationType>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationType>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationType>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationType> deleteManyOrFail(iterable $entities, array $options = [])
 */
class AuthorizationTypesTable extends Table
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

        $this->setTable('authorization_types');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('MartialGroups', [
            'foreignKey' => 'martial_groups_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('MemberAuthorizationTypes', [
            'foreignKey' => 'authorization_type_id',
        ]);
        $this->hasMany('PendingAuthorizations', [
            'foreignKey' => 'authorization_type_id',
        ]);
        $this->hasMany('Permissions', [
            'foreignKey' => 'authorization_type_id',
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->integer('length')
            ->requirePresence('length', 'create')
            ->notEmptyString('length');

        $validator
            ->integer('martial_groups_id')
            ->notEmptyString('martial_groups_id');

        $validator
            ->integer('minimum_age')
            ->allowEmptyString('minimum_age');

        $validator
            ->integer('maximum_age')
            ->allowEmptyString('maximum_age');

        $validator
            ->integer('num_required_authorizors')
            ->notEmptyString('num_required_authorizors');

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
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);
        $rules->add($rules->existsIn(['martial_groups_id'], 'MartialGroups'), ['errorField' => 'martial_groups_id']);

        return $rules;
    }
}
