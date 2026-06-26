<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * Court agendas group bestowals into printable court plans for a gathering.
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsTo $Gatherings
 * @property \Awards\Model\Table\CourtAgendaSegmentsTable&\Cake\ORM\Association\HasMany $CourtAgendaSegments
 */
class CourtAgendasTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_court_agendas');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        $this->belongsTo('Gatherings', [
            'foreignKey' => 'gathering_id',
            'joinType' => 'INNER',
            'className' => 'Gatherings',
        ]);
        $this->hasMany('CourtAgendaSegments', [
            'foreignKey' => 'court_agenda_id',
            'className' => 'Awards.CourtAgendaSegments',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'sort' => ['CourtAgendaSegments.sort_order' => 'ASC', 'CourtAgendaSegments.id' => 'ASC'],
        ]);
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('gathering_id')
            ->requirePresence('gathering_id', 'create')
            ->notEmptyString('gathering_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->boolean('is_default')
            ->allowEmptyString('is_default');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['gathering_id'], 'Gatherings'), ['errorField' => 'gathering_id']);

        return $rules;
    }

    /**
     * @param \Cake\ORM\Query\SelectQuery $query Query to scope.
     * @param array<int> $branchIDs Branch IDs.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function addBranchScopeQuery($query, $branchIDs): SelectQuery
    {
        if (empty($branchIDs)) {
            return $query;
        }

        return $query->matching('Gatherings', function ($q) use ($branchIDs) {
            return $q->where(['Gatherings.branch_id IN' => $branchIDs]);
        });
    }
}
