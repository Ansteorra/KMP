<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WorkflowApprovalResponse Entity
 *
 * Individual approval decision from a member for a workflow approval gate.
 *
 * @property int $id
 * @property int $workflow_approval_id
 * @property int $member_id
 * @property string $decision
 * @property string|null $comment
 * @property \Cake\I18n\DateTime $responded_at
 * @property \Cake\I18n\DateTime|null $created
 *
 * @property \App\Model\Entity\WorkflowApproval $workflow_approval
 * @property \App\Model\Entity\Member $member
 */
class WorkflowApprovalResponse extends BaseEntity
{
    public const DECISION_APPROVE = 'approve';
    public const DECISION_REJECT = 'reject';
    public const DECISION_ABSTAIN = 'abstain';
    public const DECISION_REQUEST_CHANGES = 'request_changes';

    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'workflow_approval_id' => true,
        'member_id' => true,
        'decision' => true,
        'comment' => true,
        'responded_at' => true,
    ];
}
