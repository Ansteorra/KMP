<?php

declare(strict_types=1);

namespace Waivers\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * WaiverTypes Model
 *
 * @property \Waivers\Model\Table\GatheringActivityWaiversTable&\Cake\ORM\Association\HasMany $GatheringActivityWaivers
 * @property \Waivers\Model\Table\GatheringWaiversTable&\Cake\ORM\Association\HasMany $GatheringWaivers
 *
 * @method \Waivers\Model\Entity\WaiverType newEmptyEntity()
 * @method \Waivers\Model\Entity\WaiverType newEntity(array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\WaiverType> newEntities(array $data, array $options = [])
 * @method \Waivers\Model\Entity\WaiverType get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Waivers\Model\Entity\WaiverType findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Waivers\Model\Entity\WaiverType patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\WaiverType> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Waivers\Model\Entity\WaiverType|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Waivers\Model\Entity\WaiverType saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Waivers\Model\Entity\WaiverType>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\WaiverType>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\WaiverType>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\WaiverType> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\WaiverType>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\WaiverType>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\WaiverType>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\WaiverType> deleteManyOrFail(iterable $entities, array $options = [])
 */
class WaiverTypesTable extends Table
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

        $this->setTable('waivers_waiver_types');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        // TODO: Uncomment when Documents table and document_id column are implemented
        // $this->belongsTo('Documents', [
        //     'className' => 'Documents',
        //     'foreignKey' => 'document_id',
        //     'joinType' => 'LEFT',
        // ]);

        $this->hasMany('GatheringActivityWaivers', [
            'foreignKey' => 'waiver_type_id',
            'className' => 'Waivers.GatheringActivityWaivers',
        ]);
        $this->hasMany('GatheringWaivers', [
            'foreignKey' => 'waiver_type_id',
            'className' => 'Waivers.GatheringWaivers',
        ]);
        $this->hasMany('GatheringWaiverExemptions', [
            'foreignKey' => 'waiver_type_id',
            'className' => 'Waivers.GatheringWaiverExemptions',
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
            ->maxLength('name', 100)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('retention_policy')
            ->requirePresence('retention_policy', 'create')
            ->notEmptyString('retention_policy')
            ->add('retention_policy', 'validJson', [
                'rule' => function ($value, $context) {
                    // Parse JSON
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return 'Invalid JSON format: ' . json_last_error_msg();
                    }

                    // Validate structure is an array
                    if (!is_array($decoded)) {
                        return 'Retention policy must be a JSON object';
                    }

                    // Must have an anchor field
                    if (empty($decoded['anchor'])) {
                        return 'Missing required field: anchor';
                    }

                    // Validate anchor value
                    $validAnchors = ['gathering_end_date', 'upload_date', 'permanent'];
                    if (!in_array($decoded['anchor'], $validAnchors)) {
                        return sprintf(
                            'Invalid anchor "%s". Must be one of: %s',
                            $decoded['anchor'],
                            implode(', ', $validAnchors)
                        );
                    }

                    // If not permanent, must have duration object with at least one field
                    if ($decoded['anchor'] !== 'permanent') {
                        if (empty($decoded['duration']) || !is_array($decoded['duration'])) {
                            return 'Non-permanent retention policies must include a duration object';
                        }

                        // Check for at least one duration field
                        $years = $decoded['duration']['years'] ?? 0;
                        $months = $decoded['duration']['months'] ?? 0;
                        $days = $decoded['duration']['days'] ?? 0;

                        if ($years === 0 && $months === 0 && $days === 0) {
                            return 'Duration must specify at least one of: years, months, or days';
                        }

                        // Validate duration values are positive integers
                        if (isset($decoded['duration']['years']) && (!is_numeric($years) || $years < 0)) {
                            return 'Years must be a positive number';
                        }
                        if (isset($decoded['duration']['months']) && (!is_numeric($months) || $months < 0 || $months > 11)) {
                            return 'Months must be between 0 and 11';
                        }
                        if (isset($decoded['duration']['days']) && (!is_numeric($days) || $days < 0 || $days > 365)) {
                            return 'Days must be between 0 and 365';
                        }
                    }

                    return true;
                },
                'message' => '{0}', // Use the error message returned from the rule function
            ]);

        $validator
            ->boolean('convert_to_pdf')
            ->notEmptyString('convert_to_pdf');

        $validator
            ->boolean('is_active')
            ->notEmptyString('is_active');

        $validator
            ->scalar('exemption_reasons')
            ->allowEmptyString('exemption_reasons')
            ->add('exemption_reasons', 'validJson', [
                'rule' => function ($value, $context) {
                    if (empty($value)) {
                        return true; // Allow empty
                    }

                    // Parse JSON
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return 'Invalid JSON format: ' . json_last_error_msg();
                    }

                    // Validate structure is an array
                    if (!is_array($decoded)) {
                        return 'Exemption reasons must be a JSON array';
                    }

                    // Each item must be a non-empty string
                    foreach ($decoded as $reason) {
                        if (!is_string($reason) || trim($reason) === '') {
                            return 'Each exemption reason must be a non-empty string';
                        }
                    }

                    return true;
                },
                'message' => '{0}', // Use the error message returned from the rule function
            ]);

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

        return $rules;
    }

    /**
     * Find active waiver types
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findActive(SelectQuery $query): SelectQuery
    {
        return $query->where(['WaiverTypes.is_active' => true]);
    }
}
