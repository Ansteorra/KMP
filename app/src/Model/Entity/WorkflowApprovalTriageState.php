<?php
declare(strict_types=1);

namespace App\Model\Entity;

/**
 * Private workflow approval triage state for one approver.
 *
 * @property int $id
 * @property int $workflow_approval_id
 * @property int $member_id
 * @property string $state
 * @property string|null $note
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 *
 * @property \App\Model\Entity\WorkflowApproval $workflow_approval
 * @property \App\Model\Entity\Member $member
 */
class WorkflowApprovalTriageState extends BaseEntity
{
    public const STATE_NEW = 'new';
    public const STATE_REVIEWING = 'reviewing';
    public const STATE_NEEDS_RESEARCH = 'needs_research';
    public const STATE_READY_TO_DECIDE = 'ready_to_decide';
    public const STATE_ON_HOLD = 'on_hold';

    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'workflow_approval_id' => true,
        'member_id' => true,
        'state' => true,
        'note' => true,
        'created_by' => true,
        'modified_by' => true,
    ];

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::STATE_NEW => __('New'),
            self::STATE_REVIEWING => __('Reviewing'),
            self::STATE_NEEDS_RESEARCH => __('Needs Research'),
            self::STATE_READY_TO_DECIDE => __('Ready to Decide'),
            self::STATE_ON_HOLD => __('On Hold'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function states(): array
    {
        return array_keys(self::labels());
    }
}
