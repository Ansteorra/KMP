<?php
declare(strict_types=1);

namespace Waivers\Model\Table;

use App\Model\Table\BaseTable;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/** Loss reports are audit records, separate from documents and exemptions. */
class GatheringWaiverLossesTable extends BaseTable
{
    /** Configure loss report associations and timestamps. */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('waivers_gathering_waiver_losses');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Gatherings', ['className' => 'Gatherings']);
        $this->belongsTo('WaiverTypes', ['className' => 'Waivers.WaiverTypes']);
        $this->belongsTo('CreatedByMembers', ['className' => 'Members', 'foreignKey' => 'created_by']);
        $this->belongsTo('ReversedByMembers', ['className' => 'Members', 'foreignKey' => 'reversed_by']);
    }

    /** Require a waiver type and a bounded, nonempty loss explanation. */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->integer('gathering_id')->requirePresence('gathering_id', 'create')->notEmptyString('gathering_id')
            ->integer('waiver_type_id')->requirePresence('waiver_type_id', 'create')->notEmptyString('waiver_type_id')
            ->scalar('notes')->requirePresence('notes', 'create')->notEmptyString('notes')
            ->maxLength('notes', 10000);
    }

    /** Preserve references and allow one active report per gathering and waiver type. */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn('gathering_id', 'Gatherings'));
        $rules->add($rules->existsIn('waiver_type_id', 'WaiverTypes'));
        $rules->add($rules->existsIn('created_by', 'CreatedByMembers'));
        $rules->add(function ($entity): bool {
            if ($entity->reversed_at !== null) {
                return true;
            }
            $conditions = [
                'gathering_id' => $entity->gathering_id,
                'waiver_type_id' => $entity->waiver_type_id,
                'reversed_at IS' => null,
            ];
            if (!$entity->isNew()) {
                $conditions['id !='] = $entity->id;
            }

            return !$this->exists($conditions);
        }, 'activeLossUnique', ['errorField' => 'waiver_type_id']);

        return $rules;
    }
}
