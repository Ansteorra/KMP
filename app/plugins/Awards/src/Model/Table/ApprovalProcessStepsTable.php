<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Awards\Model\Entity\ApprovalProcessStep;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class ApprovalProcessStepsTable extends BaseTable
{
    /**
     * Initialize table associations and behaviors.
     *
     * @param array $config Table configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_approval_process_steps');
        $this->setDisplayField('label');
        $this->setPrimaryKey('id');

        $this->belongsTo('ApprovalProcesses', [
            'className' => 'Awards.ApprovalProcesses',
            'foreignKey' => 'approval_process_id',
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('approval_process_id')
            ->allowEmptyString('approval_process_id');

        $validator
            ->scalar('step_key')
            ->maxLength('step_key', 100)
            ->requirePresence('step_key', 'create')
            ->notEmptyString('step_key')
            ->regex('step_key', '/^[a-z0-9_]+$/', __('Use lowercase letters, numbers, and underscores only.'));

        $validator
            ->scalar('label')
            ->maxLength('label', 255)
            ->requirePresence('label', 'create')
            ->notEmptyString('label');

        $validator
            ->integer('sequence')
            ->notEmptyString('sequence');

        $validator
            ->scalar('step_type')
            ->inList('step_type', [ApprovalProcessStep::STEP_TYPE_APPROVAL])
            ->notEmptyString('step_type');

        $validator
            ->scalar('approver_type')
            ->inList('approver_type', array_keys(ApprovalProcessStep::APPROVER_TYPE_OPTIONS))
            ->notEmptyString('approver_type');

        $validator
            ->integer('approver_source_id')
            ->requirePresence(
                'approver_source_id',
                function (array $context): bool {
                    return ($context['data']['approver_type'] ?? null) !== ApprovalProcessStep::APPROVER_TYPE_DYNAMIC;
                },
            )
            ->notEmptyString(
                'approver_source_id',
                __('Select the role, permission, office, or member for this step.'),
                function (array $context): bool {
                    return ($context['data']['approver_type'] ?? null) !== ApprovalProcessStep::APPROVER_TYPE_DYNAMIC;
                },
            )
            ->allowEmptyString(
                'approver_source_id',
                null,
                function (array $context): bool {
                    return ($context['data']['approver_type'] ?? null) === ApprovalProcessStep::APPROVER_TYPE_DYNAMIC;
                },
            );

        $validator
            ->scalar('approver_source_key')
            ->maxLength('approver_source_key', 100)
            ->requirePresence(
                'approver_source_key',
                function (array $context): bool {
                    return ($context['data']['approver_type'] ?? null) === ApprovalProcessStep::APPROVER_TYPE_DYNAMIC;
                },
            )
            ->notEmptyString(
                'approver_source_key',
                __('Enter the dynamic resolver key for this step.'),
                function (array $context): bool {
                    return ($context['data']['approver_type'] ?? null) === ApprovalProcessStep::APPROVER_TYPE_DYNAMIC;
                },
            )
            ->allowEmptyString(
                'approver_source_key',
                null,
                function (array $context): bool {
                    return ($context['data']['approver_type'] ?? null) !== ApprovalProcessStep::APPROVER_TYPE_DYNAMIC;
                },
            );

        $validator
            ->scalar('branch_mode')
            ->inList('branch_mode', array_keys(ApprovalProcessStep::BRANCH_MODE_OPTIONS))
            ->notEmptyString('branch_mode');

        $validator
            ->scalar('branch_type')
            ->maxLength('branch_type', 50)
            ->requirePresence(
                'branch_type',
                function (array $context): bool {
                    return ($context['data']['branch_mode'] ?? null) === ApprovalProcessStep::BRANCH_MODE_ANCESTOR_TYPE;
                },
            )
            ->notEmptyString(
                'branch_type',
                __('Select the ancestor branch type for this branch scope.'),
                function (array $context): bool {
                    return ($context['data']['branch_mode'] ?? null) === ApprovalProcessStep::BRANCH_MODE_ANCESTOR_TYPE;
                },
            )
            ->allowEmptyString(
                'branch_type',
                null,
                function (array $context): bool {
                    return ($context['data']['branch_mode'] ?? null) !== ApprovalProcessStep::BRANCH_MODE_ANCESTOR_TYPE;
                },
            );

        $validator
            ->scalar('threshold_mode')
            ->inList('threshold_mode', array_keys(ApprovalProcessStep::THRESHOLD_MODE_OPTIONS))
            ->notEmptyString('threshold_mode');

        $validator
            ->integer('required_count')
            ->requirePresence(
                'required_count',
                function (array $context): bool {
                    return ($context['data']['threshold_mode'] ?? null) === ApprovalProcessStep::THRESHOLD_COUNT;
                },
            )
            ->notEmptyString(
                'required_count',
                __('Required count must be greater than zero when using a specific count threshold.'),
                function (array $context): bool {
                    return ($context['data']['threshold_mode'] ?? null) === ApprovalProcessStep::THRESHOLD_COUNT;
                },
            )
            ->allowEmptyString(
                'required_count',
                null,
                function (array $context): bool {
                    return ($context['data']['threshold_mode'] ?? null) !== ApprovalProcessStep::THRESHOLD_COUNT;
                },
            )
            ->add('required_count', 'positiveForCount', [
                'rule' => function ($value, array $context): bool {
                    return ($context['data']['threshold_mode'] ?? null) !== ApprovalProcessStep::THRESHOLD_COUNT
                        || (int)$value > 0;
                },
                'message' => __('Required count must be greater than zero when using a specific count threshold.'),
            ]);

        $validator
            ->scalar('on_reject')
            ->maxLength('on_reject', 100)
            ->notEmptyString('on_reject');

        $validator
            ->scalar('on_request_changes')
            ->maxLength('on_request_changes', 100)
            ->notEmptyString('on_request_changes');

        $validator
            ->boolean('retain_read_visibility')
            ->notEmptyString('retain_read_visibility');

        return $validator;
    }

    /**
     * Application integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['approval_process_id'], 'ApprovalProcesses'), [
            'errorField' => 'approval_process_id',
        ]);
        $rules->add($rules->isUnique(['approval_process_id', 'step_key']), [
            'errorField' => 'step_key',
            'message' => __('Step keys must be unique within an approval process.'),
        ]);

        return $rules;
    }
}
