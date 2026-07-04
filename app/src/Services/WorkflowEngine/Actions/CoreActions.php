<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace App\Services\WorkflowEngine\Actions;

use App\Mailer\QueuedMailerAwareTrait;
use App\Model\Entity\ActiveWindowBaseEntity;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\WorkflowEngine\ActionResult;
use App\Services\WorkflowEngine\ExpressionEvaluator;
use App\Services\WorkflowEngine\WorkflowActionTrait;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use Cake\Core\Plugin;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\TableLocator;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Core workflow actions: email, notes, entity updates, role assignment,
 * variable setting, and ActiveWindow temporal management.
 */
class CoreActions
{
    use QueuedMailerAwareTrait;
    use WorkflowActionTrait;
    use WorkflowContextAwareTrait;

    private ActiveWindowManagerInterface $activeWindowManager;
    private ExpressionEvaluator $expressionEvaluator;

    /**
     * @param \App\Services\ActiveWindowManager\ActiveWindowManagerInterface $activeWindowManager
     * @param \App\Services\WorkflowEngine\ExpressionEvaluator|null $expressionEvaluator
     */
    public function __construct(
        ActiveWindowManagerInterface $activeWindowManager,
        ?ExpressionEvaluator $expressionEvaluator = null,
    ) {
        $this->activeWindowManager = $activeWindowManager;
        $this->expressionEvaluator = $expressionEvaluator ?? new ExpressionEvaluator();
    }

    /**
     * Send an email notification.
     *
     * The action requires a 'template' value, which is normally a stable slug such as
     * "warrant-issued". Numeric IDs are still accepted for migration compatibility and
     * route through the same KMPMailer::sendFromTemplate path.
     *
     * @param array $context Current workflow context
     * @param array $config Action configuration
     * @return array Output with 'sent' boolean
     */
    public function sendEmail(array $context, array $config): array
    {
        try {
            $to = $this->resolveValue($config['to'] ?? '', $context);
            $vars = [];
            foreach (($config['vars'] ?? []) as $key => $val) {
                $vars[$key] = $this->resolveValue($val, $context);
            }

            $replyTo = !empty($config['replyTo'])
                ? $this->resolveValue($config['replyTo'], $context)
                : null;

            if (empty($config['template'])) {
                throw new RuntimeException('Core.SendEmail requires a template slug or numeric template ID.');
            }

            $templateRef = (string)$this->resolveValue($config['template'], $context);
            $mergedVars = array_merge(['_templateId' => $templateRef], $vars);
            if ($replyTo) {
                $mergedVars['_replyTo'] = $replyTo;
            }
            $this->queueMail('KMP', 'sendFromTemplate', $to, $mergedVars);

            return ActionResult::success(['sent' => true])->toArray();
        } catch (Throwable $e) {
            Log::error('Workflow SendEmail failed: ' . $e->getMessage());

            return ActionResult::failure($e->getMessage(), ['sent' => false])->toArray();
        }
    }

    /**
     * Create a note on an entity.
     *
     * @param array $context Current workflow context
     * @param array $config Action configuration with 'entityType', 'entityId', 'subject', 'body'
     * @return array Output with 'noteId'
     */
    public function createNote(array $context, array $config): array
    {
        $notesTable = TableRegistry::getTableLocator()->get('Notes');

        $note = $notesTable->newEntity([
            'topic_model' => $this->resolveValue($config['entityType'], $context),
            'topic_id' => $this->resolveValue($config['entityId'], $context),
            'subject' => $this->resolveValue($config['subject'], $context),
            'body' => $this->resolveValue($config['body'], $context),
            'author_id' => $context['triggeredBy'] ?? null,
        ]);

        $saved = $notesTable->save($note);
        if (!$saved) {
            return [
                'success' => false,
                'data' => ['noteId' => null],
                'error' => 'Failed to save workflow note.',
                'noteId' => null,
            ];
        }

        return ['success' => true, 'data' => ['noteId' => $saved->id], 'error' => null, 'noteId' => $saved->id];
    }

