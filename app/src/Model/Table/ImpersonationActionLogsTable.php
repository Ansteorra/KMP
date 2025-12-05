<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * Table class providing access to impersonation audit records.
 */
class ImpersonationActionLogsTable extends BaseTable
{
    /**
     * Initialize table configuration.
     *
     * @param array $config Runtime configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('impersonation_action_logs');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Impersonators', [
            'className' => 'Members',
            'foreignKey' => 'impersonator_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('ImpersonatedMembers', [
            'className' => 'Members',
            'foreignKey' => 'impersonated_member_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('impersonator_id')
            ->integer('impersonator_id');

        $validator
            ->requirePresence('impersonated_member_id')
            ->integer('impersonated_member_id');

        $validator
            ->requirePresence('operation')
            ->scalar('operation')
            ->maxLength('operation', 20);

        $validator
            ->requirePresence('table_name')
            ->scalar('table_name')
            ->maxLength('table_name', 191);

        $validator
            ->requirePresence('entity_primary_key')
            ->scalar('entity_primary_key')
            ->maxLength('entity_primary_key', 191);

        $validator
            ->allowEmptyString('request_method')
            ->maxLength('request_method', 10);

        $validator
            ->allowEmptyString('request_url')
            ->maxLength('request_url', 512);

        $validator
            ->allowEmptyString('ip_address')
            ->maxLength('ip_address', 45);

        $validator
            ->allowEmptyString('metadata');

        return $validator;
    }

    /**
     * Build integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker instance
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['impersonator_id'], 'Impersonators'));
        $rules->add($rules->existsIn(['impersonated_member_id'], 'ImpersonatedMembers'));

        return $rules;
    }
}
