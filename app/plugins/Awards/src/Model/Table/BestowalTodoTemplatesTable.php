<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * BestowalTodoTemplates Table - reusable bestowal checklists assigned to awards.
 */
class BestowalTodoTemplatesTable extends BaseTable
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

        $this->setTable('awards_bestowal_todo_templates');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->hasMany('BestowalTodoTemplateItems', [
            'className' => 'Awards.BestowalTodoTemplateItems',
            'foreignKey' => 'template_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'sort' => ['BestowalTodoTemplateItems.sort_order' => 'ASC'],
        ]);
        $this->hasMany('Awards', [
            'className' => 'Awards.Awards',
            'foreignKey' => 'bestowal_todo_template_id',
        ]);
        $this->belongsTo('Branches', [
            'className' => 'Branches',
            'foreignKey' => 'branch_id',
            'joinType' => 'LEFT',
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->boolean('is_active')
            ->notEmptyString('is_active');

        $validator
            ->integer('branch_id')
            ->allowEmptyString('branch_id');

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
        $rules->add($rules->isUnique(['name']), [
            'errorField' => 'name',
            'message' => __('Template names must be unique.'),
        ]);

        return $rules;
    }
}
