<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\BaseTable;

/**
 * WarrantPeriods Model
 *
 * @method \App\Model\Entity\WarrantPeriod newEmptyEntity()
 * @method \App\Model\Entity\WarrantPeriod newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\WarrantPeriod> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\WarrantPeriod get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\WarrantPeriod findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\WarrantPeriod patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\WarrantPeriod> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\WarrantPeriod|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\WarrantPeriod saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\WarrantPeriod>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WarrantPeriod>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WarrantPeriod>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WarrantPeriod> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WarrantPeriod>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WarrantPeriod>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WarrantPeriod>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WarrantPeriod> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class WarrantPeriodsTable extends BaseTable
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('warrant_periods');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->date('start_date')
            ->requirePresence('start_date', 'create')
            ->notEmptyDate('start_date');

        $validator
            ->date('end_date')
            ->requirePresence('end_date', 'create')
            ->notEmptyDate('end_date');

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        return $validator;
    }
}
