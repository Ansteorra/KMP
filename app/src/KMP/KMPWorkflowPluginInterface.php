<?php

declare(strict_types=1);

namespace App\KMP;

/**
 * Interface for plugins that provide workflow components.
 *
 * Plugins implementing this interface register their triggers, actions,
 * conditions, and entity types with the workflow engine during bootstrap.
 */
interface KMPWorkflowPluginInterface
{
    /**
     * Register workflow triggers that this plugin provides.
     * Called during application bootstrap.
     *
     * @return array Array of trigger definitions, each with:
     *   - event: string (unique key like 'PluginName.EventName')
     *   - label: string (human-readable name)
     *   - description: string
     *   - payloadSchema: array (describes data fields the event provides)
     */
    public function getWorkflowTriggers(): array;

    /**
     * Register workflow actions that this plugin provides.
     *
     * @return array Array of action definitions, each with:
     *   - action: string (unique key like 'PluginName.ActionName')
     *   - label: string
     *   - description: string
     *   - inputSchema: array
     *   - outputSchema: array
     *   - serviceClass: string (FQCN)
     *   - serviceMethod: string
     *   - isAsync: bool
     */
    public function getWorkflowActions(): array;

    /**
     * Register workflow conditions that this plugin provides.
     *
     * @return array Array of condition definitions, each with:
     *   - condition: string (unique key like 'PluginName.ConditionName')
     *   - label: string
     *   - description: string
     *   - inputSchema: array
     *   - evaluatorClass: string (FQCN)
     *   - evaluatorMethod: string
     */
    public function getWorkflowConditions(): array;

    /**
     * Register entity types that this plugin's workflows operate on.
     *
     * @return array Array of entity definitions, each with:
     *   - entityType: string (unique key like 'PluginName.EntityName')
     *   - label: string
     *   - description: string
     *   - tableClass: string (FQCN of CakePHP Table class)
     *   - fields: array (field definitions with types)
     */
    public function getWorkflowEntities(): array;
}
