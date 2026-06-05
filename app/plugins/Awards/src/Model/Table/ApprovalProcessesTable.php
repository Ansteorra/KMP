<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class ApprovalProcessesTable extends BaseTable
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

        $this->setTable('awards_approval_processes');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->hasMany('ApprovalProcessSteps', [
            'className' => 'Awards.ApprovalProcessSteps',
            'foreignKey' => 'approval_process_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'sort' => ['ApprovalProcessSteps.sequence' => 'ASC'],
        ]);
        $this->hasMany('Awards', [
            'className' => 'Awards.Awards',
            'foreignKey' => 'approval_process_id',
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
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);

        return $rules;
    }
}
