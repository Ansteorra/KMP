<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AuthorizationGroups Model
 *
 * @method \App\Model\Entity\AuthorizationGroup newEmptyEntity()
 * @method \App\Model\Entity\AuthorizationGroup newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\AuthorizationGroup> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\AuthorizationGroup get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\AuthorizationGroup findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\AuthorizationGroup patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\AuthorizationGroup> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\AuthorizationGroup|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\AuthorizationGroup saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationGroup>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationGroup>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationGroup>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationGroup> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationGroup>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationGroup>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AuthorizationGroup>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AuthorizationGroup> deleteManyOrFail(iterable $entities, array $options = [])
 */
class AuthorizationGroupsTable extends Table
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

        $this->setTable("authorization_groups");
        $this->setDisplayField("name");
        $this->setPrimaryKey("id");

        $this->HasMany("AuthorizationTypes", [
            "foreignKey" => "authorization_groups_id",
        ]);
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
