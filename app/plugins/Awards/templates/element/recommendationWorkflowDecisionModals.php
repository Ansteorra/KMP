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
?>

<div class="modal fade" id="recommendationWorkflowDecisionModal" tabindex="-1" aria-labelledby="recommendationWorkflowDecisionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <?= $this->Form->create(null, [
                'url' => ['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'workflowDecisionFromGrid'],
                'id' => 'recommendationWorkflowDecisionForm',
                'data-controller' => 'approval-response',
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
                    <i class="bi bi-check2-square me-2" aria-hidden="true"></i>
                    <span id="recommendationWorkflowDecisionModalTitle">
                        <?= __('Respond to Recommendation Approval') ?>
                    </span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body bg-light-subtle">
                <input type="hidden" name="approvalId" id="recommendationWorkflowDecisionApprovalId" value="">
                <div id="recommendationWorkflowDecisionBulkApprovalIds"></div>
                <div class="alert alert-info" id="recommendationWorkflowDecisionSelectionNotice" role="status" hidden></div>
                <div class="alert alert-warning" id="recommendationWorkflowDecisionSkippedNotice" role="status" hidden></div>

                <fieldset class="border rounded-3 bg-white shadow-sm p-3 mb-3" data-approval-response-target="decisionSection">
                    <legend class="float-none w-auto px-2 fw-semibold fs-6 mb-3" data-approval-response-target="decisionLegend"><?= __('Decision') ?></legend>
                    <div class="d-flex gap-3 flex-wrap" data-approval-response-target="decisionOptions">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="decision" id="decisionApprove" value="approve"
                                data-approval-response-target="decision"
                                data-action="change->approval-response#onDecisionChange">
                            <label class="form-check-label text-success fw-semibold" for="decisionApprove">
                                <i class="bi bi-check-circle me-1" aria-hidden="true"></i><?= __('Approve') ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="decision" id="decisionReject" value="reject"
                                data-approval-response-target="decision"
                                data-action="change->approval-response#onDecisionChange">
                            <label class="form-check-label text-danger fw-semibold" for="decisionReject">
                                <i class="bi bi-x-circle me-1" aria-hidden="true"></i><?= __('Reject') ?>
                            </label>
                        </div>
                    </div>
                </fieldset>

                <div class="border rounded-3 bg-white shadow-sm p-3 mb-3">
                    <label class="form-label" for="recommendationWorkflowDecisionComment">
                        <?= __('Comment') ?>
                        <span class="text-danger" data-approval-response-target="commentRequiredHint" hidden><?= __('(required for rejections)') ?></span>
                    </label>
                    <textarea class="form-control" id="recommendationWorkflowDecisionComment" name="comment" rows="3"
                        data-approval-response-target="comment"
                        placeholder="<?= __('Optional comment...') ?>"></textarea>
                    <div class="form-text text-muted small" data-approval-response-target="commentWarning" hidden>
                        <i class="bi bi-eye me-1" aria-hidden="true"></i><span data-approval-response-target="commentWarningText"></span>
                    </div>
                </div>

                <div class="border rounded-3 bg-white shadow-sm p-3" data-approval-response-target="nextApproverSection" hidden>
                    <div class="alert alert-info py-2 small" role="alert" data-approval-response-target="infoText"></div>
                    <label class="form-label fw-semibold" for="recommendation-next-approver-disp"><?= __('Select Next Approver') ?></label>
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
                            <button type="button" class="btn btn-outline-secondary" data-ac-target="clearBtn" data-action="ac#clear" disabled><?= __('Clear') ?></button>
                        </div>
                        <ul data-ac-target="results"
                            id="recommendation-next-approver-results"
                            role="listbox"
                            class="list-group z-3 col-12 position-absolute auto-complete-list"
                            hidden="hidden"></ul>
                        <div id="recommendation-next-approver-status" class="visually-hidden" role="status" aria-live="polite" aria-atomic="true"
                            data-ac-target="status"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-primary" data-approval-response-target="submitBtn" disabled>
                    <i class="bi bi-send me-1" aria-hidden="true"></i><?= __('Submit Response') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('recommendationWorkflowDecisionModal');
    const form = document.getElementById('recommendationWorkflowDecisionForm');
    if (!modal || !form) return;

    document.addEventListener('outlet-btn:notice', function(event) {
        if (event.target?.dataset?.bulkActionKey === 'workflow-decision') {
            event.target.dataset.workflowDecisionSelection = JSON.stringify(event.detail || {});
        }
    });

    const configureApprovalController = function(config) {
        const controller = window.Stimulus?.getControllerForElementAndIdentifier(form, 'approval-response');
        if (controller) {
            controller.configure(config);
        }
    };

    const setNotice = function(elementId, message) {
        const element = document.getElementById(elementId);
        if (!element) return;
        element.textContent = message || '';
        element.hidden = !message;
    };

    const renderBulkApprovalInputs = function(approvalIds) {
        const container = document.getElementById('recommendationWorkflowDecisionBulkApprovalIds');
        if (!container) return;
        container.innerHTML = '';
        approvalIds.forEach(function(approvalId) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'approval_ids[]';
            input.value = approvalId;
            container.appendChild(input);
        });
    };

    const configureBulkDecision = function(btn) {
        let selection = {};
        try {
            selection = JSON.parse(btn.dataset.workflowDecisionSelection || '{}');
        } catch (e) {
            selection = {};
        }
        const selectedRows = Array.isArray(selection.checkboxes) ? selection.checkboxes : [];
        const approvalIds = [];
        let skipped = 0;
        selectedRows.forEach(function(row) {
            if (row.pendingApprovalId) {
                approvalIds.push(row.pendingApprovalId);
            } else {
                skipped += 1;
            }
        });

        form.action = form.dataset.bulkDecisionUrl;
        form.dataset.workflowDecisionMode = 'bulk';
        form.dataset.workflowDecisionBulkCount = String(approvalIds.length);
        document.getElementById('recommendationWorkflowDecisionApprovalId').value = '';
        renderBulkApprovalInputs(approvalIds);
        document.getElementById('recommendationWorkflowDecisionModalTitle').textContent =
            '<?= __('Respond to Selected Recommendations') ?>';
        setNotice(
            'recommendationWorkflowDecisionSelectionNotice',
            approvalIds.length === 1
                ? '<?= __('One selected recommendation is pending your approval.') ?>'
                : approvalIds.length + ' <?= __('selected recommendations are pending your approval.') ?>',
        );
        setNotice(
            'recommendationWorkflowDecisionSkippedNotice',
            skipped === 0
                ? ''
                : skipped + ' <?= __('selected recommendation(s) are not pending your approval and will be skipped.') ?>',
        );

        configureApprovalController({
            id: 0,
            serialPickNext: false,
            requiredCount: 1,
            approvedCount: 0,
            eligibleUrl: '',
            commentWarning: '',
            requiresComment: false,
            feedbackResponse: false,
            decisionOptions: [],
            decisionPromptLabel: '<?= __('Decision') ?>',
            approveLabel: '<?= __('Approve') ?>',
            hideReject: false,
            defaultDecision: null,
        });
    };

    const configureSingleDecision = function(btn) {
        let btnData = {};
        try {
            btnData = JSON.parse(btn.getAttribute('data-outlet-btn-btn-data-value') || '{}');
        } catch (e) {
            btnData = {};
        }

        const approvalId = btnData.approvalId || btnData.id || 0;
        const approverConfig = btnData.approverConfig || btnData.approver_config || {};
        const requiredCount = btnData.requiredCount || btnData.required_count || 1;
        const approvedCount = btnData.approvedCount || btnData.approved_count || 0;
        const feedbackResponse = approverConfig.feedback_response || false;
        const decisionOptions = Array.isArray(approverConfig.decision_options)
            ? approverConfig.decision_options
            : (Array.isArray(approverConfig.decisionOptions) ? approverConfig.decisionOptions : []);

        form.action = form.dataset.singleDecisionUrl;
        form.dataset.workflowDecisionMode = 'single';
        form.dataset.workflowDecisionBulkCount = '0';
        document.getElementById('recommendationWorkflowDecisionApprovalId').value = approvalId;
        renderBulkApprovalInputs([]);
        document.getElementById('recommendationWorkflowDecisionModalTitle').textContent =
            '<?= __('Respond to Recommendation Approval') ?>';
        setNotice('recommendationWorkflowDecisionSelectionNotice', '');
        setNotice('recommendationWorkflowDecisionSkippedNotice', '');

        configureApprovalController({
            id: approvalId,
            serialPickNext: approverConfig.serial_pick_next || false,
            requiredCount: requiredCount,
            approvedCount: approvedCount,
            eligibleUrl: '/approvals/eligible-approvers/' + approvalId,
            commentWarning: approverConfig.comment_warning || '',
            requiresComment: approverConfig.requires_comment || false,
            feedbackResponse: feedbackResponse,
            decisionOptions: decisionOptions,
            decisionPromptLabel: approverConfig.decision_prompt_label
                || approverConfig.decisionPromptLabel
                || '<?= __('Decision') ?>',
            approveLabel: approverConfig.approve_label || '<?= __('Approve') ?>',
            hideReject: feedbackResponse || approverConfig.hide_reject || false,
            defaultDecision: feedbackResponse && decisionOptions.length === 0 ? 'approve' : null,
        });
    };

    modal.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        if (!btn) return;

        if (btn.dataset.bulkActionKey === 'workflow-decision') {
            configureBulkDecision(btn);
            return;
        }

        configureSingleDecision(btn);
    });

    form.addEventListener('submit', function(event) {
        if (
            form.dataset.workflowDecisionMode === 'bulk'
            && Number(form.dataset.workflowDecisionBulkCount || '0') === 0
        ) {
            event.preventDefault();
            setNotice(
                'recommendationWorkflowDecisionSkippedNotice',
                '<?= __('Select at least one recommendation that is pending your approval.') ?>',
            );
        }
    });
});
</script>
