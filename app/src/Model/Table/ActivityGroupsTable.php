<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ActivityGroups Model
 *
 * @method \App\Model\Entity\ActivityGroup newEmptyEntity()
 * @method \App\Model\Entity\ActivityGroup newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\ActivityGroup> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\ActivityGroup get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ActivityGroup findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ActivityGroup patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\ActivityGroup> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\ActivityGroup|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ActivityGroup saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\ActivityGroup>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ActivityGroup>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ActivityGroup>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ActivityGroup> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ActivityGroup>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ActivityGroup>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ActivityGroup>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ActivityGroup> deleteManyOrFail(iterable $entities, array $options = [])
 */
class ActivityGroupsTable extends Table
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

        $this->setTable("activity_groups");
        $this->setDisplayField("name");
        $this->setPrimaryKey("id");

        $this->HasMany("Activities", [
            "foreignKey" => "activity_group_id",
        ]);
        $this->addBehavior("Timestamp");
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
            ->scalar("name")
            ->maxLength("name", 255)
            ->requirePresence("name", "create")
            ->notEmptyString("name");

        return $validator;
    }
}
