<?php

declare(strict_types=1);

/**
 * BaseTable - Foundational table class for KMP application.
 *
 * Provides cache management, branch-based query scoping, and event hooks.
 *
 * Cache invalidation via class constants:
 * - CACHES_TO_CLEAR: Static cache keys
 * - ID_CACHES_TO_CLEAR: Entity-ID-based cache prefixes
 * - CACHE_GROUPS_TO_CLEAR: Cache groups to clear entirely
 */

namespace App\Model\Table;

use App\Services\ImpersonationService;
use Cake\Cache\Cache;
use Cake\Datasource\EntityInterface;
use Cake\Log\Log;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

class BaseTable extends Table
{
    /** @var array<array{string, string}> Static cache entries to clear on save */
    protected const CACHES_TO_CLEAR = [];

    /** @var array<array{string, string}> Entity-ID cache prefixes to clear on save */
    protected const ID_CACHES_TO_CLEAR = [];

    /** @var array<string> Cache groups to clear entirely on save */
    protected const CACHE_GROUPS_TO_CLEAR = [];

    /**
     * After-save handler for automatic cache invalidation.
     *
     * @param \Cake\Event\EventInterface $event The afterSave event
     * @param \Cake\Datasource\EntityInterface $entity The saved entity
     * @param \ArrayObject $options Save options
     * @return void
     */
    public function afterSave($event, $entity, $options): void
    {
        // Phase 1: Clear static cache entries
        if (!empty($this::CACHES_TO_CLEAR)) {
            foreach ($this::CACHES_TO_CLEAR as $cache) {
                // Each cache entry: [cache_key, cache_config]
                Cache::delete($cache[0], $cache[1]);
            }
        }

        // Phase 2: Clear entity-ID-based cache entries
        if (!empty($this::ID_CACHES_TO_CLEAR)) {
            foreach ($this::ID_CACHES_TO_CLEAR as $cache) {
                // Each cache entry: [prefix, cache_config]
                // Combines prefix with entity ID: prefix{entity_id}
                Cache::delete($cache[0] . $entity->id, $cache[1]);
            }
        }

        // Phase 3: Clear cache groups entirely
        if (!empty($this::CACHE_GROUPS_TO_CLEAR)) {
            foreach ($this::CACHE_GROUPS_TO_CLEAR as $cache) {
                // Clear all cache entries in the specified group
                Cache::clearGroup($cache);
            }
        }
        $this->logImpersonationAction('save', $entity);
    }

    /**
     * After delete hook to capture impersonation audit trail entries.
     *
     * @param \Cake\Event\EventInterface $event Delete event
     * @param \Cake\Datasource\EntityInterface $entity Entity being deleted
     * @param \ArrayObject $options Delete options
     * @return void
     */
    public function afterDelete($event, $entity, $options): void
    {
        $this->logImpersonationAction('delete', $entity);
    }

    /**
     * Add branch-based data scoping to a query.
     *
     * Child tables should override for custom branch relationships.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to modify
     * @param array<int> $branchIDs Authorized branch IDs
     * @return \Cake\ORM\Query\SelectQuery Query with branch filtering
     */
    public function addBranchScopeQuery($query, $branchIDs): SelectQuery
    {
        // Return query unmodified if no branch restrictions
        if (empty($branchIDs)) {
            return $query;
        }

        // Apply branch filtering using IN clause
        // Default assumes direct Branches table relationship
        $query = $query->where([
            'Branches.id IN' => $branchIDs,
        ]);

        return $query;
    }

    /**
     * Record impersonated writes to audit log table.
     *
     * @param string $defaultOperation Operation fallback (save/delete)
     * @param \Cake\Datasource\EntityInterface $entity Affected entity
     * @return void
     */
    protected function logImpersonationAction(string $defaultOperation, EntityInterface $entity): void
    {
        if ($this->getAlias() === 'ImpersonationActionLogs') {
            return;
        }

        $request = Router::getRequest();
        if ($request === null) {
            return;
        }

        if (!$request->is(['post', 'put', 'patch', 'delete'])) {
            return;
        }

        $session = $request->getSession();
        if ($session === null) {
            return;
        }

        $impersonationService = new ImpersonationService();
        $state = $impersonationService->getState($session);
        if ($state === null) {
            return;
        }

        try {
            $logsTable = TableRegistry::getTableLocator()->get('ImpersonationActionLogs');
        } catch (\Throwable $exception) {
            Log::warning('Impersonation log table missing: ' . $exception->getMessage());
            return;
        }

        $method = strtoupper($request->getMethod());
        $operation = match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => $defaultOperation,
        };

        $primaryKey = $this->getPrimaryKey();
        $primaryValue = '';
        if ($primaryKey !== null) {
            $primaryKeyValue = $entity->get($primaryKey);
            if (is_scalar($primaryKeyValue) || $primaryKeyValue === null) {
                $primaryValue = (string)($primaryKeyValue ?? '');
            } else {
                $primaryValue = json_encode($primaryKeyValue);
            }
        }

        $metadata = [
            'alias' => $this->getAlias(),
            'primaryKeyField' => $primaryKey,
        ];
        try {
            $metadataJson = json_encode($metadata, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            $metadataJson = null;
        }

        $logEntity = $logsTable->newEntity([
            'impersonator_id' => (int)($state['impersonator_id'] ?? 0),
            'impersonated_member_id' => (int)($state['impersonated_member_id'] ?? 0),
            'operation' => $operation,
            'table_name' => $this->getTable(),
            'entity_primary_key' => $primaryValue,
            'request_method' => $method,
            'request_url' => $request->getRequestTarget(),
            'ip_address' => $request->clientIp(),
            'metadata' => $metadataJson,
        ], ['accessibleFields' => ['*' => true]]);

        if ($logEntity->hasErrors()) {
            Log::warning('Failed to build impersonation log entry: ' . json_encode($logEntity->getErrors()));
            return;
        }

        $logsTable->save($logEntity, ['checkRules' => false, 'atomic' => false]);
    }
}
