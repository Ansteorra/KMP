<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Awards\Model\Entity\CourtAgendaSegment;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * Court agenda segments represent named courts, breaks, or business blocks.
 *
 * @property \Awards\Model\Table\CourtAgendasTable&\Cake\ORM\Association\BelongsTo $CourtAgendas
 * @property \App\Model\Table\GatheringScheduledActivitiesTable&\Cake\ORM\Association\BelongsTo $GatheringScheduledActivities
 * @property \Awards\Model\Table\CourtAgendaItemsTable&\Cake\ORM\Association\HasMany $CourtAgendaItems
 */
class CourtAgendaSegmentsTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_court_agenda_segments');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        $this->belongsTo('CourtAgendas', [
            'foreignKey' => 'court_agenda_id',
            'joinType' => 'INNER',
            'className' => 'Awards.CourtAgendas',
        ]);
        $this->belongsTo('GatheringScheduledActivities', [
            'foreignKey' => 'gathering_scheduled_activity_id',
            'joinType' => 'LEFT',
            'className' => 'GatheringScheduledActivities',
        ]);
        $this->hasMany('CourtAgendaItems', [
            'foreignKey' => 'court_agenda_segment_id',
            'className' => 'Awards.CourtAgendaItems',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'sort' => ['CourtAgendaItems.sort_order' => 'ASC', 'CourtAgendaItems.id' => 'ASC'],
        ]);
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('court_agenda_id')
            ->requirePresence('court_agenda_id', 'create')
            ->notEmptyString('court_agenda_id');

        $validator
            ->integer('gathering_scheduled_activity_id')
            ->allowEmptyString('gathering_scheduled_activity_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('court_type')
            ->maxLength('court_type', 50)
            ->notEmptyString('court_type')
            ->inList('court_type', [
                CourtAgendaSegment::TYPE_COURT,
                CourtAgendaSegment::TYPE_BREAK,
                CourtAgendaSegment::TYPE_BUSINESS,
            ]);

        $validator
            ->integer('sort_order')
            ->notEmptyString('sort_order');

        $validator
            ->scalar('planned_start_time')
            ->maxLength('planned_start_time', 20)
            ->allowEmptyString('planned_start_time');

        $validator
            ->integer('planned_duration_minutes')
            ->greaterThanOrEqual('planned_duration_minutes', 0)
            ->notEmptyString('planned_duration_minutes');

        $validator
            ->scalar('notes')
            ->allowEmptyString('notes');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['court_agenda_id'], 'CourtAgendas'), ['errorField' => 'court_agenda_id']);
        $rules->add($rules->existsIn(['gathering_scheduled_activity_id'], 'GatheringScheduledActivities'), [
            'errorField' => 'gathering_scheduled_activity_id',
        ]);

        return $rules;
    }
}
