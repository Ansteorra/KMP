<?php
declare(strict_types=1);

namespace App\Model\Entity;

/**
 * ActionItem Entity - a reusable, polymorphic "to-do" / check.
 *
 * Each action item is attached to an arbitrary owner entity via entity_type +
 * entity_id and declares who may complete it using the same assignee vocabulary
 * as the workflow approval subsystem.
 *
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $title
 * @property string|null $description
 * @property string $assignee_type
 * @property array|null $assignee_config
 * @property string|null $assignee_lookup_type
 * @property int|null $assignee_lookup_id
 * @property string|null $assignee_lookup_name
 * @property int|null $assignee_lookup_branch_id
 * @property int|null $branch_id
 * @property string $status
 * @property bool $is_gating
 * @property int $sort_order
 * @property string|null $source_ref
 * @property array|null $completion_config
 * @property \Cake\I18n\DateTime|null $completed_at
 * @property int|null $completed_by
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \App\Model\Entity\Member|null $completed_by_member
 * @property \App\Model\Entity\ActionItemLog[] $action_item_logs
 */
class ActionItem extends BaseEntity
{
    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const ASSIGNEE_TYPE_PERMISSION = 'permission';

    public const ASSIGNEE_TYPE_ROLE = 'role';

    public const ASSIGNEE_TYPE_MEMBER = 'member';

    public const ASSIGNEE_TYPE_DYNAMIC = 'dynamic';

    public const ASSIGNEE_TYPE_POLICY = 'policy';

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'entity_type' => true,
        'entity_id' => true,
        'title' => true,
        'description' => true,
        'assignee_type' => true,
        'assignee_config' => true,
        'assignee_lookup_type' => true,
        'assignee_lookup_id' => true,
        'assignee_lookup_name' => true,
        'assignee_lookup_branch_id' => true,
        'branch_id' => true,
        'status' => true,
        'is_gating' => true,
        'sort_order' => true,
        'source_ref' => true,
        'completion_config' => true,
        'completed_at' => true,
        'completed_by' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'completed_by_member' => true,
        'action_item_logs' => true,
    ];

    /**
     * Whether this item is still open (actionable).
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Whether this item has been completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Return normalized completion-time required-field metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRequiredFieldConfigs(): array
    {
        $config = $this->completion_config;
        if (!is_array($config)) {
            return [];
        }

        $requiredFields = $config['required_fields'] ?? [];
        if (!is_array($requiredFields)) {
            return [];
        }

        return array_values(array_filter(
            $requiredFields,
            static fn(mixed $field): bool => is_array($field) && !empty($field['field']),
        ));
    }

    /**
     * Whether the action item declares completion requirements.
     *
     * @return bool
     */
    public function hasCompletionRequirements(): bool
    {
        return $this->getRequiredFieldConfigs() !== [];
    }
}