    /**
     * Update fields on an entity.
     *
     * @param array $context Current workflow context
     * @param array $config Action configuration with 'entityType', 'entityId', 'fields'
     * @return array Output with 'updated' boolean
     */
    public function updateEntity(array $context, array $config): array
    {
        try {
            $tableName = $this->resolveValue($config['entityType'], $context);

            // Validate entity type is registered in WorkflowEntityRegistry
            $registeredEntity = WorkflowEntityRegistry::getEntity($tableName);
            if ($registeredEntity === null) {
                Log::warning("Workflow UpdateEntity rejected: entity type '{$tableName}' is not registered in WorkflowEntityRegistry");

                return ['updated' => false, 'error' => "Entity type '{$tableName}' is not registered for workflow operations."];
            }

            // Validate fields against the registered entity's allowed field list
            $allowedFields = array_keys($registeredEntity['fields'] ?? []);
            if (!empty($allowedFields)) {
                $requestedFields = array_keys($config['fields'] ?? []);
                $disallowed = array_diff($requestedFields, $allowedFields);
                if (!empty($disallowed)) {
                    $fieldList = implode(', ', $disallowed);
                    Log::warning("Workflow UpdateEntity rejected: fields [{$fieldList}] are not in the allowed list for '{$tableName}'");

                    return ['updated' => false, 'error' => "Fields [{$fieldList}] are not allowed for entity type '{$tableName}'."];
                }
            }

            $table = TableRegistry::getTableLocator()->get($tableName);
            $entityId = $this->resolveValue($config['entityId'], $context);
            $entity = $table->get($entityId);
            $fields = [];
            foreach ($config['fields'] as $key => $val) {
                $fields[$key] = $this->resolveValue($val, $context);
            }
            $entity = $table->patchEntity($entity, $fields);
            $result = $table->save($entity);

            return ['updated' => $result !== false];
        } catch (Throwable $e) {
            Log::error('Workflow UpdateEntity failed: ' . $e->getMessage());

            return ['updated' => false];
        }
    }

