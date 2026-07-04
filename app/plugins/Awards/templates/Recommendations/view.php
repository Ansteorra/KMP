<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Recommendation $recommendation
 * @var array $memberAttendanceGatherings
 * @var array $workflowContext
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Award Recommendation - ' . $recommendation->member_sca_name . ' for ' . $recommendation->award->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($recommendation->member_sca_name . ' for ' . $recommendation->award->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($user->checkCan('edit', $recommendation)) : ?>
<button type="button" class="btn btn-primary btn-sm edit-rec" data-bs-toggle="modal" data-bs-target="#editModal"
    data-controller="outlet-btn" data-action="click->outlet-btn#fireNotice"
    data-outlet-btn-btn-data-value='{ "id":<?= $recommendation->id ?>}'>Edit</button>
<?php endif; ?>
<?php if ($user->checkCan('requestFeedback', $recommendation)) : ?>
<button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
    data-bs-target="#requestRecommendationFeedbackModal" data-controller="outlet-btn"
    data-action="click->outlet-btn#fireNotice" data-outlet-btn-btn-data-value='{ "id":<?= $recommendation->id ?>}'>
    <i class="bi bi-chat-left-text"></i> <?= __('Request Feedback') ?>
</button>
<?php endif; ?>
<?php if (!empty($workflowContext['canStartWorkflow']) && $user->checkCan('startApprovalWorkflow', $recommendation)) : ?>
<?= $this->Form->postLink(
        '<i class="bi bi-play-circle"></i> ' . __('Start Approval'),
        ['action' => 'startApprovalWorkflow', $recommendation->id],
        [
            'class' => 'btn btn-outline-success btn-sm',
            'confirm' => __('Start a new approval workflow for this recommendation?'),
            'escape' => false,
        ],
    ) ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<?php if ($recommendation->isGroupChild()) : ?>
<tr>
    <td colspan="2">
        <div class="alert alert-info mb-0">
            <i class="bi bi-link-45deg"></i>
            <?= __('This recommendation is linked to a group.') ?>
            <?= $this->Html->link(
                    __('View Group Head'),
                    ['action' => 'view', $recommendation->recommendation_group_id],
                    ['class' => 'alert-link']
                ) ?>
        </div>
    </td>
</tr>
<?php endif; ?>
<?php if ($recommendation->isLockedByBestowal()) : ?>
<tr>
    <td colspan="2">
        <?= $this->element('recommendation_bestowal_lock_notice', ['recommendation' => $recommendation]) ?>
    </td>
