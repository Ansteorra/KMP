<?php

declare(strict_types=1);

namespace Awards\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Database\Schema\TableSchemaInterface;
use App\Model\Table\BaseTable;

/**
 * Awards Model
 *
 * @property \Awards\Model\Table\AwardsDomainsTable&\Cake\ORM\Association\BelongsTo $AwardsDomains
 * @property \Awards\Model\Table\AwardsLevelsTable&\Cake\ORM\Association\BelongsTo $AwardsLevels
 * @property \Awards\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches
 *
 * @method \Awards\Model\Entity\Award newEmptyEntity()
 * @method \Awards\Model\Entity\Award newEntity(array $data, array $options = [])
 * @method array<\Awards\Model\Entity\Award> newEntities(array $data, array $options = [])
 * @method \Awards\Model\Entity\Award get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\Award findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Awards\Model\Entity\Award patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Awards\Model\Entity\Award> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Awards\Model\Entity\Award|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\Award saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Awards\Model\Entity\Award>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Award>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Award>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Award> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Award>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Award>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Award>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Award> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class AwardsTable extends BaseTable
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

        $this->setTable('awards_awards');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('Domains', [
            'foreignKey' => 'domain_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Domains',
        ]);
        $this->belongsTo('Levels', [
            'foreignKey' => 'level_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Levels',
        ]);
        $this->belongsTo('Branches', [
            'foreignKey' => 'branch_id',
            'joinType' => 'INNER',
            'className' => 'Branches',
        ]);

        $this->hasMany('Recommendations', [
            'foreignKey' => 'award_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Recommendations',
        ]);

        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
    }

    public function getSchema(): TableSchemaInterface
    {
        $schema = parent::getSchema();
        $schema->setColumnType('specialties', 'json');

        return $schema;
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('abbriviation')
            ->maxLength('abbriviation', 20)
            ->requirePresence('name', 'create')
            ->notEmptyString('abbriviation');

        $validator
            ->scalar('insignia')
            ->allowEmptyString('insignia');

        $validator
            ->scalar('badge')
            ->allowEmptyString('badge');

        $validator
            ->scalar('charter')
            ->allowEmptyString('charter');

        $validator
            ->integer('domain_id')
            ->notEmptyString('domain_id');

        $validator
            ->integer('level_id')
            ->notEmptyString('level_id');

        $validator
            ->integer('branch_id')
            ->notEmptyString('branch_id');

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        $validator
            ->integer('modified_by')
            ->allowEmptyString('modified_by');

        $validator
            ->dateTime('deleted')
            ->allowEmptyDateTime('deleted');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);
        $rules->add($rules->existsIn(['domain_id'], 'Domains'), ['errorField' => 'domain_id']);
        $rules->add($rules->existsIn(['level_id'], 'Levels'), ['errorField' => 'level_id']);
        $rules->add($rules->existsIn(['branch_id'], 'Branches'), ['errorField' => 'branch_id']);

        return $rules;
    }

    public function addBranchScopeQuery($query, $branchIDs): SelectQuery
    {
        if (empty($branchIDs)) {
            return $query;
        }
        $query = $query->where([
            "branch_id IN" => $branchIDs,
        ]);
        return $query;
    }
}