<?php
declare(strict_types=1);

namespace App\Model\Behavior;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Log\Log;
use Cake\ORM\Behavior;
use Cake\Routing\Router;
use Throwable;

/**
 * WorkflowTrigger Behavior
 *
 * Auto-dispatches workflow triggers on entity lifecycle events (afterSave, afterDelete).
 * Attach to any Table class to fire workflow triggers without manual controller code.
 *
 * @see \App\Services\WorkflowEngine\TriggerDispatcher
 */
class WorkflowTriggerBehavior extends Behavior
{
    /**
     * When true, all trigger dispatching is suppressed.
     * Set this during workflow engine execution or migrations to prevent infinite loops.
     *
     * @var bool
     */
    public static bool $suppressTriggers = false;

    /**
     * Default configuration.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'triggers' => [],
        'contextFields' => null,
        'contextAliases' => [],
        'eventDataKey' => 'trigger',
        'includeChangedFields' => true,
        'fieldConditions' => [],
    ];

    /**
     * afterSave callback — dispatches triggers for create and update events.
     *
     * @param \Cake\Event\EventInterface $event The afterSave event
     * @param \Cake\Datasource\EntityInterface $entity The saved entity
     * @param \ArrayObject $options Save options
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (static::$suppressTriggers) {
            return;
        }

        $isNew = $entity->isNew();
        $eventType = $isNew ? 'create' : 'update';
        $triggers = $this->getConfig('triggers');

        // Determine which trigger keys apply
        $applicableKeys = ['afterSave'];
        if ($isNew) {
            $applicableKeys[] = 'afterSave.new';
        } else {
            $applicableKeys[] = 'afterSave.existing';
        }

        foreach ($applicableKeys as $key) {
            if (!isset($triggers[$key])) {
                continue;
            }

            $triggerConfigs = $this->normalizeTriggerConfig($triggers[$key]);

            foreach ($triggerConfigs as $config) {
                if (!$this->shouldFireTrigger($config, $entity)) {
                    continue;
                }

                $context = $this->buildContext($entity, $eventType);
                $this->dispatchTrigger($config['trigger'], $context);
            }
        }
    }

    /**
     * afterDelete callback — dispatches triggers for delete events.
     *
     * @param \Cake\Event\EventInterface $event The afterDelete event
     * @param \Cake\Datasource\EntityInterface $entity The deleted entity
     * @param \ArrayObject $options Delete options
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (static::$suppressTriggers) {
            return;
        }

        $triggers = $this->getConfig('triggers');

        if (!isset($triggers['afterDelete'])) {
            return;
        }

        $triggerConfigs = $this->normalizeTriggerConfig($triggers['afterDelete']);

        foreach ($triggerConfigs as $config) {
            if (!$this->shouldFireTrigger($config, $entity)) {
                continue;
            }

            $context = $this->buildContext($entity, 'delete');
            $this->dispatchTrigger($config['trigger'], $context);
        }
    }

    /**
     * Normalize trigger config to a consistent array-of-arrays format.
     *
     * Accepts a string trigger name, a single config array, or an array of configs.
     *
     * @param array|string $config Raw trigger configuration
     * @return array<array{trigger: string, onlyIfChanged?: array}> Normalized configs
     */
    protected function normalizeTriggerConfig(string|array $config): array
    {
        // Simple string: 'Members.Registered'
        if (is_string($config)) {
            return [['trigger' => $config]];
        }

        // Single config array with 'trigger' key
        if (isset($config['trigger'])) {
            return [$config];
        }

        // Array of configs (numeric keys)
        $result = [];
        foreach ($config as $item) {
            if (is_string($item)) {
                $result[] = ['trigger' => $item];
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Check whether the trigger should fire based on field-change conditions.
     *
     * @param array $config Trigger config with optional 'onlyIfChanged' key
     * @param \Cake\Datasource\EntityInterface $entity The entity being checked
     * @return bool True if the trigger should fire
     */
    protected function shouldFireTrigger(array $config, EntityInterface $entity): bool
    {
        if (empty($config['onlyIfChanged'])) {
            return true;
        }

        $requiredFields = (array)$config['onlyIfChanged'];

        foreach ($requiredFields as $field) {
            if ($entity->isDirty($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the trigger context from entity data.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @param string $eventType One of 'create', 'update', 'delete'
     * @return array The context array
     */
    protected function buildContext(EntityInterface $entity, string $eventType): array
    {
        $primaryKey = $this->_table->getPrimaryKey();
        $entityId = is_string($primaryKey) ? $entity->get($primaryKey) : null;

        $entityData = $this->extractEntityData($entity);

        $context = [
            'entity' => $entityData,
            'event' => $eventType,
            'table' => $this->_table->getTable(),
            'entity_id' => $entityId,
            'user_id' => $this->getCurrentUserId(),
        ];

        foreach ((array)$this->getConfig('contextAliases') as $alias => $field) {
            if (!is_string($alias) || !is_string($field)) {
                continue;
            }

            if (array_key_exists($field, $entityData)) {
                $context[$alias] = $entityData[$field];
            }
        }

        if ($this->getConfig('includeChangedFields') && $eventType !== 'delete') {
            $context['changes'] = $this->extractChanges($entity);
        }

        return $context;
    }

    /**
     * Extract entity data, optionally filtered by contextFields config.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @return array Filtered entity data
     */
    protected function extractEntityData(EntityInterface $entity): array
    {
        $data = $entity->toArray();

        $contextFields = $this->getConfig('contextFields');
        if ($contextFields !== null) {
            $data = array_intersect_key($data, array_flip($contextFields));
        }

        return $data;
    }

    /**
     * Extract changed fields with old and new values.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @return array<string, array{old: mixed, new: mixed}> Changed fields
     */
    protected function extractChanges(EntityInterface $entity): array
    {
        $changes = [];
        $dirtyFields = $entity->getDirty();
        $contextFields = $this->getConfig('contextFields');

        foreach ($dirtyFields as $field) {
            if ($contextFields !== null && !in_array($field, $contextFields, true)) {
                continue;
            }

            $changes[$field] = [
                'old' => $entity->getOriginal($field),
                'new' => $entity->get($field),
            ];
        }

        return $changes;
    }

    /**
     * Get the current authenticated user ID from the request, if available.
     *
     * @return int|null User ID or null when not in an HTTP context
     */
    protected function getCurrentUserId(): ?int
    {
        $request = Router::getRequest();
        if ($request === null) {
            return null;
        }

        $identity = $request->getAttribute('identity');
        if ($identity === null) {
            return null;
        }

        $id = $identity->getIdentifier();

        return $id !== null ? (int)$id : null;
    }

    /**
     * Dispatch a trigger event through CakePHP's EventManager.
     *
     * Uses the 'Workflow.trigger' event so TriggerDispatcher can pick it up
     * without requiring a direct dependency on the engine.
     *
     * @param string $triggerName The workflow trigger name
     * @param array $context The trigger context data
     * @return void
     */
    protected function dispatchTrigger(string $triggerName, array $context): void
    {
        try {
            $eventDataKey = $this->getConfig('eventDataKey');
            $eventData = is_string($eventDataKey) && $eventDataKey !== ''
                ? [$eventDataKey => $context]
                : $context;

            $event = new Event('Workflow.trigger', $this, [
                'eventName' => $triggerName,
                'eventData' => $eventData,
                'triggeredBy' => $context['user_id'] ?? null,
            ]);

            EventManager::instance()->dispatch($event);
        } catch (Throwable $e) {
            Log::error(sprintf(
                'WorkflowTriggerBehavior: Failed to dispatch trigger "%s" for table "%s": %s',
                $triggerName,
                $this->_table->getTable(),
                $e->getMessage(),
            ));
        }
    }
}
