<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AuthorizationTypes Model
 *
 * @property \Cake\ORM\Association\BelongsTo $MartialGroups
 * @property \Cake\ORM\Association\HasMany $ParticipantAuthorizationTypes
 * @property \Cake\ORM\Association\HasMany $PendingAuthorizations
 * @property \Cake\ORM\Association\BelongsToMany $Roles
 *
 * @method \App\Model\Entity\AuthorizationType get($primaryKey, $options = [])
 * @method \App\Model\Entity\AuthorizationType newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\AuthorizationType[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\AuthorizationType|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\AuthorizationType patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\AuthorizationType[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\AuthorizationType findOrCreate($search, callable $callback = null)
 */
class AuthorizationTypesTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('authorization_types');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('MartialGroups', [
            'foreignKey' => 'martial_groups_id',
            'joinType' => 'INNER'
        ]);
        $this->hasMany('ParticipantAuthorizationTypes', [
            'foreignKey' => 'authorization_type_id'
        ]);
        $this->hasMany('PendingAuthorizations', [
            'foreignKey' => 'authorization_type_id'
        ]);
        $this->belongsToMany('Roles', [
            'foreignKey' => 'authorization_type_id',
            'targetForeignKey' => 'role_id',
            'joinTable' => 'roles_authorization_types'
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('name', 'create')
            ->notEmpty('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->integer('length')
            ->requirePresence('length', 'create')
            ->notEmpty('length');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->isUnique(['name']));
        $rules->add($rules->existsIn(['martial_groups_id'], 'MartialGroups'));

        return $rules;
    }
}
