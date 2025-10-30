<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Database\Schema\TableSchemaInterface;

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
 *
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
     * Configure database schema with JSON field support
     *
     * Extends the base schema configuration to properly handle JSON fields,
     * specifically the available_vars field used for storing template variable metadata.
     * This ensures proper data type handling and serialization for JSON content.
     *
     * @return \Cake\Database\Schema\TableSchemaInterface Configured schema with JSON field types
     */
    public function getSchema(): TableSchemaInterface
    {
        $schema = parent::getSchema();
        $schema->setColumnType('available_vars', 'json');

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
            ->scalar('mailer_class')
            ->maxLength('mailer_class', 255)
            ->requirePresence('mailer_class', 'create')
            ->notEmptyString('mailer_class', 'Please specify the Mailer class')
            ->add('mailer_class', 'validClass', [
                'rule' => function ($value) {
                    return class_exists($value);
                },
                'message' => 'The specified Mailer class does not exist',
            ]);

        $validator
            ->scalar('action_method')
            ->maxLength('action_method', 255)
            ->requirePresence('action_method', 'create')
            ->notEmptyString('action_method', 'Please specify the action method');

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
                    // At least one of html_template or text_template must be provided
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

                    // Accept arrays (will be auto-converted to JSON by the json type)
                    if (is_array($value)) {
                        return true;
                    }

                    // Accept valid JSON strings
                    if (is_string($value)) {
                        json_decode($value);
                        return json_last_error() === JSON_ERROR_NONE;
                    }

                    return false;
                },
                'message' => 'Available vars must be valid JSON or an array',
            ]);

        $validator
            ->boolean('is_active')
            ->notEmptyString('is_active');

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
        // Ensure unique combination of mailer_class and action_method
        $rules->add(
            $rules->isUnique(
                ['mailer_class', 'action_method'],
                'A template for this mailer and action already exists'
            ),
            ['errorField' => 'action_method']
        );

        return $rules;
    }

    /**
     * Find template for a specific mailer class and action
     *
     * @param string $mailerClass Fully qualified mailer class name
     * @param string $actionMethod Action method name
     * @return \App\Model\Entity\EmailTemplate|null
     */
    public function findForMailer(string $mailerClass, string $actionMethod): ?object
    {
        return $this->find()
            ->where([
                'mailer_class' => $mailerClass,
                'action_method' => $actionMethod,
                'is_active' => true,
            ])
            ->first();
    }

    /**
     * Get all active templates
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findActive(SelectQuery $query): SelectQuery
    {
        return $query->where(['is_active' => true]);
    }

    /**
     * Get templates grouped by mailer class
     *
     * @return array
     */
    public function getTemplatesByMailer(): array
    {
        $templates = $this->find()
            ->orderBy(['mailer_class' => 'ASC', 'action_method' => 'ASC'])
            ->all();

        $grouped = [];
        foreach ($templates as $template) {
            $className = $template->mailer_class;
            if (!isset($grouped[$className])) {
                $grouped[$className] = [];
            }
            $grouped[$className][] = $template;
        }

        return $grouped;
    }
}
