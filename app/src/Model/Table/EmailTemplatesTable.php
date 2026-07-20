<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Database\Schema\TableSchemaInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * EmailTemplates Model
 *
 * @method \App\Model\Entity\EmailTemplate newEmptyEntity()
 * @method \App\Model\Entity\EmailTemplate newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\EmailTemplate> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\EmailTemplate get(mixed $primaryKey, array|string $finder = [], \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\EmailTemplate findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\EmailTemplate patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\EmailTemplate> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\EmailTemplate|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\EmailTemplate saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\EmailTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmailTemplate>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\EmailTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmailTemplate> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\EmailTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmailTemplate>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\EmailTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmailTemplate> deleteManyOrFail(iterable $entities, array $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class EmailTemplatesTable extends Table
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

        $this->setTable('email_templates');
        $this->setDisplayField('display_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * Configure database schema with JSON field support for available_vars and variables_schema.
     *
     * @return \Cake\Database\Schema\TableSchemaInterface
     */
    public function getSchema(): TableSchemaInterface
    {
        $schema = parent::getSchema();
        if ($schema->hasColumn('available_vars')) {
            $schema->setColumnType('available_vars', 'json');
        }
        if ($schema->hasColumn('variables_schema')) {
            $schema->setColumnType('variables_schema', 'json');
        }

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
        // --- Workflow-native slug identity ---
        $validator
            ->scalar('slug')
            ->maxLength('slug', 100)
            ->requirePresence('slug', 'create')
            ->notEmptyString('slug', 'Please provide a slug')
            ->regex(
                'slug',
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'Slug must contain only lowercase letters, digits, and hyphens',
            );

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->allowEmptyString('name');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        // --- Template content ---
        $validator
            ->scalar('subject_template')
            ->maxLength('subject_template', 500)
            ->requirePresence('subject_template', 'create')
            ->notEmptyString('subject_template', 'Please provide a subject template');

        $validator
            ->scalar('html_template')
            ->allowEmptyString('html_template');

        $validator
            ->scalar('text_template')
            ->allowEmptyString('text_template')
            ->add('text_template', 'atLeastOne', [
                'rule' => function ($value, $context) {
                    return !empty($value) || !empty($context['data']['html_template']);
                },
                'message' => 'Either HTML template or text template must be provided',
            ]);

        $validator
            ->allowEmptyString('available_vars')
            ->add('available_vars', 'validJsonOrArray', [
                'rule' => function ($value) {
                    if (empty($value)) {
                        return true;
                    }

                    if (is_array($value)) {
                        return true;
                    }

                    if (is_string($value)) {
                        json_decode($value);

                        return json_last_error() === JSON_ERROR_NONE;
                    }

                    return false;
                },
                'message' => 'Available vars must be valid JSON or an array',
            ]);

        $validator
            ->allowEmptyString('variables_schema')
            ->add('variables_schema', 'validJsonOrArray', [
                'rule' => function ($value) {
                    if (empty($value)) {
                        return true;
                    }

                    if (is_array($value)) {
                        return true;
                    }

                    if (is_string($value)) {
                        json_decode($value);

                        return json_last_error() === JSON_ERROR_NONE;
                    }

                    return false;
                },
                'message' => 'Variables schema must be valid JSON or an array',
            ]);

        $validator
            ->boolean('is_active')
            ->notEmptyString('is_active');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(
            function ($entity) {
                if (empty($entity->slug)) {
                    return true;
                }

                $query = $this->find()->where(['slug' => $entity->slug]);

                if (!$entity->isNew()) {
                    $query->where(['id !=' => $entity->id]);
                }

                return !$query->count();
            },
            [
                'errorField' => 'slug',
                    'message' => 'A template with this slug already exists',
            ],
        );

        return $rules;
    }

    /**
     * Find an active template by its workflow-native slug.
     *
     * @param string $slug Template slug
     * @return \App\Model\Entity\EmailTemplate|null
     */
    public function findForSlug(string $slug): ?object
    {
        return $this->find()
            ->where(['slug' => $slug, 'is_active' => true])
            ->first();
    }

    /**
     * Custom finder for active templates.
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findActive(SelectQuery $query): SelectQuery
    {
        return $query->where(['is_active' => true]);
    }
}
