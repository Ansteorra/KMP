<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * MemberAuthorizationTypes Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Members
 * @property \Cake\ORM\Association\BelongsTo $AuthorizationTypes
 *
 * @method \App\Model\Entity\MemberAuthorizationType get($primaryKey, $options = [])
 * @method \App\Model\Entity\MemberAuthorizationType newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\MemberAuthorizationType[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\MemberAuthorizationType|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\MemberAuthorizationType patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\MemberAuthorizationType[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\MemberAuthorizationType findOrCreate($search, callable $callback = null)
 */
class MemberAuthorizationTypesTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('member_authorization_types');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Member', [
            'className' => 'Members', // 'MembersTable
            'foreignKey' => 'member_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('AuthorizationType', [
            'className' => 'AuthorizationTypes', // 'AuthorizationTypesTable
            'foreignKey' => 'authorization_type_id',
            'joinType' => 'INNER'
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
            ->requirePresence('authorized_by', 'create')
            ->notEmpty('authorized_by');

        $validator
            ->date('expires_on')
            ->requirePresence('expires_on', 'create')
            ->notEmpty('expires_on');

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
        $rules->add($rules->existsIn(['Member_id'], 'Members'));
        $rules->add($rules->existsIn(['authorization_type_id'], 'AuthorizationTypes'));

        return $rules;
    }
}
