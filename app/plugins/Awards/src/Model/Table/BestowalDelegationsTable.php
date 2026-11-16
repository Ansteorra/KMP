<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/** Tenant-owned delegation history; revocation preserves the original grant. */
class BestowalDelegationsTable extends BaseTable
{
    /** @inheritDoc */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('awards_bestowal_delegations');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Gatherings', ['className' => 'Gatherings']);
        $this->belongsTo('Branches', ['className' => 'Branches']);
        $this->belongsTo('Grantors', ['className' => 'Members', 'foreignKey' => 'grantor_id']);
        $this->belongsTo('Delegates', ['className' => 'Members', 'foreignKey' => 'delegate_id']);
        $this->belongsTo('Revokers', ['className' => 'Members', 'foreignKey' => 'revoked_by']);
    }

    /** @inheritDoc */
    public function validationDefault(Validator $validator): Validator
    {
        foreach (['gathering_id', 'branch_id', 'delegate_id'] as $field) {
            $validator->integer($field)->greaterThan($field, 0)
                ->requirePresence($field, 'create')->notEmptyString($field);
        }

        return $validator;
    }

    /** @inheritDoc */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        foreach (
            ['gathering_id' => 'Gatherings', 'branch_id' => 'Branches',
            'grantor_id' => 'Grantors', 'delegate_id' => 'Delegates'] as $field => $association
        ) {
            $rules->add($rules->existsIn([$field], $association), ['errorField' => $field]);
        }
        $rules->add(
            static fn($entity): bool => (int)$entity->grantor_id !== (int)$entity->delegate_id,
            'differentMembers',
            ['errorField' => 'delegate_id', 'message' => 'Choose another member.'],
        );

        return $rules;
    }
}
