<?php

/**
 * Recommendation workflow decision modal for row and bulk grid actions.
 *
 * @var \App\View\AppView $this
 */

$singleDecisionUrl = $this->Url->build([
    'plugin' => 'Awards',
    'controller' => 'Recommendations',
    'action' => 'workflowDecisionFromGrid',
]);
$bulkDecisionUrl = $this->Url->build([
    'plugin' => 'Awards',
    'controller' => 'Recommendations',
    'action' => 'bulkWorkflowDecision',
]);
$bestowalGatheringLookupUrl = $this->Url->build([
    'plugin' => 'Awards',
    'controller' => 'Bestowals',
    'action' => 'gatheringsForBestowalAutoComplete',
]);
?>


<?php
$dialogBindings = [
    'respondToSelectedRecommendations' => __('Respond to Selected Recommendations'),
    'oneSelectedRecommendationIsPendingYourApproval' => __('One selected recommendation is pending your approval.'),
    'selectedRecommendationsArePendingYourApproval' => __('selected recommendations are pending your approval.'),
    'selectedRecommendationSAreNotPendingYour' =>
        __('selected recommendation(s) are not pending your approval and will be skipped.'),
    'decision' => __('Decision'),
    'approve' => __('Approve'),
    'respondToRecommendationApproval' => __('Respond to Recommendation Approval'),
    'selectAtLeastOneRecommendationThatIs' => __('Select at least one recommendation that is pending your approval.'),
];
?>
<div class="modal fade" id="recommendationWorkflowDecisionModal"
    data-controller="awards-workflow-decision"
    data-awards-workflow-decision-bindings-value="<?= h(json_encode($dialogBindings)) ?>"
    tabindex="-1" aria-labelledby="recommendationWorkflowDecisionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <?= $this->Form->create(null, [
                'url' => [
                    'plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'workflowDecisionFromGrid',
                ],
                'id' => 'recommendationWorkflowDecisionForm',
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
                                                  'data-single-decision-url' => $singleDecisionUrl,
                                                  'data-bulk-decision-url' => $bulkDecisionUrl,
            ]) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="recommendationWorkflowDecisionModalLabel">
                    <kmp-icon name="checkbox" class="me-2" aria-hidden="true"></kmp-icon>
                    <span id="recommendationWorkflowDecisionModalTitle">
                        <?= __('Respond to Recommendation Approval') ?>
                    </span>
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>">
                </button>
            </div>
            <div class="modal-body bg-light-subtle">
                <input type="hidden" name="approvalId" id="recommendationWorkflowDecisionApprovalId" value="">
                <input
                    type="hidden"
                    name="recommendation_id"
                    id="recommendationWorkflowDecisionRecommendationId"
                    value="">
                <?= $this->Form->hidden('page_context_url', ['value' => '']) ?>
                <div id="recommendationWorkflowDecisionBulkApprovalIds"></div>
                <div
                    class="alert alert-info"
                    id="recommendationWorkflowDecisionSelectionNotice"
                    role="status"
                    hidden>
                </div>
                <div
                    class="alert alert-warning"
                    id="recommendationWorkflowDecisionSkippedNotice"
                    role="status"
                    hidden>
                </div>

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

                <div class="border rounded-3 bg-white shadow-sm p-3 mb-3">
                    <label class="form-label" for="recommendationWorkflowDecisionComment">
                        <?= __('Comment') ?>
                        <span
                            class="text-danger"
                            data-approval-response-target="commentRequiredHint"
                            hidden>
                        <?= __('(required for rejections)') ?>
                        </span>
                    </label>
                    <textarea class="form-control" id="recommendationWorkflowDecisionComment" name="comment" rows="3"
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
                    <div class="form-text" id="recommendationApprovalBestowalGatheringHelp">
                        <?=
                            __('Optional: choose a future event or court if you already know where '
                            . 'the bestowal should be scheduled.')
                            ?>
                    </div>
                    <div class="text-danger small" id="recommendationApprovalBestowalGatheringError"
                        data-approval-response-target="bestowalGatheringError" hidden>
                        <kmp-icon name="alert-circle" class="me-1" aria-hidden="true"></kmp-icon>
                        <?= __('Select a valid gathering or leave this field blank.') ?>
                    </div>
                </div>

                <div
                    class="border rounded-3 bg-white shadow-sm p-3"
                    data-approval-response-target="nextApproverSection"
                    hidden>
                    <div
                        class="alert alert-info py-2 small"
                        role="alert"
                        data-approval-response-target="infoText">
                    </div>
                    <label
                        class="form-label fw-semibold" for="recommendation-next-approver-disp">
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
                                   id="recommendation-next-approver-disp"
                                   role="combobox"
                                   aria-autocomplete="list"
                                   aria-expanded="false"
                                   aria-controls="recommendation-next-approver-results"
                                   aria-describedby="recommendation-next-approver-status"
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
                            id="recommendation-next-approver-results"
                            role="listbox"
                            class="list-group z-3 col-12 position-absolute auto-complete-list"
                            hidden="hidden"></ul>
                        <div
                            id="recommendation-next-approver-status"
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