    /**
     * Assign a role to a member, optionally using ActiveWindow for temporal bounds.
     *
     * When entityType+entityId+startOn are all provided, delegates to ActiveWindowManager
     * so the role assignment is linked to the granting entity with temporal lifecycle.
     *
     * @param array $context Current workflow context
     * @param array $config Action configuration with 'memberId', 'roleId', optional date/entity fields
     * @return array Output with 'memberRoleId'
     */
    public function assignRole(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $roleId = (int)$this->resolveValue($config['roleId'], $context);
            $startOn = !empty($config['startOn']) ? $this->resolveValue($config['startOn'], $context) : null;
            $expiresOn = !empty($config['expiresOn']) ? $this->resolveValue($config['expiresOn'], $context) : null;
            $entityType = !empty($config['entityType']) ? $this->resolveValue($config['entityType'], $context) : null;
            $entityId = !empty($config['entityId']) ? (int)$this->resolveValue($config['entityId'], $context) : null;
            $branchId = !empty($config['branchId']) ? (int)$this->resolveValue($config['branchId'], $context) : null;

            // Delegate to ActiveWindowManager when temporal + entity params are present
            if ($startOn !== null && $entityType !== null && $entityId !== null) {
                $startDateTime = $startOn instanceof DateTime ? $startOn : new DateTime($startOn);
                $expiresDateTime = null;
                if ($expiresOn !== null) {
                    $expiresDateTime = $expiresOn instanceof DateTime ? $expiresOn : new DateTime($expiresOn);
                }

                $result = $this->activeWindowManager->start(
                    $entityType,
                    $entityId,
                    $context['triggeredBy'] ?? $memberId,
                    $startDateTime,
                    $expiresDateTime,
                    null,
                    $roleId,
                    true,
                    $branchId,
                );

                return ['memberRoleId' => $result->isSuccess() ? ($result->getData() ?? true) : null];
            }

            // Standard role assignment without temporal management
            $memberRolesTable = TableRegistry::getTableLocator()->get('MemberRoles');
            $data = [
                'member_id' => $memberId,
                'role_id' => $roleId,
            ];

            if ($startOn !== null) {
                $data['start_on'] = $startOn;
            }
            if ($expiresOn !== null) {
                $data['expires_on'] = $expiresOn;
            }
            if ($entityType !== null) {
                $data['granting_model'] = $entityType;
            }
            if ($entityId !== null) {
                $data['granting_id'] = $entityId;
            }

            $memberRole = $memberRolesTable->newEntity($data);
            $saved = $memberRolesTable->save($memberRole);

            return ['memberRoleId' => $saved ? $saved->id : null];
        } catch (Throwable $e) {
            Log::error('Workflow AssignRole failed: ' . $e->getMessage());

            return ['memberRoleId' => null];
        }
    }

    /**
     * Set a variable in the workflow context.
     *
     * Values prefixed with '=' are evaluated as expressions (date math, templates, etc.).
     * All other values use standard context path resolution for backward compatibility.
     *
     * @param array $context Current workflow context
     * @param array $config Action configuration with 'name' and 'value'
     * @return array Output with the variable name and value
     */
    public function setVariable(array $context, array $config): array
    {
        $name = trim((string)($config['name'] ?? ''));
        $value = $config['value'] ?? null;

        // Expression mode: "=" prefix triggers expression evaluation
        if (is_string($value) && str_starts_with($value, '=')) {
            $expression = substr($value, 1);
            $resolvedValue = $this->expressionEvaluator->evaluate($expression, $context);

            return $this->variableResult($name, $resolvedValue);
        }

        return $this->variableResult($name, $this->resolveValue($value, $context));
    }

    /**
     * Get one registered workflow object by primary key.
     *
     * Only workflow-safe entity fields are returned so this action cannot expose
     * columns filtered out by the workflow entity registry.
     *
     * @param array $context Current workflow context
     * @param array $config Action configuration with 'entityType' and 'entityId'
     * @return array Output with 'found', 'record', and diagnostic fields
     */
    public function getObjectById(array $context, array $config): array
    {
        try {
            $entityType = (string)$this->resolveValue($config['entityType'] ?? '', $context);
            $entityId = $this->resolveValue($config['entityId'] ?? null, $context);
            $registeredEntity = WorkflowEntityRegistry::getEntityWithSchema($entityType);
            if ($registeredEntity === null) {
                return [
                    'found' => false,
                    'record' => null,
                    'entityType' => $entityType,
                    'entityId' => $entityId,
                    'error' => "Entity type '{$entityType}' is not available for workflow operations.",
                ];
            }

            $table = TableRegistry::getTableLocator()->get($this->tableAliasForRegisteredEntity($registeredEntity));
            if (is_array($table->getPrimaryKey())) {
                return [
                    'found' => false,
                    'record' => null,
                    'entityType' => $entityType,
                    'entityId' => $entityId,
                    'error' => "Entity type '{$entityType}' uses a composite primary key and cannot be fetched by one ID.",
                ];
            }

            $fields = array_keys($registeredEntity['fields'] ?? []);
            $query = $table->find()
                ->where([$table->getPrimaryKey() => $entityId]);
            if (!empty($fields)) {
                $query = $query->select($fields);
            }
            $record = $query->first();

            if ($record === null) {
                return [
                    'found' => false,
                    'record' => null,
                    'entityType' => $entityType,
                    'entityId' => $entityId,
                ];
            }

            $recordData = [];
            foreach ($fields as $field) {
                $recordData[$field] = $record->get($field);
            }

            return [
                'found' => true,
                'record' => $recordData,
                'entityType' => $entityType,
                'entityId' => $entityId,
            ];
        } catch (Throwable $e) {
            Log::error('Workflow GetObjectById failed: ' . $e->getMessage());

            return [
                'found' => false,
                'record' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Start temporal management (active window) for an entity.
     *
     * @param array $context Current workflow context
     * @param array $config Action configuration with 'entityType', 'entityId', optional dates and roleId
     * @return array Output with 'memberRoleId' and 'status'
     */
    public function startActiveWindow(array $context, array $config): array
    {
        try {
            $entityType = $this->resolveValue($config['entityType'], $context);
            $entityId = (int)$this->resolveValue($config['entityId'], $context);
            $memberId = (int)($this->resolveValue($config['memberId'] ?? null, $context) ?? $context['triggeredBy'] ?? 0);
            $roleId = !empty($config['roleId']) ? (int)$this->resolveValue($config['roleId'], $context) : null;
            $branchId = !empty($config['branchId']) ? (int)$this->resolveValue($config['branchId'], $context) : null;

            $startOnRaw = $this->resolveValue($config['startOn'] ?? null, $context);
            $startOn = $startOnRaw instanceof DateTime ? $startOnRaw : ($startOnRaw ? new DateTime($startOnRaw) : DateTime::now());

            $expiresOnRaw = $this->resolveValue($config['expiresOn'] ?? null, $context);
            $expiresOn = null;
            if ($expiresOnRaw !== null) {
                $expiresOn = $expiresOnRaw instanceof DateTime ? $expiresOnRaw : new DateTime($expiresOnRaw);
            }

            $closeExisting = $config['closeExisting'] ?? true;

            $result = $this->activeWindowManager->start(
                $entityType,
                $entityId,
                $memberId,
                $startOn,
                $expiresOn,
                null,
                $roleId,
                $closeExisting,
                $branchId,
            );

            return [
                'memberRoleId' => $result->getData(),
                'status' => $result->isSuccess() ? 'started' : 'failed',
                'error' => $result->isSuccess() ? null : $result->getError(),
            ];
        } catch (Throwable $e) {
            Log::error('Workflow StartActiveWindow failed: ' . $e->getMessage());

            return ['memberRoleId' => null, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Stop temporal management (active window) for an entity.
     *
     * @param array $context Current workflow context
     * @param array $config Action configuration with 'entityType', 'entityId', 'newStatus'
     * @return array Output with 'stopped' boolean
     */
    public function stopActiveWindow(array $context, array $config): array
    {
        try {
            $entityType = $this->resolveValue($config['entityType'], $context);
            $entityId = (int)$this->resolveValue($config['entityId'], $context);
            $memberId = (int)($this->resolveValue($config['memberId'] ?? null, $context) ?? $context['triggeredBy'] ?? 0);
            $newStatus = $this->resolveValue($config['newStatus'] ?? ActiveWindowBaseEntity::DEACTIVATED_STATUS, $context);
            $reason = $this->resolveValue($config['reason'] ?? '', $context);

            $expiresOnRaw = $this->resolveValue($config['expiresOn'] ?? null, $context);
            $expiresOn = $expiresOnRaw instanceof DateTime ? $expiresOnRaw : ($expiresOnRaw ? new DateTime($expiresOnRaw) : DateTime::now());

            $result = $this->activeWindowManager->stop(
                $entityType,
                $entityId,
                $memberId,
                $newStatus,
                $reason,
                $expiresOn,
            );

            return ['stopped' => $result->isSuccess(), 'error' => $result->isSuccess() ? null : $result->getError()];
        } catch (Throwable $e) {
            Log::error('Workflow StopActiveWindow failed: ' . $e->getMessage());

            return ['stopped' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Batch sync all temporal statuses (Upcoming→Current, Current→Expired).
     *
     * Mirrors the logic in SyncActiveWindowStatusesCommand for use from workflows.
     *
     * @param array $context Current workflow context
     * @param array $config Action configuration with optional 'entityType' filter
     * @return array Output with transition counts
     */
    public function syncActiveWindowStatuses(array $context, array $config): array
    {
        try {
            $filterEntityType = !empty($config['entityType'])
                ? $this->resolveValue($config['entityType'], $context)
                : null;

            $now = DateTime::now();
            $tableLocator = TableRegistry::getTableLocator();
            $transitioned = ['upcoming_to_current' => 0, 'current_to_expired' => 0];

            if ($filterEntityType !== null) {
                $this->syncTable($tableLocator->get($filterEntityType), $now, $transitioned);
            } else {
                $aliases = $this->discoverActiveWindowAliases($tableLocator);
                foreach ($aliases as $alias) {
                    $this->syncTable($tableLocator->get($alias), $now, $transitioned);
                }
            }

            return ['transitioned' => $transitioned];
        } catch (Throwable $e) {
            Log::error('Workflow SyncActiveWindowStatuses failed: ' . $e->getMessage());

            return ['transitioned' => ['upcoming_to_current' => 0, 'current_to_expired' => 0], 'error' => $e->getMessage()];
        }
    }

    /**
     * Synchronize statuses for a single table.
     *
     * @param \Cake\ORM\Table $table Table to sync
     * @param \Cake\I18n\DateTime $now Current timestamp
     * @param array &$transitioned Transition counters (modified by reference)
     * @return void
     */
    private function syncTable(Table $table, DateTime $now, array &$transitioned): void
    {
        $schema = $table->getSchema();
        if (!$schema->hasColumn('status') || !$schema->hasColumn('start_on')) {
            return;
        }

        // Upcoming → Current
        $upcoming = $table->find()
            ->where([
                $table->aliasField('status') => ActiveWindowBaseEntity::UPCOMING_STATUS,
                $table->aliasField('start_on <=') => $now,
            ])
            ->all();

        foreach ($upcoming as $entity) {
            $entity->set('status', ActiveWindowBaseEntity::CURRENT_STATUS);
            if ($table->save($entity, ['atomic' => false, 'checkRules' => false, 'validate' => false])) {
                $transitioned['upcoming_to_current']++;
            }
        }

        // Current → Expired
        $current = $table->find()
            ->where([
                $table->aliasField('status') => ActiveWindowBaseEntity::CURRENT_STATUS,
                $table->aliasField('expires_on <=') => $now,
            ])
            ->andWhere(function ($exp) use ($table) {
                return $exp->isNotNull($table->aliasField('expires_on'));
            })
            ->all();

        foreach ($current as $entity) {
            $entity->set('status', ActiveWindowBaseEntity::EXPIRED_STATUS);
            if ($table->save($entity, ['atomic' => false, 'checkRules' => false, 'validate' => false])) {
                $transitioned['current_to_expired']++;
            }
        }
    }

    /**
     * Discover table aliases whose entities extend ActiveWindowBaseEntity.
     *
     * @param \Cake\ORM\Locator\TableLocator $tableLocator Table locator instance
     * @return array<string>
     */
    private function discoverActiveWindowAliases(TableLocator $tableLocator): array
    {
        $aliases = [];
        $appTablePath = APP . 'Model' . DS . 'Table' . DS;

        if (is_dir($appTablePath)) {
            $aliases = array_merge($aliases, $this->scanTableDir($appTablePath, $tableLocator));
        }

        foreach (Plugin::loaded() as $plugin) {
            $pluginPath = Plugin::path($plugin) . 'src' . DS . 'Model' . DS . 'Table' . DS;
            if (is_dir($pluginPath)) {
                $aliases = array_merge($aliases, $this->scanTableDir($pluginPath, $tableLocator, $plugin));
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * Build the assign-variable action result and context update payload.
     *
     * @param string $name Variable name
     * @param mixed $value Variable value
     * @return array
     */
    private function variableResult(string $name, mixed $value): array
    {
        return [
            $name => $value,
            'name' => $name,
            'value' => $value,
            '_contextUpdates' => [
                'variables' => [
                    $name => $value,
                ],
            ],
        ];
    }

    /**
     * Resolve a workflow entity registration to a Cake table alias.
     *
     * @param array $registeredEntity Workflow entity registration
     * @return string Table alias for TableLocator
     */
    private function tableAliasForRegisteredEntity(array $registeredEntity): string
    {
        if (!empty($registeredEntity['tableAlias'])) {
            return (string)$registeredEntity['tableAlias'];
        }

        $tableClass = (string)($registeredEntity['tableClass'] ?? '');
        if (
            preg_match(
                '/^(?:(?<plugin>[^\\\\]+)\\\\)?Model\\\\Table\\\\(?<table>[^\\\\]+)Table$/',
                ltrim($tableClass, '\\'),
                $matches,
            )
        ) {
            if (($matches['plugin'] ?? '') && $matches['plugin'] !== 'App') {
                return $matches['plugin'] . '.' . $matches['table'];
            }

            return $matches['table'];
        }

        return (string)($registeredEntity['entityType'] ?? $tableClass);
    }

    /**
     * Scan a directory for Table classes and return aliases of ActiveWindow tables.
     *
     * @param string $path Directory path
     * @param \Cake\ORM\Locator\TableLocator $tableLocator Table locator
     * @param string|null $plugin Plugin prefix
     * @return array<string>
     */
    private function scanTableDir(string $path, TableLocator $tableLocator, ?string $plugin = null): array
    {
        $aliases = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Table.php')) {
                continue;
            }
            $alias = substr($file->getFilename(), 0, -9); // Remove Table.php
            if ($alias === '') {
                continue;
            }
            $fullAlias = $plugin ? "{$plugin}.{$alias}" : $alias;
            try {
                $table = $tableLocator->get($fullAlias);
                $entityClass = $table->getEntityClass();
                if ($entityClass && is_subclass_of($entityClass, ActiveWindowBaseEntity::class) && $table->getSchema()->hasColumn('status')) {
                    $aliases[] = $fullAlias;
                }
                $tableLocator->remove($fullAlias);
            } catch (Throwable $e) {
                // Skip tables that can't be loaded
            }
        }

        return $aliases;
    }
}
