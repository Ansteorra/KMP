<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Model\Entity\WorkflowVersion;
use App\Services\ServiceResult;

/**
 * Contract for managing workflow version lifecycle.
 *
 * Handles draft creation, publishing, archiving, instance migration,
 * and version comparison for workflow definitions.
 */
interface WorkflowVersionManagerInterface
{
    /**
     * Create a new draft version for a workflow definition.
     *
     * @param int $definitionId Workflow definition to version
     * @param array $definition Workflow graph (nodes, edges)
     * @param array|null $canvasLayout Optional visual layout data
     * @param string|null $changeNotes Optional notes describing changes
     * @return \App\Services\ServiceResult
     */
    public function createDraft(int $definitionId, array $definition, ?array $canvasLayout = null, ?string $changeNotes = null): ServiceResult;

    /**
     * Update an existing draft version.
     *
     * @param int $versionId Draft version to update
     * @param array $definition Updated workflow graph
     * @param array|null $canvasLayout Optional visual layout data
     * @param string|null $changeNotes Optional notes describing changes
     * @return \App\Services\ServiceResult
     */
    public function updateDraft(int $versionId, array $definition, ?array $canvasLayout = null, ?string $changeNotes = null): ServiceResult;

    /**
     * Publish a draft version, making it the active version.
     *
     * @param int $versionId Draft version to publish
     * @param int $publishedBy ID of the user publishing
     * @return \App\Services\ServiceResult
     */
    public function publish(int $versionId, int $publishedBy): ServiceResult;

    /**
     * Archive a version, removing it from active use.
     *
     * @param int $versionId Version to archive
     * @return \App\Services\ServiceResult
     */
    public function archive(int $versionId): ServiceResult;

    /**
     * Get the currently published version for a definition.
     *
     * @param int $definitionId Workflow definition ID
     * @return \App\Model\Entity\WorkflowVersion|null
     */
    public function getCurrentVersion(int $definitionId): ?WorkflowVersion;

    /**
     * Get all versions for a definition, newest first.
     *
     * @param int $definitionId Workflow definition ID
     * @return \App\Model\Entity\WorkflowVersion[]
     */
    public function getVersionHistory(int $definitionId): array;

    /**
     * Migrate a running instance to a different version.
     *
     * @param int $instanceId Instance to migrate
     * @param int $targetVersionId Target version (must be published)
     * @param int $migratedBy ID of the user performing migration
     * @param array|null $nodeMapping Optional explicit node key mapping
     * @return \App\Services\ServiceResult
     */
    public function migrateInstance(int $instanceId, int $targetVersionId, int $migratedBy, ?array $nodeMapping = null): ServiceResult;

    /**
     * Compare two versions and return structural differences.
     *
     * @param int $versionId1 First version ID
     * @param int $versionId2 Second version ID
     * @return array{added: array, removed: array, modified: array}
     */
    public function compareVersions(int $versionId1, int $versionId2): array;
}
