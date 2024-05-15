<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * PendingAuthorizations Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Participants
 * @property \Cake\ORM\Association\BelongsTo $Participants
 * @property \Cake\ORM\Association\BelongsTo $AuthorizationTypes
 *
 * @method \App\Model\Entity\PendingAuthorization get($primaryKey, $options = [])
 * @method \App\Model\Entity\PendingAuthorization newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\PendingAuthorization[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\PendingAuthorization|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\PendingAuthorization patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\PendingAuthorization[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\PendingAuthorization findOrCreate($search, callable $callback = null)
 */
class PendingAuthorizationsTable extends Table
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

        $this->setTable('pending_authorizations');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Participants', [
            'foreignKey' => 'participant_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('ParticipantMarshals', [
            'className'=>'Participants',
            'foreignKey' => 'participant_marshal_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('AuthorizationTypes', [
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
            ->requirePresence('authorization_token', 'create')
            ->notEmpty('authorization_token');

        $validator
            ->date('requested_on')
            ->requirePresence('requested_on', 'create')
            ->notEmpty('requested_on');

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
        $rules->add($rules->existsIn(['participant_id'], 'Participants'));
        $rules->add($rules->existsIn(['participant_marshal_id'], 'Participants'));
        $rules->add($rules->existsIn(['authorization_type_id'], 'AuthorizationTypes'));

        return $rules;
    }
}
