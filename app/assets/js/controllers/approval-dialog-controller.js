import { Controller } from '@hotwired/stimulus';
import { createControllerScope } from '../services/controller-scope-service.js';
import { createIcon } from '../services/icon-service.js';

/** Configure the dialog on every visit; release listeners and delayed work on exit. */
class ApprovalDialogController extends Controller {
    static values = {
        bindings: Object
    };
    connect() {
        this.lifecycle = createControllerScope();
        const { listen, setTimeout } = this.lifecycle;
        const bindings = this.bindingsValue;
        const modal = document.getElementById('approvalResponseModal');
        if (!modal) return;
        listen(modal, 'show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const bulkSelection = parseBulkSelection(btn);
            let btnData = {};
            let bulkIds = [];
            let bulkError = '';
            if (bulkSelection) {
                bulkIds = bulkSelection.ids;
                const selectedRows = Array.isArray(bulkSelection.checkboxes) ? bulkSelection.checkboxes : [];
                const selectedTypes = [...new Set(selectedRows.map(row => row.approvalTypeKey).filter(Boolean))];
                if (selectedTypes.length !== 1) {
                    bulkError = bindings.selectApprovalsOfTheSameTypeBefore;
                }
                try {
                    btnData = JSON.parse(selectedRows[0]?.approvalResponsePayload || '{}');
                } catch (e) {
                    bulkError = bindings.unableToReadTheSelectedApprovalDetails;
                }
            } else {
                try {
                    btnData = JSON.parse(btn.getAttribute('data-outlet-btn-btn-data-value') || '{}');
                } catch (e) {
                    return;
                }
            }
            const approvalId = btnData.id || 0;
            const approverConfig = btnData.approver_config || {};
            const requiredCount = btnData.required_count || 1;
            const approvedCount = btnData.approved_count || 0;
            const serialPickNext = approverConfig.serial_pick_next || false;
            const commentWarning = approverConfig.comment_warning || '';
            const feedbackResponse = approverConfig.feedback_response || false;
            const responseLabel = approverConfig.response_label || (feedbackResponse ? bindings.sendFeedback : bindings.submitResponse);
            const approveLabel = approverConfig.approve_label || 'Approve';
            const hideReject = feedbackResponse || approverConfig.hide_reject || false;
            const requiresComment = approverConfig.requires_comment || false;
            const decisionPromptLabel = approverConfig.decision_prompt_label || approverConfig.decisionPromptLabel || bindings.decision;
            const decisionOptions = Array.isArray(approverConfig.decision_options) ? approverConfig.decision_options : Array.isArray(approverConfig.decisionOptions) ? approverConfig.decisionOptions : [];
            const form = document.getElementById('approvalResponseForm');
            const modalTitle = document.getElementById('approvalResponseModalLabel');
            if (modalTitle) {
                modalTitle.replaceChildren(createIcon(feedbackResponse ? 'message' : 'checkbox', { class: 'me-2' }),
                    document.createTextNode(bulkIds.length > 1 ? bindings.respondToSelectedApprovals
                        : feedbackResponse ? bindings.sendFeedback : bindings.respondToApproval));
            }
            const approveLabelEl = document.querySelector('label[for="decisionApprove"]');
            if (approveLabelEl) {
                approveLabelEl.replaceChildren(createIcon('circle-check', { class: 'me-1' }),
                    document.createTextNode(approveLabel));
            }
            const rejectOption = document.getElementById('decisionReject')?.closest('.form-check');
            if (rejectOption) {
                rejectOption.hidden = !!hideReject;
            }
            const submitBtn = form?.querySelector('[data-approval-response-target="submitBtn"]');
            if (submitBtn) {
                submitBtn.replaceChildren(createIcon('send', { class: 'me-1' }), document.createTextNode(responseLabel));
            }
            const commentHint = form?.querySelector('[data-approval-response-target="commentRequiredHint"]');
            if (commentHint) {
                commentHint.textContent = feedbackResponse ? bindings.required : bindings.requiredForRejections;
            }
            const commentLabel = form?.querySelector('label[for="approvalComment"]');
            if (commentLabel) {
                const labelText = feedbackResponse ? (bindings.unableToReadTheSelectedApprovalDetails + "0").replaceAll("KMP_BIND_10", () => bindings.feedback) : (bindings.unableToReadTheSelectedApprovalDetails + "1").replaceAll("KMP_BIND_11", () => bindings.comment);
                commentLabel.replaceChildren(document.createTextNode(labelText + ' '));
                if (commentHint) commentLabel.append(commentHint);
            }

            // Set hidden field
            document.getElementById('approvalResponseApprovalId').value = approvalId;
            document.getElementById('approvalResponseApprovalIds').value = bulkIds.join(',');

            // Configure the Stimulus controller
            const controller = window.Stimulus?.getControllerForElementAndIdentifier(form, 'approval-response');
            if (controller) {
                controller.configure({
                    id: approvalId,
                    bulkIds: bulkIds,
                    bulkError: bulkError,
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
                    requiresBestowalGathering: approverConfig.requires_bestowal_gathering || approverConfig.requiresBestowalGathering || false,
                    bestowalGatheringOptions: Array.isArray(approverConfig.bestowal_gathering_options) ? approverConfig.bestowal_gathering_options : Array.isArray(approverConfig.bestowalGatheringOptions) ? approverConfig.bestowalGatheringOptions : [],
                    bestowalGatheringUrl: approverConfig.bestowal_gathering_url || approverConfig.bestowalGatheringUrl || ''
                });
            }
        });
        function parseBulkSelection(btn) {
            const liveSelection = getLiveBulkSelection(btn);
            if (liveSelection) {
                return liveSelection;
            }
            if (!btn.dataset.bulkActionSelection) {
                return null;
            }
            try {
                const selection = JSON.parse(btn.dataset.bulkActionSelection);
                const ids = Array.isArray(selection.ids) ? selection.ids.filter(Boolean) : [];
                if (ids.length === 0) {
                    return null;
                }
                return {
                    ids: ids,
                    checkboxes: Array.isArray(selection.checkboxes) ? selection.checkboxes : []
                };
            } catch (e) {
                return {
                    ids: [],
                    checkboxes: []
                };
            }
        }
        function getLiveBulkSelection(btn) {
            if (!btn.dataset.bulkActionKey) {
                return null;
            }
            const grid = btn.closest('turbo-frame') || btn.closest('[data-controller~="grid-view"]') || document;
            if (!grid) {
                return null;
            }
            const selectedRows = Array.from(grid.querySelectorAll('[data-grid-view-target~="rowCheckbox"]:checked:not(:disabled)'));
            if (selectedRows.length === 0) {
                return null;
            }
            return {
                ids: selectedRows.map(row => row.value).filter(Boolean),
                checkboxes: selectedRows.map(row => ({
                    id: row.value,
                    ...row.dataset
                }))
            };
        }
        if (bindings.focusedApprovalId) {
            // Auto-open response modal for token deep-link approval
            setTimeout(function () {
                const focusedId = (bindings.unableToReadTheSelectedApprovalDetails + "2").replaceAll("KMP_BIND_12", () => bindings.focusedApprovalId);
                const respondBtn = document.querySelector('[data-outlet-btn-btn-data-value*="\"id\":' + focusedId + '"]');
                if (respondBtn) {
                    respondBtn.click();
                }
            }, 1000);
        }
    }
    disconnect() {
        this.lifecycle?.dispose();
    }
}
window.Controllers ??= {};
window.Controllers['approval-dialog'] = ApprovalDialogController;
export default ApprovalDialogController;