</tr>
<?php endif; ?>
<tr>
    <th scope="row"><?= __('Award') ?></th>
    <td><?= $recommendation->hasValue('award') ? $this->Html->link($recommendation->award->name, ['controller' => 'Awards', 'action' => 'view', $recommendation->award->id]) : '' ?>
        <?= h(($recommendation->specialty ? " (" . $recommendation->specialty . ")" : "")) ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Member Sca Name') ?></th>
    <td><?php
        if ($recommendation->member_id == null) {
            echo h($recommendation->member_sca_name);
        } else {
            if ($user->checkCan('view', $recommendation->member)) {
                echo $this->Html->link($recommendation->member->sca_name, ['plugin' => null, 'controller' => 'Members', 'action' => 'view', $recommendation->member_id]);
            } else {
                echo h($recommendation->member->sca_name);
            }
        } ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Reason') ?></th>
    <td><?= $this->Text->autoParagraph(h($recommendation->reason)) ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Status') ?></th>
    <td><?= h($recommendation->status)  ?>
        <?php
        if ($recommendation->close_reason) {
            echo " - " . h($recommendation->close_reason);
        } ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('State') ?></th>
    <td><?= h($recommendation->state) ?>
        <?php
        if ($recommendation->given != null) :
            // Format as date only (no timezone conversion) since it's stored as midnight UTC
            $given = $recommendation->given->format('F j, Y');
            if ($recommendation->assigned_gathering):
                $gatheringName = $recommendation->assigned_gathering->name;
                $gatheringLink = $this->Html->link(
                    $gatheringName,
                    ['plugin' => null, 'controller' => 'Gatherings', 'action' => 'view', $recommendation->assigned_gathering->public_id],
                    ['data-turbo-frame' => '_top']
                );
                $isCancelled = $recommendation->assigned_gathering->cancelled_at !== null;
                if ($isCancelled) {
                    echo ' at <span class="text-danger fw-bold">[CANCELLED]</span> ' . $gatheringLink . ' on ' . $given;
                } else {
                    echo " at " . $gatheringLink . " on " . $given;
                }
            else:
                echo " on " . $given;
            endif;
        endif;
        if ($recommendation->assigned_gathering && $recommendation->given == null):
            $gatheringName = $recommendation->assigned_gathering->name;
            $gatheringLink = $this->Html->link(
                $gatheringName,
                ['plugin' => null, 'controller' => 'Gatherings', 'action' => 'view', $recommendation->assigned_gathering->public_id],
                ['data-turbo-frame' => '_top']
            );
            $isCancelled = $recommendation->assigned_gathering->cancelled_at !== null;
            if ($isCancelled) {
                echo '<div class="alert alert-danger mt-2 mb-0 py-1 px-2"><i class="bi bi-exclamation-triangle-fill"></i> <strong>' . __('Warning:') . '</strong> ' . __('Scheduled for cancelled gathering:') . ' <span class="fw-bold">[CANCELLED]</span> ' . $gatheringLink . '. ' . __('Please reschedule.') . '</div>';
            } else {
                echo "to be given at " . $gatheringLink;
            }
        endif; ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Requester Sca Name') ?></th>
    <td><?php
        if ($recommendation->requester_id == null) {
            echo h($recommendation->requester_sca_name);
        } else {
            if ($user->checkCan('view', $recommendation->requester)) {
                echo $this->Html->link($recommendation->requester->sca_name, ['plugin' => null, 'controller' => 'Members', 'action' => 'view', $recommendation->requester_id]);
            } else {
                echo h($recommendation->requester->sca_name);
            }
        } ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Contact Email') ?></th>
    <td><?= h($recommendation->contact_email) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Contact Number') ?></th>
    <td><?= h($recommendation->contact_number) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Suggested Gatherings') ?></th>
    <td>
        <ul>
            <?php foreach ($recommendation->gatherings as $gathering) :
                $isCancelled = $gathering->cancelled_at !== null;
            ?>
            <li>
                <?php if ($isCancelled): ?><span class="text-danger fw-bold">[CANCELLED]</span> <?php endif; ?>
                <?= $this->Html->link($gathering->name, ['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id, 'plugin' => null], $isCancelled ? ['class' => 'text-danger'] : []) ?>
                <?php if ($recommendation->gathering_id == $gathering->id) {
                        echo " (Plan to Give)";
                    } ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </td>
</tr>
<?php if (!empty($memberAttendanceGatherings)) : ?>
<tr>
    <th scope="row"><?= __('Member\'s Planned Attendance') ?></th>
    <td>
        <ul>
            <?php foreach ($memberAttendanceGatherings as $gathering) : ?>
            <li>
                <?= $this->Html->link($gathering->name, ['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id, 'plugin' => null]) ?>
                <?php if ($gathering->start_date) : ?>
                <small class="text-muted">(<?= $gathering->start_date->format('M j, Y') ?>)</small>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <small class="text-muted">
            <i class="bi bi-info-circle"></i> Events the member has indicated they plan to attend
        </small>
    </td>
</tr>
<?php endif; ?>
<tr>
    <th scope="row"><?= __('Call Into Court') ?></th>
    <td><?= h($recommendation->call_into_court) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Court Availability') ?></th>
    <td><?= h($recommendation->court_availability) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Person to Notify') ?></th>
    <td><?= h($recommendation->person_to_notify) ?></td>
</tr>
<?php
$this->KMP->endBlock() ?>
<?php

use App\KMP\WorkflowApprovalDecisionOptions;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;

$feedbackRequests = [];
foreach ($recommendation->feedback_request_items ?? [] as $feedbackItem) {
    if (!empty($feedbackItem->feedback_request)) {
        $feedbackRequests[$feedbackItem->feedback_request->id] = $feedbackItem->feedback_request;
    }
}
$runStatusLabel = static function (?string $status): string {
    if ($status === null || $status === '') {
        return __('No workflow');
    }

    return ucwords(str_replace('_', ' ', $status));
};
$runStatusClass = static function (?string $status): string {
    return match ($status) {
        'in_progress', 'changes_requested' => 'bg-primary',
        'approved', 'consumed' => 'bg-success',
        'closed', 'cancelled' => 'bg-secondary',
        default => 'bg-light text-dark',
    };
};
?>
<?php $this->KMP->startBlock("tabButtons") ?>
<?php if (!empty($recommendation->group_children)) : ?>
<button class="nav-link" id="nav-grouped-tab" data-bs-toggle="tab" data-bs-target="#nav-grouped" type="button"
    role="tab" aria-controls="nav-grouped" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="5"
    style="order: 5;">
    <?= __("Grouped") ?> <span class="badge bg-info"><?= count($recommendation->group_children) ?></span>
</button>
<?php endif; ?>
<button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button" role="tab"
    aria-controls="nav-notes" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="10"
    style="order: 10;"><?= __("Notes") ?>
</button>
<button class="nav-link" id="nav-approval-tab" data-bs-toggle="tab" data-bs-target="#nav-approval" type="button"
    role="tab" aria-controls="nav-approval" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="12"
    style="order: 12;">
    <?= __("Approval") ?>
    <?php if (!empty($workflowContext['activeRun'])) : ?>
    &nbsp;<span class="badge bg-primary"><?= __('Active') ?></span>
    <?php endif; ?>
</button>
<?php if (!empty($feedbackRequests)) : ?>
<button class="nav-link" id="nav-feedback-tab" data-bs-toggle="tab" data-bs-target="#nav-feedback" type="button"
    role="tab" aria-controls="nav-feedback" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="15"
    style="order: 15;">
    <?= __("Feedback") ?> <span class="badge bg-info"><?= count($feedbackRequests) ?></span>
</button>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<?php if (!empty($recommendation->group_children)) : ?>
<div class="related tab-pane fade m-3" id="nav-grouped" role="tabpanel" aria-labelledby="nav-grouped-tab"
    data-detail-tabs-target="tabContent" data-tab-order="5" style="order: 5;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-collection"></i> <?= __('Grouped Recommendations') ?></h5>
        <?php if ($user->checkCan('edit', $recommendation)) : ?>
        <?= $this->Form->postLink(
                    '<i class="bi bi-x-circle"></i> ' . __('Ungroup All'),
                    ['action' => 'ungroupRecommendations'],
                    [
                        'data' => ['recommendation_id' => $recommendation->id],
                        'confirm' => __('Ungroup all children? They will be restored to their previous states.'),
                        'class' => 'btn btn-outline-warning btn-sm',
                        'escape' => false,
                    ]
                ) ?>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?= __('Award') ?></th>
                    <th><?= __('For') ?></th>
                    <th><?= __('Reason') ?></th>
                    <th><?= __('Requester') ?></th>
                    <th><?= __('Submitted') ?></th>
                    <?php if ($user->checkCan('edit', $recommendation)) : ?>
                    <th class="text-end"><?= __('Actions') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recommendation->group_children as $child) : ?>
                <tr>
                    <td><?= h($child->award->abbreviation ?? $child->award->name ?? '—') ?></td>
                    <td><?= h($child->member_sca_name) ?></td>
                    <td><?= $this->Text->autoParagraph(h($child->reason ?? '')) ?></td>
                    <td><?= h($child->requester->sca_name ?? $child->requester_sca_name) ?></td>
                    <td><?= $child->created ? $child->created->format('Y-m-d') : '—' ?></td>
                    <?php if ($user->checkCan('edit', $recommendation)) : ?>
                    <td class="text-end text-nowrap">
                        <?= $this->Html->link(
                                        '<i class="bi bi-eye"></i>',
                                        ['action' => 'view', $child->id],
                                        ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => __('View')]
                                    ) ?>
                        <?= $this->Form->postLink(
                                        '<i class="bi bi-x-lg"></i>',
                                        ['action' => 'removeFromGroup'],
                                        [
                                            'data' => ['recommendation_id' => $child->id],
                                            'confirm' => __('Remove this recommendation from the group?'),
                                            'class' => 'btn btn-sm btn-outline-danger',
                                            'escape' => false,
                                            'title' => __('Remove from group'),
                                        ]
                                    ) ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<div class="related tab-pane fade m-3" id="nav-notes" role="tabpanel" aria-labelledby="nav-notes-tab"
    data-detail-tabs-target="tabContent">
    <?= $this->cell('Notes', [
        'entity_id' => $recommendation->id,
        'entity_type' => 'Awards.Recommendations',
        'viewPrivate' => $user->checkCan("viewPrivateNotes", $recommendation),
        'canCreate' => $user->checkCan('edit', $recommendation),
    ]) ?>
</div>
<div class="related tab-pane fade m-3" id="nav-approval" role="tabpanel" aria-labelledby="nav-approval-tab"
    data-detail-tabs-target="tabContent" data-tab-order="12" style="order: 12;">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h5 class="mb-1"><i class="bi bi-diagram-3" aria-hidden="true"></i> <?= __('Approval Workflow') ?></h5>
            <?php $latestRun = $workflowContext['latestRun'] ?? null; ?>
            <p class="text-muted mb-0">
                <?php if ($latestRun) : ?>
                <?= __('Latest workflow status:') ?>
                <span class="badge <?= h($runStatusClass((string)$latestRun->status)) ?>">
                    <?= h($runStatusLabel((string)$latestRun->status)) ?>
                </span>
                <?php if (!empty($latestRun->current_step_label)) : ?>
                <?= __('Current step: {0}', h($latestRun->current_step_label)) ?>
                <?php endif; ?>
                <?php else : ?>
                <?= __('No approval workflow has been started for this recommendation yet.') ?>
                <?php endif; ?>
            </p>
        </div>
        <?php if (!empty($workflowContext['canStartWorkflow']) && $user->checkCan('startApprovalWorkflow', $recommendation)) : ?>
        <?= $this->Form->postLink(
                '<i class="bi bi-play-circle"></i> ' . __('Start Approval Workflow'),
                ['action' => 'startApprovalWorkflow', $recommendation->id],
                [
                    'class' => 'btn btn-outline-success btn-sm',
                    'confirm' => __('Start a new approval workflow for this recommendation?'),
                    'escape' => false,
                ],
            ) ?>
        <?php endif; ?>
    </div>

    <?php $workflowSummary = $workflowContext['summary'] ?? []; ?>
    <?php if (!empty($workflowSummary['hasRun'])) : ?>
    <section class="card cardbox mb-3" aria-labelledby="approval-progress-heading">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <?php if (false == true): ?>
                <div class="flex-grow-1">
                    <h6 class="mb-2" id="approval-progress-heading">
                        <i class="bi bi-list-check me-1" aria-hidden="true"></i><?= __('Approval Progress') ?>
                    </h6>
                    <div class="progress mb-2" role="img"
                        aria-label="<?= h(__('Approval workflow is {0}% complete', (int)$workflowSummary['progressPercent'])) ?>">
                        <div class="progress-bar" style="width: <?= (int)$workflowSummary['progressPercent'] ?>%"></div>
                    </div>
                    <p class="text-muted mb-0">
                        <?= __(
                                    '{0} of {1} approval steps complete',
                                    (int)$workflowSummary['completedSteps'],
                                    (int)$workflowSummary['totalSteps'],
                                ) ?>
                    </p>
                </div>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2" aria-label="<?= h(__('Approval workflow counts')) ?>">
                    <span class="badge text-bg-success p-2">
                        <?= __('{0} Complete', (int)$workflowSummary['completedSteps']) ?>
                    </span>
                    <span class="badge text-bg-warning p-2">
                        <?= __('{0} Pending Steps', (int)$workflowSummary['pendingSteps']) ?>
                    </span>
                    <span class="badge text-bg-primary p-2">
                        <?= __('{0} Pending Responses', (int)$workflowSummary['pendingResponses']) ?>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <section class="card h-100" aria-labelledby="approval-next-steps-heading">
                <div class="card-header">
                    <h6 class="mb-0" id="approval-next-steps-heading">
                        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i><?= __('Next / Pending Steps') ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($workflowSummary['nextSteps']) && empty($workflowSummary['upcomingSteps'])) : ?>
                    <p class="text-muted mb-0"><?= __('There are no pending or upcoming approval steps right now.') ?>
                    </p>
                    <?php else : ?>
                    <ol class="list-group list-group-numbered">
                        <?php foreach ($workflowSummary['nextSteps'] as $nextStep) : ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-semibold"><?= h($nextStep['label']) ?></div>
                                <span class="text-muted">
                                    <?= __(
                                                    '{0} of {1} approvals recorded',
                                                    (int)$nextStep['approved'],
                                                    (int)$nextStep['required'],
                                                ) ?>
                                </span>
                                <?php if (!empty($nextStep['currentApprover'])) : ?>
                                <div class="small text-muted">
                                    <?= __('Current approver: {0}', h($nextStep['currentApprover'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <span class="badge text-bg-primary rounded-pill">
                                <?= __('{0} pending', (int)$nextStep['pendingResponses']) ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                        <?php foreach ($workflowSummary['upcomingSteps'] as $upcomingStep) : ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-semibold"><?= h($upcomingStep['label']) ?></div>
                                <span class="text-muted">
                                    <?= __('This step starts after the current approval is complete.') ?>
                                </span>
                            </div>
                            <span class="badge text-bg-secondary rounded-pill">
                                <?= __('Upcoming') ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card h-100" aria-labelledby="approval-completed-steps-heading">
                <div class="card-header">
                    <h6 class="mb-0" id="approval-completed-steps-heading">
                        <i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= __('Completed Steps') ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($workflowSummary['completedStepLabels'])) : ?>
                    <p class="text-muted mb-0"><?= __('No approval steps have completed yet.') ?></p>
                    <?php else : ?>
                    <ul class="list-group">
                        <?php foreach ($workflowSummary['completedStepLabels'] as $completedStep) : ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold"><?= h($completedStep['label']) ?></span>
                                <span class="badge <?= h($runStatusClass((string)$completedStep['status'])) ?>">
                                    <?= h($runStatusLabel((string)$completedStep['status'])) ?>
                                </span>
                            </div>
                            <?php if (!empty($completedStep['responses'])) : ?>
                            <ul class="small text-muted mb-0 mt-1 ps-3">
                                <?php foreach ($completedStep['responses'] as $responseSummary) : ?>
                                <li>
                                    <?= h($responseSummary['member']) ?>:
                                    <?= h($responseSummary['decision']) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($workflowContext['canDecide']) && !empty($workflowContext['pendingApproval'])) : ?>
    <?php
        $pendingApproval = $workflowContext['pendingApproval'];
        $approverConfig = $pendingApproval->approver_config ?? [];
        $requiresComment = !empty($approverConfig['requires_comment']);
        $requiresBestowalGathering = !empty($approverConfig['requires_bestowal_gathering'])
            || !empty($approverConfig['requiresBestowalGathering']);
        $bestowalGatheringLookupUrl = (string)($approverConfig['bestowal_gathering_url']
            ?? $approverConfig['bestowalGatheringUrl']
            ?? $this->Url->build([
                'plugin' => 'Awards',
                'controller' => 'Bestowals',
                'action' => 'gatheringsForBestowalAutoComplete',
                '?' => [
                    'recommendation_id' => (int)$recommendation->id,
                ],
            ]));
        $decisionHelpId = 'approval-decision-help-' . (int)$pendingApproval->id;
        $commentId = 'approval-comment-' . (int)$pendingApproval->id;
        ?>
    <section class="card border-primary mb-3" aria-labelledby="approval-decision-heading">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0" id="approval-decision-heading"><?= __('Your Approval Decision') ?></h6>
        </div>
        <div class="card-body">
            <p class="mb-3" id="<?= h($decisionHelpId) ?>">
                <?= __('This recommendation is waiting for your response at the {0} step.', h($workflowContext['activeRun']->current_step_label ?? $pendingApproval->node_id)) ?>
            </p>
            <?= $this->Form->create(null, [
                    'url' => ['action' => 'workflowDecision', $recommendation->id],
                    'aria-describedby' => $decisionHelpId,
                ]) ?>
            <div class="mb-3">
                <label class="form-label" for="<?= h($commentId) ?>">
                    <?= __('Decision Comment') ?>
                    <?php if ($requiresComment) : ?><span class="text-danger" aria-hidden="true">*</span><?php endif; ?>
                </label>
                <textarea class="form-control" id="<?= h($commentId) ?>" name="comment" rows="3"
                    <?php if ($requiresComment) : ?>required aria-required="true" <?php endif; ?>></textarea>
                <div class="form-text">
                    <?= $requiresComment
                            ? __('A comment is required for this approval response.')
                            : __('Optional context for the approval history.') ?>
                </div>
            </div>
            <?php if ($requiresBestowalGathering) : ?>
            <div class="mb-3">
                <?= $this->KMP->autoCompleteControl(
                    $this->Form,
                    'bestowal_gathering_name',
                    'bestowal_gathering_id',
                    $bestowalGatheringLookupUrl,
                    __('Bestowal Gathering'),
                    false,
                    false,
                    2,
                    [
                        'data-ac-show-on-focus-value' => 'true',
                    ],
                ) ?>
                <div class="form-text">
                    <?= __('Optional: choose a future event or court if you already know where the bestowal should be scheduled.') ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" name="decision" value="<?= h(WorkflowApprovalResponse::DECISION_APPROVE) ?>"
                    class="btn btn-success">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> <?= __('Approve') ?>
                </button>
                <button type="submit" name="decision" value="<?= h(WorkflowApprovalResponse::DECISION_REJECT) ?>"
                    class="btn btn-outline-danger">
                    <i class="bi bi-x-circle" aria-hidden="true"></i> <?= __('Decline') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (empty($workflowContext['runs'])) : ?>
    <div class="alert alert-info" role="status">
        <?= __('No approval history is available for this recommendation.') ?>
    </div>
    <?php else : ?>
    <?php foreach ($workflowContext['runs'] as $runContext) : ?>
    <?php
            $run = $runContext['run'];
            $instance = $runContext['workflowInstance'];
            $runSummary = $runContext['summary'] ?? [];
            $approvals = $runContext['approvals'] ?? [];
            ?>
    <section class="card mb-3" aria-labelledby="approval-run-<?= (int)$run->id ?>-heading">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0" id="approval-run-<?= (int)$run->id ?>-heading">
                <?= __('Approval Run #{0}', (int)$run->id) ?>
            </h6>
            <span class="badge <?= h($runStatusClass((string)$run->status)) ?>">
                <?= h($runStatusLabel((string)$run->status)) ?>
            </span>
        </div>
        <div class="card-body">
            <dl class="row mb-3">
                <dt class="col-sm-3"><?= __('Started') ?></dt>
                <dd class="col-sm-9"><?= $run->started ? h($run->started->format('Y-m-d H:i')) : __('Unknown') ?></dd>
                <dt class="col-sm-3"><?= __('Completed') ?></dt>
                <dd class="col-sm-9">
                    <?= $run->completed ? h($run->completed->format('Y-m-d H:i')) : __('Not completed') ?></dd>
                <dt class="col-sm-3"><?= __('Current Step') ?></dt>
                <dd class="col-sm-9"><?= h($run->current_step_label ?? __('None')) ?></dd>
                <dt class="col-sm-3"><?= __('Workflow Instance') ?></dt>
                <dd class="col-sm-9">
                    <?= $instance ? h(__('Instance #{0} ({1})', (int)$instance->id, $runStatusLabel((string)$instance->status))) : __('Not available') ?>
                </dd>
                <?php if (!empty($run->terminal_reason)) : ?>
                <dt class="col-sm-3"><?= __('Terminal Reason') ?></dt>
                <dd class="col-sm-9"><?= h($runStatusLabel((string)$run->terminal_reason)) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if (!empty($runSummary['totalSteps'])) : ?>
            <div class="alert alert-light border" role="status">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <strong><?= __('Run Summary:') ?></strong>
                    <span><?= __('{0} complete', (int)$runSummary['completedSteps']) ?></span>
                    <span><?= __('{0} pending steps', (int)$runSummary['pendingSteps']) ?></span>
                    <span><?= __('{0} pending responses', (int)$runSummary['pendingResponses']) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($approvals)) : ?>
            <p class="text-muted mb-0"><?= __('No approval gate history is available for this run.') ?></p>
            <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= __('Step') ?></th>
                            <th scope="col"><?= __('Status') ?></th>
                            <th scope="col"><?= __('Responses') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvals as $approval) : ?>
                        <?php $responses = $approval->workflow_approval_responses ?? []; ?>
                        <tr>
                            <td><?= h($approval->node_id) ?></td>
                            <td>
                                <span class="badge <?= h($runStatusClass((string)$approval->status)) ?>">
                                    <?= h($runStatusLabel((string)$approval->status)) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (empty($responses)) : ?>
                                <span class="text-muted"><?= __('No responses recorded') ?></span>
                                <?php else : ?>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($responses as $response) : ?>
                                    <li>
                                        <strong><?= h($response->member->sca_name ?? $response->member_id) ?></strong>:
                                        <?= h(WorkflowApprovalDecisionOptions::labelForDecision((string)$response->decision, $approval->approver_config ?? [])) ?>
                                        <?php if (!empty($response->responded_at)) : ?>
                                        <span
                                            class="text-muted"><?= h($response->responded_at->format('Y-m-d H:i')) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($response->comment)) : ?>
                                        <div><?= $this->Text->autoParagraph(h($response->comment)) ?></div>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php if (!empty($feedbackRequests)) : ?>
