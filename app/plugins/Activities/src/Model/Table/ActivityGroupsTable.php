<?php

declare(strict_types=1);

namespace Activities\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\BaseTable;

/**
 * Manages ActivityGroup entities for categorical organization of activities.
 * 
 * Provides data access, validation, and organizational structure for activity categorization.
 * Activity groups serve as logical containers enabling hierarchical organization and
 * administrative management.
 *
 * @property \Activities\Model\Table\ActivitiesTable&\Cake\ORM\Association\HasMany $Activities
 * 
 * @method \Activities\Model\Entity\ActivityGroup newEmptyEntity()
 * @method \Activities\Model\Entity\ActivityGroup newEntity(array $data, array $options = [])
 * @method \Activities\Model\Entity\ActivityGroup get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Activities\Model\Entity\ActivityGroup|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * 
 * @see \Activities\Model\Entity\ActivityGroup ActivityGroup entity class
 * @see /docs/5.6.6-activity-groups-entity-reference.md For comprehensive documentation
 */
class ActivityGroupsTable extends BaseTable
{
    /**
     * Initialize method - configures table associations and behaviors.
     *
     * @param array<string, mixed> $config The configuration for the Table
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable("activities_activity_groups");
        $this->setDisplayField("name");
        $this->setPrimaryKey("id");

        $this->HasMany("Activities", [
            "className" => "Activities.Activities",
            "foreignKey" => "activity_group_id",
        ]);
        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
    }

    /**
     * Default validation rules for activity group data integrity.
     *
     * @param \Cake\Validation\Validator $validator Validator instance for rule configuration
     * @return \Cake\Validation\Validator Configured validator with activity group rules
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
