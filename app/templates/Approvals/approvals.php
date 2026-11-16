<?php

/**
 * Unified Approvals - Dataverse Grid View
 *
 * @var \App\View\AppView $this
 * @var int|null $focusedApprovalId Pre-selected approval from token deep link
 * @var string|null $approvalToken Token from email deep link
 */

$focusedApprovalId = $focusedApprovalId ?? null;
$approvalToken = $approvalToken ?? null;
$triageUrl = $this->Url->build(['controller' => 'Approvals', 'action' => 'updateTriage']);
$bestowalGatheringLookupUrl = $this->Url->build([
    'controller' => 'Approvals',
    'action' => 'bestowalGatheringsAutoComplete',
]);
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': My Approvals';
$this->KMP->endBlock(); ?>

<h3><kmp-icon name="checkbox" class="me-2" aria-hidden="true"></kmp-icon><?= __('My Approvals') ?></h3>

<div data-controller="approval-detail"
     data-approval-detail-url-value="/approvals/detail/"
     data-approval-detail-triage-url-value="<?= h($triageUrl) ?>">
<?= $this->element('dv_grid', [
    'frameId' => 'approvals-grid',
    'dataUrl' => $this->Url->build(['controller' => 'Approvals', 'action' => 'approvalsGridData']),
]) ?>
</div>

<!-- Approval Response Modal -->

<?php
$dialogBindings = [
    'selectApprovalsOfTheSameTypeBefore' => __('Select approvals of the same type before responding in bulk.'),
    'unableToReadTheSelectedApprovalDetails' =>
        __('Unable to read the selected approval details. Refresh the grid and try again.'),
    'sendFeedback' => __('Send Feedback'),
    'submitResponse' => __('Submit Response'),
    'decision' => __('Decision'),
    'respondToSelectedApprovals' => __('Respond to Selected Approvals'),
    'respondToApproval' => __('Respond to Approval'),
    'required' => __('(required)'),
    'requiredForRejections' => __('(required for rejections)'),
    'feedback' => __('Feedback'),
    'comment' => __('Comment'),
    'focusedApprovalId' => $focusedApprovalId,
];
?>
<div class="modal fade" id="approvalResponseModal"
    data-controller="approval-dialog"
    data-approval-dialog-bindings-value="<?= h(json_encode($dialogBindings)) ?>"
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
                'data-turbo' => 'true',
                'data-controller' => 'approval-response turbo-modal',
                'data-action' => implode(' ', [
                    'submit->approval-response#validateSubmit',
                    'submit->turbo-modal#submitAsTurboStream',
                    'turbo:submit-start->turbo-modal#closeModalBeforeSubmit',
                ]),
                'data-approval-response-serial-pick-next-value' => 'false',
                'data-approval-response-required-count-value' => '1',
                'data-approval-response-approved-count-value' => '0',
                'data-approval-response-approval-id-value' => '0',
                'data-approval-response-eligible-url-value' => '',
            ]) ?>
            <div class="modal-body bg-light-subtle">
                <input type="hidden" name="approvalId" id="approvalResponseApprovalId" value="">
                <input type="hidden" name="approvalIds" id="approvalResponseApprovalIds" value=""
                    data-approval-response-target="approvalIds">
                <?= $this->Form->hidden('page_context_url', ['value' => '']) ?>

                <div class="alert py-2 small" role="status" aria-live="polite"
                    data-approval-response-target="bulkSummary" hidden></div>

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
                    <div class="form-text" id="approvalBestowalGatheringHelp">
                        <?=
                            __('Optional: choose a future event or court if you already know where '
                            . 'the bestowal should be scheduled.')
                            ?>
                    </div>
                    <div class="text-danger small" id="approvalBestowalGatheringError"
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
                        class="form-label fw-semibold" for="approval-next-approver-disp">
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
                                   id="approval-next-approver-disp"
                                   role="combobox"
                                   aria-autocomplete="list"
                                   aria-expanded="false"
                                   aria-controls="approval-next-approver-results"
                                   aria-describedby="approval-next-approver-status"
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
                            id="approval-next-approver-results"
                            role="listbox"
                            class="list-group z-3 col-12 position-absolute auto-complete-list"
                            hidden="hidden"></ul>
                        <div
                            id="approval-next-approver-status"
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
