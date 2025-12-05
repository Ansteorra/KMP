<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * Provides access to impersonation session events.
 */
class ImpersonationSessionLogsTable extends BaseTable
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('impersonation_session_logs');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Impersonators', [
            'className' => 'Members',
            'foreignKey' => 'impersonator_id',
        ]);
        $this->belongsTo('ImpersonatedMembers', [
            'className' => 'Members',
            'foreignKey' => 'impersonated_member_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('impersonator_id')
            ->integer('impersonator_id');

        $validator
            ->requirePresence('impersonated_member_id')
            ->integer('impersonated_member_id');

        $validator
            ->requirePresence('event')
            ->scalar('event')
            ->maxLength('event', 16);

        $validator
            ->allowEmptyString('request_url')
            ->maxLength('request_url', 512);

        $validator
            ->allowEmptyString('ip_address')
            ->maxLength('ip_address', 45);

        $validator
            ->allowEmptyString('user_agent')
            ->maxLength('user_agent', 512);

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['impersonator_id'], 'Impersonators'));
        $rules->add($rules->existsIn(['impersonated_member_id'], 'ImpersonatedMembers'));

        return $rules;
    }
}
