<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\ActionItem;
use App\Model\Entity\BaseEntity;

/**
 * A single parallel checklist item within a bestowal to-do template.
 *
 * Items declare who may complete them (assignee_*) and whether they gate the
 * bestowal's "ready to give" state. Unlike approval steps there is no sequence
 * or threshold — all items are worked in parallel.
 */
class BestowalTodoTemplateItem extends BaseEntity
{
    public const ASSIGNEE_TYPE_ROLE = 'role';

    public const ASSIGNEE_TYPE_PERMISSION = 'permission';

    public const ASSIGNEE_TYPE_OFFICE = 'office';

    public const ASSIGNEE_TYPE_MEMBER = 'member';

    public const ASSIGNEE_TYPE_DYNAMIC = 'dynamic';

    public const BRANCH_MODE_AWARD = 'award_branch';

    public const BRANCH_MODE_ANCESTOR_TYPE = 'ancestor_branch_type';

    public const ASSIGNEE_TYPE_OPTIONS = [
        self::ASSIGNEE_TYPE_ROLE => 'Role',
        self::ASSIGNEE_TYPE_PERMISSION => 'Permission',
        self::ASSIGNEE_TYPE_OFFICE => 'Office',
        self::ASSIGNEE_TYPE_MEMBER => 'Member',
        self::ASSIGNEE_TYPE_DYNAMIC => 'Dynamic resolver',
    ];

    public const BRANCH_MODE_OPTIONS = [
        self::BRANCH_MODE_AWARD => 'Award branch',
        self::BRANCH_MODE_ANCESTOR_TYPE => 'Ancestor branch type',
    ];

    /**
     * Maps template assignee types to the core ActionItem assignee vocabulary.
     * Office is resolved through a dynamic resolver at materialization time, so
     * it maps to the ActionItem dynamic type.
     *
     * @var array<string, string>
     */
    public const ASSIGNEE_TYPE_TO_ACTION_ITEM = [
        self::ASSIGNEE_TYPE_ROLE => ActionItem::ASSIGNEE_TYPE_ROLE,
        self::ASSIGNEE_TYPE_PERMISSION => ActionItem::ASSIGNEE_TYPE_PERMISSION,
        self::ASSIGNEE_TYPE_MEMBER => ActionItem::ASSIGNEE_TYPE_MEMBER,
        self::ASSIGNEE_TYPE_OFFICE => ActionItem::ASSIGNEE_TYPE_DYNAMIC,
        self::ASSIGNEE_TYPE_DYNAMIC => ActionItem::ASSIGNEE_TYPE_DYNAMIC,
    ];

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'template_id' => true,
        'item_key' => true,
        'label' => true,
        'description' => true,
        'assignee_type' => true,
        'assignee_source_id' => true,
        'assignee_source_key' => true,
        'branch_mode' => true,
        'branch_type' => true,
        'is_gating' => true,
        'sort_order' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'bestowal_todo_template' => true,
    ];

    /**
     * Summarize the configured assignee source for grids and detail views.
     *
     * @return string
     */
    protected function _getAssigneeSummary(): string
    {
        $type = self::ASSIGNEE_TYPE_OPTIONS[$this->assignee_type] ?? $this->assignee_type;
        $source = $this->assignee_source_key ?: $this->assignee_source_id;

        return $source ? sprintf('%s: %s', $type, (string)$source) : (string)$type;
    }
}
