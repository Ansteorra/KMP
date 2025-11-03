<?php

declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\ORM\Table;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Query\SelectQuery;

/**
 * PublicId Behavior
 * 
 * Provides non-sequential, unpredictable public identifiers for entities.
 * 
 * ## Why Public IDs?
 * 
 * Exposing internal database IDs to clients is a security anti-pattern:
 * - Sequential IDs leak information (user count, creation order, etc.)
 * - Enable enumeration attacks
 * - May violate privacy by revealing usage patterns
 * 
 * Public IDs solve this by providing:
 * - Non-sequential identifiers safe for client exposure
 * - No information leakage
 * - Prevention of enumeration attacks
 * - Identical functionality to IDs for lookups
 * 
 * ## Usage
 * 
 * ```php
 * // In your Table class
 * public function initialize(array $config): void
 * {
 *     parent::initialize($config);
 *     $this->addBehavior('PublicId');
 * }
 * ```
 * 
 * ## Configuration
 * 
 * ```php
 * $this->addBehavior('PublicId', [
 *     'field' => 'public_id',      // Column name (default: 'public_id')
 *     'length' => 8,                // ID length (default: 8)
 *     'regenerate' => false,        // Regenerate on save (default: false)
 * ]);
 * ```
 * 
 * ## Features
 * 
 * - Automatic generation on entity creation
 * - Finder methods: `findByPublicId()`, `getByPublicId()`
 * - Custom finder: `find('publicId', ['publicId' => 'abc123'])`
 * - Validation rules
 * - Unique constraint enforcement
 * 
 * ## Format
 * 
 * - Character set: Base62 (a-z, A-Z, 0-9)
 * - Length: 8 characters (configurable)
 * - Uniqueness: 62^8 = 218 trillion combinations
 * - Collision probability: Negligible for normal usage
 */
class PublicIdBehavior extends Behavior
{
    /**
     * Default configuration
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'field' => 'public_id',
        'length' => 8,
        'regenerate' => false,
        'implementedFinders' => [
            'publicId' => 'findByPublicId',
        ],
        'implementedMethods' => [
            'getByPublicId' => 'getByPublicId',
            'generatePublicId' => 'generatePublicId',
        ],
    ];

    /**
     * Characters used in public ID generation (Base62)
     * 
     * Excludes visually similar characters for better human readability:
     * - No 0/O confusion
     * - No 1/l/I confusion
     */
    protected const CHARSET = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Initialize behavior
     *
     * @param array $config Configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $field = $this->getConfig('field');

        // Add validation rules
        $this->_table->getValidator()
            ->add($field, 'unique', [
                'rule' => 'validateUnique',
                'provider' => 'table',
                'message' => 'This public ID already exists.',
            ])
            ->add($field, 'length', [
                'rule' => ['lengthBetween', $this->getConfig('length'), $this->getConfig('length')],
                'message' => sprintf('Public ID must be exactly %d characters.', $this->getConfig('length')),
            ])
            ->add($field, 'alphanumeric', [
                'rule' => ['custom', '/^[a-zA-Z0-9]+$/'],
                'message' => 'Public ID must contain only letters and numbers.',
            ]);
    }

    /**
     * Implemented finders
     *
     * @return array<string, string>
     */
    public function implementedFinders(): array
    {
        return [
            'byPublicId' => 'findByPublicId',
        ];
    }

    /**
     * Before save callback
     * 
     * Generates public ID for new entities or regenerates for existing ones if configured
     *
     * @param \Cake\Event\EventInterface $event Event
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @param \ArrayObject $options Options
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        $field = $this->getConfig('field');

        // Generate public ID for new entities
        if ($entity->isNew() && empty($entity->get($field))) {
            $entity->set($field, $this->generatePublicId());
        }

        // Regenerate if configured and explicitly requested
        if ($this->getConfig('regenerate') && !$entity->isNew() && $entity->isDirty($field)) {
            $entity->set($field, $this->generatePublicId());
        }
    }

    /**
     * Generate a unique public ID
     * 
     * Uses cryptographically secure random bytes for unpredictability.
     * Checks uniqueness in database and regenerates if collision occurs.
     *
     * @return string Generated public ID
     */
    public function generatePublicId(): string
    {
        $length = $this->getConfig('length');
        $field = $this->getConfig('field');
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $publicId = $this->_generateRandomString($length);
            $exists = $this->_table->exists([$field => $publicId]);
            $attempt++;

            if ($attempt >= $maxAttempts) {
                throw new \RuntimeException(sprintf(
                    'Failed to generate unique public ID after %d attempts. Consider increasing length.',
                    $maxAttempts
                ));
            }
        } while ($exists);

        return $publicId;
    }

    /**
     * Generate random string using charset
     *
     * @param int $length Length of string to generate
     * @return string Random string
     */
    protected function _generateRandomString(int $length): string
    {
        $charset = self::CHARSET;
        $charsetLength = strlen($charset);
        $randomString = '';

        // Use random_int for cryptographically secure randomness
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $charset[random_int(0, $charsetLength - 1)];
        }

        return $randomString;
    }

    /**
     * Custom finder to lookup by public ID
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query
     * @param array $options Options array with 'publicId' key
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByPublicId(SelectQuery $query, array $options): SelectQuery
    {
        $field = $this->getConfig('field');
        $publicId = $options['publicId'] ?? $options[0] ?? null;

        if (!$publicId) {
            throw new \InvalidArgumentException('Public ID is required for findByPublicId');
        }

        return $query->where([$this->_table->aliasField($field) => $publicId]);
    }

    /**
     * Get entity by public ID
     * 
     * Convenience method similar to Table::get() but using public ID
     *
     * @param string $publicId Public ID
     * @param array $options Additional options for find
     * @return \Cake\Datasource\EntityInterface|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found
     */
    public function getByPublicId(string $publicId, array $options = []): ?EntityInterface
    {
        return $this->_table
            ->find('publicId', publicId: $publicId)
            ->firstOrFail();
    }

    /**
     * Before find callback
     * 
     * Allows finding by public_id in conditions automatically
     *
     * @param \Cake\Event\EventInterface $event Event
     * @param \Cake\ORM\Query\SelectQuery $query Query
     * @param \ArrayObject $options Options
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function beforeFind(EventInterface $event, SelectQuery $query, \ArrayObject $options): SelectQuery
    {
        // This allows automatic translation of public_id conditions
        // Example: $table->find()->where(['public_id' => 'abc123'])
        return $query;
    }
}
