<?php

declare(strict_types=1);

namespace Awards\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\BaseTable;

/**
 * Events Model
 *
 * @property \Awards\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches
 *
 * @method \Awards\Model\Entity\Event newEmptyEntity()
 * @method \Awards\Model\Entity\Event newEntity(array $data, array $options = [])
 * @method array<\Awards\Model\Entity\Event> newEntities(array $data, array $options = [])
 * @method \Awards\Model\Entity\Event get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\Event findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Awards\Model\Entity\Event patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Awards\Model\Entity\Event> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Awards\Model\Entity\Event|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\Event saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Awards\Model\Entity\Event>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Event>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Event>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Event> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Event>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Event>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Event>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Event> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class EventsTable extends BaseTable
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

        $this->setTable('awards_events');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('Branches', [
            'foreignKey' => 'branch_id',
            'joinType' => 'INNER',
            'className' => 'Branches',
        ]);

        $this->hasMany('RecommendationsToGive', [
            'foreignKey' => 'event_id',
            'joinType' => 'LEFT',
            'className' => 'Awards.Recommendations',
        ]);

        $this->belongsToMany("Recommendations", [
            "joinTable" => "awards_recommendations_events",
            "foreignKey" => "event_id",
            "targetForeignKey" => "recommendation_id",
            "className" => "Awards.Recommendations",
        ]);



        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
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
            ->integer('branch_id')
            ->notEmptyString('branch_id');

        $validator
            ->date("start_date")
            ->requirePresence("start_date", "create")
            ->notEmptyDate("start_date");

        $validator
            ->date("end_date")
            ->requirePresence("end_date", "create")
            ->notEmptyDate("end_date");

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
        $rules->add($rules->existsIn(['branch_id'], 'Branches'), ['errorField' => 'branch_id']);

        return $rules;
    }
}
