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
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': My Approvals';
$this->KMP->endBlock(); ?>

<h3><i class="bi bi-check2-square me-2"></i><?= __('My Approvals') ?></h3>

<div data-controller="approval-detail"
     data-approval-detail-url-value="/approvals/detail/"
     data-approval-detail-triage-url-value="<?= h($triageUrl) ?>">
<?= $this->element('dv_grid', [
    'frameId' => 'approvals-grid',
    'dataUrl' => $this->Url->build(['controller' => 'Approvals', 'action' => 'approvalsGridData']),
]) ?>
</div>

<!-- Approval Response Modal -->
<div class="modal fade" id="approvalResponseModal" tabindex="-1" aria-labelledby="approvalResponseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalResponseModalLabel">
                    <i class="bi bi-check2-square me-2"></i><?= __('Respond to Approval') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <?= $this->Form->create(null, [
                'url' => ['controller' => 'Approvals', 'action' => 'recordApproval'],
                'id' => 'approvalResponseForm',
                'data-turbo' => 'true',
                'data-controller' => 'approval-response turbo-modal',
                'data-action' => implode(' ', [
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
                <?= $this->Form->hidden('page_context_url', ['value' => '']) ?>

                <!-- Decision -->
                <fieldset class="border rounded-3 bg-white shadow-sm p-3 mb-3" data-approval-response-target="decisionSection">
                    <legend class="float-none w-auto px-2 fw-semibold fs-6 mb-3" data-approval-response-target="decisionLegend"><?= __('Decision') ?></legend>
                    <div class="d-flex gap-3 flex-wrap" data-approval-response-target="decisionOptions">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="decision" id="decisionApprove" value="approve"
                                data-approval-response-target="decision"
                                data-action="change->approval-response#onDecisionChange">
                            <label class="form-check-label text-success fw-semibold" for="decisionApprove">
                                <i class="bi bi-check-circle me-1"></i><?= __('Approve') ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="decision" id="decisionReject" value="reject"
                                data-approval-response-target="decision"
                                data-action="change->approval-response#onDecisionChange">
                            <label class="form-check-label text-danger fw-semibold" for="decisionReject">
                                <i class="bi bi-x-circle me-1"></i><?= __('Reject') ?>
                            </label>
                        </div>
                    </div>
                </fieldset>

                <!-- Comment -->
                <div class="border rounded-3 bg-white shadow-sm p-3 mb-3">
                    <label class="form-label" for="approvalComment"><?= __('Comment') ?>
                        <span class="text-danger" data-approval-response-target="commentRequiredHint" hidden><?= __('(required for rejections)') ?></span>
                    </label>
                    <textarea class="form-control" id="approvalComment" name="comment" rows="3"
                        data-approval-response-target="comment"
                        placeholder="<?= __('Optional comment...') ?>"></textarea>
                    <div class="form-text text-muted small" data-approval-response-target="commentWarning" hidden>
                        <i class="bi bi-eye me-1"></i><span data-approval-response-target="commentWarningText"></span>
                    </div>
                </div>

                <!-- Next Approver (conditional) -->
                <div class="border rounded-3 bg-white shadow-sm p-3" data-approval-response-target="nextApproverSection" hidden>
                    <div class="alert alert-info py-2 small" role="alert" data-approval-response-target="infoText">
                    </div>
                    <label class="form-label fw-semibold" for="approval-next-approver-disp"><?= __('Select Next Approver') ?></label>
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
                            <button type="button" class="btn btn-outline-secondary" data-ac-target="clearBtn" data-action="ac#clear" disabled><?= __('Clear') ?></button>
                        </div>
                        <ul data-ac-target="results"
                            id="approval-next-approver-results"
                            role="listbox"
                            class="list-group z-3 col-12 position-absolute auto-complete-list"
                            hidden="hidden"></ul>
                        <div id="approval-next-approver-status" class="visually-hidden" role="status" aria-live="polite" aria-atomic="true"
                            data-ac-target="status"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-primary" data-approval-response-target="submitBtn" disabled>
                    <i class="bi bi-send me-1"></i><?= __('Submit Response') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('approvalResponseModal');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        if (!btn) return;

        let btnData = {};
        try {
            btnData = JSON.parse(btn.getAttribute('data-outlet-btn-btn-data-value') || '{}');
        } catch (e) {
            return;
        }

        const approvalId = btnData.id || 0;
        const approverConfig = btnData.approver_config || {};
        const requiredCount = btnData.required_count || 1;
        const approvedCount = btnData.approved_count || 0;
        const serialPickNext = approverConfig.serial_pick_next || false;
        const commentWarning = approverConfig.comment_warning || '';
        const feedbackResponse = approverConfig.feedback_response || false;
        const responseLabel = approverConfig.response_label
            || (feedbackResponse ? '<?= __('Send Feedback') ?>' : '<?= __('Submit Response') ?>');
        const approveLabel = approverConfig.approve_label || 'Approve';
        const hideReject = feedbackResponse || approverConfig.hide_reject || false;
        const requiresComment = approverConfig.requires_comment || false;
        const decisionPromptLabel = approverConfig.decision_prompt_label
            || approverConfig.decisionPromptLabel
            || '<?= __('Decision') ?>';
        const decisionOptions = Array.isArray(approverConfig.decision_options)
            ? approverConfig.decision_options
            : (Array.isArray(approverConfig.decisionOptions) ? approverConfig.decisionOptions : []);
        const form = document.getElementById('approvalResponseForm');

        const modalTitle = document.getElementById('approvalResponseModalLabel');
        if (modalTitle) {
            modalTitle.innerHTML = feedbackResponse
                ? '<i class="bi bi-chat-left-text me-2"></i><?= __('Send Feedback') ?>'
                : '<i class="bi bi-check2-square me-2"></i><?= __('Respond to Approval') ?>';
        }
        const approveLabelEl = document.querySelector('label[for="decisionApprove"]');
        if (approveLabelEl) {
            approveLabelEl.innerHTML = '<i class="bi bi-check-circle me-1"></i>' + approveLabel;
        }
        const rejectOption = document.getElementById('decisionReject')?.closest('.form-check');
        if (rejectOption) {
            rejectOption.hidden = !!hideReject;
        }
        const submitBtn = form?.querySelector('[data-approval-response-target="submitBtn"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>' + responseLabel;
        }
        const commentHint = form?.querySelector('[data-approval-response-target="commentRequiredHint"]');
        if (commentHint) {
            commentHint.textContent = feedbackResponse
                ? '<?= __('(required)') ?>'
                : '<?= __('(required for rejections)') ?>';
        }
        const commentLabel = form?.querySelector('label[for="approvalComment"]');
        if (commentLabel) {
            const hintHtml = commentHint ? commentHint.outerHTML : '';
            const labelText = feedbackResponse ? '<?= __('Feedback') ?>' : '<?= __('Comment') ?>';
            commentLabel.innerHTML = labelText + ' ' + hintHtml;
        }

        // Set hidden field
        document.getElementById('approvalResponseApprovalId').value = approvalId;

        // Configure the Stimulus controller
        const controller = window.Stimulus?.getControllerForElementAndIdentifier(form, 'approval-response');
        if (controller) {
            controller.configure({
                id: approvalId,
                serialPickNext: serialPickNext,
                requiredCount: requiredCount,
                approvedCount: approvedCount,
                eligibleUrl: '/approvals/eligible-approvers/' + approvalId,
                commentWarning: commentWarning,
                requiresComment: requiresComment,
                feedbackResponse: feedbackResponse,
                decisionOptions: decisionOptions,
                decisionPromptLabel: decisionPromptLabel,
                approveLabel: approveLabel,
                hideReject: hideReject,
                defaultDecision: feedbackResponse && decisionOptions.length === 0 ? 'approve' : null,
            });
        }
    });

    <?php if ($focusedApprovalId) : ?>
    // Auto-open response modal for token deep-link approval
    setTimeout(function () {
        const focusedId = <?= json_encode($focusedApprovalId) ?>;
        const respondBtn = document.querySelector('[data-outlet-btn-btn-data-value*="\"id\":' + focusedId + '"]');
        if (respondBtn) {
            respondBtn.click();
        }
    }, 1000);
    <?php endif; ?>
});
</script>
