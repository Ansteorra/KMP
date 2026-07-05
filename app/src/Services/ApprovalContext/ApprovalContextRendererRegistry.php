<?php
declare(strict_types=1);

namespace App\Services\ApprovalContext;

use App\Model\Entity\WorkflowInstance;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * Static registry for approval context renderers.
 *
 * Plugins register renderers keyed by source name. When the approvals UI
 * needs display context for a workflow instance, the registry iterates
 * registered renderers and returns the first match, falling back to a
 * generic default context.
 *
 * Follows the same static-registry pattern as WorkflowActionRegistry.
 */
class ApprovalContextRendererRegistry
{
    /**
     * @var array<string, \App\Services\ApprovalContext\ApprovalContextRendererInterface>
     */
    private static array $renderers = [];

    private static bool $initialized = false;

    /**
     * @var array<string, \App\Services\ApprovalContext\ApprovalContext>
     */
    private static array $contextCache = [];

    /**
     * Register a renderer for a given source (e.g. 'Authorizations', 'Awards').
     *
     * @param string $source Plugin or module identifier
     * @param \App\Services\ApprovalContext\ApprovalContextRendererInterface $renderer Renderer instance
     * @return void
     */
    public static function register(string $source, ApprovalContextRendererInterface $renderer): void
    {
        self::$renderers[$source] = $renderer;
    }

    /**
     * Render approval context for a workflow instance.
     *
     * Returns the result from the first renderer that can handle the
     * instance, or a generic fallback context.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance
     * @return \App\Services\ApprovalContext\ApprovalContext
     */
    public static function render(WorkflowInstance $instance): ApprovalContext
    {
        self::ensureInitialized();
        $cacheKey = self::contextCacheKey($instance);
        if ($cacheKey !== null && isset(self::$contextCache[$cacheKey])) {
            return self::$contextCache[$cacheKey];
        }

        foreach (self::$renderers as $renderer) {
            if ($renderer->canRender($instance)) {
                $context = $renderer->render($instance);

                if ($cacheKey !== null) {
                    self::$contextCache[$cacheKey] = $context;
                }

                return $context;
            }
        }

        $context = self::getDefaultContext($instance);
        if ($cacheKey !== null) {
            self::$contextCache[$cacheKey] = $context;
        }

        return $context;
    }

    /**
     * Build a generic fallback context when no renderer matches.
     *
     * Attempts to load the entity and derive a display name from
     * common naming fields (name, sca_name, title).
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance
     * @return \App\Services\ApprovalContext\ApprovalContext
     */
    public static function getDefaultContext(WorkflowInstance $instance): ApprovalContext
    {
        $entityType = $instance->entity_type ?? 'Unknown';
        $entityId = $instance->entity_id;
        $name = "#{$entityId}";

        if ($entityType !== 'Unknown' && $entityId !== null) {
            try {
                $table = TableRegistry::getTableLocator()->get($entityType);
                $entity = $table->find()->where(['id' => $entityId])->first();

                if ($entity !== null) {
                    $name = $entity->name ?? $entity->sca_name ?? $entity->title ?? $name;
                }
            } catch (Exception $e) {
                Log::warning(sprintf(
                    'ApprovalContextRendererRegistry: Could not load %s#%s — %s',
                    $entityType,
                    $entityId,
                    $e->getMessage(),
                ));
            }
        }

        return new ApprovalContext(
            title: sprintf('Approval Required: %s', $entityType),
            description: sprintf('Approval requested for %s %s', $entityType, $name),
            fields: [
                ['label' => 'Entity Type', 'value' => $entityType],
                ['label' => 'Entity', 'value' => (string)$name],
            ],
            icon: 'bi-question-circle',
        );
    }

    /**
     * Get all registered renderers.
     *
     * @return array<string, \App\Services\ApprovalContext\ApprovalContextRendererInterface>
     */
    public static function getAllRenderers(): array
    {
        self::ensureInitialized();

        return self::$renderers;
    }

    /**
     * Get all registered source identifiers.
     *
     * @return array<int, string>
     */
    public static function getRegisteredSources(): array
    {
        self::ensureInitialized();

        return array_keys(self::$renderers);
    }

    /**
     * Check if a source has a registered renderer.
     *
     * @param string $source Source identifier
     * @return bool
     */
    public static function isRegistered(string $source): bool
    {
        self::ensureInitialized();

        return isset(self::$renderers[$source]);
    }

    /**
     * Remove the renderer for a specific source.
     *
     * @param string $source Source identifier
     * @return void
     */
    public static function unregister(string $source): void
    {
        unset(self::$renderers[$source]);
    }

    /**
     * Clear all registered renderers. Primarily for testing.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$renderers = [];
        self::$initialized = false;
        self::$contextCache = [];
    }

    /**
     * Ensure the registry is initialized.
     *
     * @return void
     */
    private static function ensureInitialized(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
    }

    /**
     * Build a request-local cache key for persisted workflow instances.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance.
     * @return string|null
     */
    private static function contextCacheKey(WorkflowInstance $instance): ?string
    {
        if (empty($instance->id)) {
            return null;
        }

        return implode(':', [
            (int)$instance->id,
            (string)($instance->entity_type ?? ''),
            (string)($instance->entity_id ?? ''),
            (string)($instance->workflow_definition_id ?? ''),
            (string)($instance->modified?->toUnixString() ?? ''),
        ]);
    }
}
