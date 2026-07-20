<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WorkflowSchedule;
use Cake\Database\Exception\QueryException;
use Cake\I18n\DateTime;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\RulesChecker;
use Cake\Utility\Text;
use Cake\Validation\Validator;

/**
 * WorkflowSchedules Model
 *
 * Tracks last/next run times for scheduled workflow definitions.
 *
 * @property \App\Model\Table\WorkflowDefinitionsTable&\Cake\ORM\Association\BelongsTo $WorkflowDefinitions
 * @method \App\Model\Entity\WorkflowSchedule newEmptyEntity()
 * @method \App\Model\Entity\WorkflowSchedule newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\WorkflowSchedule patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\WorkflowSchedule get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\WorkflowSchedule findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\WorkflowSchedule saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class WorkflowSchedulesTable extends BaseTable
{
    private const CLAIM_LEASE_SECONDS = 900;

    /**
     * @param array $config Configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_schedules');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('WorkflowDefinitions', [
            'foreignKey' => 'workflow_definition_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('workflow_definition_id')
            ->requirePresence('workflow_definition_id', 'create')
            ->notEmptyString('workflow_definition_id');

        $validator
            ->dateTime('last_run_at')
            ->allowEmptyDateTime('last_run_at');

        $validator
            ->dateTime('next_run_at')
            ->allowEmptyDateTime('next_run_at');

        $validator
            ->boolean('is_enabled')
            ->notEmptyString('is_enabled');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules Rules checker instance
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['workflow_definition_id'], 'WorkflowDefinitions'));
        $rules->add($rules->isUnique(['workflow_definition_id']));

        return $rules;
    }

    /**
     * Return the single schedule row, tolerating a concurrent first insert.
     */
    public function getOrCreateForDefinition(int $workflowDefinitionId): WorkflowSchedule
    {
        $schedule = $this->find()
            ->where(['workflow_definition_id' => $workflowDefinitionId])
            ->first();
        if ($schedule instanceof WorkflowSchedule) {
            return $schedule;
        }

        $connection = $this->getConnection();
        $connection->enableSavePoints();
        try {
            return $this->saveOrFail($this->newEntity([
                'workflow_definition_id' => $workflowDefinitionId,
                'is_enabled' => true,
            ]));
        } catch (QueryException | PersistenceFailedException $exception) {
            $schedule = $this->find()
                ->where(['workflow_definition_id' => $workflowDefinitionId])
                ->first();
            if ($schedule instanceof WorkflowSchedule) {
                return $schedule;
            }

            throw $exception;
        }
    }

    /**
     * Atomically claim a schedule snapshot for dispatch.
     *
     * The expected last_run_at prevents a worker with a stale pre-claim read from
     * reclaiming a schedule that another worker has already completed.
     * Dispatch callers should hold the surrounding transaction open until they
     * complete the claim so the updated row remains locked during execution.
     */
    public function claimExecution(WorkflowSchedule $schedule, DateTime $claimedAt): ?string
    {
        $claimToken = Text::uuid();
        $leaseCutoff = $claimedAt->modify('-' . self::CLAIM_LEASE_SECONDS . ' seconds');

        $conditions = [
            'id' => $schedule->id,
            'is_enabled' => true,
            'OR' => [
                ['claim_token IS' => null],
                ['claimed_at IS' => null],
                ['claimed_at <' => $leaseCutoff],
            ],
        ];
        if ($schedule->last_run_at === null) {
            $conditions['last_run_at IS'] = null;
        } else {
            $conditions['last_run_at'] = $schedule->last_run_at;
        }

        $claimed = $this->updateAll(
            [
                'claim_token' => $claimToken,
                'claimed_at' => $claimedAt,
            ],
            $conditions,
        );

        return $claimed === 1 ? $claimToken : null;
    }

    /**
     * Record a successful dispatch and release its lease.
     */
    public function completeExecutionClaim(
        int $scheduleId,
        string $claimToken,
        DateTime $lastRunAt,
        DateTime $nextRunAt,
    ): bool {
        return $this->updateAll(
            [
                'last_run_at' => $lastRunAt,
                'next_run_at' => $nextRunAt,
                'claim_token' => null,
                'claimed_at' => null,
            ],
            [
                'id' => $scheduleId,
                'claim_token' => $claimToken,
            ],
        ) === 1;
    }

    /**
     * Release a failed dispatch without consuming the scheduled occurrence.
     */
    public function releaseExecutionClaim(int $scheduleId, string $claimToken): bool
    {
        return $this->updateAll(
            [
                'claim_token' => null,
                'claimed_at' => null,
            ],
            [
                'id' => $scheduleId,
                'claim_token' => $claimToken,
            ],
        ) === 1;
    }
}