<div class="related tab-pane fade m-3" id="nav-feedback" role="tabpanel" aria-labelledby="nav-feedback-tab"
    data-detail-tabs-target="tabContent" data-tab-order="15" style="order: 15;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> <?= __('Feedback Requests') ?></h5>
    </div>
    <?php foreach ($feedbackRequests as $feedbackRequest) : ?>
    <?php
            $answerTally = [];
            $respondedCount = 0;
            $hasDecisionAnswers = false;
            foreach ($feedbackRequest->recipients ?? [] as $recipient) {
                $response = $recipient->workflow_approval_response ?? null;
                $approvalConfig = $recipient->workflow_approval->approver_config ?? [];
                $hasOptions = WorkflowApprovalDecisionOptions::normalizeOptions($approvalConfig) !== [];
                if ($recipient->status !== 'responded' || empty($response?->decision) || !$hasOptions) {
                    continue;
                }

                $respondedCount++;
                $hasDecisionAnswers = true;
                $answerLabel = WorkflowApprovalDecisionOptions::labelForDecision((string)$response->decision, $approvalConfig);
                $answerTally[$answerLabel] = ($answerTally[$answerLabel] ?? 0) + 1;
            }
            $recipientCount = count($feedbackRequest->recipients ?? []);
            ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong><?= __('Request #{0}', $feedbackRequest->id) ?></strong>
                <span class="badge bg-secondary ms-2"><?= h(ucfirst((string)$feedbackRequest->status)) ?></span>
                <span class="text-muted ms-2">
                    <?= __('Requested by {0}', h($feedbackRequest->requester->sca_name ?? $feedbackRequest->requester->member_number ?? $feedbackRequest->requester_id)) ?>
                </span>
            </div>
            <?php if (
                        $feedbackRequest->status === 'pending'
                        && $user->checkCan('retractFeedback', $recommendation)
                    ) : ?>
            <?= $this->Form->postLink(
                            '<i class="bi bi-x-circle"></i> ' . __('Retract'),
                            ['action' => 'retractFeedback'],
                            [
                                'data' => ['feedback_request_id' => $feedbackRequest->id],
                                'class' => 'btn btn-sm btn-outline-danger',
                                'confirm' => __('Retract this pending feedback request?'),
                                'escape' => false,
                            ]
                        ) ?>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!empty($feedbackRequest->message)) : ?>
            <p class="mb-3"><strong><?= __('Message:') ?></strong> <?= h($feedbackRequest->message) ?></p>
            <?php endif; ?>
            <?php if ($hasDecisionAnswers) : ?>
            <div class="mb-3" aria-label="<?= h(__('Feedback answer summary')) ?>">
                <div class="fw-semibold mb-2">
                    <?= __('Answer Summary ({0} of {1} responded)', $respondedCount, $recipientCount) ?>
                </div>
                <?php foreach ($answerTally as $answerLabel => $answerCount) : ?>
                <?php $percentage = $respondedCount > 0 ? round(($answerCount / $respondedCount) * 100) : 0; ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span><?= h($answerLabel) ?></span>
                        <span><?= __('{0} ({1}%)', $answerCount, $percentage) ?></span>
                    </div>
                    <div class="progress" role="img"
                        aria-label="<?= h(__('{0}: {1} of {2} responses, {3}%', $answerLabel, $answerCount, $respondedCount, $percentage)) ?>">
                        <div class="progress-bar" style="width: <?= (int)$percentage ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th><?= __('Recipient') ?></th>
                            <th><?= __('Status') ?></th>
                            <th><?= __('Answer') ?></th>
                            <th><?= __('Response') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbackRequest->recipients ?? [] as $recipient) : ?>
                        <?php
                                    $response = $recipient->workflow_approval_response ?? null;
                                    $approvalConfig = $recipient->workflow_approval->approver_config ?? [];
                                    $answerLabel = null;
                                    if (
                                        !empty($response?->decision)
                                        && WorkflowApprovalDecisionOptions::normalizeOptions($approvalConfig) !== []
                                    ) {
                                        $answerLabel = WorkflowApprovalDecisionOptions::labelForDecision(
                                            (string)$response->decision,
                                            $approvalConfig,
                                        );
                                    }
                                    ?>
                        <tr>
                            <td><?= h($recipient->recipient_member->sca_name ?? $recipient->recipient_id) ?></td>
                            <td>
                                <span class="badge bg-secondary"><?= h(ucfirst((string)$recipient->status)) ?></span>
                            </td>
                            <td>
                                <?php if ($answerLabel !== null) : ?>
                                <span class="badge bg-info text-dark"><?= h($answerLabel) ?></span>
                                <?php else : ?>
                                <span class="text-muted"><?= __('No answer') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($recipient->response_comment)) : ?>
                                <?= $this->Text->autoParagraph(h($recipient->response_comment)) ?>
                                <?php else : ?>
                                <span class="text-muted"><?= __('No response yet') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals"); ?>

<?= $this->element('recommendationEditModal') ?>
<?php if ($user->checkCan("requestFeedback", $recommendation)) : ?>
<?= $this->element('recommendationFeedbackModal', [
    'modalId' => 'requestRecommendationFeedbackModal',
    'feedbackOrigin' => 'detail',
    'selectedRecommendationIds' => [$recommendation->id],
    'staticPageContext' => true,
    'pageContextUrl' => $this->Url->build([
        'plugin' => 'Awards',
        'controller' => 'Recommendations',
        'action' => 'view',
        $recommendation->id,
    ]),
]) ?>
<?php endif; ?>

<?php $this->KMP->endBlock(); ?>