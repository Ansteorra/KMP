<?php
declare(strict_types=1);

namespace App\Model\Behavior;

use App\Services\TurboConfig;
use App\Services\UiUpdateOutbox;
use App\Services\UiUpdateRegistry;
use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;

/** Capture original owners and append invalidations inside the same transaction. */
class UiUpdateBehavior extends Behavior
{
    /** Capture previous owner IDs before patching can move a record. */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (
            !TurboConfig::liveEnabled() || $entity->isNew()
            || filter_var(env('KMP_ADMIN_JOB', false), FILTER_VALIDATE_BOOL)
        ) {
            return;
        }
        if (!isset(UiUpdateRegistry::definitions()[$this->_table->getRegistryAlias()])) {
            return;
        }
        $definition = UiUpdateRegistry::definitions()[$this->_table->getRegistryAlias()];
        $primaryKeys = (array)$this->_table->getPrimaryKey();
        $conditions = [];
        foreach ($primaryKeys as $key) {
            $conditions[$key] = $entity->getOriginal($key) ?? $entity->get($key);
        }
        $fields = array_merge($primaryKeys, ['entity_type', 'entity_id'], array_keys($definition['parents']));
        $fields = array_values(array_filter(
            $fields,
            fn(string $field): bool => $this->_table->getSchema()->hasColumn($field),
        ));
        $previous = $this->_table->find($this->_table->hasBehavior('Trash') ? 'withTrashed' : 'all')->select($fields)
            ->where($conditions)->first();
        $options['uiUpdatePrevious'] = $previous?->toArray() ?? [];
    }

    /** The ORM calls this while the save transaction is still active. */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!TurboConfig::liveEnabled() || filter_var(env('KMP_ADMIN_JOB', false), FILTER_VALIDATE_BOOL)) {
            return;
        }
        (new UiUpdateOutbox())->record(
            $this->_table->getConnection(),
            UiUpdateRegistry::changes($this->_table, $entity, $options['uiUpdatePrevious'] ?? []),
        );
    }

    /** Deleted records invalidate their former owners. */
    public function afterDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        $this->afterSave($event, $entity, $options);
    }

    /** Retain owner fields even when callers loaded only an ID. */
    public function beforeDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        $this->beforeSave($event, $entity, $options);
    }
}
