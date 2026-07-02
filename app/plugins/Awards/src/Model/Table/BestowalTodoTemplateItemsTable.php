<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Awards\Model\Entity\BestowalTodoTemplateItem as TodoItem;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * BestowalTodoTemplateItems Table - parallel checklist items within a template.
 */
class BestowalTodoTemplateItemsTable extends BaseTable
{
    /**
     * Initialize table associations and behaviors.
     *
     * @param array<string, mixed> $config Table configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_bestowal_todo_template_items');
        $this->setDisplayField('label');
        $this->setPrimaryKey('id');

        $this->belongsTo('BestowalTodoTemplates', [
            'className' => 'Awards.BestowalTodoTemplates',
            'foreignKey' => 'template_id',
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        if ($this->getSchema()->hasColumn('required_field_config')) {
            $this->getSchema()->setColumnType('required_field_config', 'json');
        }
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
            ->integer('template_id')
            ->allowEmptyString('template_id');

        $validator
            ->scalar('item_key')
            ->maxLength('item_key', 100)
            ->requirePresence('item_key', 'create')
            ->notEmptyString('item_key')
            ->regex('item_key', '/^[a-z0-9_]+$/', __('Use lowercase letters, numbers, and underscores only.'));

        $validator
            ->scalar('label')
            ->maxLength('label', 255)
            ->requirePresence('label', 'create')
            ->notEmptyString('label');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('assignee_type')
            ->inList('assignee_type', array_keys(TodoItem::ASSIGNEE_TYPE_OPTIONS))
            ->notEmptyString('assignee_type');

        $validator
            ->integer('assignee_source_id')
            ->requirePresence(
                'assignee_source_id',
                function (array $context): bool {
                    return ($context['data']['assignee_type'] ?? null) !== TodoItem::ASSIGNEE_TYPE_DYNAMIC;
                },
            )
            ->notEmptyString(
                'assignee_source_id',
                __('Select the role, permission, office, or member for this item.'),
                function (array $context): bool {
                    return ($context['data']['assignee_type'] ?? null) !== TodoItem::ASSIGNEE_TYPE_DYNAMIC;
                },
            )
            ->allowEmptyString(
                'assignee_source_id',
                null,
                function (array $context): bool {
                    return ($context['data']['assignee_type'] ?? null) === TodoItem::ASSIGNEE_TYPE_DYNAMIC;
                },
            );

        $validator
            ->scalar('assignee_source_key')
            ->maxLength('assignee_source_key', 100)
            ->requirePresence(
                'assignee_source_key',
                function (array $context): bool {
                    return ($context['data']['assignee_type'] ?? null) === TodoItem::ASSIGNEE_TYPE_DYNAMIC;
                },
            )
            ->notEmptyString(
                'assignee_source_key',
                __('Enter the dynamic resolver key for this item.'),
                function (array $context): bool {
                    return ($context['data']['assignee_type'] ?? null) === TodoItem::ASSIGNEE_TYPE_DYNAMIC;
                },
            )
            ->allowEmptyString(
                'assignee_source_key',
                null,
                function (array $context): bool {
                    return ($context['data']['assignee_type'] ?? null) !== TodoItem::ASSIGNEE_TYPE_DYNAMIC;
                },
            );

        $validator
            ->scalar('branch_mode')
            ->inList('branch_mode', array_keys(TodoItem::BRANCH_MODE_OPTIONS))
            ->notEmptyString('branch_mode');

        $validator
            ->scalar('branch_type')
            ->maxLength('branch_type', 50)
            ->requirePresence(
                'branch_type',
                function (array $context): bool {
                    return ($context['data']['branch_mode'] ?? null) === TodoItem::BRANCH_MODE_ANCESTOR_TYPE;
                },
            )
            ->notEmptyString(
                'branch_type',
                __('Select the ancestor branch type for this branch scope.'),
                function (array $context): bool {
                    return ($context['data']['branch_mode'] ?? null) === TodoItem::BRANCH_MODE_ANCESTOR_TYPE;
                },
            )
            ->allowEmptyString(
                'branch_type',
                null,
                function (array $context): bool {
                    return ($context['data']['branch_mode'] ?? null) !== TodoItem::BRANCH_MODE_ANCESTOR_TYPE;
                },
            );

        $validator
            ->boolean('is_gating')
            ->notEmptyString('is_gating');

        $validator
            ->scalar('required_field')
            ->maxLength('required_field', 100)
            ->allowEmptyString('required_field')
            ->inList('required_field', array_keys(TodoItem::REQUIRED_FIELD_OPTIONS));

        $validator
            ->allowEmptyArray('required_field_config');

        $validator
            ->integer('sort_order')
            ->allowEmptyString('sort_order');

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
        $rules->add($rules->existsIn(['template_id'], 'BestowalTodoTemplates'), [
            'errorField' => 'template_id',
        ]);
        $rules->add($rules->isUnique(['template_id', 'item_key']), [
            'errorField' => 'item_key',
            'message' => __('Item keys must be unique within a template.'),
        ]);

        return $rules;
    }
}
