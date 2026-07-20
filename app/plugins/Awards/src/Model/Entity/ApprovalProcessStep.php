<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * Ordered approval step within an award approval process.
 */
class ApprovalProcessStep extends BaseEntity
{
    public const STEP_TYPE_APPROVAL = 'approval';

    public const APPROVER_TYPE_ROLE = 'role';
    public const APPROVER_TYPE_PERMISSION = 'permission';
    public const APPROVER_TYPE_OFFICE = 'office';
    public const APPROVER_TYPE_MEMBER = 'member';
    public const APPROVER_TYPE_DYNAMIC = 'dynamic';

    public const BRANCH_MODE_AWARD = 'award_branch';
    public const BRANCH_MODE_ANCESTOR_TYPE = 'ancestor_branch_type';

    public const THRESHOLD_ANY = 'any';
    public const THRESHOLD_ALL = 'all';
    public const THRESHOLD_COUNT = 'count';

    public const ACTION_RETURN_PREVIOUS = 'return_previous';
    public const ACTION_RETURN_STEP_PREFIX = 'return_step:';
    public const ACTION_CLOSE = 'close';

    public const APPROVER_TYPE_OPTIONS = [
        self::APPROVER_TYPE_ROLE => 'Role',
        self::APPROVER_TYPE_PERMISSION => 'Permission',
        self::APPROVER_TYPE_OFFICE => 'Office',
        self::APPROVER_TYPE_MEMBER => 'Member',
        self::APPROVER_TYPE_DYNAMIC => 'Dynamic resolver',
    ];

    public const BRANCH_MODE_OPTIONS = [
        self::BRANCH_MODE_AWARD => 'Award branch',
        self::BRANCH_MODE_ANCESTOR_TYPE => 'Ancestor branch type',
    ];

    public const THRESHOLD_MODE_OPTIONS = [
        self::THRESHOLD_ANY => 'Any one approver',
        self::THRESHOLD_ALL => 'All resolved approvers',
        self::THRESHOLD_COUNT => 'Specific number of approvers',
    ];

    public const ACTION_OPTIONS = [
        self::ACTION_RETURN_PREVIOUS => 'Return to previous step',
        self::ACTION_CLOSE => 'Close recommendation',
    ];

    protected array $_accessible = [
        'approval_process_id' => true,
        'step_key' => true,
        'label' => true,
        'sequence' => true,
        'step_type' => true,
        'approver_type' => true,
        'approver_source_id' => true,
        'approver_source_key' => true,
        'branch_mode' => true,
        'branch_type' => true,
        'threshold_mode' => true,
        'required_count' => true,
        'on_reject' => true,
        'on_request_changes' => true,
        'retain_read_visibility' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'approval_process' => true,
    ];

    /**
     * Summarize the configured approver source.
     *
     * @return string
     */
    protected function _getApproverSummary(): string
    {
        $type = self::APPROVER_TYPE_OPTIONS[$this->approver_type] ?? $this->approver_type;
        $source = $this->approver_source_key ?: $this->approver_source_id;

        return $source ? sprintf('%s: %s', $type, (string)$source) : (string)$type;
    }

    /**
     * Summarize the configured approval threshold.
     *
     * @return string
     */
    protected function _getThresholdSummary(): string
    {
        if ($this->threshold_mode === self::THRESHOLD_COUNT) {
            return (string)__('{0} approvers', $this->required_count);
        }

        return self::THRESHOLD_MODE_OPTIONS[$this->threshold_mode] ?? (string)$this->threshold_mode;
    }
}
