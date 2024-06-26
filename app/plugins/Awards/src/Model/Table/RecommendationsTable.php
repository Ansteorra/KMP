<?php

declare(strict_types=1);

namespace Awards\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Recommendations Model
 *
 * @property \Awards\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \Awards\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 *
 * @method \Awards\Model\Entity\Recommendation newEmptyEntity()
 * @method \Awards\Model\Entity\Recommendation newEntity(array $data, array $options = [])
 * @method array<\Awards\Model\Entity\Recommendation> newEntities(array $data, array $options = [])
 * @method \Awards\Model\Entity\Recommendation get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\Recommendation findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Awards\Model\Entity\Recommendation patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Awards\Model\Entity\Recommendation> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Awards\Model\Entity\Recommendation|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\Recommendation saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Awards\Model\Entity\Recommendation>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Recommendation>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Recommendation>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Recommendation> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Recommendation>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Recommendation>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Recommendation>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Recommendation> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RecommendationsTable extends Table
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

        $this->setTable('awards_recommendations');
        $this->setDisplayField('member_sca_name');
        $this->setPrimaryKey('id');

        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
        $this->addBehavior("Sortable", [
            'field' => 'stack_rank',
        ]);

        $this->belongsTo('Requesters', [
            'foreignKey' => 'requester_id',
            'joinType' => 'LEFT',
            'className' => 'Members',
        ]);
        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'LEFT',
            'className' => 'Members',
        ]);
        $this->belongsTo('Branches', [
            'foreignKey' => 'branch_id',
            'className' => 'Branches',
        ]);
        $this->belongsTo('Awards', [
            'foreignKey' => 'award_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Awards',
        ]);
        $this->belongsToMany("Events", [
            "joinTable" => "awards_recommendations_events",
            "foreignKey" => "recommendation_id",
            "targetForeignKey" => "event_id",
            "className" => "Awards.Events",
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
            ->integer('requester_id')
            ->allowEmptyString('requester_id');

        $validator
            ->integer('member_id')
            ->allowEmptyString('member_id');

        $validator
            ->integer('branch_id')
            ->allowEmptyString('branch_id');

        $validator
            ->integer('award_id')
            ->notEmptyString('award_id');

        $validator
            ->scalar('requester_sca_name')
            ->maxLength('requester_sca_name', 255)
            ->requirePresence('requester_sca_name', 'create')
            ->notEmptyString('requester_sca_name');

        $validator
            ->scalar('member_sca_name')
            ->maxLength('member_sca_name', 255)
            ->requirePresence('member_sca_name', 'create')
            ->notEmptyString('member_sca_name');

        $validator
            ->scalar('contact_number')
            ->maxLength('contact_number', 100)
            ->requirePresence('contact_number', 'create')
            ->notEmptyString('contact_number');

        $validator
            ->scalar('reason')
            ->allowEmptyString('reason');

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        $validator
            ->integer('modified_by')
            ->allowEmptyString('modified_by');

        $validator
            ->dateTime('deleted')
            ->allowEmptyDateTime('deleted');

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
        $rules->add($rules->existsIn(['requester_id'], 'Members'), ['errorField' => 'requester_id']);
        $rules->add($rules->existsIn(['member_id'], 'Members'), ['errorField' => 'member_id']);
        $rules->add($rules->existsIn(['branch_id'], 'Branches'), ['errorField' => 'branch_id']);
        $rules->add($rules->existsIn(['award_id'], 'Awards'), ['errorField' => 'award_id']);

        return $rules;
    }
}