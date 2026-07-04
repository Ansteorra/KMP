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

    public const ITEM_KEY_EVENT_SCHEDULED = 'event_scheduled';

    public const ITEM_KEY_ADDED_TO_AGENDA = 'added_to_agenda';

    public const REQUIRED_FIELD_GATHERING = 'gathering_id';

    public const COMPLETION_PROVIDER_BESTOWAL_GATHERING = 'Awards.BestowalGathering';

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

    public const REQUIRED_FIELD_OPTIONS = [
        '' => 'None',
        self::REQUIRED_FIELD_GATHERING => 'Bestowal gathering',
    ];

    /**
     * Default required-field metadata for built-in template items.
     *
     * @param string|null $sourceRef Action item source reference.
     * @return array<string, mixed>|null
     */
    public static function getDefaultRequiredFieldConfigForSourceRef(?string $sourceRef): ?array
    {
        if ($sourceRef !== self::ITEM_KEY_EVENT_SCHEDULED) {
            return null;
        }

        return [
            'provider' => self::COMPLETION_PROVIDER_BESTOWAL_GATHERING,
            'field' => self::REQUIRED_FIELD_GATHERING,
            'label' => 'Bestowal Gathering',
            'help' => 'Choose the gathering where this bestowal will be presented.',
            'conditional_complete_on_assign' => true,
        ];
    }

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
        'required_field' => true,
        'required_field_config' => true,
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

    /**
     * Build completion metadata consumed by core ActionItems.
     *
     * @return array<string, mixed>|null
     */
    public function getCompletionConfig(): ?array
    {
        $requiredField = (string)($this->required_field ?? '');
        if ($requiredField === '') {
            return null;
        }

        $config = $this->required_field_config;
        if (!is_array($config)) {
            $config = [];
        }

        $fieldConfig = [
            'provider' => $config['provider'] ?? self::providerForRequiredField($requiredField),
            'field' => $requiredField,
            'label' => $config['label'] ?? self::labelForRequiredField($requiredField),
            'help' => $config['help'] ?? self::helpForRequiredField($requiredField),
            'conditional_complete_on_assign' => (bool)($config['conditional_complete_on_assign'] ?? true),
        ];

        return [
            'required_fields' => [$fieldConfig],
        ];
    }

    /**
     * @param string $requiredField Required field key.
     * @return string|null Completion provider key.
     */
    public static function providerForRequiredField(string $requiredField): ?string
    {
        return $requiredField === self::REQUIRED_FIELD_GATHERING
            ? self::COMPLETION_PROVIDER_BESTOWAL_GATHERING
            : null;
    }

    /**
     * @param string $requiredField Required field key.
     * @return string Label.
     */
    public static function labelForRequiredField(string $requiredField): string
    {
        return self::REQUIRED_FIELD_OPTIONS[$requiredField] ?? $requiredField;
    }

    /**
     * @param string $requiredField Required field key.
     * @return string Help text.
     */
    public static function helpForRequiredField(string $requiredField): string
    {
        return $requiredField === self::REQUIRED_FIELD_GATHERING
            ? 'Choose the future event or court where this bestowal will be presented.'
            : '';
    }
}
