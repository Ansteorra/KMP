<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Backups Model — stores backup job metadata.
 *
 * @method \App\Model\Entity\Backup newEmptyEntity()
 * @method \App\Model\Entity\Backup get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 */
class BackupsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('backups');
        $this->setDisplayField('filename');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('filename')
            ->maxLength('filename', 255)
            ->requirePresence('filename', 'create')
            ->notEmptyString('filename');

        $validator
            ->scalar('storage_type')
            ->inList('storage_type', ['local', 's3', 'azure']);

        $validator
            ->scalar('status')
            ->inList('status', ['pending', 'running', 'completed', 'failed']);

        return $validator;
    }
}
