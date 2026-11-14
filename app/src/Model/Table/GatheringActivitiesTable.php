<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\GatheringActivity;
use App\Services\GatheringActivityOrderingService;
use App\Services\GatheringActivityPresentationService;
use App\Services\IconCatalog;
use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GatheringActivities Model
 *
 * GatheringActivities are configuration/template objects that define types of activities
 * (e.g., "Armored Combat", "Archery"). They can be reused across many gatherings.
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsToMany $Gatherings
 * @property \App\Model\Table\GatheringTypesTable&\Cake\ORM\Association\BelongsToMany $GatheringTypes
 * @method \App\Model\Entity\GatheringActivity newEmptyEntity()
 * @method \App\Model\Entity\GatheringActivity newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringActivity[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringActivity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\GatheringActivity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\GatheringActivity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringActivity[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringActivity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\GatheringActivity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class GatheringActivitiesTable extends Table
{
    /**
     * @param \Cake\ORM\Query\SelectQuery $query Activity query, including list finders
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findForDisplay(SelectQuery $query): SelectQuery
    {
        return $query->orderBy(GatheringActivityOrderingService::queryOrder($this->getAlias()), true);
    }

    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('gathering_activities');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        $this->belongsToMany('AssignmentPermissions', [
            'className' => 'Permissions',
            'foreignKey' => 'gathering_activity_id',
            'targetForeignKey' => 'permission_id',
            'joinTable' => 'gathering_activity_assignment_permissions',
            'through' => 'GatheringActivityAssignmentPermissions',
        ]);

        // Many-to-many relationship with Gatherings through join table
        $this->belongsToMany('Gatherings', [
            'foreignKey' => 'gathering_activity_id',
            'targetForeignKey' => 'gathering_id',
            'joinTable' => 'gatherings_gathering_activities',
            'through' => 'GatheringsGatheringActivities',
            'sort' => ['GatheringsGatheringActivities.sort_order' => 'ASC'],
        ]);

        // Many-to-many relationship with GatheringTypes for template activities
        $this->belongsToMany('GatheringTypes', [
            'foreignKey' => 'gathering_activity_id',
            'targetForeignKey' => 'gathering_type_id',
            'joinTable' => 'gathering_type_gathering_activities',
            'through' => 'GatheringTypeGatheringActivities',
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('activity_type')
            ->inList('activity_type', GatheringActivity::ACTIVITY_TYPES, __('Choose a listed activity type.'))
            ->notEmptyString('activity_type');

        $validator->integer('display_order')
            ->range('display_order', [0, 2147483647])
            ->notEmptyString('display_order');

        $validator->inList('icon_source', ['catalog', 'bootstrap', 'upload']);
        $validator->add('icon_name', 'knownIcon', [
            'rule' => static function ($value, array $context): bool {
                if (is_string($value) && IconCatalog::canonical($value) !== null) {
                    return true;
                }
                // Preserve an existing unmapped identifier on unrelated edits; never accept a new unknown choice.
                $existing = $context['entity'] ?? null;

                return !$context['newRecord'] && $existing instanceof GatheringActivity
                    && $existing->icon_name === $value;
            },
            'message' => __('Choose an icon from the KMP catalog.'),
        ]);
        foreach (['foreground_color', 'background_color'] as $field) {
            $validator->add($field, 'hexColor', [
                'rule' => ['custom', '/^#[0-9a-fA-F]{6}$/'],
                'message' => __('Choose a six-digit hexadecimal color.'),
            ]);
        }
        $validator->add('background_color', 'contrast', [
            'rule' => static function ($value, array $context): bool {
                if (!$context['newRecord'] && !array_key_exists('foreground_color', $context['data'])) {
                    return true;
                }
                $foreground = $context['data']['foreground_color'] ?? '#212529';

                return GatheringActivityPresentationService::contrastRatio($foreground, $value) >= 4.5;
            },
            'message' => __('Choose colors with at least 4.5:1 contrast so activity labels remain readable.'),
        ]);

        return $validator;
    }

    /**
     * Canonicalize explicit choices and new defaults for every ORM writer; preserve untouched legacy/unknown values.
     *
     * @param \Cake\Event\EventInterface $event Save event
     * @param \Cake\Datasource\EntityInterface $entity Activity
     * @param \ArrayObject $options Save options
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if ($entity->isNew() || $entity->isDirty('icon_name')) {
            $canonical = IconCatalog::canonical($entity->get('icon_name'));
            if ($canonical !== null) {
                $entity->set('icon_name', $canonical);
            } elseif ($entity->isNew() && !$entity->get('icon_name')) {
                $entity->set('icon_name', 'activity');
            }
        }
        if (
            ($entity->isNew() || $entity->isDirty('icon_source'))
            && in_array($entity->get('icon_source'), [null, 'bootstrap'], true)
        ) {
            $entity->set('icon_source', 'catalog');
        }
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
        $rules->add(static fn($activity): bool => GatheringActivityPresentationService::contrastRatio(
            $activity->foreground_color ?? '#212529',
            $activity->background_color ?? '#e9ecef',
        ) >= 4.5, 'readableAppearance', [
            'errorField' => 'background_color',
            'message' => __('Choose colors with at least 4.5:1 contrast so activity labels remain readable.'),
        ]);

        return $rules;
    }
}
