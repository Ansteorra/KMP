<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Participants Model
 *
 * @property \Cake\ORM\Association\HasMany $ParticipantAuthorizationTypes
 * @property \Cake\ORM\Association\BelongsToMany $Roles
 *
 * @method \App\Model\Entity\Participant get($primaryKey, $options = [])
 * @method \App\Model\Entity\Participant newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Participant[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Participant|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Participant patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Participant[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Participant findOrCreate($search, callable $callback = null)
 */
class ParticipantsTable extends Table
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

        $this->setTable('participants');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->hasMany('ParticipantAuthorizationTypes', [
            'foreignKey' => 'participant_id'
        ]);
        $this->hasMany('PendingAuthorizations', [
            'foreignKey' => 'participant_id'
        ]);
        $this->hasMany('PendingAuthorizationsToApprove', [
            'className'=> 'PendingAuthorizations',
            'foreignKey' => 'participant_marshal_id'
        ]);

        $this->belongsToMany('Roles', [
            'foreignKey' => 'participant_id',
            'targetForeignKey' => 'role_id',
            'joinTable' => 'participants_roles'
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
            ->dateTime('last_updated')
            ->notEmpty('last_updated');

        $validator
            ->requirePresence('password', 'create')
            ->notEmpty('password');

        $validator
            ->allowEmpty('sca_name');

        $validator
            ->requirePresence('first_name', 'create')
            ->notEmpty('first_name');

        $validator
            ->allowEmpty('middle_name');

        $validator
            ->requirePresence('last_name', 'create')
            ->notEmpty('last_name');

        $validator
            ->requirePresence('street_address', 'create')
            ->notEmpty('street_address');

        $validator
            ->requirePresence('city', 'create')
            ->notEmpty('city');

        $validator
            ->requirePresence('state', 'create')
            ->notEmpty('state');

        $validator
            ->requirePresence('zip', 'create')
            ->notEmpty('zip');

        $validator
            ->requirePresence('phone_number', 'create')
            ->notEmpty('phone_number');

        $validator
            ->requirePresence('email_address', 'create')
            ->notEmpty('email_address');

        $validator
            ->integer('membership_number')
            ->allowEmpty('membership_number');

        $validator
            ->date('membership_expires_on')
            ->allowEmpty('membership_expires_on');

        $validator
            ->allowEmpty('branch_name');

        $validator
            ->allowEmpty('notes');

        $validator
            ->integer('birth_month')
            ->allowEmpty('birth_month');

        $validator
            ->integer('birth_year')
            ->allowEmpty('birth_year');

        $validator
            ->allowEmpty('parent_name');

        $validator
            ->date('background_check_expires_on')
            ->allowEmpty('background_check_expires_on');

        $validator
            ->boolean('hidden')
            ->requirePresence('hidden', 'create')
            ->notEmpty('hidden');

        return $validator;
    }

    public function findAuth(\Cake\ORM\Query $query, array $options)
    {
        $query
            ->select(['id', 'email_address', 'password'])
            ->where(['Participants.hidden' => 0]);

        return $query;
    }
}
