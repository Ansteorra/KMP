<?php

/**
 * Admin All Approvals - Dataverse Grid View
 *
 * Shows all approvals across the system without user scoping.
 * Requires workflow management permissions.
 *
 * @var \App\View\AppView $this
 */

$bestowalGatheringLookupUrl = $this->Url->build([
    'controller' => 'Approvals',
    'action' => 'bestowalGatheringsAutoComplete',
]);
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': All Approvals';
$this->KMP->endBlock(); ?>

<h3><kmp-icon name="clipboard-check" class="me-2" aria-hidden="true"></kmp-icon><?= __('All Approvals') ?></h3>

<div data-controller="approval-detail"
     data-approval-detail-url-value="/approvals/detail/">
<?= $this->element('dv_grid', [
    'frameId' => 'all-approvals-grid',
    'dataUrl' => $this->Url->build(['controller' => 'Approvals', 'action' => 'allApprovalsGridData']),
]) ?>
</div>

<!-- Approval Response Modal (same as My Approvals) -->

<?php
$dialogBindings = [
    'sendFeedback' => __('Send Feedback'),
    'submitResponse' => __('Submit Response'),
    'decision' => __('Decision'),
    'respondToApproval' => __('Respond to Approval'),
    'required' => __('(required)'),
    'requiredForRejections' => __('(required for rejections)'),
    'feedback' => __('Feedback'),
    'comment' => __('Comment'),
];
?>
<div class="modal fade" id="approvalResponseModal"
    data-controller="all-approvals-dialog"
    data-all-approvals-dialog-bindings-value="<?= h(json_encode($dialogBindings)) ?>"
    tabindex="-1" aria-labelledby="approvalResponseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalResponseModalLabel">
                    <kmp-icon
                        name="checkbox"
                        class="me-2"
                        aria-hidden="true">
                    </kmp-icon>
                    <?= __('Respond to Approval') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <?= $this->Form->create(null, [
                'url' => ['controller' => 'Approvals', 'action' => 'recordApproval'],
                'id' => 'approvalResponseForm',
                'data-controller' => 'approval-response',
                'data-action' => 'submit->approval-response#validateSubmit',
                'data-approval-response-serial-pick-next-value' => 'false',
                'data-approval-response-required-count-value' => '1',
                'data-approval-response-approved-count-value' => '0',
                'data-approval-response-approval-id-value' => '0',
                'data-approval-response-eligible-url-value' => '',
            ]) ?>
            <div class="modal-body bg-light-subtle">
                <input type="hidden" name="approvalId" id="approvalResponseApprovalId" value="">

                <!-- Decision -->
                <fieldset
                    class="border rounded-3 bg-white shadow-sm p-3 mb-3"
                    data-approval-response-target="decisionSection">
                    <legend
                        class="float-none w-auto px-2 fw-semibold fs-6 mb-3"
                        data-approval-response-target="decisionLegend">
                    <?= __('Decision') ?>
                    </legend>
                    <div class="d-flex gap-3 flex-wrap" data-approval-response-target="decisionOptions">
                        <div class="form-check">
                        <input
                            class="form-check-input"
                            type="radio"
                            name="decision"
                            id="decisionApprove"
                            value="approve"
                                data-approval-response-target="decision"
                                data-action="change->approval-response#onDecisionChange">
                            <label class="form-check-label text-success fw-semibold" for="decisionApprove">
                        <kmp-icon
                            name="circle-check"
                            class="me-1"
                            aria-hidden="true">
                        </kmp-icon>
                        <?= __('Approve') ?>
                            </label>
                        </div>
                        <div class="form-check">
                        <input
                            class="form-check-input"
                            type="radio"
                            name="decision"
                            id="decisionReject"
                            value="reject"
                                data-approval-response-target="decision"
                                data-action="change->approval-response#onDecisionChange">
                            <label class="form-check-label text-danger fw-semibold" for="decisionReject">
                                <kmp-icon name="circle-x" class="me-1" aria-hidden="true"></kmp-icon><?= __('Reject') ?>
                            </label>
                        </div>
                    </div>
                </fieldset>

                <!-- Comment -->
                <div class="border rounded-3 bg-white shadow-sm p-3 mb-3">
                    <label class="form-label" for="approvalComment"><?= __('Comment') ?>
                        <span
                            class="text-danger"
                            data-approval-response-target="commentRequiredHint"
                            hidden>
                        <?= __('(required for rejections)') ?>
                        </span>
                    </label>
                    <textarea class="form-control" id="approvalComment" name="comment" rows="3"
                        data-approval-response-target="comment"
                        placeholder="<?= __('Optional comment...') ?>"></textarea>
                    <div class="form-text text-muted small" data-approval-response-target="commentWarning" hidden>
                        <kmp-icon
                            name="eye"
                            class="me-1"
                            aria-hidden="true">
                        </kmp-icon>
                        <span
                            data-approval-response-target="commentWarningText">
                        </span>
                    </div>
                </div>

                <!-- Bestowal Gathering (conditional) -->
                <div class="border rounded-3 bg-white shadow-sm p-3 mb-3"
                    data-approval-response-target="bestowalGatheringSection" hidden>
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
                            'data-approval-response-target' => 'bestowalGathering',
                            'data-ac-show-on-focus-value' => 'true',
                            'data-action' => 'autocomplete.change->approval-response#onBestowalGatheringChange',
                        ],
                    ) ?>
                    <div class="form-text" id="allApprovalBestowalGatheringHelp">
                        <?=
                            __('Optional: choose a future event or court if you already know where '
                            . 'the bestowal should be scheduled.')
                            ?>
                    </div>
                    <div class="text-danger small" id="allApprovalBestowalGatheringError"
                        data-approval-response-target="bestowalGatheringError" hidden>
                        <kmp-icon name="alert-circle" class="me-1" aria-hidden="true"></kmp-icon>
                        <?= __('Select a valid gathering or leave this field blank.') ?>
                    </div>
                </div>

                <!-- Next Approver (conditional) -->
                <div
                    class="border rounded-3 bg-white shadow-sm p-3"
                    data-approval-response-target="nextApproverSection"
                    hidden>
                    <div class="alert alert-info py-2 small" role="alert" data-approval-response-target="infoText">
                    </div>
                    <label
                        class="form-label fw-semibold" for="all-approval-next-approver-disp">
                    <?= __('Select Next Approver') ?>
                    </label>
                    <div data-controller="ac"
                         data-ac-url-value="/workflows/eligible-approvers/0"
                         data-ac-min-length-value="0"
                         data-ac-show-on-focus-value="true"
                         data-ac-allow-other-value="false"
                         class="position-relative mb-3 kmp_autoComplete">
                        <input type="hidden" name="next_approver_id"
                               data-ac-target="hidden"
                               data-approval-response-target="nextApproverInput">
                        <input type="hidden" data-ac-target="hiddenText">
                        <div class="input-group">
                            <input type="text" class="form-control"
                                   id="all-approval-next-approver-disp"
                                   role="combobox"
                                   aria-autocomplete="list"
                                   aria-expanded="false"
                                   aria-controls="all-approval-next-approver-results"
                                   aria-describedby="all-approval-next-approver-status"
                                   data-ac-target="input"
                                   placeholder="<?= __('Click to see all or type to filter...') ?>">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-ac-target="clearBtn"
                            data-action="ac#clear"
                            disabled>
                        <?= __('Clear') ?>
                        </button>
                        </div>
                        <ul data-ac-target="results"
                            id="all-approval-next-approver-results"
                            role="listbox"
                            class="list-group z-3 col-12 position-absolute auto-complete-list"
                            hidden="hidden"></ul>
                        <div
                            id="all-approval-next-approver-status"
                            class="visually-hidden"
                            role="status"
                            aria-live="polite"
                            aria-atomic="true"
                            data-ac-target="status"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-primary" data-approval-response-target="submitBtn" disabled>
                    <kmp-icon name="send" class="me-1" aria-hidden="true"></kmp-icon><?= __('Submit Response') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<!-- Approval Reassign Modal -->
