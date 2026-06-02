<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Recommendation $recommendation
 * @var array $memberAttendanceGatherings
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
<button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestRecommendationFeedbackModal"
    data-controller="outlet-btn" data-action="click->outlet-btn#fireNotice"
    data-outlet-btn-btn-data-value='{ "id":<?= $recommendation->id ?>}'>
    <i class="bi bi-chat-left-text"></i> <?= __('Request Feedback') ?>
</button>
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
$feedbackRequests = [];
foreach ($recommendation->feedback_request_items ?? [] as $feedbackItem) {
    if (!empty($feedbackItem->feedback_request)) {
        $feedbackRequests[$feedbackItem->feedback_request->id] = $feedbackItem->feedback_request;
    }
}
?>
<?php $this->KMP->startBlock("tabButtons") ?>
<?php if (!empty($recommendation->group_children)) : ?>
<button class="nav-link" id="nav-grouped-tab" data-bs-toggle="tab" data-bs-target="#nav-grouped" type="button" role="tab"
    aria-controls="nav-grouped" aria-selected="false" data-detail-tabs-target='tabBtn'
    data-tab-order="5" style="order: 5;">
    <?= __("Grouped") ?> <span class="badge bg-info"><?= count($recommendation->group_children) ?></span>
</button>
<?php endif; ?>
<button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button" role="tab"
    aria-controls="nav-notes" aria-selected="false" data-detail-tabs-target='tabBtn'
    data-tab-order="10" style="order: 10;"><?= __("Notes") ?>
</button>
<?php if (!empty($feedbackRequests)) : ?>
<button class="nav-link" id="nav-feedback-tab" data-bs-toggle="tab" data-bs-target="#nav-feedback" type="button" role="tab"
    aria-controls="nav-feedback" aria-selected="false" data-detail-tabs-target='tabBtn'
    data-tab-order="15" style="order: 15;">
    <?= __("Feedback") ?> <span class="badge bg-info"><?= count($feedbackRequests) ?></span>
</button>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<?php if (!empty($recommendation->group_children)) : ?>
<div class="related tab-pane fade m-3" id="nav-grouped" role="tabpanel" aria-labelledby="nav-grouped-tab"
    data-detail-tabs-target="tabContent"
    data-tab-order="5" style="order: 5;">
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
<?php if (!empty($feedbackRequests)) : ?>
<div class="related tab-pane fade m-3" id="nav-feedback" role="tabpanel" aria-labelledby="nav-feedback-tab"
    data-detail-tabs-target="tabContent" data-tab-order="15" style="order: 15;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> <?= __('Feedback Requests') ?></h5>
    </div>
    <?php foreach ($feedbackRequests as $feedbackRequest) : ?>
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
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th><?= __('Recipient') ?></th>
                            <th><?= __('Status') ?></th>
                            <th><?= __('Response') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbackRequest->recipients ?? [] as $recipient) : ?>
                        <tr>
                            <td><?= h($recipient->recipient_member->sca_name ?? $recipient->recipient_id) ?></td>
                            <td>
                                <span class="badge bg-secondary"><?= h(ucfirst((string)$recipient->status)) ?></span>
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
<?= $this->element('recommendationFeedbackModal', ['modalId' => 'requestRecommendationFeedbackModal']) ?>
<?php endif; ?>

<?php $this->KMP->endBlock(); ?>