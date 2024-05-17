<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Permissions Model
 *
 * @property \App\Model\Table\AuthorizationTypesTable&\Cake\ORM\Association\BelongsTo $AuthorizationTypes
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsToMany $Roles
 *
 * @method \App\Model\Entity\Permission newEmptyEntity()
 * @method \App\Model\Entity\Permission newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Permission> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Permission get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Permission findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Permission patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Permission> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Permission|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Permission saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Permission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Permission>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Permission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Permission> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Permission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Permission>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Permission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Permission> deleteManyOrFail(iterable $entities, array $options = [])
 */
class PermissionsTable extends Table
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

        $this->setTable('permissions');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('AuthorizationTypes', [
            'foreignKey' => 'authorization_type_id',
        ]);
        $this->belongsToMany('Roles', [
            'foreignKey' => 'permission_id',
            'targetForeignKey' => 'role_id',
            'joinTable' => 'roles_permissions',
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
            ->notEmptyString('name');

        $validator
            ->integer('authorization_type_id')
            ->allowEmptyString('authorization_type_id');

        $validator
            ->boolean('require_active_membership')
            ->notEmptyString('require_active_membership');

        $validator
            ->boolean('require_active_background_check')
            ->notEmptyString('require_active_background_check');

        $validator
            ->integer('require_min_age')
            ->notEmptyString('require_min_age');

        $validator
            ->boolean('system')
            ->notEmptyString('system');

        $validator
            ->boolean('is_super_user')
            ->notEmptyString('is_super_user');

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
        $rules->add($rules->existsIn(['authorization_type_id'], 'AuthorizationTypes'), ['errorField' => 'authorization_type_id']);

        return $rules;
    }
}
