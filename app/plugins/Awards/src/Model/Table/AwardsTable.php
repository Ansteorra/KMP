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
 * Awards Table - Manages award configuration with Domain/Level/Branch hierarchy.
 *
 * @property \Awards\Model\Table\DomainsTable&\Cake\ORM\Association\BelongsTo $Domains
 * @property \Awards\Model\Table\LevelsTable&\Cake\ORM\Association\BelongsTo $Levels
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches
 * @property \Awards\Model\Table\RecommendationsTable&\Cake\ORM\Association\HasMany $Recommendations
 * @property \App\Model\Table\GatheringActivitiesTable&\Cake\ORM\Association\BelongsToMany $GatheringActivities
 *
 * @method \Awards\Model\Entity\Award newEmptyEntity()
 * @method \Awards\Model\Entity\Award newEntity(array $data, array $options = [])
 * @method \Awards\Model\Entity\Award get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\Award findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Awards\Model\Entity\Award patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Awards\Model\Entity\Award|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\Award saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Muffin\Footprint\Model\Behavior\FootprintBehavior
 * @mixin \Muffin\Trash\Model\Behavior\TrashBehavior
 */
class AwardsTable extends BaseTable
{
    /**
     * @param array<string,mixed> $config Configuration options
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

        // Aliased association for Awards->Branches to avoid conflicts
        // when Recommendations also has a Branches association (member's branch)
        $this->belongsTo('AwardBranch', [
            'foreignKey' => 'branch_id',
            'joinType' => 'LEFT',
            'className' => 'Branches',
            'propertyName' => 'award_branch',
        ]);

        $this->hasMany('Recommendations', [
            'foreignKey' => 'award_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Recommendations',
        ]);

        // Many-to-many relationship with GatheringActivities
        $this->belongsToMany('GatheringActivities', [
            'foreignKey' => 'award_id',
            'targetForeignKey' => 'gathering_activity_id',
            'joinTable' => 'award_gathering_activities',
            'through' => 'Awards.AwardGatheringActivities',
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
     * @param \Cake\Validation\Validator $validator Validator instance
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
     * Build application rules for referential integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object
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

    /**
     * Filter awards by allowed branch IDs.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query to filter
     * @param array<int>|int $branchIDs Branch IDs to filter by
     * @return \Cake\ORM\Query\SelectQuery
     */
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
