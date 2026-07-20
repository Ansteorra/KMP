<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WorkflowVersion Entity
 *
 * Immutable versioned snapshot of a workflow design. Each version contains
 * the full workflow graph and optional visual canvas layout.
 *
 * @property int $id
 * @property int $workflow_definition_id
 * @property int $version_number
 * @property array $definition
 * @property array|null $canvas_layout
 * @property string $status
 * @property \Cake\I18n\DateTime|null $published_at
 * @property int|null $published_by
 * @property string|null $change_notes
 * @property int|null $created_by
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\WorkflowDefinition $workflow_definition
 */
class WorkflowVersion extends BaseEntity
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'workflow_definition_id' => true,
        'version_number' => true,
        'definition' => true,
        'canvas_layout' => true,
        'status' => true,
        'published_at' => true,
        'published_by' => true,
        'change_notes' => true,
        'created_by' => true,
    ];

    /**
     * Check if this version is published.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Check if this version is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