<div
    class="modal fade"
    id="approvalReassignModal"
    tabindex="-1"
    aria-labelledby="approvalReassignModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalReassignModalLabel">
                    <kmp-icon name="user-cog" class="me-2" aria-hidden="true"></kmp-icon><?= __('Reassign Approval') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <?= $this->Form->create(null, [
                'url' => '/approvals/reassign',
                'id' => 'approvalReassignForm',
                'data-controller' => 'approval-reassign',
            ]) ?>
            <div class="modal-body bg-light-subtle">
                <input type="hidden" name="approvalId" data-approval-reassign-target="approvalId" value="">

                <div class="alert alert-info border-start border-info border-4 py-2 small" role="alert">
                    <kmp-icon name="info-circle" class="me-1" aria-hidden="true"></kmp-icon>
                    <?=
                        __('Reassigning this approval will transfer it to a different eligible '
                        . 'member. If the workflow has an on_reassigned action configured, '
                        . 'the new approver will be notified.')
                        ?>
                </div>

                <!-- New Approver Picker -->
                <div class="border rounded-3 bg-white shadow-sm p-3">
                    <label
                        class="form-label fw-semibold" for="approval-reassign-new-approver-disp">
                    <?= __('New Approver') ?>
                    </label>
                    <div data-controller="ac"
                         data-ac-url-value="/approvals/eligible-approvers/0"
                         data-ac-min-length-value="0"
                         data-ac-show-on-focus-value="true"
                         data-ac-allow-other-value="false"
                         class="position-relative kmp_autoComplete">
                        <input type="hidden" name="newApproverId"
                               data-ac-target="hidden"
                               data-action="change->approval-reassign#onApproverChange">
                        <input type="hidden" data-ac-target="hiddenText">
                        <div class="input-group">
                            <input type="text" class="form-control"
                                   id="approval-reassign-new-approver-disp"
                                   role="combobox"
                                   aria-autocomplete="list"
                                   aria-expanded="false"
                                   aria-controls="approval-reassign-new-approver-results"
                                   aria-describedby="approval-reassign-new-approver-status"
                                   data-ac-target="input"
                                   placeholder="<?= __('Click to see all or type to filter...') ?>">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-ac-target="clearBtn"
                            data-action="ac#clear"
                            disabled>
                        <?= __('Clear') ?>
                        </button>
                        </div>
                        <ul data-ac-target="results"
                            id="approval-reassign-new-approver-results"
                            role="listbox"
                            class="list-group z-3 col-12 position-absolute auto-complete-list"
                            hidden="hidden"></ul>
                        <div
                            id="approval-reassign-new-approver-status"
                            class="visually-hidden"
                            role="status"
                            aria-live="polite"
                            aria-atomic="true"
                            data-ac-target="status"></div>
                    </div>
                </div>

                <!-- Reason -->
                <div class="mb-3">
                    <label class="form-label" for="reassignReason"><?= __('Reason') ?></label>
                    <textarea class="form-control" id="reassignReason" name="reason" rows="2"
                        data-approval-reassign-target="reason"
                        placeholder="<?= __('Optional reason for reassignment...') ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-warning" data-approval-reassign-target="submitBtn" disabled>
                    <kmp-icon name="user-cog" class="me-1" aria-hidden="true"></kmp-icon><?= __('Reassign') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
