<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ParticipantRoles Model
 *
 * @property \App\Model\Table\ParticipantsTable&\Cake\ORM\Association\BelongsTo $Participants
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsTo $Roles
 * @property \App\Model\Table\ParticipantsTable&\Cake\ORM\Association\BelongsTo $Participants
 *
 * @method \App\Model\Entity\ParticipantRole newEmptyEntity()
 * @method \App\Model\Entity\ParticipantRole newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\ParticipantRole> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\ParticipantRole get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ParticipantRole findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ParticipantRole patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\ParticipantRole> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\ParticipantRole|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ParticipantRole saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\ParticipantRole>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ParticipantRole>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ParticipantRole>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ParticipantRole> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ParticipantRole>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ParticipantRole>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ParticipantRole>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ParticipantRole> deleteManyOrFail(iterable $entities, array $options = [])
 */
class ParticipantRolesTable extends Table
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

        $this->setTable('participant_roles');
        $this->setDisplayField(['participant_id', 'role_id']);
        $this->setPrimaryKey(['participant_id', 'role_id']);

        $this->belongsTo('Participants', [
            'foreignKey' => 'participant_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Roles', [
            'foreignKey' => 'role_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Authorized_By', [
            'className' => 'Participants',
            'foreignKey' => 'authorized_by_id',
            'joinType' => 'INNER',
            'propertyName' => 'authorized_by'
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
            ->date('ended_on')
            ->allowEmptyDate('ended_on');

        $validator
            ->date('start_on')
            ->notEmptyDate('start_on');

        $validator
            ->integer('authorized_by_id')
            ->notEmptyString('authorized_by_id');

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
        $rules->add($rules->existsIn(['participant_id'], 'Participants'), ['errorField' => 'participant_id']);
        $rules->add($rules->existsIn(['role_id'], 'Roles'), ['errorField' => 'role_id']);
        $rules->add($rules->existsIn(['authorized_by_id'], 'Participants'), ['errorField' => 'authorized_by_id']);

        return $rules;
    }
}
