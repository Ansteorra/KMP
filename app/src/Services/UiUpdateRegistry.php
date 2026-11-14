<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\StaticHelpers;
use Cake\Core\Plugin;
use Cake\Datasource\EntityInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\ServerRequest;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

/** Server-owned resource and invalidation mapping; plugins register their own domains. */
final class UiUpdateRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $definitions = [];

    /** @param array<string, mixed> $definition Resource metadata, never browser-supplied. */
    public static function register(string $table, array $definition): void
    {
        $definition['invalidatesScreens'] = array_values(array_unique(array_merge(
            self::$definitions[$table]['invalidatesScreens'] ?? [],
            $definition['invalidatesScreens'] ?? [],
        )));
        self::$definitions[$table] = array_replace(self::$definitions[$table] ?? [], $definition)
            + ['parents' => [], 'changes' => ['record']];
    }

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        $coreTables = [
            'Members', 'Branches', 'Gatherings', 'Roles', 'Permissions', 'WarrantRosters', 'ActionItems',
            'AppSettings', 'GatheringTypes', 'GatheringActivities', 'WarrantPeriods', 'EmailTemplates',
        ];
        foreach ($coreTables as $table) {
            if (!isset(self::$definitions[$table])) {
                self::register($table, ['controller' => $table]);
            }
        }
        self::$definitions += [
            'GatheringAttendances' => [
                'controller' => 'GatheringAttendances', 'changes' => ['attendance'],
                'parents' => ['gathering_id' => 'Gatherings', 'member_id' => 'Members'],
            ],
            'GatheringScheduledActivities' => [
                'changes' => ['schedule'], 'parents' => ['gathering_id' => 'Gatherings'],
            ],
            'GatheringStaff' => [
                'changes' => ['staff'], 'parents' => ['gathering_id' => 'Gatherings', 'member_id' => 'Members'],
            ],
            'Notes' => ['changes' => ['notes'], 'parents' => []],
            'MemberRoles' => [
                'changes' => ['permissions'], 'parents' => ['member_id' => 'Members', 'role_id' => 'Roles'],
            ],
            'RolesPermissions' => ['changes' => ['permissions'],
                'parents' => ['role_id' => 'Roles', 'permission_id' => 'Permissions']],
            'PermissionPolicies' => ['changes' => ['permissions'], 'parents' => ['permission_id' => 'Permissions']],
            'Warrants' => [
                'changes' => ['warrants'],
                'parents' => ['member_id' => 'Members', 'warrant_roster_id' => 'WarrantRosters'],
            ],
            'WorkflowApprovals' => ['controller' => 'Approvals', 'changes' => ['approvals'],
                'parents' => ['workflow_instance_id' => 'WorkflowInstances'],
                'listActions' => ['approvals' => 'approvals', 'allApprovals' => 'allApprovals']],
            'WorkflowApprovalResponses' => ['changes' => ['approvals'],
                'parents' => ['workflow_approval_id' => 'WorkflowApprovals']],
            'WorkflowInstances' => ['changes' => ['approvals'], 'parents' => [], 'entityOwner' => true],
        ];
        self::$definitions['Gatherings']['listActions'] = [
            'index' => 'index', 'calendar' => 'index', 'allGatherings' => 'index',
        ];
        self::$definitions['ActionItems']['listActions'] = ['myTasks' => 'myTasks'];
        self::$definitions['GatheringAttendances']['listActions'] = ['myRsvps' => 'myRsvps'];
        self::$definitions['Members']['notesPrivate'] = 'table';
        self::$definitions['Members']['idField'] = 'id';
        self::$definitions['Members']['parents'] = ['branch_id' => 'Branches'];
        self::$definitions['Branches']['invalidatesScreens'] = ['Members', 'Gatherings'];
        self::$definitions['Gatherings']['invalidatesScreens'] = ['Members'];
        self::$definitions['GatheringActivities']['invalidatesScreens'] = ['Gatherings'];
        self::$definitions['GatheringTypes']['invalidatesScreens'] = ['Gatherings'];
        self::$definitions['WarrantRosters']['notesPrivate'] = 'table';
        foreach (Plugin::loaded() as $plugin) {
            $provider = $plugin . '\\Services\\UiUpdateProvider';
            if (is_subclass_of($provider, UiUpdateProviderInterface::class)) {
                foreach ($provider::definitions() as $alias => $definition) {
                    self::register($alias, $definition);
                }
            }
        }

        return self::$definitions;
    }

    /** Advertise subscriptions only for explicitly registered read screens. */
    public static function supports(ServerRequest $request): bool
    {
        if ($request->getParam('prefix')) {
            return false;
        }
        $action = $request->getParam('action');
        foreach (self::definitions() as $alias => $definition) {
            $plugin = str_contains($alias, '.') ? explode('.', $alias, 2)[0] : null;
            if (
                $plugin === $request->getParam('plugin')
                && ($definition['controller'] ?? null) === $request->getParam('controller')
            ) {
                return $action === 'view' || isset($definition['contexts'][$action])
                    || isset(($definition['listActions'] ?? ['index' => 'index'])[$action]);
            }
        }

        return false;
    }

    /** Resolve only registered read screens on this tenant's host. @return array<string, mixed> */
    public static function resolve(ServerRequest $request, string $url): array
    {
        if (!str_starts_with($url, '/') || str_starts_with($url, '//') || strpbrk($url, "\\\r\n") !== false) {
            throw new BadRequestException('Invalid UI context.');
        }
        $uri = $request->getUri()->withPath((string)parse_url($url, PHP_URL_PATH))
            ->withQuery((string)parse_url($url, PHP_URL_QUERY));
        parse_str($uri->getQuery(), $query);
        $contextRequest = $request->withUri($uri)->withMethod('GET')->withQueryParams($query);
        $params = Router::parseRequest($contextRequest);
        $action = $params['action'] ?? '';
        if (!empty($params['prefix'])) {
            throw new BadRequestException('This screen does not support live updates.');
        }
        foreach (self::definitions() as $alias => $definition) {
            $plugin = str_contains($alias, '.') ? explode('.', $alias, 2)[0] : null;
            if ($plugin !== null && !StaticHelpers::pluginEnabled($plugin)) {
                continue;
            }
            if (
                ($params['plugin'] ?? null) !== $plugin
                || ($params['controller'] ?? '') !== ($definition['controller'] ?? null)
            ) {
                continue;
            }
            $table = TableRegistry::getTableLocator()->get($alias);
            if (isset($definition['contexts'][$action])) {
                return self::readDependencies(
                    $alias,
                    $definition['contexts'][$action]($contextRequest, $params) + ['url' => $url],
                );
            }
            $listActions = $definition['listActions'] ?? ['index' => 'index'];
            if (isset($listActions[$action])) {
                return self::readDependencies($alias, [
                    'resource' => $table, 'action' => $listActions[$action],
                    'topic' => self::topic($alias), 'url' => $url,
                ]);
            }
            if ($action !== 'view') {
                continue;
            }
            $id = $params['pass'][0] ?? null;
            if (!is_scalar($id)) {
                throw new BadRequestException('Missing record.');
            }
            $field = $definition['idField'] ?? ($table->getSchema()->hasColumn('public_id') ? 'public_id' : 'id');
            $entity = $table->find()->where([$table->getAlias() . '.' . $field => $id])->firstOrFail();

            return self::readDependencies($alias, [
                'resource' => $entity, 'action' => 'view',
                'topic' => self::topic($alias, $entity->get('id')), 'url' => $url,
                'alias' => $alias, 'notesPrivate' => $definition['notesPrivate'] ?? null,
            ]);
        }
        throw new BadRequestException('Unknown UI context.');
    }

    /** Catalogue dependencies carry invalidation only; every content read still reauthorizes the owning screen. */
    private static function readDependencies(string $alias, array $context): array
    {
        $topics = [$context['topic']];
        foreach (self::definitions() as $source => $definition) {
            if (in_array($alias, $definition['invalidatesScreens'] ?? [], true)) {
                $topics[] = self::topic($source, 'catalogue');
            }
        }
        $context['topics'] = array_values(array_unique($topics));

        return $context;
    }

    /** Build an opaque resource channel from server-owned identifiers. */
    public static function topic(string $alias, mixed $id = null): string
    {
        return $alias . ':' . ($id === null ? 'list' : (string)$id);
    }

    /** @param array<string, mixed> $previous Original fields before mutation. @return array<string, list<string>> */
    public static function changes(Table $table, EntityInterface $entity, array $previous = []): array
    {
        $alias = $table->getRegistryAlias();
        $definition = self::definitions()[$alias] ?? null;
        if ($definition === null) {
            return [];
        }
        $changes = $definition['changes'];
        $topics = [self::topic($alias) => $changes, self::topic($alias, $entity->get('id')) => $changes];
        if (!empty($definition['invalidatesScreens'])) {
            $topics[self::topic($alias, 'catalogue')] = $changes;
        }
        if (in_array($alias, ['MemberRoles', 'Roles', 'RolesPermissions', 'Permissions', 'PermissionPolicies'], true)) {
            $topics['Security:list'] = ['permissions'];
        }
        $current = $previous;
        foreach (array_merge(['entity_type', 'entity_id'], array_keys($definition['parents'])) as $field) {
            if ($entity->has($field) || $entity->isDirty($field)) {
                $current[$field] = $entity->get($field);
            }
        }
        foreach ([$previous, $current] as $values) {
            foreach ($definition['parents'] as $field => $parent) {
                if (!empty($values[$field])) {
                    $topics[self::topic($parent, $values[$field])] = $changes;
                    $topics[self::topic($parent)] = $changes;
                }
            }
            if (in_array($alias, ['Notes', 'ActionItems', 'WorkflowInstances'], true)) {
                $owner = $values['entity_type'] ?? null;
                if (is_string($owner) && isset(self::definitions()[$owner]) && !empty($values['entity_id'])) {
                    $topics[self::topic($owner, $values['entity_id'])] = $changes;
                    $topics[self::topic($owner)] = $changes;
                }
            }
        }

        $visited = [];
        foreach ([$previous, $current] as $values) {
            self::expandParents($topics, $definition['parents'], $values, $changes, $visited);
        }

        return $topics;
    }

    /**
     * Follow metadata-only ownership chains (item → segment → agenda → gathering).
     *
     * @param array<string, list<string>> $topics Invalidation accumulator.
     * @param array<string, string> $parents Foreign-key ownership mapping.
     * @param array<string, mixed> $values Original or current foreign keys.
     * @param list<string> $changes Change kinds.
     * @param array<string, bool> $visited Bound cycles and repeated reads.
     */
    private static function expandParents(
        array &$topics,
        array $parents,
        array $values,
        array $changes,
        array &$visited,
        int $depth = 0,
    ): void {
        if ($depth >= 4) {
            return;
        }
        foreach ($parents as $field => $alias) {
            $id = $values[$field] ?? null;
            if (!$id || isset($visited[$alias . ':' . $id])) {
                continue;
            }
            $visited[$alias . ':' . $id] = true;
            $topics[self::topic($alias, $id)] = $changes;
            $topics[self::topic($alias)] = $changes;
            $definition = self::definitions()[$alias] ?? null;
            if (!$definition) {
                continue;
            }
            $parentMap = $definition['parents'];
            $dynamicOwner = ($definition['entityOwner'] ?? false)
                || in_array($alias, ['Notes', 'ActionItems'], true);
            $table = TableRegistry::getTableLocator()->get($alias);
            $fields = array_keys($parentMap);
            if ($dynamicOwner) {
                $fields = array_merge($fields, ['entity_type', 'entity_id']);
            }
            $fields = array_filter($fields, fn(string $key): bool => $table->getSchema()->hasColumn($key));
            if ($fields === []) {
                continue;
            }
            $row = $table->find()->select($fields)->where([$table->getAlias() . '.id' => $id])->first();
            if ($row !== null) {
                $owner = $row->get('entity_type');
                if ($dynamicOwner && is_string($owner) && isset(self::definitions()[$owner])) {
                    $parentMap['entity_id'] = $owner;
                }
                self::expandParents($topics, $parentMap, $row->toArray(), $changes, $visited, $depth + 1);
            }
        }
    }
}
