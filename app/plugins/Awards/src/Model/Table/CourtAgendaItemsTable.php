<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Awards\Model\Entity\CourtAgendaItem;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * Ordered bestowal and manual block items inside court agenda segments.
 *
 * @property \Awards\Model\Table\CourtAgendaSegmentsTable&\Cake\ORM\Association\BelongsTo $CourtAgendaSegments
 * @property \Awards\Model\Table\BestowalsTable&\Cake\ORM\Association\BelongsTo $Bestowals
 */
class CourtAgendaItemsTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_court_agenda_items');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        $this->belongsTo('CourtAgendaSegments', [
            'foreignKey' => 'court_agenda_segment_id',
            'joinType' => 'INNER',
            'className' => 'Awards.CourtAgendaSegments',
        ]);
        $this->belongsTo('Bestowals', [
            'foreignKey' => 'bestowal_id',
            'joinType' => 'LEFT',
            'className' => 'Awards.Bestowals',
        ]);
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('court_agenda_segment_id')
            ->requirePresence('court_agenda_segment_id', 'create')
            ->notEmptyString('court_agenda_segment_id');

        $validator
            ->integer('bestowal_id')
            ->allowEmptyString('bestowal_id');

        $validator
            ->scalar('item_type')
            ->maxLength('item_type', 50)
            ->notEmptyString('item_type')
            ->inList('item_type', [CourtAgendaItem::TYPE_BESTOWAL, CourtAgendaItem::TYPE_BLOCK]);

        $validator
            ->scalar('role')
            ->maxLength('role', 50)
            ->notEmptyString('role')
            ->inList('role', [
                CourtAgendaItem::ROLE_PRESENT,
                CourtAgendaItem::ROLE_START,
                CourtAgendaItem::ROLE_FINISH,
                CourtAgendaItem::ROLE_ANNOUNCE,
                CourtAgendaItem::ROLE_BREAK,
            ]);

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->allowEmptyString('title');

        $validator
            ->integer('sort_order')
            ->notEmptyString('sort_order');

        $validator
            ->scalar('planned_action')
            ->maxLength('planned_action', 255)
            ->allowEmptyString('planned_action');

        $validator
            ->integer('estimated_minutes')
            ->range('estimated_minutes', [0, 240])
            ->notEmptyString('estimated_minutes');

        $validator
            ->boolean('duration_locked')
            ->allowEmptyString('duration_locked');

        $validator
            ->scalar('presentation_notes')
            ->allowEmptyString('presentation_notes');

        $validator
            ->scalar('print_notes')
            ->allowEmptyString('print_notes');

        $validator
            ->boolean('is_optional')
            ->allowEmptyString('is_optional');

        $validator
            ->boolean('include_reasons')
            ->allowEmptyString('include_reasons');

        $validator
            ->boolean('include_specialties')
            ->allowEmptyString('include_specialties');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['court_agenda_segment_id'], 'CourtAgendaSegments'), [
            'errorField' => 'court_agenda_segment_id',
        ]);
        $rules->add($rules->existsIn(['bestowal_id'], 'Bestowals'), ['errorField' => 'bestowal_id']);
        $rules->add(function (CourtAgendaItem $entity): bool {
            if ($entity->item_type === CourtAgendaItem::TYPE_BLOCK) {
                return trim((string)$entity->title) !== '';
            }

            return $entity->bestowal_id !== null;
        }, 'itemHasContent', [
            'errorField' => 'title',
            'message' => 'Agenda items must reference a bestowal or provide a block title.',
        ]);

        return $rules;
    }
}
