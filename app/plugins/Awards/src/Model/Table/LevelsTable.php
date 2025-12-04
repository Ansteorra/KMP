<?php

declare(strict_types=1);

namespace Awards\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\BaseTable;

/**
 * Manages award level data with hierarchical precedence ordering.
 * 
 * Implements progression-based level organization for the awards hierarchy.
 * Uses soft deletion and tracks user modifications for audit trail support.
 * 
 * See /docs/5.2.2-awards-levels-table.md for complete documentation.
 * 
 * @property \Awards\Model\Table\AwardsTable&\Cake\ORM\Association\HasMany $Awards
 * @property \Cake\ORM\Behavior\TimestampBehavior&\Cake\ORM\Behavior $Timestamp
 * @property \Muffin\Footprint\Model\Behavior\FootprintBehavior&\Cake\ORM\Behavior $Footprint
 * @property \Muffin\Trash\Model\Behavior\TrashBehavior&\Cake\ORM\Behavior $Trash
 *
 * @method \Awards\Model\Entity\Level newEmptyEntity()
 * @method \Awards\Model\Entity\Level newEntity(array $data, array $options = [])
 * @method array<\Awards\Model\Entity\Level> newEntities(array $data, array $options = [])
 * @method \Awards\Model\Entity\Level get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\Level findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Awards\Model\Entity\Level patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Awards\Model\Entity\Level> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Awards\Model\Entity\Level|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\Level saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Awards\Model\Entity\Level>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Level>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Level>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Level> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Level>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Level>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Level>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Level> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class LevelsTable extends BaseTable
{
    /**
     * Initialize table settings, associations, and behaviors.
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_levels');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->hasMany('Awards', [
            'foreignKey' => 'level_id',
            'className' => 'Awards.Awards',
        ]);

        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
    }

    /**
     * Default validation rules for level data.
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
            ->integer('progression_order')
            ->allowEmptyString('progression_order');

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
     * Business rules for level name uniqueness validation.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);

        return $rules;
    }

    /**
     * Get all level names ordered by progression.
     *
     * @return array Array of level names in hierarchical progression order
     */
    public function getAllLevelNames(): array
    {
        $names = $this->find()
            ->select(['name'])
            ->where(['deleted IS' => null])
            ->orderBy(['progression_order' => 'ASC'])
            ->toArray();
        return array_map(fn($level) => $level->name, $names);
    }
}
